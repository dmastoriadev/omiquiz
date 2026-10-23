<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SketchTurn extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.v';
    protected $fillable = ['drawer_id', 'position', 'prompt', 'aliases', 'time_limit_seconds', 'stroke_sequence', 'opened_at', 'deadline_at', 'closed_at'];
    protected $hidden = ['prompt', 'aliases'];
    protected function casts(): array
    {
        return ['aliases' => 'array', 'drawer_id' => 'integer', 'position' => 'integer', 'time_limit_seconds' => 'integer', 'stroke_sequence' => 'integer', 'opened_at' => 'immutable_datetime', 'deadline_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime'];
    }
    public function session(): BelongsTo { return $this->belongsTo(GameSession::class, 'game_session_id'); }
    public function drawer(): BelongsTo { return $this->belongsTo(GameSessionPlayer::class, 'drawer_id'); }
    public function guesses(): HasMany { return $this->hasMany(SketchGuess::class); }
    public function strokes(): HasMany { return $this->hasMany(SketchStroke::class)->orderBy('seq'); }
}
