<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_changes_own_password(): void
    {
        $c = $this->clinic();

        $this->actingAs($c['assistant'])->put(route('account.password'), ['current_password' => 'mala', 'password' => 'Nueva2026x', 'password_confirmation' => 'Nueva2026x'])
            ->assertSessionHasErrors('current_password');
        $this->actingAs($c['assistant'])->put(route('account.password'), ['current_password' => 'secreto123', 'password' => 'Nueva2026x', 'password_confirmation' => 'Nueva2026x'])
            ->assertRedirect(route('today'));

        $this->assertTrue(Hash::check('Nueva2026x', $c['assistant']->fresh()->password));
    }

    public function test_temporary_password_must_be_changed_first(): void
    {
        $c = $this->clinic();
        $c['assistant']->update(['must_change_password' => true]);

        $this->actingAs($c['assistant'])->get('/')->assertRedirect(route('account'));
        $this->actingAs($c['assistant'])->get(route('account'))->assertOk();
        $this->actingAs($c['assistant'])->put(route('account.password'), ['current_password' => 'secreto123', 'password' => 'Nueva2026x', 'password_confirmation' => 'Nueva2026x']);
        $this->actingAs($c['assistant']->fresh())->get('/')->assertOk();
    }

    public function test_doctor_manages_the_team(): void
    {
        $c = $this->clinic();

        $this->actingAs($c['doctor'])->post(route('team.store'), ['name' => 'Nueva Asistente', 'email' => 'nueva@prueba.test', 'role' => 'asistente'])
            ->assertSessionHas('temp_password');
        $new = User::where('email', 'nueva@prueba.test')->firstOrFail();
        $this->assertTrue($new->must_change_password);
        $this->assertSame($c['clinic']->id, $new->clinic_id);

        $this->actingAs($c['doctor'])->post(route('team.toggle', $c['assistant']));
        $this->assertFalse($c['assistant']->fresh()->active);
        $this->actingAs($c['assistant']->fresh())->get('/')->assertRedirect(route('login'));

        $this->actingAs($c['doctor'])->post(route('team.toggle', $c['doctor']))->assertSessionHasErrors('user');
        $this->actingAs($c['doctor'])->post(route('team.reset', $new))->assertSessionHas('temp_password');
    }

    public function test_assistant_cannot_manage_the_team_and_clinics_are_isolated(): void
    {
        $c = $this->clinic();
        $other = $this->clinic('Otra');

        $this->actingAs($c['assistant'])->post(route('team.store'), ['name' => 'X', 'email' => 'x@x.test', 'role' => 'doctora'])->assertForbidden();
        $this->actingAs($c['doctor'])->post(route('team.reset', $other['assistant']))->assertNotFound();
    }
}
