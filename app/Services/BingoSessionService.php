<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameSession;
use App\Models\GameSessionPlayer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BingoSessionService
{
    public function create(Game $game, User $host): GameSession
    {
        return DB::transaction(function () use ($game, $host): GameSession {
            $game = Game::query()->lockForUpdate()->findOrFail($game->id);
            if (! $game->isBingo() || $game->status !== Game::STATUS_ACTIVE) {
                $this->fail('game', 'Only an active Reverse Bingo game can create this session.');
            }
            $this->validateSettings($game);
            return $game->sessions()->create([
                'hosted_by' => $host->id, 'game_type' => Game::TYPE_BINGO, 'game_title' => $game->title,
                'min_players' => $game->min_players, 'max_players' => $game->max_players,
                'time_limit_per_question' => $game->time_limit_per_question,
                'bingo_min_plays' => $game->bingo_min_plays, 'bingo_max_plays' => $game->bingo_max_plays,
                'total_questions' => $game->bingo_max_plays,
            ]);
        }, 3);
    }

    public function start(GameSession $session): GameSession
    {
        return DB::transaction(function () use ($session): GameSession {
            // Match the game-edit lock order before locking this waiting room.
            $gameId = GameSession::query()->whereKey($session->id)->value('game_id');
            $game = $gameId ? Game::withTrashed()->lockForUpdate()->find($gameId) : null;
            $session = $this->lockSession($session);
            if ($session->status !== GameSession::STATUS_WAITING) {
                $this->fail('session', 'Only a waiting session can be started.');
            }
            if ($game) {
                $session->bingo_min_plays = $game->bingo_min_plays;
                $session->bingo_max_plays = $game->bingo_max_plays;
            }
            $this->validateSettings($session);
            $players = $session->players()->where('status', GameSessionPlayer::STATUS_JOINED)
                ->orderBy('joined_at')->orderBy('id')->get();
            if ($players->count() < $session->min_players || $players->count() > $session->max_players) {
                $this->fail('players', "This game needs between {$session->min_players} and {$session->max_players} players.");
            }
            if ($session->bingoDraws()->exists() || $session->bingoCards()->exists() || $session->bingo_plan !== null) {
                $this->fail('session', 'This session already has a saved bingo plan.');
            }
            $plan = app(BingoPlanGenerator::class)->make($players->pluck('id')->map(fn ($id): int => (int) $id)->all(), $session->bingo_min_plays, $session->bingo_max_plays);
            foreach ($plan['draws'] as $index => $number) {
                $session->bingoDraws()->create(['position' => $index + 1, 'number' => $number]);
            }
            foreach ($plan['players'] as $row) {
                foreach ($row['cards'] as $index => $card) {
                    $session->bingoCards()->create([
                        'game_session_player_id' => $row['player_id'], 'slot' => $index + 1,
                        'number' => $card['number'], 'cells' => $card['cells'],
                    ]);
                }
            }
            $now = CarbonImmutable::now();
            $session->fill([
                'bingo_plan' => [
                    'version' => $plan['version'],
                    'players' => array_map(fn (array $row): array => array_intersect_key($row, array_flip(['player_id', 'rank', 'eliminated_on_draw'])), $plan['players']),
                    'forecast' => $plan['forecast'],
                ],
                'status' => GameSession::STATUS_IN_PROGRESS, 'started_at' => $now,
                'current_question_position' => 0, 'total_questions' => $session->bingo_max_plays,
            ])->save();
            $session->players()->where('status', GameSessionPlayer::STATUS_JOINED)->update([
                'status' => GameSessionPlayer::STATUS_PLAYING, 'last_seen_at' => $now->format('Y-m-d H:i:s.v'),
            ]);
            return $session->refresh();
        }, 3);
    }

    /** Exactly one persisted draw per host action; polls and the scheduler never call this. */
    public function revealNext(GameSession $session, int $expectedPosition): GameSession
    {
        if ($expectedPosition < 0) $this->fail('expected_position', 'Refresh to see the current draw.');
        return DB::transaction(function () use ($session, $expectedPosition): GameSession {
            $session = $this->lockSession($session);
            // Concurrent double taps and a retry after a lost response cannot consume another draw.
            if (in_array($session->status, [GameSession::STATUS_IN_PROGRESS, GameSession::STATUS_COMPLETED], true)
                && $expectedPosition < $session->current_question_position) return $session->refresh();
            if ($session->status !== GameSession::STATUS_IN_PROGRESS) {
                $this->fail('session', 'Only a running Reverse Bingo game can reveal a number.');
            }
            if ($expectedPosition !== $session->current_question_position) {
                $this->fail('expected_position', 'The draw has changed. Refresh before revealing the next number.');
            }
            $position = $session->current_question_position + 1;
            $draw = $session->bingoDraws()->where('position', $position)->first();
            if (! $draw || $draw->revealed_at || $position > $session->bingo_max_plays) {
                $this->fail('session', 'The saved draw sequence is incomplete.');
            }
            $now = CarbonImmutable::now();
            $draw->update(['revealed_at' => $now]);
            // Evaluate the persisted visible grid, including every numbered cell.
            // Null cells preserve already-running legacy single-number sessions.
            $matchingIds = $session->bingoCards()->whereNull('closed_on_draw')->get()
                ->filter(fn ($card): bool => $card->cells === null
                    ? $card->number === $draw->number
                    : in_array($draw->number, $card->cells, true))->modelKeys();
            if ($matchingIds !== []) {
                $session->bingoCards()->whereKey($matchingIds)->update([
                    'closed_on_draw' => $position, 'closed_at' => $now->format('Y-m-d H:i:s.v'),
                ]);
            }
            $session->current_question_position = $position;
            $session->save();
            if ($position === $session->bingo_max_plays) $this->finalize($session, $now);
            return $session->refresh();
        }, 3);
    }

    public function cancel(GameSession $session): GameSession
    {
        return DB::transaction(function () use ($session): GameSession {
            $session = $this->lockSession($session);
            if ($session->status === GameSession::STATUS_COMPLETED) {
                $this->fail('session', 'A completed session cannot be cancelled.');
            }
            if ($session->status !== GameSession::STATUS_CANCELLED) {
                $now = CarbonImmutable::now();
                $session->fill(['status' => GameSession::STATUS_CANCELLED, 'cancelled_at' => $now])->save();
                $session->players()->whereIn('status', [GameSessionPlayer::STATUS_JOINED, GameSessionPlayer::STATUS_PLAYING])->update([
                    'status' => GameSessionPlayer::STATUS_LEFT, 'left_at' => $now->format('Y-m-d H:i:s.v'),
                ]);
            }
            return $session->refresh();
        }, 3);
    }

    private function finalize(GameSession $session, CarbonImmutable $now): void
    {
        $players = $session->players()->with('bingoCards')->get()->keyBy('id');
        $rows = $session->bingo_plan['players'] ?? [];
        $remainingCards = $session->bingoCards()->whereNull('closed_on_draw')->get();
        if (count($rows) !== $players->count() || $remainingCards->count() !== 1) {
            $this->fail('session', 'The saved bingo plan does not have exactly one winning card.');
        }
        foreach ($rows as $row) {
            $player = $players->get($row['player_id']);
            $remaining = $player?->bingoCards->whereNull('closed_on_draw')->count();
            $eliminatedOn = $remaining ? null : (int) $player?->bingoCards->max('closed_on_draw');
            if (! $player || $player->bingoCards->count() !== 3 || $remaining !== ($row['rank'] === 1 ? 1 : 0)
                || $eliminatedOn !== $row['eliminated_on_draw']) {
                $this->fail('session', 'The saved bingo results do not match the revealed cards.');
            }
            $player->result()->updateOrCreate([], [
                'game_session_id' => $session->id, 'player_name' => $player->display_name, 'player_email' => $player->email,
                'total_score' => $row['rank'] === 1 ? $session->bingo_max_plays + 1 : $eliminatedOn,
                'rank' => $row['rank'], 'total_questions' => $session->bingo_max_plays,
                'bingo_cards_remaining' => $remaining, 'bingo_eliminated_on_draw' => $eliminatedOn,
                'correct_answers' => 0, 'wrong_answers' => 0, 'unanswered_questions' => 0,
                'total_response_time_ms' => 0, 'guess_points' => 0, 'draw_points' => 0, 'finalized_at' => $now,
            ]);
            $player->fill(['status' => GameSessionPlayer::STATUS_FINISHED, 'finished_at' => $now])->save();
        }
        $session->fill(['status' => GameSession::STATUS_COMPLETED, 'completed_at' => $now])->save();
    }

    private function validateSettings(Game|GameSession $game): void
    {
        if (! $game->isBingo() || $game->min_players < 2 || $game->max_players > 32 || $game->max_players < $game->min_players
            || $game->bingo_max_plays < 6 || $game->bingo_max_plays > 50
            || $game->bingo_min_plays < 3 || $game->bingo_min_plays > $game->bingo_max_plays - 3) {
            $this->fail('game', 'Reverse Bingo needs 2–32 players, 6–50 total plays, and a first elimination between play 3 and three plays before the end.');
        }
    }

    private function lockSession(GameSession $session): GameSession
    {
        $session = GameSession::query()->lockForUpdate()->findOrFail($session->id);
        if (! $session->isBingo()) $this->fail('session', 'These controls are only available for Reverse Bingo.');
        return $session;
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
