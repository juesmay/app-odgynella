<?php

namespace Database\Seeders;

use App\Http\Controllers\AccountSettingsController;
use App\Models\Clinic;
use App\Support\Brand;
use App\Models\ConsentTemplate;
use App\Models\Sede;
use App\Models\Service;
use App\Models\User;
use App\Support\DefaultConsents;
use Illuminate\Database\Seeder;

/**
 * Datos de arranque del consultorio de la Dra. Gynella: sedes, servicios y dos usuarios.
 * Los precios salen de su portafolio (punto medio de cada rango) y las duraciones son
 * una primera aproximación: ajústalos en Configuración.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $clinic = Clinic::create([
            'name' => 'Odgynella',
            'doctor_name' => 'Dra. Gynella Medina',
            'schedule' => [],
        ]);
        $clinic->update(Brand::odgynellaDefaults($clinic->id));

        foreach ([
            ['Envigado', 'Calle 39 sur #42-17, Ed. Piedra Luna, Local 204'],
            ['Sabaneta', 'Cra. 43A #64 sur - 65, Alto Las Flores'],
            ['San Gil', 'Cl. 10 #8-70'],
            ['Bucaramanga', 'Coosanjoyas, Consultorio 215, Calle 34 #24-60'],
        ] as [$name, $address]) {
            Sede::create(['clinic_id' => $clinic->id, 'name' => $name, 'address' => $address]);
        }

        foreach ([
            ['Valoración presencial', 30, 30000, false, true],
            ['Control', 20, 0, false, true],
            ['Limpieza', 45, 140000, false, true],
            ['Aclaramiento', 60, 375000, false, true],
            ['Prótesis', 60, 1250000, false, true],
            ['Microdiseño', 90, 1400000, false, true],
            ['Diseño de sonrisa completo', 120, 2000000, false, true],
            // Paquetes: quedan inactivos hasta que la doctora confirme su precio.
            ['Paquete LITE', 90, 0, true, false],
            ['Paquete PRO', 120, 0, true, false],
            ['Paquete GOLD', 150, 0, true, false],
        ] as [$name, $minutes, $price, $package, $active]) {
            Service::create([
                'clinic_id' => $clinic->id, 'name' => $name, 'duration_min' => $minutes,
                'price' => $price, 'is_package' => $package, 'active' => $active,
            ]);
        }

        foreach (DefaultConsents::all() as [$name, $body]) {
            ConsentTemplate::create(['clinic_id' => $clinic->id, 'name' => $name, 'body' => $body]);
        }

        // En el hosting (APP_ENV=production) las contraseñas son temporales y aleatorias: se muestran
        // una sola vez en la terminal y el sistema obliga a cambiarlas al entrar.
        $production = app()->isProduction();
        $users = [
            ['Gynella Medina', env('DOCTORA_EMAIL', 'doctora@odgynella.test'), User::DOCTORA],
            ['Asistente', env('ASISTENTE_EMAIL', 'asistente@odgynella.test'), User::ASISTENTE],
        ];
        foreach ($users as [$name, $email, $role]) {
            $password = $production ? AccountSettingsController::tempPassword() : 'Cambiar.2026';
            User::create([
                'clinic_id' => $clinic->id, 'name' => $name, 'email' => $email,
                'password' => $password, 'role' => $role, 'must_change_password' => $production,
            ]);
            if ($production) {
                $this->command?->warn("Usuario {$email} · contraseña temporal: {$password}");
            }
        }
    }
}
