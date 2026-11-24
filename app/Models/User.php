<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_LEARNER = 'learner';

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /** Người học đang hoạt động (đối tượng cần theo dõi check-in) */
    public function scopeActiveLearners(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_LEARNER)->where('is_active', true);
    }
}
