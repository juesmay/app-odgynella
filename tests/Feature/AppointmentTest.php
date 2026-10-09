<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    private function book(array $c, int $patientId, string $time, ?int $serviceId = null, ?string $date = null)
    {
        return $this->actingAs($c['assistant'])->post('/citas', [
            'patient_id' => $patientId, 'service_id' => $serviceId ?? $c['service']->id, 'sede_id' => $c['sede']->id,
            'date' => $date ?? today()->addDay()->toDateString(), 'start_time' => $time,
        ]);
    }

    public function test_books_an_appointment_with_the_service_duration(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);

        $this->book($c, $p->id, '09:00')->assertSessionHasNoErrors()->assertRedirect();

        $a = Appointment::firstOrFail();
        $this->assertSame('09:30', $a->end_time);
        $this->assertSame('agendada', $a->status);
    }

    public function test_overlapping_appointments_are_rejected_even_in_another_sede(): void
    {
        $c = $this->clinic();
        $p1 = $this->patient($c['clinic'], '3000000001');
        $p2 = $this->patient($c['clinic'], '3000000002');
        $long = Service::create(['clinic_id' => $c['clinic']->id, 'name' => 'Diseño', 'duration_min' => 120, 'price' => 1]);

        $this->book($c, $p1->id, '08:00', $long->id)->assertSessionHasNoErrors();
        $this->book($c, $p2->id, '09:30')->assertSessionHasErrors('start_time');
        $this->book($c, $p2->id, '10:00')->assertSessionHasNoErrors();
        $this->assertSame(2, Appointment::count());
    }

    public function test_cancelled_appointments_free_the_slot(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);
        $this->book($c, $p->id, '09:00');
        Appointment::first()->update(['status' => 'cancelada']);

        $this->book($c, $p->id, '09:00')->assertSessionHasNoErrors();
    }

    public function test_free_slots_skip_busy_times(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);
        $date = today()->addDay()->toDateString();
        $this->book($c, $p->id, '08:00', null, $date);

        $slots = $this->actingAs($c['assistant'])
            ->getJson('/citas/horas?fecha='.$date.'&servicio='.$c['service']->id)
            ->assertOk()->json('slots');

        $values = array_column($slots, 'value');
        $this->assertNotContains('08:00', $values);
        $this->assertContains('08:30', $values);
        $this->assertSame('18:30', end($values));
    }

    public function test_status_follows_allowed_transitions(): void
    {
        $c = $this->clinic();
        $p = $this->patient($c['clinic']);
        $this->book($c, $p->id, '09:00');
        $a = Appointment::first();

        $this->patch(route('appointments.status', $a), ['status' => 'atendida'])->assertSessionHasErrors('status');
        $this->patch(route('appointments.status', $a), ['status' => 'en_sala'])->assertSessionHasNoErrors();
        $this->patch(route('appointments.status', $a), ['status' => 'atendida'])->assertSessionHasNoErrors();
        $this->assertSame('atendida', $a->fresh()->status);
    }

    public function test_cannot_book_a_patient_from_another_clinic(): void
    {
        $a = $this->clinic('A');
        $b = $this->clinic('B');
        $foreign = $this->patient($b['clinic']);

        $this->book($a, $foreign->id, '09:00')->assertSessionHasErrors('patient_id');
    }
}
