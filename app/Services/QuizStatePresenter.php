<?php

namespace App\Services;

use App\Models\GameSession;
use App\Models\GameSessionPlayer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QuizStatePresenter
{
    /** Spectators see submission status for this question, never cumulative answer progress. */
    public function spectator(GameSession $session): array
    {
        if ($session->isBingo()) return app(BingoStatePresenter::class)->spectator($session);
        if ($session->isSketch()) return app(SketchStatePresenter::class)->spectator($session);
        $state = $this->present($session);
        unset($state['progress'], $state['leaderboard']);
        $state['players'] = array_map(fn ($person): array => array_intersect_key($person, array_flip([
            'public_id', 'name', 'profile', 'queue_position', 'answered', 'is_ready',
        ])), $state['players']);

        return $state;
    }

    /** Shared spectator/host/player state; personal fields exist only for that player. */
    public function present(GameSession $session, ?GameSessionPlayer $player = null): array
    {
        if ($session->isBingo()) return app(BingoStatePresenter::class)->present($session, $player);
        if ($session->isSketch()) return app(SketchStatePresenter::class)->present($session, $player);
        return DB::transaction(function () use ($session, $player): array {
            $session = GameSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($player) {
                $player = $session->players()->findOrFail($player->id);
            }
            $catalog = app(RpgProfileCatalog::class);
            $players = $session->players()->withCount('answers')->orderBy('joined_at')->orderBy('id')->get();
            $question = $session->status === GameSession::STATUS_IN_PROGRESS
                ? $session->questions()->where('position', $session->current_question_position)->first() : null;
            $now = Carbon::now();
            $revealEndsAt = $question?->closed_at?->addSeconds(QuizSessionService::ANSWER_REVEAL_SECONDS);
            $phase = $session->status === GameSession::STATUS_IN_PROGRESS
                ? ($question?->closed_at
                    ? ($revealEndsAt && $now->lessThan($revealEndsAt) ? 'answer_reveal' : 'leaderboard')
                    : 'question') : $session->status;
            $answerReveal = $phase === 'answer_reveal' && $question ? [
                'question_id' => $question->id, 'question_position' => $question->position,
                'answer' => $question->options[$question->correct_option], 'option_key' => $question->correct_option,
                'starts_at' => $question->closed_at->toISOString(), 'ends_at' => $revealEndsAt->toISOString(),
                'duration_seconds' => QuizSessionService::ANSWER_REVEAL_SECONDS,
                'is_final' => $question->position >= $session->total_questions,
            ] : null;
            $answeredIds = $phase === 'question' && $question
                ? $question->answers()->whereNotNull('selected_option')->pluck('game_session_player_id')->all() : [];
            $selectedOption = $player && $phase === 'question' && $question
                ? $question->answers()->where('game_session_player_id', $player->id)->value('selected_option') : null;
            $closedQuestions = $session->questions()->whereNotNull('closed_at')->get(['id', 'time_limit_seconds']);
            $closedDuration = (int) $closedQuestions->sum('time_limit_seconds') * 1000;

            // Only closed questions contribute to standings; current answers remain private.
            $totals = $this->closedTotals($session);
            $profiles = $players->mapWithKeys(fn ($p): array => [
                $p->id => array_intersect_key($catalog->profile((int) ($p->rpg_profile_id ?: $p->id)), array_flip([
                    'id', 'avatar_url', 'sprite_url',
                ])),
            ]);
            $roster = $players->values()->map(function ($p, $index) use ($profiles, $totals, $answeredIds, $session): array {
                return [
                    'public_id' => $p->public_id, 'name' => $p->display_name,
                    'profile' => $profiles[$p->id], 'queue_position' => $index + 1,
                    'answered' => in_array($p->id, $answeredIds),
                    'is_ready' => (bool) $p->is_ready,
                    'questions_answered' => $p->answers_count,
                    'progress_percent' => $session->total_questions > 0
                        ? min(100, (int) floor($p->answers_count * 100 / $session->total_questions)) : 0,
                    'score' => (int) ($totals->get($p->id)->score ?? 0),
                ];
            })->all();
            $standings = $this->rankedStandings($players, $profiles, $totals, $closedDuration);
            $roundLeaderboard = null;
            if ($phase === 'leaderboard' && $question) {
                // Compare against the same roster before this question closed. This
                // survives refreshes and gives a newcomer to the top five a real origin.
                $before = $this->rankedStandings(
                    $players,
                    $profiles,
                    $this->closedTotals($session, $question->id),
                    max(0, $closedDuration - $question->time_limit_seconds * 1000),
                );
                $beforeByPlayer = array_column($before, null, 'public_id');
                $entries = array_map(function (array $standing) use ($beforeByPlayer): array {
                    $previous = $beforeByPlayer[$standing['public_id']];

                    return array_intersect_key($standing, array_flip([
                        'public_id', 'name', 'profile', 'score', 'rank', 'position',
                    ])) + [
                        'previous_rank' => $previous['rank'],
                        'previous_position' => $previous['position'],
                        'movement' => $previous['position'] - $standing['position'],
                    ];
                }, array_slice($standings, 0, 5));
                $roundLeaderboard = [
                    'question_id' => $question->id,
                    'question_position' => $question->position,
                    'starts_at' => $revealEndsAt->toISOString(),
                    'ends_at' => $revealEndsAt->addSeconds(QuizSessionService::LEADERBOARD_SECONDS)->toISOString(),
                    'duration_seconds' => QuizSessionService::LEADERBOARD_SECONDS,
                    'is_final' => $question->position >= $session->total_questions,
                    'entries' => $entries,
                ];
            }
            $results = $session->status === GameSession::STATUS_COMPLETED
                ? $session->results()->with('player')->get()->map(fn ($result): array => [
                    'public_id' => $result->player->public_id,
                    'profile' => $profiles[$result->game_session_player_id],
                    'rank' => $result->rank, 'name' => $result->player_name, 'score' => $result->total_score,
                    'correct' => $result->correct_answers, 'wrong' => $result->wrong_answers,
                    'unanswered' => $result->unanswered_questions,
                    'total_response_time_ms' => $result->total_response_time_ms,
                    'is_winner' => $result->rank === 1,
                ])->all() : [];
            if ($session->status === GameSession::STATUS_COMPLETED) {
                $standings = array_map(fn ($result): array => array_intersect_key($result, array_flip([
                    'public_id', 'name', 'profile', 'score', 'rank', 'total_response_time_ms',
                ])), $results);
                $finalByPlayer = array_column($results, null, 'public_id');
                foreach ($roster as &$person) {
                    if (isset($finalByPlayer[$person['public_id']])) {
                        $saved = $finalByPlayer[$person['public_id']];
                        $person['score'] = $saved['score'];
                        $person['questions_answered'] = $saved['correct'] + $saved['wrong'];
                        $person['progress_percent'] = $session->total_questions > 0
                            ? min(100, (int) floor($person['questions_answered'] * 100 / $session->total_questions)) : 0;
                    }
                }
                unset($person);
            }
            return [
                'game_type' => 'quiz', 'status' => $session->status, 'phase' => $phase, 'title' => $session->game_title,
                'min_players' => $session->min_players, 'max_players' => $session->max_players,
                'time_limit_per_question' => $session->time_limit_per_question,
                'player_count' => $players->count(), 'ready_count' => $players->where('is_ready', true)->count(),
                'total_questions' => $session->total_questions,
                'current_position' => $session->current_question_position, 'server_now' => $now->toISOString(),
                'question' => $phase === 'question' && $question ? [
                    'id' => $question->id, 'position' => $question->position, 'text' => $question->question_text,
                    'options' => $question->options, 'deadline_at' => $question->deadline_at?->toISOString(),
                ] : null,
                'answered' => $player ? in_array($player->id, $answeredIds) : false,
                'player' => $player ? [
                    'public_id' => $player->public_id, 'name' => $player->display_name,
                    'profile' => $profiles[$player->id],
                    'is_ready' => (bool) $player->is_ready,
                    'gender' => GameSessionPlayer::GENDERS[$player->gender] ?? 'Not specified',
                ] : null,
                'players' => $roster, 'results' => $results, 'leaderboard' => $standings,
                'round_leaderboard' => $roundLeaderboard, 'answer_reveal' => $answerReveal,
                'progress' => [
                    'completed_questions' => $closedQuestions->count(), 'total_questions' => $session->total_questions,
                    'percent' => $session->total_questions > 0
                        ? min(100, (int) floor($closedQuestions->count() * 100 / $session->total_questions)) : 0,
                    'answered_count' => count($answeredIds),
                ],
            ] + ($player ? ['selected_option' => $selectedOption] : []);
        }, 3);
    }

    /** Aggregate only accepted submissions belonging to closed questions. */
    private function closedTotals(GameSession $session, ?int $excludeQuestionId = null): Collection
    {
        return DB::table('game_answers as a')
            ->join('game_session_questions as q', 'q.id', '=', 'a.game_session_question_id')
            ->where('a.game_session_id', $session->id)
            ->whereNotNull('q.closed_at')->whereNotNull('a.selected_option')
            ->when($excludeQuestionId !== null, fn ($query) => $query->where('q.id', '!=', $excludeQuestionId))
            ->select('a.game_session_player_id')
            ->selectRaw('SUM(a.points_awarded) as score, SUM(a.response_time_ms) as response_ms, SUM(q.time_limit_seconds) as answered_seconds')
            ->groupBy('a.game_session_player_id')->get()->keyBy('game_session_player_id');
    }

    /** Stable display positions coexist with shared competition ranks for exact ties. */
    private function rankedStandings(Collection $players, Collection $profiles, Collection $totals, int $closedDuration): array
    {
        $standings = $players->map(function ($player) use ($profiles, $totals, $closedDuration): array {
            $total = $totals->get($player->id);

            return [
                'sort_id' => $player->id,
                'public_id' => $player->public_id, 'name' => $player->display_name, 'profile' => $profiles[$player->id],
                'score' => (int) ($total->score ?? 0),
                'total_response_time_ms' => max(0, $closedDuration - (int) ($total->answered_seconds ?? 0) * 1000)
                    + (int) ($total->response_ms ?? 0),
            ];
        })->all();
        usort($standings, fn ($left, $right): int => ($right['score'] <=> $left['score'])
            ?: ($left['total_response_time_ms'] <=> $right['total_response_time_ms'])
            ?: ($left['sort_id'] <=> $right['sort_id']));
        $previous = null;
        $rank = 0;
        foreach ($standings as $index => &$standing) {
            if ($previous === null || $standing['score'] !== $previous['score']
                || $standing['total_response_time_ms'] !== $previous['total_response_time_ms']) {
                $rank = $index + 1;
            }
            $standing['rank'] = $rank;
            $standing['position'] = $index + 1;
            unset($standing['sort_id']);
            $previous = $standing;
        }
        unset($standing);

        return $standings;
    }
}
