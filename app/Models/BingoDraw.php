<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BingoDraw extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = ['position', 'number', 'revealed_at'];

    // Future draw numbers may only leave the server through the revealed-state presenter.
    protected $hidden = ['number'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'number' => 'integer', 'revealed_at' => 'immutable_datetime'];
    }

    public function session(): BelongsTo { return $this->belongsTo(GameSession::class, 'game_session_id'); }
}
