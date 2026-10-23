<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GameSessionPlayer extends Model
{
    public const STATUS_JOINED = 'joined';
    public const STATUS_PLAYING = 'playing';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_LEFT = 'left';

    public const GENDERS = [
        'male' => 'Male',
        'female' => 'Female',
    ];

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'user_id', 'display_name', 'email', 'gender', 'status', 'joined_at',
        'last_seen_at', 'finished_at', 'left_at',
    ];

    protected $hidden = ['resume_token_hash', 'email', 'gender'];

    protected $attributes = [
        'status' => self::STATUS_JOINED,
        'is_ready' => false,
        // Keep the existing database sentinel for legacy records; new joins
        // must explicitly select Male or Female through QuizSessionService.
        'gender' => 'prefer_not_to_say',
    ];

    protected function casts(): array
    {
        return [
            'rpg_profile_id' => 'integer',
            'is_ready' => 'boolean',
            'joined_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'left_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (GameSessionPlayer $player): void {
            if (! $player->public_id) {
                $player->public_id = Str::uuid()->toString();
            }

            if (! $player->joined_at) {
                $player->joined_at = Carbon::now();
            }
        });
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(GameSession::class, 'game_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bingoCards(): HasMany { return $this->hasMany(BingoCard::class)->orderBy('slot'); }

    public function sketchGuesses(): HasMany { return $this->hasMany(SketchGuess::class); }
    public function drawnTurns(): HasMany { return $this->hasMany(SketchTurn::class, 'drawer_id'); }

    public function answers(): HasMany
    {
        return $this->hasMany(GameAnswer::class);
    }

    public function result(): HasOne
    {
        return $this->hasOne(GameResult::class);
    }
}
