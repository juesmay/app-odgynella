<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Consentimiento firmado. Guarda una copia exacta del texto y las dos firmas. */
class Consent extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id', 'patient_id', 'consent_template_id', 'title', 'body',
        'patient_signature', 'doctor_signature', 'doctor_name', 'signed_by', 'signed_at',
    ];

    protected $hidden = ['patient_signature', 'doctor_signature'];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
