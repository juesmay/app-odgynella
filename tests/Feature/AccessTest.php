<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/pacientes')->assertRedirect('/login');
    }

    public function test_user_can_log_in_and_see_today(): void
    {
        $c = $this->clinic();

        $this->post('/login', ['email' => $c['doctor']->email, 'password' => 'secreto123'])->assertRedirect(route('today'));
        $this->get('/')->assertOk()->assertSee('Hoy en Envigado');
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $c = $this->clinic();
        $c['assistant']->update(['active' => false]);

        $this->post('/login', ['email' => $c['assistant']->email, 'password' => 'secreto123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_assistant_cannot_open_settings(): void
    {
        $c = $this->clinic();

        $this->actingAs($c['assistant'])->get('/configuracion')->assertForbidden();
        $this->actingAs($c['doctor'])->get('/configuracion')->assertOk();
    }

    public function test_a_clinic_never_sees_another_clinics_patients(): void
    {
        $a = $this->clinic('Consultorio A');
        $b = $this->clinic('Consultorio B');
        $foreign = $this->patient($b['clinic'], '3009999999', 'Paciente de B');

        $this->actingAs($a['doctor'])->get(route('patients.show', $foreign->id))->assertNotFound();
        $this->actingAs($a['doctor'])->get('/pacientes')->assertDontSee('Paciente de B');
    }

    public function test_old_maintenance_routes_do_not_exist(): void
    {
        $c = $this->clinic();

        $this->actingAs($c['doctor'])->get('/cache')->assertNotFound();
        $this->actingAs($c['doctor'])->get('/storage-link')->assertNotFound();
    }
}
