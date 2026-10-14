<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Orden de la doctora. Dos tipos:
 *  - radiologia: estudios para un centro radiológico (lista de estudios, centro y entrega).
 *  - libre: orden en blanco que ella escribe (receta, laboratorio, remisión, indicaciones…).
 */
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

    public const KINDS = [
        'radiologia' => 'Radiografía',
        'libre' => 'En blanco',
    ];

    protected $fillable = [
        'clinic_id', 'patient_id', 'number', 'kind', 'title', 'body', 'issued_on', 'center', 'studies',
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
        return ($this->isFree() ? 'OR-' : 'RX-').str_pad((string) $this->number, 5, '0', STR_PAD_LEFT);
    }

    public function isFree(): bool
    {
        return $this->kind === 'libre';
    }

    public function deliveryLabel(): string
    {
        return self::DELIVERY[$this->delivery] ?? (string) $this->delivery;
    }

    public function summary(): string
    {
        if ($this->isFree()) {
            return $this->title;
        }

        return collect($this->studies)->map(fn ($s) => $s['name'].($s['zone'] ? ' ('.$s['zone'].')' : ''))->implode(', ');
    }
}
