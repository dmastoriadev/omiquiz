<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_session_players', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_session_id')->constrained('game_sessions')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('public_id')->unique();
            $table->char('resume_token_hash', 64)->nullable()->unique();
            $table->string('display_name', 120);
            $table->string('email', 254)->nullable();
            $table->string('status', 20)->default('joined');
            $table->dateTime('joined_at', 3);
            $table->dateTime('last_seen_at', 3)->nullable();
            $table->dateTime('finished_at', 3)->nullable();
            $table->dateTime('left_at', 3)->nullable();
            $table->timestamps(3);

            // Nullable user_id permits multiple guests while limiting registered users per run.
            $table->unique(['game_session_id', 'user_id'], 'gsp_session_user_unique');
            $table->unique(['game_session_id', 'id'], 'gsp_session_id_unique');
            $table->index(['game_session_id', 'status'], 'gsp_session_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_session_players');
    }
};
