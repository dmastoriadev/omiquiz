<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->string('slug', 220)->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('min_players')->default(1);
            $table->unsignedSmallInteger('max_players')->default(50);
            $table->unsignedSmallInteger('time_limit_per_question')->default(30);
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at'], 'games_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
