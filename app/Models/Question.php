<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'question_text', 'options', 'correct_option', 'explanation',
        'points', 'position', 'is_active',
    ];

    // Correctness is read on the server; player APIs must use explicit resources.
    protected $hidden = ['correct_option', 'explanation'];

    protected $attributes = [
        'points' => 1000,
        'position' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'points' => 'integer',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class)->withTrashed();
    }

    public function sessionQuestions(): HasMany
    {
        return $this->hasMany(GameSessionQuestion::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
