<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL commits schema changes before backfilling. These guards let an
        // interrupted migration resume without recreating committed columns.
        if (! Schema::hasColumn('game_session_players', 'gender')) {
            Schema::table('game_session_players', function (Blueprint $table): void {
                $table->string('gender', 20)->default('prefer_not_to_say');
            });
        }

        if (! Schema::hasColumn('game_session_players', 'rpg_profile_id')) {
            Schema::table('game_session_players', function (Blueprint $table): void {
                $table->unsignedInteger('rpg_profile_id')->nullable();
            });
        }

        if (! Schema::hasIndex('game_session_players', 'gsp_session_rpg_profile_unique')) {
            Schema::table('game_session_players', function (Blueprint $table): void {
                $table->unique(['game_session_id', 'rpg_profile_id'], 'gsp_session_rpg_profile_unique');
            });
        }

        DB::table('game_sessions')->select('id')->orderBy('id')->chunkById(100, function ($sessions): void {
            foreach ($sessions as $session) {
                DB::transaction(function () use ($session): void {
                    // New joins use this same session lock. Existing participant,
                    // answer and result records keep their IDs and timestamps.
                    DB::table('game_sessions')->where('id', $session->id)->lockForUpdate()->first();
                    $used = array_fill_keys(DB::table('game_session_players')
                        ->where('game_session_id', $session->id)
                        ->whereNotNull('rpg_profile_id')
                        ->pluck('rpg_profile_id')->all(), true);
                    $nextProfile = 1;
                    $players = DB::table('game_session_players')
                        ->where('game_session_id', $session->id)
                        ->select(['id', 'rpg_profile_id'])
                        ->orderBy('joined_at')->orderBy('id')
                        ->lazy(500);

                    foreach ($players as $player) {
                        if ($player->rpg_profile_id !== null) {
                            continue;
                        }

                        while (isset($used[$nextProfile])) {
                            ++$nextProfile;
                        }

                        // Historical sessions over today's 200-player limit
                        // retain unique numbered profiles beyond the catalog pool.
                        DB::table('game_session_players')->where('id', $player->id)->update([
                            'rpg_profile_id' => $nextProfile,
                        ]);
                        $used[$nextProfile++] = true;
                    }
                }, 3);
            }
        });
    }

    public function down(): void
    {
        Schema::table('game_session_players', function (Blueprint $table): void {
            $table->dropUnique('gsp_session_rpg_profile_unique');
            $table->dropColumn(['gender', 'rpg_profile_id']);
        });
    }
};
