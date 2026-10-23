<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', fn (Blueprint $table) => $table->unsignedSmallInteger('letter_hint_max')->default(0));
        Schema::table('game_sessions', fn (Blueprint $table) => $table->unsignedSmallInteger('letter_hint_max')->default(0));
        Schema::table('sketch_turns', function (Blueprint $table): void {
            $table->text('letter_hint')->nullable();
            $table->dateTime('hint_revealed_at', 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sketch_turns', fn (Blueprint $table) => $table->dropColumn(['letter_hint', 'hint_revealed_at']));
        Schema::table('game_sessions', fn (Blueprint $table) => $table->dropColumn('letter_hint_max'));
        Schema::table('games', fn (Blueprint $table) => $table->dropColumn('letter_hint_max'));
    }
};
