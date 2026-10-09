<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use BelongsToClinic;

    protected $fillable = ['clinic_id', 'name', 'duration_min', 'price', 'is_package', 'active'];

    protected function casts(): array
    {
        return [
            'duration_min' => 'integer',
            'price' => 'integer',
            'is_package' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
