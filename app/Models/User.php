<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    // Privilege changes must be explicit server-side assignments.
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['role' => 'user'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return strtolower(trim((string) $this->role)) === self::ROLE_ADMIN;
    }

    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }

    public function canAccessAdminDashboard(): bool
    {
        return $this->isAdmin();
    }

    public function adminLandingRouteName(): string
    {
        return 'admin.dashboard';
    }

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->whereRaw('LOWER(TRIM(role)) = ?', [self::ROLE_ADMIN]);
    }

    public function quizGames(): HasMany
    {
        return $this->hasMany(Game::class, 'created_by');
    }

    public function hostedQuizSessions(): HasMany
    {
        return $this->hasMany(GameSession::class, 'hosted_by');
    }

    public function quizParticipations(): HasMany
    {
        return $this->hasMany(GameSessionPlayer::class);
    }
}
