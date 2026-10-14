<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;

/** Categoría de gasto dentro de un tipo (consultorio, negocio o personal). La doctora las edita en Configuración. */
class ExpenseCategory extends Model
{
    use BelongsToClinic;

    public const DEFAULTS = [
        'consultorio' => ['Tiempo de consultorio', 'Arriendo', 'Servicios públicos', 'Materiales e insumos', 'Laboratorio dental',
            'Especialistas', 'Mantenimiento de equipos', 'Nómina'],
        'negocio' => ['Publicidad', 'Software', 'Impuestos', 'Contador y asesorías', 'Transporte', 'Capacitación', 'Otros'],
        'personal' => ['Alimentación', 'Hogar', 'Transporte personal', 'Salud', 'Cuidado personal', 'Entretenimiento', 'Otros personales'],
    ];

    /** Categoría que se usa para el tiempo de consultorio de cada cita. */
    public const ROOM_TIME = 'Tiempo de consultorio';

    protected $fillable = ['clinic_id', 'type', 'name', 'private', 'active'];

    protected function casts(): array
    {
        return ['private' => 'boolean', 'active' => 'boolean'];
    }

    public static function seedDefaults(int $clinicId): void
    {
        foreach (self::DEFAULTS as $type => $names) {
            foreach ($names as $name) {
                self::withoutGlobalScopes()->firstOrCreate(
                    ['clinic_id' => $clinicId, 'type' => $type, 'name' => $name],
                    ['private' => $name === 'Nómina', 'active' => true],
                );
            }
        }
    }

    /** La asistente no ve lo personal ni las categorías privadas (nómina). */
    public function hiddenFromAssistant(): bool
    {
        return $this->private || $this->type === 'personal';
    }
}
