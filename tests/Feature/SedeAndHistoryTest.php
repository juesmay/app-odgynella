<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Quote;
use App\Models\Sede;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SedeAndHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_save_keeps_history_and_exam_together(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);

        $this->actingAs($c['doctor'])->put(route('patients.history', $p), [
            'anamnesis' => ['alergia' => '1'],
            'alert_text' => 'Penicilina',
            'oncology_status' => 'no',
            'exam' => ['oclusion' => 'Clase II', 'higiene' => 'Buena'],
        ])->assertSessionHasNoErrors()->assertSessionHas('clear_draft');

        $p->refresh();
        $this->assertTrue($p->anamnesis['alergia']);
        $this->assertSame('Penicilina', $p->alert_text);
        $this->assertSame('Clase II', $p->exam['oclusion']);
        $this->assertSame('Buena', $p->exam['higiene']);
    }

    public function test_assistant_cannot_change_exam_through_the_combined_save(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);
        $p->update(['exam' => ['oclusion' => 'Clase I']]);

        $this->actingAs($c['assistant'])->put(route('patients.history', $p), [
            'oncology_status' => 'no',
            'exam' => ['oclusion' => 'Inventado'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Clase I', $p->fresh()->exam['oclusion']);
    }

    public function test_diagnoses_are_added_and_removed_without_reload(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);

        $res = $this->actingAs($c['doctor'])->postJson(route('patients.diagnoses.store', $p), ['code' => 'K02.1', 'name' => 'Caries de la dentina', 'tooth' => '36'])
            ->assertCreated()->assertJsonPath('name', 'Caries de la dentina');

        $this->assertSame(1, $p->diagnoses()->count());
        $this->deleteJson($res->json('remove'))->assertOk();
        $this->assertSame(0, $p->diagnoses()->count());

        $this->actingAs($c['assistant'])->postJson(route('patients.diagnoses.store', $p), ['name' => 'X'])->assertForbidden();
    }

    public function test_new_patients_take_the_current_sede_and_lists_filter_by_it(): void
    {
        $c = $this->clinic();
        $sabaneta = Sede::create(['clinic_id' => $c['clinic']->id, 'name' => 'Sabaneta']);

        $this->actingAs($c['doctor'])->withSession(['sede_id' => $sabaneta->id]);
        $p1 = $this->patient($c['clinic'], '3000000001', 'Ana de Sabaneta');
        $this->assertSame($sabaneta->id, $p1->sede_id);

        $p2 = $this->patient($c['clinic'], '3000000002', 'Bea de Envigado');
        $p2->update(['sede_id' => $c['sede']->id]);

        $this->get(route('patients.index'))->assertSee('Ana de Sabaneta')->assertDontSee('Bea de Envigado')->assertSee('Ver todas las sedes');
        $this->get(route('patients.index', ['sedes' => 'todas']))->assertSee('Ana de Sabaneta')->assertSee('Bea de Envigado');
        $this->get(route('patients.index', ['q' => 'Bea']))->assertSee('Hay 1 en otras sedes');

        Quote::create(['clinic_id' => $c['clinic']->id, 'patient_id' => $p2->id, 'number' => 7, 'issued_on' => today(), 'valid_until' => today()->addDays(30),
            'subtotal' => 100, 'discount_amount' => 0, 'total' => 100, 'status' => 'vigente', 'created_by' => $c['doctor']->id]);
        $this->get(route('quotes.index'))->assertDontSee('Bea de Envigado');
        $this->get(route('quotes.index', ['sedes' => 'todas']))->assertSee('Bea de Envigado');
    }

    public function test_patient_sede_can_be_changed_in_the_form(): void
    {
        $c = $this->clinic();
        $sabaneta = Sede::create(['clinic_id' => $c['clinic']->id, 'name' => 'Sabaneta']);
        $p = $this->patient($c['clinic']);
        $this->assertSame($c['sede']->id, $p->sede_id); // sin sesión: la primera sede

        $this->actingAs($c['assistant'])->put(route('patients.update', $p), [
            'name' => $p->name, 'doc_type' => 'C.C.', 'doc_number' => '123', 'phone' => $p->phone, 'sede_id' => $sabaneta->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($sabaneta->id, $p->fresh()->sede_id);

        // Una sede de otra clínica no se acepta.
        $other = $this->clinic('Otra');
        $this->put(route('patients.update', $p), [
            'name' => $p->name, 'doc_type' => 'C.C.', 'doc_number' => '123', 'phone' => $p->phone, 'sede_id' => $other['sede']->id,
        ])->assertSessionHasErrors('sede_id');
    }
}
