<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlotRoll extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'position', 'round_number', 'roll_number', 'ready_player_ids',
        'countdown_started_at', 'spin_started_at', 'revealed_at', 'result_ends_at',
    ];

    protected $hidden = ['ready_player_ids'];

    protected function casts(): array
    {
        return [
            'position' => 'integer', 'round_number' => 'integer', 'roll_number' => 'integer',
            'ready_player_ids' => 'array',
            'countdown_started_at' => 'immutable_datetime', 'spin_started_at' => 'immutable_datetime',
            'revealed_at' => 'immutable_datetime', 'result_ends_at' => 'immutable_datetime',
        ];
    }

    public function session(): BelongsTo { return $this->belongsTo(GameSession::class, 'game_session_id'); }
}
