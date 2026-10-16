<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;

class ConsentTemplate extends Model
{
    use BelongsToClinic;

    protected $fillable = ['clinic_id', 'name', 'form', 'body', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** Definición de campos del formulario, o null si es una plantilla de texto libre. @return array<string, mixed>|null */
    public function formDef(): ?array
    {
        return \App\Support\ConsentForms::get($this->form);
    }

    public function render(Patient $patient, string $doctor): string
    {
        return strtr($this->body, [
            '{paciente}' => $patient->name,
            '{documento}' => $patient->documentLabel(),
            '{doctora}' => $doctor,
            '{fecha}' => now()->translatedFormat('j \d\e F \d\e Y'),
        ]);
    }
}
