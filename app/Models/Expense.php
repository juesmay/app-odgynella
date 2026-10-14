<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToClinic;

    /**
     * Tipos de gasto. Consultorio y negocio son gastos del negocio; lo personal es lo que la doctora
     * saca para ella. Reportes separa la utilidad del negocio de lo que realmente le queda.
     */
    public const TYPES = [
        'consultorio' => 'Consultorio',
        'negocio' => 'Negocio',
        'personal' => 'Personal',
    ];

    public const METHODS = ['Efectivo de la caja', 'Transferencia', 'Tarjeta', 'Efectivo (fuera de caja)', 'Por pagar al consultorio'];

    protected $fillable = [
        'clinic_id', 'sede_id', 'type', 'patient_id', 'appointment_id', 'hours', 'cash_session_id', 'spent_on', 'category', 'amount', 'method',
        'detail', 'supplier', 'support_path', 'created_by', 'voided_at', 'voided_by', 'void_reason',
        'historical', 'legacy_ref',
    ];

    /** Si no se dice el tipo, sale de la categoría (Arriendo → consultorio, Publicidad → negocio…). */
    protected static function booted(): void
    {
        static::creating(function (Expense $e) {
            if (! empty($e->getAttributes()['type'] ?? null)) {
                return;
            }
            $e->type = ExpenseCategory::withoutGlobalScopes()->where('clinic_id', $e->clinic_id)
                ->where('name', $e->category)->orderByRaw("case type when 'consultorio' then 0 when 'negocio' then 1 else 2 end")
                ->value('type') ?? 'negocio';
        });
    }

    protected function casts(): array
    {
        return [
            'spent_on' => 'date',
            'amount' => 'integer',
            'voided_at' => 'datetime',
            'historical' => 'boolean',
            'hours' => 'float',
        ];
    }

    /** Gastos del negocio (consultorio y negocio), sin lo personal. */
    public function scopeBusiness(Builder $query): void
    {
        $query->where('type', '!=', 'personal');
    }

    /** Lo que la asistente puede ver: no personal y no de categorías privadas. */
    public function scopeVisibleToAssistant(Builder $query): void
    {
        $private = ExpenseCategory::where('private', true)->pluck('name')->all();
        $query->where('type', '!=', 'personal')->whereNotIn('category', $private);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
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
