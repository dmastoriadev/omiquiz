<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameResult extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.v';

    // Written by the completion transaction, never from a player's request body.
    protected $fillable = [
        'guess_points', 'draw_points', 'game_session_id', 'player_name', 'player_email', 'total_score',
        'correct_answers', 'wrong_answers', 'unanswered_questions',
        'total_questions', 'total_response_time_ms', 'rank', 'finalized_at',
        'bingo_cards_remaining', 'bingo_eliminated_on_draw',
    ];

    protected $hidden = ['player_email'];

    protected $attributes = [
        'total_score' => 0,
        'correct_answers' => 0,
        'wrong_answers' => 0,
        'unanswered_questions' => 0,
        'total_questions' => 0,
        'total_response_time_ms' => 0,
    ];

    protected function casts(): array
    {
        return [
            'guess_points' => 'integer', 'draw_points' => 'integer',
            'bingo_cards_remaining' => 'integer', 'bingo_eliminated_on_draw' => 'integer',
            'total_score' => 'integer',
            'correct_answers' => 'integer',
            'wrong_answers' => 'integer',
            'unanswered_questions' => 'integer',
            'total_questions' => 'integer',
            'total_response_time_ms' => 'integer',
            'rank' => 'integer',
            'finalized_at' => 'immutable_datetime',
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

    public function scopeWinners(Builder $query): Builder
    {
        return $query->where('rank', 1);
    }
}
