<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SketchStroke extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.v';
    protected $fillable = ['game_session_id', 'seq', 'request_id', 'event_index', 'payload'];
    protected function casts(): array { return ['seq' => 'integer', 'event_index' => 'integer', 'payload' => 'array']; }
    public function session(): BelongsTo { return $this->belongsTo(GameSession::class, 'game_session_id'); }
    public function turn(): BelongsTo { return $this->belongsTo(SketchTurn::class, 'sketch_turn_id'); }
}
