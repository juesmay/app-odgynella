<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Support\Clinical;
use Illuminate\Database\Eloquent\Model;

/** Nota clínica firmada. No se edita ni se borra: así lo exige la historia clínica. */
class Evolution extends Model
{
    use BelongsToClinic;

    protected $fillable = ['clinic_id', 'patient_id', 'appointment_id', 'type', 'fields', 'procedures', 'signed_by', 'signed_name', 'signed_at'];

    protected function casts(): array
    {
        return ['fields' => 'array', 'procedures' => 'array', 'signed_at' => 'datetime'];
    }

    public function typeName(): string
    {
        return Clinical::EVOLUTION_TYPES[$this->type]['name'] ?? $this->type;
    }

    /** @return list<array{0:string,1:string}> etiqueta y texto, en el orden del tipo de nota */
    public function lines(): array
    {
        $out = [];
        foreach (Clinical::EVOLUTION_TYPES[$this->type]['fields'] ?? [] as $key) {
            $value = trim((string) ($this->fields[$key] ?? ''));
            if ($value !== '') {
                $out[] = [Clinical::EVOLUTION_FIELDS[$key], $value];
            }
        }

        return $out;
    }
}
