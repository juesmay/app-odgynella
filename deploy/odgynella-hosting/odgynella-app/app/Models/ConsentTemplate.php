<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;

class ConsentTemplate extends Model
{
    use BelongsToClinic;

    protected $fillable = ['clinic_id', 'name', 'body', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
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
