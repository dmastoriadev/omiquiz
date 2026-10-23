<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('game_session_players', 'is_ready')) {
            Schema::table('game_session_players', function (Blueprint $table): void {
                $table->boolean('is_ready')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('game_session_players', 'is_ready')) {
            Schema::table('game_session_players', function (Blueprint $table): void {
                $table->dropColumn('is_ready');
            });
        }
    }
};
