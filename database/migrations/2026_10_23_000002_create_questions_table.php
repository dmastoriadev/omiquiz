<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->text('question_text');
            $table->json('options');
            $table->string('correct_option', 32);
            $table->text('explanation')->nullable();
            $table->unsignedInteger('points')->default(1000);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['game_id', 'is_active', 'position'], 'questions_game_active_position_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
