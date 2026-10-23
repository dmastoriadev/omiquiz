<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SketchWord extends Model
{
    protected $fillable = ['word', 'aliases', 'is_active', 'position'];
    protected $attributes = ['is_active' => true, 'position' => 0];
    protected function casts(): array { return ['aliases' => 'array', 'is_active' => 'boolean', 'position' => 'integer']; }
    public function game(): BelongsTo { return $this->belongsTo(Game::class); }
}
