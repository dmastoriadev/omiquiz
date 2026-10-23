<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Null identifies legacy one-number cards; existing sessions keep
        // their original saved cards and draw sequence through completion.
        Schema::table('bingo_cards', fn (Blueprint $table) => $table->json('cells')->nullable());
    }

    public function down(): void
    {
        Schema::table('bingo_cards', fn (Blueprint $table) => $table->dropColumn('cells'));
    }
};
