<?php

namespace Tests\Feature;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $over = []): array
    {
        return array_merge([
            'name' => 'Laura Gómez', 'doc_type' => 'C.C.', 'doc_number' => '1000', 'phone' => '300 123 4567',
            'city' => 'Medellín', 'source' => 'Instagram', 'data_consent' => '1',
        ], $over);
    }

    public function test_assistant_creates_a_patient_with_consent(): void
    {
        $c = $this->clinic();

        $this->actingAs($c['assistant'])->post('/pacientes', $this->payload())->assertRedirect();

        $p = Patient::firstOrFail();
        $this->assertSame('3001234567', $p->phone_key);
        $this->assertSame($c['clinic']->id, $p->clinic_id);
        $this->assertNotNull($p->data_consent_at);
    }

    public function test_patient_requires_data_consent(): void
    {
        $c = $this->clinic();

        $this->actingAs($c['assistant'])->post('/pacientes', $this->payload(['data_consent' => null]))->assertSessionHasErrors('data_consent');
        $this->assertSame(0, Patient::count());
    }

    public function test_whatsapp_is_unique_inside_a_clinic_but_not_across_clinics(): void
    {
        $a = $this->clinic('A');
        $b = $this->clinic('B');
        $this->patient($a['clinic'], '3001234567');

        $this->actingAs($a['assistant'])->post('/pacientes', $this->payload(['phone' => '(300) 123-4567']))->assertSessionHasErrors('phone_key');
        $this->actingAs($b['assistant'])->post('/pacientes', $this->payload())->assertSessionHasNoErrors();
    }

    public function test_history_answers_become_alerts(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);

        $this->actingAs($c['doctor'])->put(route('patients.history', $p), [
            'anamnesis' => ['anticoagulantes' => '1', 'fuma' => '0'],
            'alert_text' => 'Alergia a la penicilina',
        ])->assertRedirect();

        $this->assertSame(['Alergia a la penicilina', 'Toma anticoagulantes'], $p->fresh()->alerts());
    }
}
