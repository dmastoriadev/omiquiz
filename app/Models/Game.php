<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Game extends Model
{
    use SoftDeletes;

    public const TYPE_QUIZ = 'quiz';
    public const TYPE_SKETCH = 'sketch';
    public const TYPES = ['quiz' => 'Quiz', 'sketch' => 'Sketch & Guess'];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'title', 'description', 'min_players', 'max_players',
        'time_limit_per_question', 'status', 'game_type',
    ];

    protected $attributes = [
        'game_type' => self::TYPE_QUIZ,
        'min_players' => 1,
        'max_players' => 50,
        'time_limit_per_question' => 30,
        'status' => self::STATUS_DRAFT,
    ];

    protected function casts(): array
    {
        return [
            'min_players' => 'integer',
            'max_players' => 'integer',
            'time_limit_per_question' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Game $game): void {
            // Generated once; renaming a game does not invalidate its identifier.
            if (! $game->slug) {
                $base = Str::substr(Str::slug($game->title), 0, 180) ?: 'game';
                $game->slug = $base.'-'.Str::uuid()->toString();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('position')->orderBy('id');
    }

    public function isSketch(): bool { return $this->game_type === self::TYPE_SKETCH; }

    public function words(): HasMany
    {
        return $this->hasMany(SketchWord::class)->orderBy('position')->orderBy('id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
