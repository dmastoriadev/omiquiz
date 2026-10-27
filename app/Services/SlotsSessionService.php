<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameSession;
use App\Models\GameSessionPlayer;
use App\Models\SlotRoll;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SlotsSessionService
{
    public const COUNTDOWN_SECONDS = 5;
    public const SPIN_SECONDS = 5;
    public const RESULT_SECONDS = 5;
    public const RESTART_REQUIRED_MESSAGE = 'This session used the previous small-player rules and cannot continue. The host needs to create a new Slots session with at least 6 players.';

    public function create(Game $game, User $host): GameSession
    {
        return DB::transaction(function () use ($game, $host): GameSession {
            $game = Game::query()->lockForUpdate()->findOrFail($game->id);
            if (! $game->isSlots() || $game->status !== Game::STATUS_ACTIVE) {
                $this->fail('game', 'Only an active Astoria Slots game can create this session.');
            }
            $capacity = $this->waitingCapacity($game);
            return $game->sessions()->create([
                'hosted_by' => $host->id, 'game_type' => Game::TYPE_SLOTS, 'game_title' => $game->title,
                'min_players' => $capacity['min_players'], 'max_players' => $capacity['max_players'],
                'time_limit_per_question' => self::SPIN_SECONDS,
                'slot_prize_positions' => SlotsPlanGenerator::DEFAULT_PRIZE_POSITIONS,
                'total_questions' => SlotsPlanGenerator::TOTAL_ROLLS,
            ]);
        }, 3);
    }

    /** Upgrade only rooms that have not assigned any private outcomes yet. */
    public function normalizeWaiting(GameSession $session, ?Game $lockedGame = null): GameSession
    {
        return DB::transaction(function () use ($session, $lockedGame): GameSession {
            $session = $this->lockSession($session);
            if ($session->status !== GameSession::STATUS_WAITING) return $session;
            if ($session->started_at || $session->slot_plan !== null || $session->slotRolls()->exists()) {
                $this->fail('session', 'This waiting session already has a saved Slots plan.');
            }
            if ($lockedGame && (int) $lockedGame->id !== (int) $session->game_id) {
                $this->fail('session', 'These game settings do not belong to this waiting session.');
            }
            // Read without taking a game lock after the session lock. An edit
            // already holds game then session locks and applies its new values
            // once this transaction ends. Start/edit pass that locked game so
            // an earlier transaction snapshot cannot restore stale settings.
            $game = $lockedGame ?? ($session->game_id ? Game::withTrashed()->find($session->game_id) : null);
            $capacity = $this->waitingCapacity($game ?? $session);
            // A locking read includes joins committed while start/edit waited
            // for this session, even under a repeatable-read transaction.
            $joined = $session->players()->orderBy('id')->lockForUpdate()->get(['id'])->count();
            if ($joined > 200) $this->fail('players', 'Astoria Slots supports at most 200 players.');
            $capacity['max_players'] = max($capacity['max_players'], $joined);
            $session->fill($capacity + [
                'slot_prize_positions' => SlotsPlanGenerator::DEFAULT_PRIZE_POSITIONS,
                'total_questions' => SlotsPlanGenerator::TOTAL_ROLLS,
                'time_limit_per_question' => self::SPIN_SECONDS,
            ]);
            if ($session->isDirty()) $session->save();
            return $session;
        }, 3);
    }

    public function start(GameSession $session): GameSession
    {
        return DB::transaction(function () use ($session): GameSession {
            // Game edits and starts always acquire the game before the session.
            $gameId = GameSession::query()->whereKey($session->id)->value('game_id');
            $game = $gameId ? Game::withTrashed()->lockForUpdate()->find($gameId) : null;
            $session = $this->lockSession($session);
            if ($session->status !== GameSession::STATUS_WAITING) {
                $this->fail('session', 'Only a waiting session can be started.');
            }
            $session = $this->normalizeWaiting($session, $game);
            $players = $session->players()->where('status', GameSessionPlayer::STATUS_JOINED)
                ->orderBy('joined_at')->orderBy('id')->lockForUpdate()->get();
            if ($players->count() < $session->min_players || $players->count() > $session->max_players) {
                $this->fail('players', "This game needs between {$session->min_players} and {$session->max_players} players.");
            }
            if ($session->slot_plan !== null || $session->slotRolls()->exists()) {
                $this->fail('session', 'This session already has a saved Slots plan.');
            }
            $plan = app(SlotsPlanGenerator::class)->generate(
                $players->pluck('id')->map(fn ($id): int => (int) $id)->all(), $session->slot_prize_positions,
            );
            foreach ($plan['rolls'] as $roll) {
                $session->slotRolls()->create([
                    'position' => $roll['position'], 'round_number' => 1,
                    'roll_number' => $roll['roll'], 'ready_player_ids' => [],
                ]);
            }
            $now = CarbonImmutable::now();
            $session->fill([
                'slot_prize_positions' => $plan['prize_positions'], 'slot_plan' => $plan,
                'status' => GameSession::STATUS_IN_PROGRESS, 'started_at' => $now,
                'current_question_position' => 1, 'total_questions' => SlotsPlanGenerator::TOTAL_ROLLS,
                'time_limit_per_question' => self::SPIN_SECONDS,
            ])->save();
            $session->players()->where('status', GameSessionPlayer::STATUS_JOINED)->update([
                'status' => GameSessionPlayer::STATUS_PLAYING, 'is_ready' => false,
                'last_seen_at' => $now->format('Y-m-d H:i:s.v'),
            ]);
            return $session->refresh();
        }, 3);
    }

    public function ready(GameSession $session, GameSessionPlayer $player, int $expectedPosition, bool $ready): GameSession
    {
        $outcome = DB::transaction(function () use ($session, $player, $expectedPosition, $ready): array {
            $session = $this->lockSession($session);
            $now = CarbonImmutable::now();
            $this->advanceLocked($session, $now);
            $member = $session->players()->find($player->id);
            if (! $member) return ['error' => ['player' => 'This player does not belong to this session.']];
            $totalRolls = self::totalRolls($session);
            if ($expectedPosition < 1 || $expectedPosition > $totalRolls) {
                return ['error' => ['expected_position' => 'Refresh to see the current roll.']];
            }
            // An acknowledged old Ready action may be retried, but can never
            // mark the next roll ready or restart a completed countdown.
            if (in_array($session->status, [GameSession::STATUS_IN_PROGRESS, GameSession::STATUS_COMPLETED], true)
                && $expectedPosition < $session->current_question_position) return ['session' => $session->refresh()];
            if ($session->status === GameSession::STATUS_COMPLETED && $expectedPosition === $totalRolls) {
                return ['session' => $session->refresh()];
            }
            if ($session->status !== GameSession::STATUS_IN_PROGRESS || $member->status !== GameSessionPlayer::STATUS_PLAYING) {
                return ['error' => ['session' => 'This session is not accepting Ready actions.']];
            }
            if ($expectedPosition !== $session->current_question_position) {
                return ['error' => ['expected_position' => 'The roll has changed. Refresh before pressing Ready.']];
            }
            $roster = $this->rosterIds($session);
            if (! in_array((int) $member->id, $roster, true)) {
                return ['error' => ['player' => 'This player was not in the starting roster.']];
            }
            $roll = $this->currentRoll($session);
            $readyIds = array_values(array_intersect($roster, $roll->ready_player_ids ?? []));
            if ($roll->countdown_started_at) {
                if ($ready && in_array((int) $member->id, $readyIds, true)) return ['session' => $session->refresh()];
                return ['error' => ['is_ready' => 'The countdown has started for this roll.']];
            }
            if ($ready) {
                if (! in_array((int) $member->id, $readyIds, true)) $readyIds[] = (int) $member->id;
            } else {
                $readyIds = array_values(array_filter($readyIds, fn (int $id): bool => $id !== (int) $member->id));
            }
            sort($readyIds);
            $roll->ready_player_ids = $readyIds;
            if (count($readyIds) === count($roster)) {
                // One locked transaction fixes all stage boundaries. Network
                // delays and repeated polls never extend any of the intervals.
                $roll->countdown_started_at = $now;
                $roll->spin_started_at = $now->addSeconds(self::COUNTDOWN_SECONDS);
                $roll->revealed_at = $roll->spin_started_at->addSeconds(self::SPIN_SECONDS);
                $roll->result_ends_at = $roll->revealed_at->addSeconds(self::RESULT_SECONDS);
            }
            $roll->save();
            $member->last_seen_at = $now;
            $member->save();
            return ['session' => $session->refresh()];
        }, 3);
        // Reject only after commit, so a stale request cannot undo due progress.
        if (isset($outcome['error'])) throw ValidationException::withMessages($outcome['error']);
        return $outcome['session'];
    }

    public function advance(GameSession $session): GameSession
    {
        return DB::transaction(function () use ($session): GameSession {
            $session = $this->lockSession($session);
            $this->advanceLocked($session, CarbonImmutable::now());
            return $session->refresh();
        }, 3);
    }

    /** Commit incompatible-session cancellation before a direct state read. */
    public function enforceCurrentRules(GameSession $session): GameSession
    {
        return DB::transaction(function () use ($session): GameSession {
            $session = $this->lockSession($session);
            self::validatePlan($session);
            $this->cancelIncompatiblePlan($session, CarbonImmutable::now());
            return $session->refresh();
        }, 3);
    }

    public static function restartRequired(GameSession $session): bool
    {
        return $session->status === GameSession::STATUS_CANCELLED
            && ($session->slot_plan['version'] ?? null) === 4
            && is_array($session->slot_plan['player_ids'] ?? null)
            && count($session->slot_plan['player_ids']) < 6;
    }

    public function cancel(GameSession $session): GameSession
    {
        $outcome = DB::transaction(function () use ($session): array {
            $session = $this->lockSession($session);
            $now = CarbonImmutable::now();
            $this->advanceLocked($session, $now);
            if ($session->status === GameSession::STATUS_COMPLETED) {
                return ['error' => ['session' => 'A completed session cannot be cancelled.']];
            }
            if ($session->status !== GameSession::STATUS_CANCELLED) {
                $session->fill(['status' => GameSession::STATUS_CANCELLED, 'cancelled_at' => $now])->save();
                $session->players()->whereIn('status', [GameSessionPlayer::STATUS_JOINED, GameSessionPlayer::STATUS_PLAYING])->update([
                    'status' => GameSessionPlayer::STATUS_LEFT, 'left_at' => $now->format('Y-m-d H:i:s.v'),
                ]);
            }
            return ['session' => $session->refresh()];
        }, 3);
        if (isset($outcome['error'])) throw ValidationException::withMessages($outcome['error']);
        return $outcome['session'];
    }

    /** New sessions have five rolls; already-started saved plans are immutable. */
    public static function totalRolls(GameSession $session): int
    {
        self::validatePlan($session);
        return ($session->slot_plan['version'] ?? null) === 1 ? 15 : SlotsPlanGenerator::TOTAL_ROLLS;
    }

    /** Reject damaged or unknown plans before advancing or revealing any reels. */
    public static function validatePlan(GameSession $session): void
    {
        $invalid = static function (): never {
            throw ValidationException::withMessages(['session' => 'The saved Slots plan is invalid.']);
        };
        $plan = $session->slot_plan;
        if (! $session->started_at) {
            if ($plan !== null || ! in_array($session->status, [GameSession::STATUS_WAITING, GameSession::STATUS_CANCELLED], true)) $invalid();
            return;
        }
        if (! is_array($plan) || $session->status === GameSession::STATUS_WAITING
            || ! in_array($plan['version'] ?? null, [1, 2, 3, 4, 5], true)) $invalid();
        $version = $plan['version'];
        $total = $version === 1 ? 15 : SlotsPlanGenerator::TOTAL_ROLLS;
        if (($version !== 1 || array_key_exists('total_rolls', $plan)) && ($plan['total_rolls'] ?? null) !== $total) $invalid();
        $rolls = $plan['rolls'] ?? null;
        $roster = $plan['player_ids'] ?? null;
        if (! is_array($rolls) || count($rolls) !== $total || array_diff(range(1, $total), array_keys($rolls)) !== []
            || ! is_array($roster) || ! array_is_list($roster)
            || count($roster) < ($version === 4 ? 2 : (in_array($version, [3, 5], true) ? 6 : 4)) || count($roster) > 200
            || $session->current_question_position < 1 || $session->current_question_position > $total) $invalid();
        foreach ($roster as $id) if (! is_int($id) || $id < 1) $invalid();
        if (count(array_unique($roster)) !== count($roster)) $invalid();

        // Historical versions retain the schedule saved at their original start.
        $positions = $plan['prize_positions'] ?? null;
        $keys = array_keys(SlotsPlanGenerator::DEFAULT_PRIZE_POSITIONS);
        if (! is_array($positions) || count($positions) !== 4 || array_diff($keys, array_keys($positions)) !== []) {
            $invalid();
        }
        if ($version === 1) {
            foreach ($positions as $prize => $position) {
                if (! is_int($position) || ($prize === 'gold' ? $position !== 15 : ! in_array($position, [2, 4, 5, 7, 9, 10, 12, 14], true))) $invalid();
            }
            if (count(array_unique($positions)) !== 4) $invalid();
        } else {
            $expected = $version === 2
                ? ['bronze' => 2, 'silver' => 4, 'black' => 4, 'gold' => 5]
                : ($version === 4 ? array_replace(SlotsPlanGenerator::DEFAULT_PRIZE_POSITIONS, [
                    'black' => array_slice([1, 3, 4], 0, min(3, count($roster) - 1)),
                ]) : SlotsPlanGenerator::DEFAULT_PRIZE_POSITIONS);
            foreach ($expected as $prize => $position) if ($positions[$prize] !== $position) $invalid();
        }
        $expectedAwards = [];
        foreach ($positions as $prize => $prizePositions) {
            foreach ((array) $prizePositions as $position) $expectedAwards[$position][$prize] = true;
        }
        $winnerIds = [];
        $blackIds = [];
        $medalIds = [];
        foreach ($rolls as $position => $roll) {
            if (! is_array($roll) || ($roll['position'] ?? null) !== $position
                || ($roll['roll'] ?? null) !== ($version === 1 ? (($position - 1) % 5) + 1 : $position)
                || ($version === 1 && ($roll['round'] ?? null) !== intdiv($position - 1, 5) + 1)) $invalid();
            $rows = $roll['players'] ?? null;
            if (! is_array($rows) || count($rows) !== count($roster)) $invalid();
            $awarded = [];
            foreach ($roster as $id) {
                $row = $rows[$id] ?? null;
                if (! is_array($row) || ! array_key_exists('prize', $row)
                    || ! is_array($row['reels'] ?? null) || ! array_is_list($row['reels']) || count($row['reels']) !== 3) $invalid();
                foreach ($row['reels'] as $symbol) {
                    if (! is_string($symbol) || ! isset(SlotsPlanGenerator::PRIZE_POINTS[$symbol])) $invalid();
                }
                $prize = $row['prize'];
                $triple = count(array_unique($row['reels'])) === 1;
                if ($prize === null) {
                    if ($triple) $invalid();
                    continue;
                }
                if (! is_string($prize) || ! isset($expectedAwards[$position][$prize])
                    || ! $triple || $row['reels'][0] !== $prize || isset($awarded[$prize])
                    || ($version !== 4 && isset($winnerIds[$id]))) $invalid();
                if ($prize === 'black') {
                    if (isset($winnerIds[$id])) $invalid();
                    $blackIds[$id] = true;
                } else {
                    if (isset($blackIds[$id])) $invalid();
                    $medalIds[$id] = true;
                }
                $winnerIds[$id] = true;
                $awarded[$prize] = true;
            }
            if (count($awarded) !== count($expectedAwards[$position] ?? [])) $invalid();
        }
        if ($version === 4) {
            $blackCount = min(3, count($roster) - 1);
            if (count($blackIds) !== $blackCount || count($medalIds) !== min(3, count($roster) - $blackCount)) $invalid();
        } elseif (count($winnerIds) !== (in_array($version, [3, 5], true) ? 6 : 4)) $invalid();
    }

    /** The same stage calculation is used by both state presentation and tests. */
    public static function phaseFor(SlotRoll $roll, CarbonImmutable $now): string
    {
        if (! $roll->countdown_started_at) return 'slots_ready';
        if ($now->lessThan($roll->spin_started_at)) return 'slots_countdown';
        if ($now->lessThan($roll->revealed_at)) return 'slots_spinning';
        return 'slots_result';
    }

    private function advanceLocked(GameSession $session, CarbonImmutable $now): void
    {
        self::validatePlan($session);
        $this->cancelIncompatiblePlan($session, $now);
        if ($session->status !== GameSession::STATUS_IN_PROGRESS) return;
        $roll = $this->currentRoll($session);
        if (! $roll->result_ends_at || $now->lessThan($roll->result_ends_at)) return;
        if ($roll->position === self::totalRolls($session)) {
            $this->finalize($session, $roll->result_ends_at, $now);
            return;
        }
        $next = $session->slotRolls()->where('position', $roll->position + 1)->first();
        if (! $next || $next->countdown_started_at || $next->ready_player_ids !== []) {
            $this->fail('session', 'The next Slots roll is not ready to accept players.');
        }
        $session->current_question_position = $next->position;
        $session->save();
        // Always stop at this fresh Ready gate, even when a scheduler resumes
        // long after the preceding result was due. Players start the next clock.
    }

    private function cancelIncompatiblePlan(GameSession $session, CarbonImmutable $now): void
    {
        if ($session->status !== GameSession::STATUS_IN_PROGRESS
            || ($session->slot_plan['version'] ?? null) !== 4
            || count($session->slot_plan['player_ids']) >= 6) return;
        // Retain the immutable plan and timestamps as history. Cancellation
        // freezes public visibility now, before any new gate or finalization.
        $session->fill(['status' => GameSession::STATUS_CANCELLED, 'cancelled_at' => $now])->save();
        $session->players()->whereIn('status', [GameSessionPlayer::STATUS_JOINED, GameSessionPlayer::STATUS_PLAYING])->update([
            'status' => GameSessionPlayer::STATUS_LEFT, 'left_at' => $now->format('Y-m-d H:i:s.v'),
        ]);
    }

    private function finalize(GameSession $session, CarbonImmutable $completedAt, CarbonImmutable $now): void
    {
        $plan = $session->slot_plan;
        $roster = $this->rosterIds($session);
        $players = $session->players()->whereIn('id', $roster)->get()->keyBy('id');
        $totalRolls = self::totalRolls($session);
        $rolls = $session->slotRolls()->orderBy('position')->get();
        if ($players->count() !== count($roster) || $rolls->pluck('position')->all() !== range(1, $totalRolls)
            || $rolls->contains(fn (SlotRoll $roll): bool => ! $roll->result_ends_at || $roll->result_ends_at->greaterThan($now))) {
            $this->fail('session', 'The Slots session has not revealed every roll.');
        }
        // totalRolls validated every saved outcome and the version's complete
        // award schedule, including the smaller-party medal repeats in v4.
        $wins = [];
        $scores = [];
        foreach (range(1, $totalRolls) as $position) {
            foreach ($plan['rolls'][$position]['players'] as $id => $row) {
                $prize = $row['prize'];
                if ($prize === null) continue;
                $points = SlotsPlanGenerator::PRIZE_POINTS[$prize];
                $scores[$id] = ($scores[$id] ?? 0) + $points;
                if (! isset($wins[$id]) || $points > SlotsPlanGenerator::PRIZE_POINTS[$wins[$id]['prize']]) {
                    $wins[$id] = ['prize' => $prize, 'position' => $position];
                }
            }
        }
        foreach ($roster as $id) {
            $player = $players->get($id);
            $win = $wins[$id] ?? null;
            $points = $win ? SlotsPlanGenerator::PRIZE_POINTS[$win['prize']] : 0;
            $player->result()->updateOrCreate([], [
                'game_session_id' => $session->id, 'player_name' => $player->display_name, 'player_email' => $player->email,
                'total_score' => $scores[$id] ?? 0, 'rank' => $points ? 5 - $points : 5,
                'total_questions' => $totalRolls,
                'slot_prize' => $win['prize'] ?? null, 'slot_won_position' => $win['position'] ?? null,
                'correct_answers' => 0, 'wrong_answers' => 0, 'unanswered_questions' => 0,
                'total_response_time_ms' => 0, 'guess_points' => 0, 'draw_points' => 0, 'finalized_at' => $now,
            ]);
            $player->fill(['status' => GameSessionPlayer::STATUS_FINISHED, 'finished_at' => $completedAt])->save();
        }
        $session->fill(['status' => GameSession::STATUS_COMPLETED, 'completed_at' => $completedAt])->save();
    }

    private function rosterIds(GameSession $session): array
    {
        self::validatePlan($session);
        return $session->slot_plan['player_ids'];
    }

    private function currentRoll(GameSession $session): SlotRoll
    {
        $roll = $session->slotRolls()->where('position', $session->current_question_position)->first();
        if (! $roll) $this->fail('session', 'The current Slots roll is missing.');
        return $roll;
    }

    private function waitingCapacity(Game|GameSession $game): array
    {
        if (! $game->isSlots() || $game->max_players < 2 || $game->max_players > 200) {
            $this->fail('players', 'Astoria Slots needs a capacity between 6 and 200 players.');
        }
        // Six distinct prizes require six people. Repair older small waiting
        // capacities while preserving any larger capacity and joined roster.
        return ['min_players' => 6, 'max_players' => max(6, (int) $game->max_players)];
    }

    private function lockSession(GameSession $session): GameSession
    {
        $session = GameSession::query()->lockForUpdate()->findOrFail($session->id);
        if (! $session->isSlots()) $this->fail('session', 'These controls are only available for Astoria Slots.');
        return $session;
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
