<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const DOCTORA = 'doctora';

    public const ASISTENTE = 'asistente';

    protected $fillable = ['clinic_id', 'name', 'email', 'password', 'role', 'active', 'must_change_password'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['active' => true, 'must_change_password' => false];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function isDoctor(): bool
    {
        return $this->role === self::DOCTORA;
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0];
    }
}
