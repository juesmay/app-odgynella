<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Orden de estudios radiográficos para un centro radiológico. */
class RadiologyOrder extends Model
{
    use BelongsToClinic;

    /** Estudios más pedidos. "zone" indica si se pide diente o zona. */
    public const STUDIES = [
        'Radiografía panorámica' => false,
        'Radiografía periapical' => true,
        'Radiografías coronales (aleta de mordida)' => true,
        'Radiografía oclusal' => true,
        'Radiografía cefálica lateral (cefalometría)' => false,
        'Radiografía de ATM (boca abierta y cerrada)' => false,
        'Tomografía cone beam de un maxilar' => true,
        'Tomografía cone beam de ambos maxilares' => false,
        'Tomografía cone beam sectorizada' => true,
        'Fotografías clínicas extra e intraorales' => false,
        'Escáner intraoral' => false,
    ];

    public const DELIVERY = [
        'correo_doctora' => 'Enviar los resultados al correo de la doctora',
        'paciente_recoge' => 'El paciente recoge los resultados',
        'virtual_paciente' => 'Enviar los resultados al correo del paciente',
    ];

    protected $fillable = [
        'clinic_id', 'patient_id', 'number', 'issued_on', 'center', 'studies',
        'indication', 'delivery', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'studies' => 'array',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function code(): string
    {
        return 'RX-'.str_pad((string) $this->number, 5, '0', STR_PAD_LEFT);
    }

    public function deliveryLabel(): string
    {
        return self::DELIVERY[$this->delivery] ?? $this->delivery;
    }

    public function summary(): string
    {
        return collect($this->studies)->map(fn ($s) => $s['name'].($s['zone'] ? ' ('.$s['zone'].')' : ''))->implode(', ');
    }
}
