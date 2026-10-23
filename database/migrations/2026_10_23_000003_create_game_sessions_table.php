<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->restrictOnDelete();
            $table->foreignId('hosted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('slug', 64)->unique();

            // Preserve the settings used for this run independently of the game template.
            $table->string('game_title', 180);
            $table->unsignedSmallInteger('min_players');
            $table->unsignedSmallInteger('max_players');
            $table->unsignedSmallInteger('time_limit_per_question');

            $table->string('status', 20)->default('waiting');
            $table->unsignedInteger('current_question_position')->default(0);
            $table->unsignedInteger('total_questions')->default(0);
            $table->dateTime('started_at', 3)->nullable();
            $table->dateTime('completed_at', 3)->nullable();
            $table->dateTime('cancelled_at', 3)->nullable();
            $table->timestamps(3);

            $table->index(['game_id', 'created_at'], 'gs_game_created_index');
            $table->index(['status', 'completed_at'], 'gs_status_completed_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
    }
};
