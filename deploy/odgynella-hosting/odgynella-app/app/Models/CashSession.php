<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    use BelongsToClinic;

    protected $fillable = [
        'clinic_id', 'sede_id', 'opened_at', 'opened_by', 'opening_amount',
        'closed_at', 'closed_by', 'expected_cash', 'counted_cash',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_amount' => 'integer',
            'expected_cash' => 'integer',
            'counted_cash' => 'integer',
        ];
    }

    public static function openFor(int $sedeId): ?self
    {
        return static::where('sede_id', $sedeId)->whereNull('closed_at')->latest('opened_at')->first();
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function cashReceived(): int
    {
        return (int) $this->payments()->whereNull('voided_at')->where('method', 'Efectivo')->sum('amount');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** Gastos pagados con el efectivo de esta caja. */
    public function cashSpent(): int
    {
        return (int) $this->expenses()->whereNull('voided_at')->sum('amount');
    }

    public function expectedNow(): int
    {
        return $this->opening_amount + $this->cashReceived() - $this->cashSpent();
    }

    public function difference(): ?int
    {
        return $this->closed_at ? $this->counted_cash - $this->expected_cash : null;
    }
}
