<?php

namespace App\Models\Concerns;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aísla los datos por consultorio: con sesión iniciada, cada consulta solo ve
 * los registros de la clínica del usuario, y cada registro nuevo se asigna a ella.
 * Así un consultorio nunca ve ni toca datos de otro, ni siquiera cambiando un id en la URL.
 */
trait BelongsToClinic
{
    protected static function bootBelongsToClinic(): void
    {
        static::addGlobalScope('clinic', function (Builder $query) {
            $clinicId = auth()->user()?->clinic_id;

            if ($clinicId) {
                $query->where($query->getModel()->getTable().'.clinic_id', $clinicId);
            }
        });

        static::creating(function ($model) {
            if (! $model->clinic_id && auth()->check()) {
                $model->clinic_id = auth()->user()->clinic_id;
            }
        });
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
