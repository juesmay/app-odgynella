<?php

namespace Tests\Feature;

use App\Models\Consent;
use App\Models\ConsentTemplate;
use App\Support\ConsentForms;
use App\Support\DefaultConsents;
use App\Support\QuotePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsentFormsTest extends TestCase
{
    use RefreshDatabase;

    private const SIG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    public function test_all_nine_forms_load_with_their_text(): void
    {
        $c = $this->clinic();
        DefaultConsents::seedForms($c['clinic']->id);
        DefaultConsents::seedForms($c['clinic']->id); // no duplica

        $this->assertSame(9, ConsentTemplate::where('clinic_id', $c['clinic']->id)->whereNotNull('form')->count());
        foreach (array_keys(ConsentForms::all()) as $key) {
            $blocks = ConsentForms::blocks(ConsentForms::body($key));
            $this->assertGreaterThanOrEqual(8, count(array_filter($blocks, fn ($b) => $b[0] === 'h')), $key);
            $this->assertStringNotContainsString('____', ConsentForms::body($key), $key);
        }
    }

    public function test_doctor_fills_an_extraction_consent_on_screen_with_her_saved_signature(): void
    {
        $c = $this->clinic();
        $c['clinic']->update(['doctor_license' => 'RP 12345', 'signature' => self::SIG]);
        DefaultConsents::seedForms($c['clinic']->id);
        $p = $this->patient($c['clinic'], '3001112233', 'Laura Gómez');
        $p->update(['birth_date' => now()->subYears(35)]);
        $tpl = ConsentTemplate::where('form', 'extraccion')->firstOrFail();

        $this->actingAs($c['doctor'])->get(route('consents.create', $p))->assertOk()->assertSee('Extracción dental');
        $this->get(route('consents.create', [$p, 'plantilla' => $tpl->id]))->assertOk()
            ->assertSee('Diente(s) por extraer')->assertSee('Exodoncia quirúrgica')->assertSee('Firmar a mano en su lugar')->assertSee('RP 12345');

        // Falta lo obligatorio.
        $this->post(route('consents.store', $p), ['template_id' => $tpl->id, 'patient_signature' => self::SIG])
            ->assertSessionHasErrors(['f.dientes', 'f.modalidad', 'decision']);

        $this->post(route('consents.store', $p), [
            'template_id' => $tpl->id, 'patient_signature' => self::SIG,
            'f' => ['dientes' => '38, 48', 'arcada' => 'Inferior', 'diagnostico' => 'Terceros molares impactados', 'modalidad' => 'Exodoncia simple', 'fecha_lugar' => 'Envigado, hoy'],
            'decision' => '1',
        ])->assertSessionHasNoErrors();

        $consent = Consent::firstOrFail();
        $this->assertSame(self::SIG, $consent->doctor_signature); // su firma guardada
        $rows = collect($consent->form_data['rows'])->mapWithKeys(fn ($r) => [$r[0] => $r[1]]);
        $this->assertSame('38, 48', $rows['Diente(s) por extraer (nomenclatura FDI)']);
        $this->assertSame('Exodoncia simple', $rows['Modalidad prevista']);
        $this->assertSame('RP 12345', $rows['Registro profesional']);
        $this->assertTrue($consent->form_data['decision']['accepted']);

        $this->get(route('consents.show', [$p, $consent]))->assertOk()->assertSee('38, 48')->assertSee('AUTORIZO la extracción indicada');
        if (QuotePdf::available()) {
            $this->get(route('consents.pdf', [$p, $consent]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }
    }

    public function test_pediatric_consent_requires_the_parent_and_keeps_the_childs_assent(): void
    {
        $c = $this->clinic();
        DefaultConsents::seedForms($c['clinic']->id);
        $kid = $this->patient($c['clinic'], '3001119999', 'Samuel Pérez');
        $kid->update(['birth_date' => now()->subYears(7), 'doc_type' => 'T.I.']);
        $tpl = ConsentTemplate::where('form', 'odontopediatria')->firstOrFail();
        $base = ['template_id' => $tpl->id, 'patient_signature' => self::SIG, 'doctor_signature' => self::SIG, 'decision' => '1',
            'f' => ['procedimientos' => ['Aplicación tópica de flúor', 'Sellantes de fosas y fisuras'], 'fecha_lugar' => 'Envigado']];

        $this->actingAs($c['doctor'])->post(route('consents.store', $kid), $base)->assertSessionHasErrors(['rep.nombre', 'rep.documento', 'rep.vinculo']);
        $this->post(route('consents.store', $kid), array_replace($base, ['f' => ['procedimientos' => ['Inventado'], 'fecha_lugar' => 'Envigado'], 'rep' => ['nombre' => 'A', 'documento' => 'B', 'vinculo' => 'Madre']]))->assertSessionHasErrors('f.procedimientos.0');

        $this->post(route('consents.store', $kid), $base + [
            'rep' => ['nombre' => 'Ana Pérez', 'documento' => 'C.C. 43.000.111', 'vinculo' => 'Madre'],
            'assent' => 'Estoy de acuerdo', 'menor' => 'Samuel',
        ])->assertSessionHasNoErrors();

        $fd = Consent::firstOrFail()->form_data;
        $this->assertSame('Ana Pérez', $fd['signer']['name']);
        $this->assertSame('Estoy de acuerdo', $fd['assent']['answer']);
        $this->assertContains(['Edad', '7 años'], $fd['rows']);
        $this->assertContains(['Procedimientos autorizados', "Aplicación tópica de flúor\nSellantes de fosas y fisuras"], $fd['rows']);
    }

    public function test_other_option_and_refusal(): void
    {
        $c = $this->clinic();
        DefaultConsents::seedForms($c['clinic']->id);
        $p = $this->patient($c['clinic']);
        $tpl = ConsentTemplate::where('form', 'protesis_fija')->firstOrFail();
        $base = ['template_id' => $tpl->id, 'patient_signature' => self::SIG, 'doctor_signature' => self::SIG, 'decision' => '0',
            'f' => ['pilares' => '11', 'tratamiento' => 'Corona individual', 'material' => '__otro', 'fecha_lugar' => 'Sabaneta']];

        $this->actingAs($c['doctor'])->post(route('consents.store', $p), $base)->assertSessionHasErrors('otro.material');
        $this->post(route('consents.store', $p), $base + ['otro' => ['material' => 'Disilicato de litio']])->assertSessionHasNoErrors();

        $fd = Consent::firstOrFail()->form_data;
        $this->assertContains(['Material informado / seleccionado', 'Otro: Disilicato de litio'], $fd['rows']);
        $this->assertFalse($fd['decision']['accepted']);
        $this->get(route('patients.consents', $p))->assertSee('No aceptó');
    }
}
