<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_uploads_and_removes_profile_photo(): void
    {
        Storage::fake('local');
        $c = $this->clinic();

        $this->actingAs($c['assistant'])->post(route('account.avatar'), ['avatar' => UploadedFile::fake()->image('yo.png', 600, 400)])
            ->assertSessionHasNoErrors();
        $u = $c['assistant']->fresh();
        $this->assertNotNull($u->avatar_path);
        Storage::disk('local')->assertExists($u->avatar_path);
        if (function_exists('imagecreatefromstring')) {
            [$w, $h] = getimagesizefromstring(Storage::disk('local')->get($u->avatar_path));
            $this->assertSame([256, 256], [$w, $h]);
        }

        // Se ve arriba y la doctora la ve en el equipo.
        $this->get(route('today'))->assertSee(route('users.avatar', $u), false);
        $this->actingAs($c['doctor'])->get(route('users.avatar', $u))->assertOk();
        $this->get(route('account'))->assertSee(route('users.avatar', $u), false);

        // Otro consultorio no la ve.
        $other = $this->clinic('Otra');
        $this->actingAs($other['doctor'])->get(route('users.avatar', $u))->assertNotFound();

        // No acepta otros archivos.
        $this->actingAs($u)->post(route('account.avatar'), ['avatar' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('avatar');

        $path = $u->avatar_path;
        $this->post(route('account.avatar'), ['quitar' => 1])->assertSessionHasNoErrors();
        $this->assertNull($u->fresh()->avatar_path);
        Storage::disk('local')->assertMissing($path);
        $this->get(route('today'))->assertSee('>AP<', false); // iniciales de "Asistente Prueba"
    }
}
