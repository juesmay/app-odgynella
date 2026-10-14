<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    use BelongsToClinic;

    protected $fillable = ['clinic_id', 'name', 'address', 'active', 'hourly_rate'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'hourly_rate' => 'integer'];
    }
}
