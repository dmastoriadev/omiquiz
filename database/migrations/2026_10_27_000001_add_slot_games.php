<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['games', 'game_sessions'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->json('slot_prize_positions')->nullable());
        }
        Schema::table('game_sessions', fn (Blueprint $table) => $table->json('slot_plan')->nullable());
        Schema::table('game_results', function (Blueprint $table): void {
            $table->string('slot_prize', 16)->nullable();
            $table->unsignedTinyInteger('slot_won_position')->nullable();
        });
        Schema::create('slot_rolls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_session_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('position');
            $table->unsignedTinyInteger('round_number');
            $table->unsignedTinyInteger('roll_number');
            // The session lock serializes changes to this frozen-roster readiness set.
            $table->json('ready_player_ids');
            $table->dateTime('countdown_started_at', 3)->nullable();
            $table->dateTime('spin_started_at', 3)->nullable();
            $table->dateTime('revealed_at', 3)->nullable();
            $table->dateTime('result_ends_at', 3)->nullable();
            $table->timestamps(3);
            $table->unique(['game_session_id', 'position'], 'sr_session_position_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_rolls');
        Schema::table('game_results', fn (Blueprint $table) => $table->dropColumn(['slot_prize', 'slot_won_position']));
        Schema::table('game_sessions', fn (Blueprint $table) => $table->dropColumn('slot_plan'));
        foreach (['game_sessions', 'games'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('slot_prize_positions'));
        }
    }
};
