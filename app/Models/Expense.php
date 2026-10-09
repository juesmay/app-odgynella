<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToClinic;

    public const CATEGORIES = [
        'Arriendo', 'Servicios públicos', 'Nómina', 'Laboratorio dental', 'Materiales e insumos',
        'Publicidad', 'Mantenimiento de equipos', 'Software', 'Impuestos', 'Otros',
    ];

    /** Categorías que la asistente no ve en la lista (salarios y similares). */
    public const PRIVATE_CATEGORIES = ['Nómina'];

    public const METHODS = ['Efectivo de la caja', 'Transferencia', 'Tarjeta', 'Efectivo (fuera de caja)'];

    protected $fillable = [
        'clinic_id', 'sede_id', 'cash_session_id', 'spent_on', 'category', 'amount', 'method',
        'detail', 'supplier', 'support_path', 'created_by', 'voided_at', 'voided_by', 'void_reason',
        'historical', 'legacy_ref',
    ];

    protected function casts(): array
    {
        return [
            'spent_on' => 'date',
            'amount' => 'integer',
            'voided_at' => 'datetime',
            'historical' => 'boolean',
        ];
    }

    public function scopeValid(Builder $query): void
    {
        $query->whereNull('voided_at');
    }

    /** Lo que cuenta para Reportes, caja y contador: válido y no histórico. */
    public function scopeCounted(Builder $query): void
    {
        $query->whereNull('voided_at')->where('historical', false);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function fromCash(): bool
    {
        return $this->method === 'Efectivo de la caja';
    }
}
