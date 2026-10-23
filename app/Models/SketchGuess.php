<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SketchGuess extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.v';
    protected $fillable = ['game_session_id', 'game_session_player_id', 'points_awarded', 'drawer_bonus', 'response_time_ms', 'guessed_at'];
    protected function casts(): array { return ['points_awarded' => 'integer', 'drawer_bonus' => 'integer', 'response_time_ms' => 'integer', 'guessed_at' => 'immutable_datetime']; }
    public function session(): BelongsTo { return $this->belongsTo(GameSession::class, 'game_session_id'); }
    public function turn(): BelongsTo { return $this->belongsTo(SketchTurn::class, 'sketch_turn_id'); }
    public function player(): BelongsTo { return $this->belongsTo(GameSessionPlayer::class, 'game_session_player_id'); }
}
