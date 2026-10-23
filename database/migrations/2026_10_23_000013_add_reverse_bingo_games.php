<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['games', 'game_sessions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->unsignedTinyInteger('bingo_min_plays')->default(3);
                $table->unsignedTinyInteger('bingo_max_plays')->default(20);
            });
        }
        Schema::table('game_sessions', fn (Blueprint $table) => $table->json('bingo_plan')->nullable());
        Schema::table('game_results', function (Blueprint $table): void {
            $table->unsignedTinyInteger('bingo_cards_remaining')->nullable();
            $table->unsignedTinyInteger('bingo_eliminated_on_draw')->nullable();
        });
        Schema::create('bingo_draws', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_session_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('position');
            $table->unsignedTinyInteger('number');
            $table->dateTime('revealed_at', 3)->nullable();
            $table->timestamps(3);
            $table->unique(['game_session_id', 'position'], 'bd_session_position_unique');
            $table->unique(['game_session_id', 'number'], 'bd_session_number_unique');
        });
        Schema::create('bingo_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_session_id');
            $table->foreignId('game_session_player_id');
            $table->unsignedTinyInteger('slot');
            $table->unsignedTinyInteger('number');
            $table->unsignedTinyInteger('closed_on_draw')->nullable();
            $table->dateTime('closed_at', 3)->nullable();
            $table->timestamps(3);
            $table->unique(['game_session_player_id', 'slot'], 'bc_player_slot_unique');
            $table->unique(['game_session_player_id', 'number'], 'bc_player_number_unique');
            $table->index(['game_session_id', 'number'], 'bc_session_number_index');
            $table->foreign(['game_session_id', 'game_session_player_id'], 'bc_player_foreign')
                ->references(['game_session_id', 'id'])->on('game_session_players')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bingo_cards');
        Schema::dropIfExists('bingo_draws');
        Schema::table('game_results', fn (Blueprint $table) => $table->dropColumn(['bingo_cards_remaining', 'bingo_eliminated_on_draw']));
        Schema::table('game_sessions', fn (Blueprint $table) => $table->dropColumn('bingo_plan'));
        foreach (['game_sessions', 'games'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['bingo_min_plays', 'bingo_max_plays']));
        }
    }
};
