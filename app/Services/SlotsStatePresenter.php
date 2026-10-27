<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameSession;
use App\Models\GameSessionPlayer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SlotsStatePresenter
{
    public static function symbolCatalog(): array
    {
        return [
            'gold' => ['key' => 'gold', 'label' => 'Gold jackpot', 'image_url' => '/images/logos/AHR-logo-gold.webp'],
            'silver' => ['key' => 'silver', 'label' => 'Silver prize', 'image_url' => '/images/logos/AHR-logo-silver.webp'],
            'bronze' => ['key' => 'bronze', 'label' => 'Bronze prize', 'image_url' => '/images/logos/AHR-logo-bronze.webp'],
            'black' => ['key' => 'black', 'label' => 'Black consolation', 'image_url' => '/images/logos/AHR-logo-square.webp'],
        ];
    }

    public function spectator(GameSession $session): array
    {
        return $this->present($session);
    }

    public function present(GameSession $session, ?GameSessionPlayer $player = null): array
    {
        if (! $session->isSlots()) abort(404);
        $session = app(SlotsSessionService::class)->enforceCurrentRules($session);
        return DB::transaction(function () use ($session, $player): array {
            // Ready, reveal and state reads use the same session lock, so a
            // player's last Ready cannot race with a partly built state payload.
            $session = GameSession::query()->lockForUpdate()->findOrFail($session->id);
            if (! $session->isSlots()) abort(404);
            if ($session->status === GameSession::STATUS_WAITING) {
                $session = app(SlotsSessionService::class)->normalizeWaiting($session);
            }
            $people = $session->players()->orderBy('joined_at')->orderBy('id')->get();
            if ($player) $player = $people->firstWhere('id', $player->id) ?? abort(404);
            $now = CarbonImmutable::now();
            $totalRolls = SlotsSessionService::totalRolls($session);
            // A cancellation freezes what has already been shown; scheduled
            // future timestamps must not publish prizes after a cancelled game.
            $visibleAt = $session->cancelled_at && $session->cancelled_at->lessThan($now) ? $session->cancelled_at : $now;
            $roll = $session->slotRolls()->where('position', $session->current_question_position)->first();
            $phase = $session->status === GameSession::STATUS_IN_PROGRESS && $roll
                ? SlotsSessionService::phaseFor($roll, $now) : $session->status;
            $phaseEndsAt = match ($phase) {
                'slots_countdown' => $roll?->spin_started_at,
                'slots_spinning' => $roll?->revealed_at,
                'slots_result' => $roll?->result_ends_at,
                default => null,
            };
            $symbolCatalog = self::symbolCatalog();
            $profileCatalog = app(RpgProfileCatalog::class);
            $profiles = $people->mapWithKeys(fn ($person): array => [
                $person->id => array_intersect_key(
                    $profileCatalog->profile((int) ($person->rpg_profile_id ?: $person->id)),
                    array_flip(['id', 'avatar_url', 'sprite_url']),
                ),
            ]);
            $peopleById = $people->keyBy('id');
            $plan = $session->slot_plan ?? [];
            $winners = [];
            // Planned reel data is never serialized. Construct history only
            // from rolls whose five-second spin has actually ended.
            $revealedRolls = $session->slotRolls()->whereNotNull('revealed_at')
                ->where('revealed_at', '<=', $visibleAt->format('Y-m-d H:i:s.v'))->get();
            foreach ($revealedRolls as $revealed) {
                foreach ($plan['rolls'][$revealed->position]['players'] ?? [] as $playerId => $outcome) {
                    $prize = $outcome['prize'] ?? null;
                    $person = $peopleById->get($playerId);
                    if ($prize === null || ! $person || ! isset($symbolCatalog[$prize])) continue;
                    $winners[] = [
                        'public_id' => $person->public_id, 'name' => $person->display_name, 'profile' => $profiles[$person->id],
                        'prize' => $prize, 'prize_label' => $symbolCatalog[$prize]['label'],
                        'position' => $revealed->position, 'roll' => $revealed->position,
                    ];
                }
            }
            $wonPrizes = [];
            $awardsByPlayer = [];
            foreach ($winners as $winner) {
                $id = $winner['public_id'];
                $awardsByPlayer[$id][] = array_intersect_key($winner, array_flip(['prize', 'prize_label', 'position', 'roll']));
                if (! isset($wonPrizes[$id])
                    || SlotsPlanGenerator::PRIZE_POINTS[$winner['prize']] > SlotsPlanGenerator::PRIZE_POINTS[$wonPrizes[$id]]) {
                    $wonPrizes[$id] = $winner['prize'];
                }
            }
            $currentRevealed = $roll && $roll->revealed_at && $roll->revealed_at->lessThanOrEqualTo($visibleAt);
            $currentRows = $currentRevealed ? ($plan['rolls'][$roll->position]['players'] ?? []) : [];
            $readyIds = array_values(array_intersect($plan['player_ids'] ?? [], $roll?->ready_player_ids ?? []));
            $roster = $people->values()->map(function ($person, $index) use ($profiles, $session, $readyIds, $currentRows, $wonPrizes, $awardsByPlayer): array {
                $slotReady = in_array((int) $person->id, $readyIds, true);
                $outcome = $currentRows[$person->id] ?? null;
                return [
                    'public_id' => $person->public_id, 'name' => $person->display_name, 'profile' => $profiles[$person->id],
                    'queue_position' => $index + 1,
                    'is_ready' => $session->status === GameSession::STATUS_WAITING ? (bool) $person->is_ready : $slotReady,
                    'answered' => false, 'slot_ready' => $slotReady,
                    'slot_reels' => $outcome['reels'] ?? null, 'slot_prize' => $outcome['prize'] ?? null,
                    'won_prize' => $wonPrizes[$person->public_id] ?? null,
                    'slot_awards' => $awardsByPlayer[$person->public_id] ?? [],
                ];
            })->all();
            $rosterByPublicId = array_column($roster, null, 'public_id');
            $canReady = $phase === 'slots_ready' && $player && $player->status === GameSessionPlayer::STATUS_PLAYING
                && in_array((int) $player->id, $plan['player_ids'] ?? [], true);
            $results = $session->status === GameSession::STATUS_COMPLETED
                ? $session->results()->with('player')->get()->values()->map(fn ($result, $index): array => [
                    'public_id' => $result->player->public_id, 'name' => $result->player_name,
                    'profile' => $profiles[$result->game_session_player_id], 'rank' => $result->rank,
                    'position' => $index + 1, 'score' => $result->total_score,
                    'slot_prize' => $result->slot_prize, 'slot_won_position' => $result->slot_won_position,
                    'slot_awards' => $awardsByPlayer[$result->player->public_id] ?? [],
                    'prize_label' => $symbolCatalog[$result->slot_prize]['label'] ?? null,
                    'is_winner' => $result->rank === 1,
                    'correct' => 0, 'wrong' => 0, 'unanswered' => 0,
                    'total_questions' => $result->total_questions, 'total_response_time_ms' => 0,
                ])->all() : [];
            $currentWinners = $currentRevealed
                ? array_values(array_filter($winners, fn (array $winner): bool => $winner['position'] === $roll->position)) : [];
            $readyCount = $session->status === GameSession::STATUS_WAITING ? $people->where('is_ready', true)->count() : count($readyIds);
            $restartRequired = SlotsSessionService::restartRequired($session);
            return [
                'game_type' => Game::TYPE_SLOTS, 'status' => $session->status, 'phase' => $phase, 'title' => $session->game_title,
                'min_players' => $session->min_players, 'max_players' => $session->max_players,
                'time_limit_per_question' => SlotsSessionService::SPIN_SECONDS,
                'player_count' => $people->count(), 'ready_count' => $readyCount,
                'total_questions' => $totalRolls, 'current_position' => $session->current_question_position,
                'server_now' => $now->toISOString(),
                'slots_restart_required' => $restartRequired,
                'slots_restart_message' => $restartRequired ? SlotsSessionService::RESTART_REQUIRED_MESSAGE : null,
                'question' => null, 'sketch' => null, 'bingo' => null, 'answered' => false,
                'answer_reveal' => null, 'round_leaderboard' => null,
                'player' => $player ? $rosterByPublicId[$player->public_id] + [
                    'gender' => GameSessionPlayer::GENDERS[$player->gender] ?? 'Not specified', 'can_ready' => (bool) $canReady,
                ] : null,
                'players' => $roster, 'results' => $results, 'leaderboard' => $results,
                'slots' => [
                    'position' => $session->current_question_position, 'roll' => $roll?->position ?? 1,
                    'total_rolls' => $totalRolls,
                    'phase_ends_at' => $phaseEndsAt?->toISOString(),
                    'countdown_started_at' => $roll?->countdown_started_at?->toISOString(),
                    'spin_started_at' => $roll?->spin_started_at?->toISOString(),
                    'revealed_at' => $roll?->revealed_at?->toISOString(),
                    'ready_count' => count($readyIds), 'player_count' => $people->count(), 'can_ready' => (bool) $canReady,
                    'current_winners' => $currentWinners, 'winners' => $winners, 'symbolcatalog' => $symbolCatalog,
                ],
                'progress' => [
                    'completed_questions' => $revealedRolls->count(), 'total_questions' => $totalRolls,
                    'percent' => (int) floor(100 * $revealedRolls->count() / $totalRolls),
                    'answered_count' => 0,
                ],
            ];
        }, 3);
    }
}
