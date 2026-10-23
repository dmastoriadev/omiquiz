<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_session_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_session_id')->constrained('game_sessions')->restrictOnDelete();
            $table->foreignId('question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->unsignedInteger('position');

            // The copied question remains available after its source is edited or removed.
            $table->text('question_text');
            $table->json('options');
            $table->string('correct_option', 32);
            $table->text('explanation')->nullable();
            $table->unsignedInteger('points');
            $table->unsignedSmallInteger('time_limit_seconds');

            $table->dateTime('opened_at', 3)->nullable();
            $table->dateTime('deadline_at', 3)->nullable();
            $table->dateTime('closed_at', 3)->nullable();
            $table->timestamps(3);

            $table->unique(['game_session_id', 'position'], 'gsq_session_position_unique');
            $table->unique(['game_session_id', 'id'], 'gsq_session_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_session_questions');
    }
};
