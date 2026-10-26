<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameSession;
use App\Models\GameSessionPlayer;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;

class SketchStatePresenter
{
    public function spectator(GameSession $session): array
    {
        $state = $this->present($session);
        unset($state['progress'], $state['leaderboard']);
        return $state;
    }

    public function present(GameSession $session, ?GameSessionPlayer $player = null): array
    {
        return DB::transaction(function () use ($session, $player): array {
            // The waiting bank can contain 2,000 words; none belongs in a state poll.
            $session = GameSession::query()->select([
                'id', 'game_type', 'status', 'game_title', 'min_players', 'max_players',
                'time_limit_per_question', 'letter_hint_max', 'total_questions', 'current_question_position',
            ])->lockForUpdate()->findOrFail($session->id);
            if ($player) $player = $session->players()->findOrFail($player->id);
            $players = $session->players()->orderBy('joined_at')->orderBy('id')->get();
            $turn = $session->sketchTurns()->where('position', $session->current_question_position)->first();
            $turnOrder = $session->sketchTurns()
                ->where('position', '>=', max(1, $session->current_question_position - 2))
                ->limit(12)->get(['drawer_id', 'position']);
            $playersById = $players->keyBy('id');
            $now = CarbonImmutable::now();
            $revealEndsAt = $turn?->closed_at?->addSeconds(SketchSessionService::ANSWER_REVEAL_SECONDS);
            $phase = $session->status === GameSession::STATUS_IN_PROGRESS
                ? ($turn?->closed_at
                    ? ($revealEndsAt && $now->lessThan($revealEndsAt) ? 'answer_reveal' : 'leaderboard')
                    : 'drawing') : $session->status;
            $answerReveal = $phase === 'answer_reveal' && $turn ? [
                'question_id' => $turn->id, 'question_position' => $turn->position,
                'turn_id' => $turn->id, 'drawer_name' => $playersById->get($turn->drawer_id)?->display_name,
                'answer' => $turn->prompt, 'option_key' => null,
                'starts_at' => $turn->closed_at->toISOString(), 'ends_at' => $revealEndsAt->toISOString(),
                'duration_seconds' => SketchSessionService::ANSWER_REVEAL_SECONDS,
                'is_final' => $turn->position >= $session->total_questions,
            ] : null;
            $guessedIds = $turn ? $turn->guesses()->pluck('game_session_player_id')->all() : [];
            $catalog = app(RpgProfileCatalog::class);
            $profiles = $players->mapWithKeys(fn ($p): array => [$p->id => array_intersect_key($catalog->profile((int) ($p->rpg_profile_id ?: $p->id)), array_flip(['id', 'avatar_url', 'sprite_url']))]);
            $roster = $players->values()->map(fn ($p, $index): array => [
                'public_id' => $p->public_id, 'name' => $p->display_name, 'profile' => $profiles[$p->id],
                'queue_position' => $index + 1, 'is_ready' => (bool) $p->is_ready,
                'answered' => $phase === 'drawing' && in_array($p->id, $guessedIds),
            ])->all();
            $service = app(SketchSessionService::class);
            $standings = $service->standings($session);
            $safeStanding = fn (array $row): array => array_intersect_key($row, array_flip([
                'public_id', 'name', 'profile', 'score', 'rank', 'position', 'total_response_time_ms', 'guess_points', 'draw_points',
            ]));
            $round = null;
            if ($phase === 'leaderboard' && $turn) {
                $previous = array_column($service->standings($session, $turn->position), null, 'public_id');
                $entries = array_map(function ($row) use ($previous, $safeStanding): array {
                    $before = $previous[$row['public_id']];
                    return $safeStanding($row) + ['previous_rank' => $before['rank'], 'previous_position' => $before['position'], 'movement' => $before['position'] - $row['position']];
                }, array_slice($standings, 0, 5));
                $round = [
                    'question_id' => $turn->id, 'question_position' => $turn->position,
                    'starts_at' => $revealEndsAt->toISOString(),
                    'ends_at' => $revealEndsAt->addSeconds(SketchSessionService::LEADERBOARD_SECONDS)->toISOString(),
                    'duration_seconds' => SketchSessionService::LEADERBOARD_SECONDS, 'is_final' => $turn->position >= $session->total_questions,
                    'entries' => $entries,
                ];
            }
            $results = $session->status === GameSession::STATUS_COMPLETED ? $session->results()->with('player')->get()->map(fn ($r): array => [
                'public_id' => $r->player->public_id, 'name' => $r->player_name, 'profile' => $profiles[$r->game_session_player_id],
                'rank' => $r->rank, 'score' => $r->total_score, 'guess_points' => $r->guess_points, 'draw_points' => $r->draw_points,
                'correct' => $r->correct_answers, 'wrong' => $r->wrong_answers, 'unanswered' => $r->unanswered_questions,
                'total_questions' => $r->total_questions, 'total_response_time_ms' => $r->total_response_time_ms, 'is_winner' => $r->rank === 1,
            ])->all() : [];
            if ($results !== []) $standings = array_map(fn ($r, $index): array => $r + ['position' => $index + 1], $results, array_keys($results));
            $sketch = null;
            if ($turn) {
                $drawer = $playersById->get($turn->drawer_id);
                $isDrawer = $player && $player->id === $turn->drawer_id;
                $sketch = [
                    'turn_id' => $turn->id, 'position' => $turn->position,
                    'drawer_public_id' => $drawer?->public_id, 'drawer_name' => $drawer?->display_name,
                    'is_drawer' => (bool) $isDrawer, 'deadline_at' => $turn->deadline_at?->toISOString(),
                    'guessed' => $player && in_array($player->id, $guessedIds), 'correct_count' => count($guessedIds),
                    'eligible_guessers' => max(0, $players->count() - 1),
                    'can_draw' => $phase === 'drawing' && $isDrawer && CarbonImmutable::now()->lessThan($turn->deadline_at),
                    'hint' => $turn->hint_revealed_at ? $turn->letter_hint : null,
                    'hint_available' => $turn->letter_hint !== null,
                    'hint_revealed' => $turn->hint_revealed_at !== null,
                    'can_reveal_hint' => $phase === 'drawing' && $isDrawer && $turn->letter_hint !== null
                        && ! $turn->hint_revealed_at && CarbonImmutable::now()->lessThan($turn->deadline_at),
                    'guesses' => $turn->guessMessages()->orderByDesc('id')->limit(50)->get()
                        ->reverse()->values()->map(function ($message) use ($playersById): array {
                            $guesser = $playersById->get($message->game_session_player_id);
                            return [
                                'id' => $message->id, 'public_id' => $guesser?->public_id,
                                'name' => $guesser?->display_name, 'is_correct' => $message->is_correct,
                                'text' => $message->is_correct ? null : $message->text,
                            ];
                        })->all(),
                    'turn_order' => $turnOrder->map(function ($t) use ($playersById): array {
                        $drawer = $playersById->get($t->drawer_id);
                        return ['public_id' => $drawer?->public_id, 'name' => $drawer?->display_name, 'position' => $t->position];
                    })->all(),
                ];
                if ($isDrawer && $phase === 'drawing') $sketch['prompt'] = $turn->prompt;
                if ($turn->closed_at) $sketch['revealed_word'] = $turn->prompt;
            }
            $closedCount = $session->sketchTurns()->whereNotNull('closed_at')->count();
            return [
                'game_type' => Game::TYPE_SKETCH, 'status' => $session->status, 'phase' => $phase, 'title' => $session->game_title,
                'min_players' => $session->min_players, 'max_players' => $session->max_players,
                'time_limit_per_question' => $session->time_limit_per_question,
                'letter_hint_max' => $session->letter_hint_max,
                'player_count' => $players->count(), 'ready_count' => $players->where('is_ready', true)->count(),
                'total_questions' => $session->total_questions, 'current_position' => $session->current_question_position,
                'server_now' => $now->toISOString(), 'question' => null, 'sketch' => $sketch,
                'answered' => $player && in_array($player->id, $guessedIds),
                'player' => $player ? ['public_id' => $player->public_id, 'name' => $player->display_name, 'profile' => $profiles[$player->id], 'is_ready' => (bool) $player->is_ready, 'gender' => GameSessionPlayer::GENDERS[$player->gender] ?? 'Not specified'] : null,
                'players' => $roster, 'results' => $results, 'leaderboard' => array_map($safeStanding, $standings), 'round_leaderboard' => $round, 'answer_reveal' => $answerReveal,
                'progress' => ['completed_questions' => $closedCount, 'total_questions' => $session->total_questions, 'percent' => $session->total_questions ? (int) floor(100 * $closedCount / $session->total_questions) : 0, 'answered_count' => count($guessedIds)],
            ];
        }, 3);
    }
}
