<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_session_id');
            $table->foreignId('game_session_player_id');
            $table->foreignId('game_session_question_id');
            $table->string('selected_option', 32)->nullable();
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('points_awarded')->default(0);
            $table->unsignedBigInteger('response_time_ms')->default(0);
            $table->dateTime('answered_at', 3)->nullable();
            $table->dateTime('recorded_at', 3);
            $table->timestamps(3);

            $table->unique(['game_session_player_id', 'game_session_question_id'], 'ga_player_question_unique');
            $table->index(['game_session_id', 'game_session_player_id'], 'ga_session_player_index');
            $table->index(['game_session_id', 'game_session_question_id'], 'ga_session_question_index');

            // Both references must belong to the answer's session.
            $table->foreign(['game_session_id', 'game_session_player_id'], 'ga_session_player_foreign')
                ->references(['game_session_id', 'id'])
                ->on('game_session_players')
                ->restrictOnDelete();

            $table->foreign(['game_session_id', 'game_session_question_id'], 'ga_session_question_foreign')
                ->references(['game_session_id', 'id'])
                ->on('game_session_questions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_answers');
    }
};
