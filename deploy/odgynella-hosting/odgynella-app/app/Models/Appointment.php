<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use BelongsToClinic;

    public const STATUSES = [
        'agendada' => ['Agendada', 'c-sched'],
        'confirmada' => ['Confirmada', 'c-conf'],
        'en_sala' => ['En sala', 'c-room'],
        'atendida' => ['Atendida', 'c-done'],
        'no_vino' => ['No vino', 'c-noshow'],
        'cancelada' => ['Cancelada', 'c-cancel'],
    ];

    /** Estados que todavía ocupan un espacio en la agenda. */
    public const BLOCKING_STATUSES = ['agendada', 'confirmada', 'en_sala', 'atendida'];

    /** Estados de una cita que aún no ha ocurrido. */
    public const OPEN_STATUSES = ['agendada', 'confirmada', 'en_sala'];

    /** Cambios de estado permitidos desde cada estado. */
    public const TRANSITIONS = [
        'agendada' => ['confirmada', 'en_sala', 'no_vino', 'cancelada'],
        'confirmada' => ['en_sala', 'no_vino', 'cancelada'],
        'en_sala' => ['atendida', 'cancelada'],
        'atendida' => [],
        'no_vino' => [],
        'cancelada' => [],
    ];

    protected $fillable = [
        'clinic_id', 'patient_id', 'sede_id', 'service_id', 'date', 'start_time', 'end_time',
        'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusClass(): string
    {
        return self::STATUSES[$this->status][1] ?? 'c-sched';
    }

    public function canMoveTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function durationMinutes(): int
    {
        return \App\Support\Time::toMinutes($this->end_time) - \App\Support\Time::toMinutes($this->start_time);
    }
}
