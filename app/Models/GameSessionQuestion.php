<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameSessionQuestion extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'question_id', 'position', 'question_text', 'options', 'correct_option',
        'explanation', 'points', 'time_limit_seconds', 'opened_at',
        'deadline_at', 'closed_at',
    ];

    protected $hidden = ['correct_option', 'explanation'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'options' => 'array',
            'points' => 'integer',
            'time_limit_seconds' => 'integer',
            'opened_at' => 'immutable_datetime',
            'deadline_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(GameSession::class, 'game_session_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class)->withTrashed();
    }

    public function answers(): HasMany
    {
        return $this->hasMany(GameAnswer::class);
    }
}
