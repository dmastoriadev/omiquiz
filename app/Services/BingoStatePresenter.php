<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameSession;
use App\Models\GameSessionPlayer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BingoStatePresenter
{
    public function spectator(GameSession $session): array
    {
        return $this->present($session);
    }

    public function present(GameSession $session, ?GameSessionPlayer $player = null): array
    {
        return DB::transaction(function () use ($session, $player): array {
            // Use the same session lock as a host draw so cards and number never disagree.
            $session = GameSession::query()->lockForUpdate()->findOrFail($session->id);
            if (! $session->isBingo()) abort(404);
            $players = $session->players()->with('bingoCards')->orderBy('joined_at')->orderBy('id')->get();
            if ($player) $player = $players->firstWhere('id', $player->id) ?? abort(404);
            $catalog = app(RpgProfileCatalog::class);
            $profiles = $players->mapWithKeys(fn ($person): array => [
                $person->id => array_intersect_key($catalog->profile((int) ($person->rpg_profile_id ?: $person->id)), array_flip(['id', 'avatar_url', 'sprite_url'])),
            ]);
            // Only revealed draws are selected. Future draws and private trigger
            // identifiers never enter the player or spectator payload.
            $draws = $session->bingoDraws()->whereNotNull('revealed_at')->get()->map(fn ($draw): array => [
                'position' => $draw->position, 'number' => $draw->number,
            ])->all();
            $calledNumbers = array_column($draws, 'number');
            $roster = $players->values()->map(function ($person, $index) use ($profiles, $session, $calledNumbers): array {
                $cards = $person->bingoCards->map(function ($card) use ($calledNumbers): array {
                    $result = [
                        'slot' => $card->slot, 'cells' => $card->cells,
                        'matched_numbers' => array_values(array_intersect($card->cells ?? [$card->number], $calledNumbers)),
                        'active' => $card->closed_on_draw === null, 'closed_on_draw' => $card->closed_on_draw,
                    ];
                    if ($card->cells === null) $result['number'] = $card->number;
                    return $result;
                })->all();
                $remaining = $cards === [] && $session->status === GameSession::STATUS_WAITING
                    ? 3 : $person->bingoCards->whereNull('closed_on_draw')->count();
                return [
                    'public_id' => $person->public_id, 'name' => $person->display_name, 'profile' => $profiles[$person->id],
                    'queue_position' => $index + 1, 'is_ready' => (bool) $person->is_ready, 'answered' => false,
                    'bingo_cards' => $cards, 'cards_remaining' => $remaining,
                    'eliminated' => $cards !== [] && $remaining === 0,
                ];
            })->all();
            $rosterByPublicId = array_column($roster, null, 'public_id');
            $results = $session->status === GameSession::STATUS_COMPLETED
                ? $session->results()->with('player')->get()->map(fn ($result): array => [
                    'public_id' => $result->player->public_id, 'name' => $result->player_name,
                    'profile' => $profiles[$result->game_session_player_id], 'rank' => $result->rank,
                    'position' => $result->rank, 'score' => $result->total_score,
                    'bingo_cards_remaining' => $result->bingo_cards_remaining,
                    'bingo_eliminated_on_draw' => $result->bingo_eliminated_on_draw,
                    'is_winner' => $result->rank === 1,
                    'correct' => 0, 'wrong' => 0, 'unanswered' => 0,
                    'total_questions' => $result->total_questions, 'total_response_time_ms' => 0,
                ])->all() : [];
            $forecast = array_map(fn (array $row): array => array_intersect_key($row, array_flip([
                'position', 'players_before', 'players_after', 'eliminated',
            ])), $session->bingo_plan['forecast'] ?? []);
            $last = $draws === [] ? null : $draws[count($draws) - 1];
            $phase = $session->status === GameSession::STATUS_IN_PROGRESS ? 'bingo' : $session->status;
            $gridCards = $session->status === GameSession::STATUS_WAITING || ($session->bingo_plan['version'] ?? 1) >= 2;
            return [
                'game_type' => Game::TYPE_BINGO, 'status' => $session->status, 'phase' => $phase, 'title' => $session->game_title,
                'min_players' => $session->min_players, 'max_players' => $session->max_players,
                'bingo_min_plays' => $session->bingo_min_plays, 'bingo_max_plays' => $session->bingo_max_plays,
                'time_limit_per_question' => $session->time_limit_per_question,
                'player_count' => $players->count(), 'ready_count' => $players->where('is_ready', true)->count(),
                'total_questions' => $session->total_questions, 'current_position' => $session->current_question_position,
                'server_now' => CarbonImmutable::now()->toISOString(),
                'question' => null, 'sketch' => null, 'answered' => false, 'round_leaderboard' => null,
                'player' => $player ? $rosterByPublicId[$player->public_id] + ['gender' => GameSessionPlayer::GENDERS[$player->gender] ?? 'Not specified'] : null,
                'players' => $roster, 'results' => $results, 'leaderboard' => $results,
                'bingo' => [
                    'draw_number' => $session->current_question_position, 'last_number' => $last['number'] ?? null,
                    'draws' => $draws, 'active_count' => count(array_filter($roster, fn (array $row): bool => ! $row['eliminated'])),
                    'cards_per_player' => 3, 'min_plays' => $session->bingo_min_plays, 'max_plays' => $session->bingo_max_plays,
                    'forecast' => $forecast,
                    'card_format' => $gridCards ? 'grid_5x5' : 'legacy_number',
                    'rules' => ($gridCards
                        ? 'Three 5×5 cards each, with a FREE center. Any called number anywhere on a card closes that whole card; lose all three and you are out. '
                        : 'Three numbered cards each. A called number closes its matching card; lose all three and you are out. ')
                        .'A random survival plan is fixed at the start. Exactly one player keeps one untouched card after the final draw. Players eliminated together are ranked using a random order fixed at the start.',
                ],
                'progress' => [
                    'completed_questions' => $session->current_question_position, 'total_questions' => $session->bingo_max_plays,
                    'percent' => (int) floor(100 * $session->current_question_position / max(1, $session->bingo_max_plays)),
                    'answered_count' => 0,
                ],
            ];
        }, 3);
    }
}
