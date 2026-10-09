<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clinic extends Model
{
    protected $fillable = [
        'name', 'doctor_name', 'schedule', 'next_receipt',
        'next_quote', 'quote_validity_days', 'payment_options',
        'doctor_title', 'doctor_license', 'phone', 'email', 'instagram', 'brand_color',
        'logo_path', 'signature', 'radiology_centers', 'payment_note', 'next_order',
    ];

    protected function casts(): array
    {
        return ['schedule' => 'array', 'radiology_centers' => 'array'];
    }

    public function sedes(): HasMany
    {
        return $this->hasMany(Sede::class);
    }

    /** Sede donde atiende la doctora un día de la semana (0 = domingo), o null. */
    public function sedeIdForWeekday(int $weekday): ?int
    {
        $id = ($this->schedule ?? [])[(string) $weekday] ?? null;

        return $id ? (int) $id : null;
    }
}
