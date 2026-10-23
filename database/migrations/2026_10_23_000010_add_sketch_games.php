<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->string('game_type', 16)->default('quiz');
        });
        Schema::table('game_sessions', function (Blueprint $table): void {
            $table->string('game_type', 16)->default('quiz');
            $table->json('sketch_words')->nullable();
        });
        Schema::table('game_results', function (Blueprint $table): void {
            $table->unsignedBigInteger('guess_points')->default(0);
            $table->unsignedBigInteger('draw_points')->default(0);
        });
        Schema::create('sketch_words', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('word', 100);
            $table->json('aliases')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
        Schema::create('sketch_turns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('drawer_id');
            $table->unsignedInteger('position');
            $table->string('prompt', 100);
            $table->json('aliases')->nullable();
            $table->unsignedSmallInteger('time_limit_seconds');
            $table->unsignedInteger('stroke_sequence')->default(0);
            $table->dateTime('opened_at', 3)->nullable();
            $table->dateTime('deadline_at', 3)->nullable();
            $table->dateTime('closed_at', 3)->nullable();
            $table->timestamps(3);
            $table->unique(['game_session_id', 'position'], 'st_session_position_unique');
            $table->unique(['game_session_id', 'id'], 'st_session_id_unique');
            $table->foreign(['game_session_id', 'drawer_id'], 'st_drawer_foreign')->references(['game_session_id', 'id'])->on('game_session_players')->restrictOnDelete();
        });
        Schema::create('sketch_guesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_session_id');
            $table->foreignId('sketch_turn_id');
            $table->foreignId('game_session_player_id');
            $table->unsignedInteger('points_awarded');
            $table->unsignedInteger('drawer_bonus');
            $table->unsignedInteger('response_time_ms');
            $table->dateTime('guessed_at', 3);
            $table->timestamps(3);
            $table->unique(['sketch_turn_id', 'game_session_player_id'], 'sg_turn_player_unique');
            $table->foreign(['game_session_id', 'sketch_turn_id'], 'sg_turn_foreign')->references(['game_session_id', 'id'])->on('sketch_turns')->restrictOnDelete();
            $table->foreign(['game_session_id', 'game_session_player_id'], 'sg_player_foreign')->references(['game_session_id', 'id'])->on('game_session_players')->restrictOnDelete();
        });
        Schema::create('sketch_strokes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_session_id');
            $table->foreignId('sketch_turn_id');
            $table->unsignedInteger('seq');
            $table->string('request_id', 64);
            $table->unsignedTinyInteger('event_index');
            $table->json('payload');
            $table->timestamps(3);
            $table->unique(['sketch_turn_id', 'seq'], 'ss_turn_seq_unique');
            $table->unique(['sketch_turn_id', 'request_id', 'event_index'], 'ss_request_unique');
            $table->foreign(['game_session_id', 'sketch_turn_id'], 'ss_turn_foreign')->references(['game_session_id', 'id'])->on('sketch_turns')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sketch_strokes');
        Schema::dropIfExists('sketch_guesses');
        Schema::dropIfExists('sketch_turns');
        Schema::dropIfExists('sketch_words');
        Schema::table('game_results', fn (Blueprint $table) => $table->dropColumn(['guess_points', 'draw_points']));
        Schema::table('game_sessions', fn (Blueprint $table) => $table->dropColumn(['game_type', 'sketch_words']));
        Schema::table('games', fn (Blueprint $table) => $table->dropColumn('game_type'));
    }
};
