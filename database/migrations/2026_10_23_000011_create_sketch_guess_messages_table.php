<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sketch_guess_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_session_id');
            $table->foreignId('sketch_turn_id');
            $table->foreignId('game_session_player_id');
            $table->string('text', 100)->nullable();
            $table->boolean('is_correct')->default(false);
            $table->dateTime('guessed_at', 3);
            $table->timestamps(3);
            $table->index(['sketch_turn_id', 'id'], 'sgm_turn_timeline_index');
            $table->foreign(['game_session_id', 'sketch_turn_id'], 'sgm_turn_foreign')->references(['game_session_id', 'id'])->on('sketch_turns')->restrictOnDelete();
            $table->foreign(['game_session_id', 'game_session_player_id'], 'sgm_player_foreign')->references(['game_session_id', 'id'])->on('game_session_players')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sketch_guess_messages');
    }
};
