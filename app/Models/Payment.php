<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToClinic;

    public const METHODS = ['Efectivo', 'Transferencia', 'Nequi', 'Daviplata', 'Tarjeta (datáfono)', 'Mercado Pago'];

    protected $fillable = [
        'clinic_id', 'patient_id', 'quote_id', 'sede_id', 'cash_session_id', 'receipt_number', 'paid_on',
        'amount', 'method', 'concept', 'created_by', 'voided_at', 'voided_by', 'void_reason',
        'historical', 'legacy_ref',
    ];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
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

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receiptCode(): string
    {
        if ($this->receipt_number === null) {
            return 'HIST-'.($this->legacy_ref ?: $this->id);
        }

        return 'RC-'.str_pad((string) $this->receipt_number, 5, '0', STR_PAD_LEFT);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
