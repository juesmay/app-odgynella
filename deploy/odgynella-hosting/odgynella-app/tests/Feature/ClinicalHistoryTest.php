<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ConsentTemplate;
use App\Models\Evolution;
use App\Models\Photo;
use App\Models\Quote;
use App\Support\QuotePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClinicalHistoryTest extends TestCase
{
    use RefreshDatabase;

    /** PNG de 1x1 válido, como el que genera el recuadro de firma. */
    private const SIG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    public function test_new_history_fields_and_private_infectious_alert(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);

        $this->actingAs($c['doctor'])->put(route('patients.history', $p), [
            'infectious' => ['VIH/SIDA'],
            'oncology_status' => 'previo',
            'oncology_ended_on' => '2024-05-10',
            'oncology_treatments' => ['radio_cabeza_cuello'],
            'family_conditions' => ['Diabetes'],
            'family_detail' => 'Madre con diabetes',
            'hereditary_history' => 'Amelogénesis imperfecta',
        ])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame(['VIH/SIDA'], $p->infectious);
        $this->assertSame('2024-05-10', $p->oncology['ended_on']);
        $this->assertSame(['Diabetes'], $p->family_history['conditions']);
        $this->assertSame('Amelogénesis imperfecta', $p->hereditary_history);

        $this->assertContains('VIH/SIDA', $p->alerts($c['doctor']));
        $this->assertContains('Antecedente oncológico: radioterapia o bifosfonatos', $p->alerts($c['doctor']));

        $forAssistant = $p->alerts($c['assistant']);
        $this->assertContains('Enfermedad infecciosa: ver antecedentes', $forAssistant);
        $this->assertNotContains('VIH/SIDA', $forAssistant);

        // La asistente no ve el dato en pantalla.
        $this->actingAs($c['assistant'])->get(route('patients.clinical', $p))->assertOk()->assertDontSee('VIH/SIDA');
        $this->actingAs($c['doctor'])->get(route('patients.clinical', $p))->assertOk()->assertSee('VIH/SIDA');
    }

    public function test_assistant_saving_history_keeps_infectious_data(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);
        $p->update(['infectious' => ['Hepatitis B']]);

        $this->actingAs($c['assistant'])->put(route('patients.history', $p), [
            'anamnesis' => ['fuma' => '1'],
            'infectious' => [], // aunque lo intente, no puede borrarlo
        ])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame(['Hepatitis B'], $p->infectious);
        $this->assertTrue($p->anamnesis['fuma']);
    }

    public function test_only_doctor_writes_clinical_data(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);

        $this->actingAs($c['assistant'])->put(route('patients.exam', $p), ['oclusion' => 'Clase I'])->assertForbidden();
        $this->actingAs($c['assistant'])->post(route('patients.diagnoses.store', $p), ['name' => 'X'])->assertForbidden();
        $this->actingAs($c['assistant'])->put(route('patients.odontogram.update', [$p, 16]), ['note' => 'x'])->assertForbidden();
        $this->actingAs($c['assistant'])->post(route('patients.evolutions.store', $p), ['type' => 'control', 'fields' => ['evolucion' => 'x']])->assertForbidden();

        $this->actingAs($c['doctor'])->put(route('patients.exam', $p), ['oclusion' => 'Clase I'])->assertSessionHasNoErrors();
        $this->assertSame('Clase I', $p->fresh()->exam['oclusion']);
    }

    public function test_diagnoses_are_added_and_discarded_without_losing_them(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);

        $this->actingAs($c['doctor'])->post(route('patients.diagnoses.store', $p), ['code' => 'K02.1', 'name' => 'Caries de la dentina', 'tooth' => '36']);
        $d = $p->diagnoses()->firstOrFail();
        $this->delete(route('patients.diagnoses.destroy', [$p, $d]))->assertSessionHasNoErrors();

        $this->assertSame(0, $p->diagnoses()->count());
        $this->assertSame(1, $p->diagnoses()->withTrashed()->count());
    }

    public function test_odontogram_saves_several_findings_by_face_and_a_note(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);

        $this->actingAs($c['doctor'])->put(route('patients.odontogram.update', [$p, 36]), [
            'findings' => ['caries', 'obturacion', 'endodoncia'],
            'surfaces' => ['caries' => ['O', 'D'], 'obturacion' => ['M'], 'fractura' => ['V']],
            'note' => 'Caries ocluso distal profunda',
        ])->assertSessionHasNoErrors();

        $t = $p->teeth()->where('tooth', 36)->firstOrFail();
        $this->assertSame([
            ['type' => 'caries', 'surfaces' => ['O', 'D']],
            ['type' => 'obturacion', 'surfaces' => ['M']],
            ['type' => 'endodoncia', 'surfaces' => []],
        ], $t->findings);
        $this->assertSame('Caries ocluso distal profunda', $t->note);
        $this->assertEquals(['O' => 'caries', 'D' => 'caries', 'M' => 'obturacion'], $t->faceColors());

        // Un hallazgo por caras sin caras marcadas no se guarda.
        $this->put(route('patients.odontogram.update', [$p, 11]), ['findings' => ['caries']])->assertSessionHasErrors('findings');
        // Dejarlo todo vacío limpia el diente.
        $this->put(route('patients.odontogram.update', [$p, 36]), []);
        $this->assertSame(0, $p->teeth()->count());
        // Un número de diente que no existe.
        $this->put(route('patients.odontogram.update', [$p, 19]), ['note' => 'x'])->assertNotFound();

        $this->get(route('patients.odontogram', $p))->assertOk();
    }

    public function test_evolution_keeps_only_the_fields_of_its_type_and_links_everything(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);
        $appt = Appointment::create([
            'clinic_id' => $c['clinic']->id, 'patient_id' => $p->id, 'sede_id' => $c['sede']->id, 'service_id' => $c['service']->id,
            'date' => today()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:30', 'status' => 'en_sala',
        ]);
        $quote = Quote::create([
            'clinic_id' => $c['clinic']->id, 'patient_id' => $p->id, 'number' => 1, 'issued_on' => today(), 'valid_until' => today()->addDays(30),
            'subtotal' => 100, 'total' => 100, 'status' => 'aceptada',
        ]);
        $item = $quote->items()->create(['description' => 'Resina', 'teeth' => '36', 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100]);

        $this->actingAs($c['doctor'])->post(route('patients.evolutions.store', $p), [
            'type' => 'sesion',
            'fields' => ['procedimiento' => 'Resina en 36', 'evolucion' => 'Sin dolor', 'motivo' => 'esto no es de sesión'],
            'procedures' => [$item->id],
        ])->assertSessionHasNoErrors();

        $e = Evolution::firstOrFail();
        $this->assertSame(['procedimiento' => 'Resina en 36', 'evolucion' => 'Sin dolor'], $e->fields);
        $this->assertSame(['Resina (36)'], $e->procedures);
        $this->assertSame('Dra. Prueba', $e->signed_name);
        $this->assertNotNull($item->fresh()->done_at);
        $this->assertSame('atendida', $appt->fresh()->status);

        $this->post(route('patients.evolutions.store', $p), ['type' => 'control', 'fields' => ['motivo' => 'solo campos de otro tipo']])
            ->assertSessionHasErrors('fields');

        $this->post(route('patients.evolutions.store', $p), [
            'type' => 'valoracion',
            'fields' => ['motivo' => 'Dolor', 'diagnostico' => 'K04.0 Pulpitis'],
            'dx' => ['K04.0|Pulpitis', '|Bruxismo'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['Bruxismo', 'Pulpitis'], $p->diagnoses()->pluck('name')->sort()->values()->all());
        $this->assertSame('K04.0', $p->diagnoses()->where('name', 'Pulpitis')->value('code'));

        $this->get(route('patients.evolutions', $p))->assertOk()->assertSee('Resina en 36')->assertSee('Firmada por Dra. Prueba');
    }

    public function test_consent_is_signed_on_screen_and_kept_as_signed(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic'], '3001112233', 'Laura Gómez');
        $tpl = ConsentTemplate::create(['clinic_id' => $c['clinic']->id, 'name' => 'Aclaramiento', 'body' => 'Yo, {paciente}, con {documento}, autorizo a la {doctora}.']);

        $this->actingAs($c['assistant'])->get(route('consents.create', $p))->assertOk()->assertSee('Yo, Laura Gómez, con C.C. 123, autorizo a la Dra. Prueba.');

        $this->post(route('consents.store', $p), ['template_id' => $tpl->id, 'patient_signature' => self::SIG, 'doctor_signature' => 'data:image/png;base64,bm9wZQ=='])
            ->assertSessionHasErrors('doctor_signature');
        $this->post(route('consents.store', $p), ['template_id' => $tpl->id, 'patient_signature' => self::SIG])->assertSessionHasErrors('doctor_signature');

        $this->post(route('consents.store', $p), ['template_id' => $tpl->id, 'patient_signature' => self::SIG, 'doctor_signature' => self::SIG])
            ->assertSessionHasNoErrors();

        $consent = $p->consents()->firstOrFail();
        $tpl->update(['body' => 'Texto nuevo']);
        $this->assertStringContainsString('Laura Gómez', $consent->fresh()->body); // lo firmado no cambia

        $this->get(route('consents.show', [$p, $consent]))->assertOk()->assertSee('Aclaramiento');
        $this->get(route('patients.consents', $p))->assertOk();

        if (QuotePdf::available()) {
            $res = $this->get(route('consents.pdf', [$p, $consent]));
            $res->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }
    }

    public function test_prospect_without_document_cannot_sign_a_consent(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);
        $p->update(['doc_number' => null, 'data_consent_at' => null]);

        $this->actingAs($c['doctor'])->get(route('consents.create', $p))->assertRedirect(route('patients.complete', $p));
    }

    public function test_photos_are_private_and_per_clinic(): void
    {
        Storage::fake('local');
        $a = $this->clinic('A');
        $b = $this->clinic('B');
        $p = $this->patient($a['clinic']);

        $this->actingAs($a['assistant'])->post(route('patients.photos.store', $p), [
            'photos' => [UploadedFile::fake()->image('antes.jpg', 800, 600), UploadedFile::fake()->image('antes2.jpg')],
            'stage' => 'antes',
            'taken_on' => today()->toDateString(),
        ])->assertSessionHasNoErrors();

        $photo = Photo::firstOrFail();
        $this->assertSame(2, Photo::count());
        Storage::disk('local')->assertExists($photo->path);

        $this->get(route('patients.photos.file', [$p, $photo]))->assertOk();
        $this->get(route('patients.photos', [$p, 'a' => $photo->id, 'b' => $photo->id + 1]))->assertOk()->assertSee('Comparación');

        $this->actingAs($b['doctor'])->get(route('patients.photos.file', [$p, $photo]))->assertNotFound();
        $this->post('/logout');
        $this->get(route('patients.photos.file', [$p, $photo]))->assertRedirect('/login');
    }

    public function test_every_tab_renders_for_both_roles(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);

        foreach (['doctor', 'assistant'] as $who) {
            foreach (['patients.show', 'patients.clinical', 'patients.odontogram', 'patients.evolutions', 'patients.photos', 'patients.consents'] as $route) {
                $this->actingAs($c[$who])->get(route($route, $p))->assertOk();
            }
        }
        $this->actingAs($c['doctor'])->get('/configuracion')->assertOk()->assertSee('Consentimientos informados');
    }
}
