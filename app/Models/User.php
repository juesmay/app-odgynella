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

    protected $fillable = ['clinic_id', 'name', 'email', 'password', 'role', 'active', 'must_change_password', 'avatar_path'];

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

    /** Iniciales para cuando no hay foto: "Gynella Medina" → "GM". */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim(preg_replace('/^(Dra?\.?)\s+/i', '', $this->name)));

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? route('users.avatar', $this).'?v='.substr(md5($this->avatar_path), 0, 8) : null;
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0];
    }
}
