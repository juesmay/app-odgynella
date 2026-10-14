<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;

/** Texto que la doctora repite en órdenes en blanco: recetas, indicaciones, remisiones. */
class OrderTemplate extends Model
{
    use BelongsToClinic;

    protected $fillable = ['clinic_id', 'title', 'body'];
}
