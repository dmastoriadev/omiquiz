<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_session_id');
            $table->foreignId('game_session_player_id')->unique('gr_player_unique');
            $table->string('player_name', 120);
            $table->string('player_email', 254)->nullable();
            $table->unsignedBigInteger('total_score')->default(0);
            $table->unsignedInteger('correct_answers')->default(0);
            $table->unsignedInteger('wrong_answers')->default(0);
            $table->unsignedInteger('unanswered_questions')->default(0);
            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedBigInteger('total_response_time_ms')->default(0);
            $table->unsignedInteger('rank');
            $table->dateTime('finalized_at', 3);
            $table->timestamps(3);

            $table->index(['game_session_id', 'game_session_player_id'], 'gr_session_player_index');
            $table->index(['game_session_id', 'rank'], 'gr_session_rank_index');
            $table->index(['game_session_id', 'total_score'], 'gr_session_score_index');

            $table->foreign(['game_session_id', 'game_session_player_id'], 'gr_session_player_foreign')
                ->references(['game_session_id', 'id'])
                ->on('game_session_players')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_results');
    }
};
