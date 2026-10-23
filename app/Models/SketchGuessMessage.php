<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SketchGuessMessage extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.v';
    protected $fillable = ['game_session_id', 'game_session_player_id', 'text', 'is_correct', 'guessed_at'];
    protected function casts(): array { return ['is_correct' => 'boolean', 'guessed_at' => 'immutable_datetime']; }
    public function session(): BelongsTo { return $this->belongsTo(GameSession::class, 'game_session_id'); }
    public function turn(): BelongsTo { return $this->belongsTo(SketchTurn::class, 'sketch_turn_id'); }
    public function player(): BelongsTo { return $this->belongsTo(GameSessionPlayer::class, 'game_session_player_id'); }
}
