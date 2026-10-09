<?php

namespace Tests;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Sede;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Crea un consultorio con una sede, un servicio de 30 min y sus dos usuarios. */
    protected function clinic(string $name = 'Odgynella'): array
    {
        $clinic = Clinic::create(['name' => $name, 'doctor_name' => 'Dra. Prueba', 'schedule' => []]);
        $sede = Sede::create(['clinic_id' => $clinic->id, 'name' => 'Envigado']);
        $service = Service::create(['clinic_id' => $clinic->id, 'name' => 'Valoración', 'duration_min' => 30, 'price' => 30000]);
        $doctor = User::create(['clinic_id' => $clinic->id, 'name' => 'Dra Prueba', 'email' => "doc@{$clinic->id}.test", 'password' => 'secreto123', 'role' => 'doctora']);
        $assistant = User::create(['clinic_id' => $clinic->id, 'name' => 'Asistente Prueba', 'email' => "asis@{$clinic->id}.test", 'password' => 'secreto123', 'role' => 'asistente']);

        return compact('clinic', 'sede', 'service', 'doctor', 'assistant');
    }

    protected function patient(Clinic $clinic, string $phone = '3001234567', string $name = 'Paciente Prueba'): Patient
    {
        return Patient::create([
            'clinic_id' => $clinic->id, 'name' => $name, 'doc_type' => 'C.C.', 'doc_number' => '123',
            'phone' => $phone, 'phone_key' => Patient::phoneKey($phone), 'data_consent_at' => now(),
        ]);
    }
}
