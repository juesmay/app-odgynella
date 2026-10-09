<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    use BelongsToClinic;

    public const STATUSES = [
        'vigente' => ['Vigente', 'c-conf'],
        'vencida' => ['Vencida', 'c-noshow'],
        'aceptada' => ['Aceptada', 'c-done'],
        'no_aceptada' => ['No aceptada', 'c-cancel'],
    ];

    protected $fillable = [
        'clinic_id', 'patient_id', 'number', 'issued_on', 'valid_until', 'subtotal',
        'discount_amount', 'discount_label', 'total', 'patient_notes', 'internal_notes',
        'status', 'created_by', 'historical', 'legacy_ref',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'valid_until' => 'date',
            'subtotal' => 'integer',
            'discount_amount' => 'integer',
            'total' => 'integer',
            'historical' => 'boolean',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('position');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function code(): string
    {
        if ($this->number === null) {
            return 'HIST-'.($this->legacy_ref ?: $this->id);
        }

        return 'COT-'.str_pad((string) $this->number, 5, '0', STR_PAD_LEFT);
    }

    /** Estado que ve la gente: una vigente pasada de fecha se muestra como vencida. */
    public function displayStatus(): string
    {
        if ($this->status === 'vigente' && $this->valid_until->lt(today())) {
            return 'vencida';
        }

        return $this->status;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->displayStatus()][0];
    }

    public function statusClass(): string
    {
        return self::STATUSES[$this->displayStatus()][1];
    }

    public function daysLeft(): int
    {
        return (int) today()->diffInDays($this->valid_until, false);
    }

    public function isEditable(): bool
    {
        return $this->status === 'vigente';
    }

    /*
    |--------------------------------------------------------------------------
    | Cuenta del plan de tratamiento (cotización aceptada)
    |--------------------------------------------------------------------------
    | total     lo que vale el plan, ya con descuento
    | realizado lo que ya se hizo, valorado con el mismo descuento
    | pagado    pagos válidos ligados a esta cotización
    | saldo     lo que falta pagar del plan completo (total - pagado)
    | debe      lo hecho que no se ha pagado (cartera)
    | anticipo  lo pagado por encima de lo hecho
    */

    /** Valor neto de un ítem: reparte el descuento de la cotización en proporción. */
    public function netValue(int $lineTotal): int
    {
        if ($this->subtotal <= 0) {
            return 0;
        }

        return (int) round($lineTotal * $this->total / $this->subtotal);
    }

    public function doneValue(): int
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        $done = $items->whereNotNull('done_at');

        if ($done->isEmpty()) {
            return 0;
        }
        if ($done->count() === $items->count()) {
            return $this->total;
        }

        return min($this->total, $this->netValue((int) $done->sum('line_total')));
    }

    public function paidAmount(): int
    {
        if ($this->relationLoaded('payments')) {
            return (int) $this->payments->whereNull('voided_at')->sum('amount');
        }

        return (int) $this->payments()->whereNull('voided_at')->sum('amount');
    }

    public function balance(): int
    {
        return max(0, $this->total - $this->paidAmount());
    }

    public function owed(): int
    {
        return max(0, $this->doneValue() - $this->paidAmount());
    }

    public function advance(): int
    {
        return max(0, $this->paidAmount() - $this->doneValue());
    }

    /** Resumen listo para mostrar. */
    public function account(): array
    {
        $done = $this->doneValue();
        $paid = $this->paidAmount();

        return [
            'total' => $this->total,
            'done' => $done,
            'paid' => $paid,
            'balance' => max(0, $this->total - $paid),
            'owed' => max(0, $done - $paid),
            'advance' => max(0, $paid - $done),
            'pending_work' => max(0, $this->total - $done),
        ];
    }
}
