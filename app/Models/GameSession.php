<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GameSession extends Model
{
    public const STATUS_WAITING = 'waiting';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    // Snapshot values must be copied from the game by the session service.
    protected $fillable = [
        'hosted_by', 'game_title', 'min_players', 'max_players',
        'time_limit_per_question', 'status', 'current_question_position',
        'total_questions', 'started_at', 'completed_at', 'cancelled_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_WAITING,
        'current_question_position' => 0,
        'total_questions' => 0,
    ];

    protected function casts(): array
    {
        return [
            'min_players' => 'integer',
            'max_players' => 'integer',
            'time_limit_per_question' => 'integer',
            'current_question_position' => 'integer',
            'total_questions' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (GameSession $session): void {
            if (! $session->slug) {
                $session->slug = Str::uuid()->toString();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class)->withTrashed();
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hosted_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(GameSessionQuestion::class)->orderBy('position');
    }

    public function players(): HasMany
    {
        return $this->hasMany(GameSessionPlayer::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(GameAnswer::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(GameResult::class)->orderBy('rank')->orderBy('id');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED)->whereNotNull('completed_at');
    }
}
