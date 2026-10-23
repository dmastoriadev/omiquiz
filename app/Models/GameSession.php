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
        'game_type', 'sketch_words', 'hosted_by', 'game_title', 'min_players', 'max_players',
        'time_limit_per_question', 'letter_hint_max', 'status', 'current_question_position',
        'total_questions', 'started_at', 'completed_at', 'cancelled_at',
        'bingo_min_plays', 'bingo_max_plays', 'bingo_plan',
    ];

    protected $hidden = ['sketch_words', 'bingo_plan'];

    protected $attributes = [
        'game_type' => Game::TYPE_QUIZ,
        'status' => self::STATUS_WAITING,
        'current_question_position' => 0,
        'total_questions' => 0,
        'letter_hint_max' => 0,
        'bingo_min_plays' => 3,
        'bingo_max_plays' => 20,
    ];

    protected function casts(): array
    {
        return [
            'sketch_words' => 'array',
            'bingo_plan' => 'array',
            'bingo_min_plays' => 'integer',
            'bingo_max_plays' => 'integer',
            'min_players' => 'integer',
            'max_players' => 'integer',
            'time_limit_per_question' => 'integer',
            'letter_hint_max' => 'integer',
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

    public function isBingo(): bool { return $this->game_type === Game::TYPE_BINGO; }

    public function bingoCards(): HasMany { return $this->hasMany(BingoCard::class)->orderBy('slot'); }
    public function bingoDraws(): HasMany { return $this->hasMany(BingoDraw::class)->orderBy('position'); }

    public function isSketch(): bool { return $this->game_type === Game::TYPE_SKETCH; }

    public function sketchTurns(): HasMany { return $this->hasMany(SketchTurn::class)->orderBy('position'); }
    public function sketchGuesses(): HasMany { return $this->hasMany(SketchGuess::class); }
    public function sketchStrokes(): HasMany { return $this->hasMany(SketchStroke::class); }

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
