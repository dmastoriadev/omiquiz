<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameAnswer extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.v';

    // Use only trusted, server-computed values for correctness, points and time.
    protected $fillable = [
        'game_session_id', 'game_session_question_id', 'selected_option',
        'is_correct', 'points_awarded', 'response_time_ms',
        'answered_at', 'recorded_at',
    ];

    protected $attributes = [
        'is_correct' => false,
        'points_awarded' => 0,
        'response_time_ms' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'points_awarded' => 'integer',
            'response_time_ms' => 'integer',
            'answered_at' => 'immutable_datetime',
            'recorded_at' => 'immutable_datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(GameSession::class, 'game_session_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(GameSessionPlayer::class, 'game_session_player_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(GameSessionQuestion::class, 'game_session_question_id');
    }
}
