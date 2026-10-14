<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;

/** Hallazgo del odontograma creado por la doctora (además de los que trae el sistema). */
class OdontogramFinding extends Model
{
    use BelongsToClinic;

    protected $fillable = ['clinic_id', 'name', 'abbr', 'faces', 'color', 'active'];

    protected function casts(): array
    {
        return ['faces' => 'boolean', 'active' => 'boolean'];
    }

    /** Clave con la que se guarda en el odontograma del paciente. */
    public function key(): string
    {
        return 'x'.$this->id;
    }
}
