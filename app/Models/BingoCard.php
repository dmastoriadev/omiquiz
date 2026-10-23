<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BingoCard extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = ['game_session_id', 'game_session_player_id', 'slot', 'number', 'cells', 'closed_on_draw', 'closed_at'];

    // The old number field is now an internal plan identifier, not the board.
    protected $hidden = ['number'];

    protected function casts(): array
    {
        return ['slot' => 'integer', 'number' => 'integer', 'cells' => 'array', 'closed_on_draw' => 'integer', 'closed_at' => 'immutable_datetime'];
    }

    public function session(): BelongsTo { return $this->belongsTo(GameSession::class, 'game_session_id'); }
    public function player(): BelongsTo { return $this->belongsTo(GameSessionPlayer::class, 'game_session_player_id'); }
}
