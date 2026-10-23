<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameSession;
use App\Models\GameSessionPlayer;
use App\Models\SketchTurn;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SketchSessionService
{
    public const LEADERBOARD_SECONDS = 7;
    public const MAX_EVENTS = 5000;

    public static function normalizeGuess(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? ''), 'UTF-8');
    }

    public function create(Game $game, User $host): GameSession
    {
        return DB::transaction(function () use ($game, $host): GameSession {
            $game = Game::query()->lockForUpdate()->findOrFail($game->id);
            if (! $game->isSketch() || $game->status !== Game::STATUS_ACTIVE) {
                $this->fail('game', 'Only an active Sketch & Guess game can create this session.');
            }
            $this->validateSettings($game);
            // Every uploaded word gets one turn; player capacity never limits the bank.
            $bank = $this->wordBank($game);
            return $game->sessions()->create([
                'hosted_by' => $host->id, 'game_type' => Game::TYPE_SKETCH,
                'game_title' => $game->title, 'min_players' => $game->min_players,
                'max_players' => $game->max_players, 'time_limit_per_question' => $game->time_limit_per_question,
                'letter_hint_max' => $game->letter_hint_max,
                'sketch_words' => $bank, 'total_questions' => count($bank),
            ]);
        }, 3);
    }

    public function start(GameSession $session): GameSession
    {
        return DB::transaction(function () use ($session): GameSession {
            $gameId = GameSession::query()->whereKey($session->id)->value('game_id');
            $game = $gameId ? Game::withTrashed()->lockForUpdate()->find($gameId) : null;
            $session = $this->lockSession($session);
            if ($session->status !== GameSession::STATUS_WAITING) {
                $this->fail('session', 'Only a waiting session can be started.');
            }
            if ($game) {
                $session->time_limit_per_question = $game->time_limit_per_question;
                $session->letter_hint_max = $game->letter_hint_max;
            }
            $this->validateSettings($session);
            $players = $session->players()->where('status', GameSessionPlayer::STATUS_JOINED)->orderBy('joined_at')->orderBy('id')->get();
            if ($players->count() < $session->min_players || $players->count() > $session->max_players) {
                $this->fail('players', "This game needs between {$session->min_players} and {$session->max_players} players.");
            }
            $bank = $session->sketch_words;
            // Earlier waiting rooms stored only a sample and always had zero turns.
            // Repair those rooms from their game's full bank before any score exists.
            if ($session->total_questions === 0 && $game) $bank = $this->wordBank($game);
            if (! is_array($bank) || ! array_is_list($bank) || $bank === [] || count($bank) > 2000) {
                $this->fail('words', 'This session needs between 1 and 2000 drawing prompts.');
            }
            foreach ($bank as $word) $this->validateWord($word['word'] ?? '', $word['aliases'] ?? []);
            $now = $this->now();
            $players = $players->values();
            foreach ($bank as $index => $word) {
                $player = $players[$index % $players->count()];
                $turn = $session->sketchTurns()->create([
                    'drawer_id' => $player->id, 'position' => $index + 1,
                    'prompt' => $word['word'], 'aliases' => $word['aliases'] ?? [],
                    'letter_hint' => app(SketchHintGenerator::class)->make($word['word'], $session->letter_hint_max),
                    'time_limit_seconds' => $session->time_limit_per_question,
                ]);
                if ($index === 0) $this->openTurn($turn, $now);
            }
            $session->fill([
                'status' => GameSession::STATUS_IN_PROGRESS, 'started_at' => $now,
                'current_question_position' => 1, 'total_questions' => count($bank),
                'sketch_words' => null,
            ])->save();
            $session->players()->where('status', GameSessionPlayer::STATUS_JOINED)->update([
                'status' => GameSessionPlayer::STATUS_PLAYING, 'last_seen_at' => $now->format('Y-m-d H:i:s.v'),
            ]);
            return $session->refresh();
        }, 3);
    }

    public function advance(GameSession $session): GameSession
    {
        return DB::transaction(function () use ($session): GameSession {
            $session = $this->lockSession($session);
            $this->advanceLocked($session, $this->now());
            return $session->refresh();
        }, 3);
    }

    public function guess(GameSession $session, GameSessionPlayer $player, int $turnId, string $guess): array
    {
        if (Str::length($guess) > 100 || self::normalizeGuess($guess) === '') $this->fail('guess', 'Enter a guess between 1 and 100 characters.');
        $outcome = DB::transaction(function () use ($session, $player, $turnId, $guess): array {
            $session = $this->lockSession($session);
            $now = $this->now();
            $this->advanceLocked($session, $now);
            $member = $session->players()->find($player->id);
            $turn = $session->sketchTurns()->find($turnId);
            if (! $member || ! $turn) return ['error' => ['turn_id' => 'This player or turn does not belong to the session.']];
            if ($turn->drawer_id === $member->id) return ['error' => ['guess' => 'The drawer cannot guess their own word.']];
            $existing = $turn->guesses()->where('game_session_player_id', $member->id)->first();
            if ($existing) return ['correct' => true, 'already_guessed' => true, 'points_awarded' => $existing->points_awarded, 'drawer_bonus' => $existing->drawer_bonus];
            if (! $this->isOpen($session, $turn, $now) || $member->status !== GameSessionPlayer::STATUS_PLAYING) {
                return ['error' => ['turn_id' => 'This drawing turn is no longer accepting guesses.']];
            }
            $normalized = self::normalizeGuess($guess);
            $answers = array_map(self::normalizeGuess(...), [$turn->prompt, ...($turn->aliases ?? [])]);
            $correct = in_array($normalized, $answers, true);
            // Correct guesses announce the player without publishing the secret word.
            // This timeline is separate from the correct-only scoring ledger.
            $turn->guessMessages()->create([
                'game_session_id' => $session->id, 'game_session_player_id' => $member->id,
                'text' => $correct ? null : trim($guess), 'is_correct' => $correct, 'guessed_at' => $now,
            ]);
            if (! $correct) return ['correct' => false, 'already_guessed' => false, 'points_awarded' => 0, 'drawer_bonus' => 0];
            $elapsed = max(0, $this->milliseconds($now) - $this->milliseconds($turn->opened_at));
            $points = max(100, 1000 - (int) floor(900 * $elapsed / ($turn->time_limit_seconds * 1000)));
            $bonus = (int) floor($points / 4);
            $turn->guesses()->create([
                'game_session_id' => $session->id, 'game_session_player_id' => $member->id,
                'points_awarded' => $points, 'drawer_bonus' => $bonus,
                'response_time_ms' => $elapsed, 'guessed_at' => $now,
            ]);
            $member->last_seen_at = $now;
            $member->save();
            return ['correct' => true, 'already_guessed' => false, 'points_awarded' => $points, 'drawer_bonus' => $bonus];
        }, 3);
        return $this->unwrap($outcome);
    }

    public function revealHint(GameSession $session, GameSessionPlayer $player, int $turnId): GameSession
    {
        $outcome = DB::transaction(function () use ($session, $player, $turnId): array {
            $session = $this->lockSession($session);
            $now = $this->now();
            $this->advanceLocked($session, $now);
            $member = $session->players()->find($player->id);
            $turn = $session->sketchTurns()->find($turnId);
            if (! $member || ! $turn || $turn->drawer_id !== $member->id) {
                return ['error' => ['player' => 'Only this turn’s drawer can reveal the hint.']];
            }
            if (! $this->isOpen($session, $turn, $now) || $member->status !== GameSessionPlayer::STATUS_PLAYING) {
                return ['error' => ['turn_id' => 'This drawing turn has ended.']];
            }
            if ($turn->letter_hint === null) return ['error' => ['hint' => 'Letter hints are not enabled for this turn.']];
            if (! $turn->hint_revealed_at) {
                $turn->hint_revealed_at = $now;
                $turn->save();
            }
            return ['session' => $session->refresh()];
        }, 3);
        return $this->unwrap($outcome)['session'];
    }

    public function appendDrawing(GameSession $session, GameSessionPlayer $player, int $turnId, string $requestId, array $events): array
    {
        if (! preg_match('/^[a-zA-Z0-9_-]{8,64}$/D', $requestId)) $this->fail('request_id', 'A valid drawing request identifier is required.');
        $events = $this->validateEvents($events);
        $outcome = DB::transaction(function () use ($session, $player, $turnId, $requestId, $events): array {
            $session = $this->lockSession($session);
            $now = $this->now();
            $this->advanceLocked($session, $now);
            $turn = $session->sketchTurns()->find($turnId);
            $member = $session->players()->find($player->id);
            if (! $turn || ! $member || $turn->drawer_id !== $member->id) return ['error' => ['player' => 'Only this turn’s drawer can change the drawing.']];
            $existing = $turn->strokes()->where('request_id', $requestId)->orderBy('event_index')->get();
            if ($existing->isNotEmpty()) {
                if ($this->validateEvents($existing->pluck('payload')->all()) !== $events) return ['error' => ['request_id' => 'This drawing request was already used for different content.']];
                return ['turn_id' => $turn->id, 'cursor' => (int) $existing->max('seq'), 'events' => $existing->map($this->eventData(...))->all()];
            }
            if (! $this->isOpen($session, $turn, $now) || $member->status !== GameSessionPlayer::STATUS_PLAYING) return ['error' => ['turn_id' => 'This drawing turn has ended.']];
            if ($turn->stroke_sequence + count($events) > self::MAX_EVENTS) return ['error' => ['events' => 'This turn has reached its drawing limit.']];
            if (in_array('undo', array_column($events, 'type'), true) && ! $this->validUndoTargets($turn, $events)) {
                return ['error' => ['events' => 'That stroke cannot be undone. Refresh to see the current sketch.']];
            }
            $saved = [];
            $sequence = $turn->stroke_sequence;
            foreach ($events as $index => $event) {
                $saved[] = $this->eventData($turn->strokes()->create([
                    'game_session_id' => $session->id, 'seq' => ++$sequence,
                    'request_id' => $requestId, 'event_index' => $index, 'payload' => $event,
                ]));
            }
            $turn->stroke_sequence = $sequence;
            $turn->save();
            return ['turn_id' => $turn->id, 'cursor' => $turn->stroke_sequence, 'events' => $saved];
        }, 3);
        return $this->unwrap($outcome);
    }

    public function drawingEvents(GameSession $session, int $turnId, int $after): array
    {
        $outcome = DB::transaction(function () use ($session, $turnId, $after): array {
            $session = $this->lockSession($session);
            $this->advanceLocked($session, $this->now());
            $turn = $session->sketchTurns()->whereKey($turnId)->where('position', $session->current_question_position)->first();
            if (! $turn || ! $turn->opened_at) return ['error' => ['turn_id' => 'Refresh to see the current drawing turn.']];
            $after = max(0, min($after, $turn->stroke_sequence));
            $events = $turn->strokes()->where('seq', '>', $after)->limit(100)->get();
            $cursor = $events->isEmpty() ? $after : (int) $events->last()->seq;
            return ['turn_id' => $turn->id, 'events' => $events->map($this->eventData(...))->all(), 'cursor' => $cursor, 'has_more' => $cursor < $turn->stroke_sequence];
        }, 3);
        return $this->unwrap($outcome);
    }

    public function cancel(GameSession $session): GameSession
    {
        $outcome = DB::transaction(function () use ($session): array {
            $session = $this->lockSession($session);
            $now = $this->now();
            $this->advanceLocked($session, $now);
            if ($session->status === GameSession::STATUS_COMPLETED) return ['error' => ['session' => 'A completed session cannot be cancelled.']];
            if ($session->status !== GameSession::STATUS_CANCELLED) {
                $turn = $session->sketchTurns()->where('position', $session->current_question_position)->first();
                if ($turn && ! $turn->closed_at) { $turn->closed_at = $now; $turn->save(); }
                $session->fill(['status' => GameSession::STATUS_CANCELLED, 'cancelled_at' => $now])->save();
                $session->players()->whereIn('status', [GameSessionPlayer::STATUS_JOINED, GameSessionPlayer::STATUS_PLAYING])->update(['status' => GameSessionPlayer::STATUS_LEFT, 'left_at' => $now->format('Y-m-d H:i:s.v')]);
            }
            return ['session' => $session->refresh()];
        }, 3);
        return $this->unwrap($outcome)['session'];
    }

    /** Totals use only closed turns and can also describe the standings before one turn. */
    public function standings(GameSession $session, ?int $beforePosition = null): array
    {
        $turns = $session->sketchTurns()->whereNotNull('closed_at')->when($beforePosition !== null, fn ($q) => $q->where('position', '<', $beforePosition))
            ->get(['drawer_id', 'time_limit_seconds']);
        $totalsQuery = DB::table('sketch_guesses as g')->join('sketch_turns as t', 't.id', '=', 'g.sketch_turn_id')
            ->where('g.game_session_id', $session->id)->whereNotNull('t.closed_at')
            ->when($beforePosition !== null, fn ($q) => $q->where('t.position', '<', $beforePosition));
        $guesses = (clone $totalsQuery)->select('g.game_session_player_id')
            ->selectRaw('SUM(g.points_awarded) as points, SUM(g.response_time_ms) as response_ms, SUM(t.time_limit_seconds) as answered_seconds, COUNT(*) as correct_count')
            ->groupBy('g.game_session_player_id')->get()->keyBy('game_session_player_id');
        $drawerBonus = (clone $totalsQuery)->select('t.drawer_id')->selectRaw('SUM(g.drawer_bonus) as bonus')
            ->groupBy('t.drawer_id')->get()->keyBy('drawer_id');
        $closedDuration = (int) $turns->sum('time_limit_seconds') * 1000;
        $ownTurnsByPlayer = $turns->groupBy('drawer_id');
        $catalog = app(RpgProfileCatalog::class);
        $rows = [];
        foreach ($session->players()->orderBy('id')->get() as $player) {
            $correct = $guesses->get($player->id);
            $guessPoints = (int) ($correct->points ?? 0);
            $drawPoints = (int) ($drawerBonus->get($player->id)->bonus ?? 0);
            $ownTurns = $ownTurnsByPlayer->get($player->id, collect());
            $eligible = $turns->count() - $ownTurns->count();
            $correctCount = (int) ($correct->correct_count ?? 0);
            $missed = max(0, $eligible - $correctCount);
            $response = max(0, $closedDuration - (int) $ownTurns->sum('time_limit_seconds') * 1000 - (int) ($correct->answered_seconds ?? 0) * 1000)
                + (int) ($correct->response_ms ?? 0);
            $rows[] = [
                'sort_id' => $player->id, 'player_id' => $player->id,
                'public_id' => $player->public_id, 'name' => $player->display_name,
                'profile' => array_intersect_key($catalog->profile((int) ($player->rpg_profile_id ?: $player->id)), array_flip(['id', 'avatar_url', 'sprite_url'])),
                'score' => $guessPoints + $drawPoints, 'guess_points' => $guessPoints, 'draw_points' => $drawPoints,
                'correct' => $correctCount, 'wrong' => 0, 'unanswered' => $missed,
                'total_questions' => $eligible, 'total_response_time_ms' => $response,
            ];
        }
        usort($rows, fn ($a, $b): int => ($b['score'] <=> $a['score']) ?: ($a['total_response_time_ms'] <=> $b['total_response_time_ms']) ?: ($a['sort_id'] <=> $b['sort_id']));
        $previous = null; $rank = 0;
        foreach ($rows as $index => &$row) {
            if ($previous === null || $row['score'] !== $previous['score'] || $row['total_response_time_ms'] !== $previous['total_response_time_ms']) $rank = $index + 1;
            $row['rank'] = $rank; $row['position'] = $index + 1;
            unset($row['sort_id']);
            $previous = $row;
        }
        unset($row);
        return $rows;
    }

    private function advanceLocked(GameSession $session, CarbonImmutable $now): void
    {
        if ($session->status !== GameSession::STATUS_IN_PROGRESS) return;
        while (true) {
            $turn = $session->sketchTurns()->where('position', $session->current_question_position)->first();
            if (! $turn || ! $turn->opened_at || ! $turn->deadline_at) $this->fail('session', 'The drawing timeline is incomplete.');
            if ($now->lessThan($turn->deadline_at)) return;
            if (! $turn->closed_at) { $turn->closed_at = $turn->deadline_at; $turn->save(); }
            $nextAt = $turn->deadline_at->addSeconds(self::LEADERBOARD_SECONDS);
            if ($now->lessThan($nextAt)) return;
            $next = $session->sketchTurns()->where('position', '>', $turn->position)->first();
            if (! $next) { $this->finalize($session, $nextAt, $now); return; }
            $this->openTurn($next, $nextAt);
            $session->current_question_position = $next->position;
            $session->save();
        }
    }

    private function finalize(GameSession $session, CarbonImmutable $completedAt, CarbonImmutable $now): void
    {
        foreach ($this->standings($session) as $row) {
            $player = $session->players()->findOrFail($row['player_id']);
            $player->result()->updateOrCreate([], [
                'game_session_id' => $session->id, 'player_name' => $player->display_name, 'player_email' => $player->email,
                'total_score' => $row['score'], 'guess_points' => $row['guess_points'], 'draw_points' => $row['draw_points'],
                'correct_answers' => $row['correct'], 'wrong_answers' => 0, 'unanswered_questions' => $row['unanswered'],
                'total_questions' => $row['total_questions'], 'total_response_time_ms' => $row['total_response_time_ms'],
                'rank' => $row['rank'], 'finalized_at' => $now,
            ]);
            $player->fill(['status' => GameSessionPlayer::STATUS_FINISHED, 'finished_at' => $completedAt])->save();
        }
        $session->fill(['status' => GameSession::STATUS_COMPLETED, 'completed_at' => $completedAt])->save();
    }

    private function validateEvents(array $events): array
    {
        if (! array_is_list($events) || count($events) < 1 || count($events) > 8 || strlen(json_encode($events) ?: '') > 120000) $this->fail('events', 'Send between 1 and 8 drawing events per request.');
        return array_map(function ($event): array {
            if (! is_array($event) || ! in_array($event['type'] ?? null, ['stroke', 'clear', 'undo'], true)) $this->fail('events', 'Invalid drawing event.');
            if ($event['type'] === 'clear') return ['type' => 'clear'];
            $strokeId = $event['stroke_id'] ?? null;
            if (($strokeId !== null || $event['type'] === 'undo')
                && (! is_string($strokeId) || ! preg_match('/^[a-zA-Z0-9_-]{8,64}$/D', $strokeId))) {
                $this->fail('events', 'A valid stroke identifier is required.');
            }
            if ($event['type'] === 'undo') return ['type' => 'undo', 'stroke_id' => $strokeId];
            $points = $event['points'] ?? null;
            $width = $event['width'] ?? null;
            if (! is_array($points) || ! array_is_list($points) || count($points) < 1 || count($points) > 100
                || ! is_numeric($width) || ! is_finite((float) $width) || $width < 1 || $width > 20
                || ! is_string($event['color'] ?? null) || ! preg_match('/^#[0-9a-fA-F]{6}$/D', $event['color'])) $this->fail('events', 'Invalid brush, color, or stroke size.');
            $normalized = array_map(function ($point): array {
                if (! is_array($point)) $this->fail('events', 'Invalid drawing coordinate.');
                foreach (['x', 'y'] as $axis) {
                    if (! isset($point[$axis]) || ! is_numeric($point[$axis]) || ! is_finite((float) $point[$axis]) || $point[$axis] < 0 || $point[$axis] > 1) $this->fail('events', 'Drawing coordinates must be between zero and one.');
                }
                return ['x' => round((float) $point['x'], 5), 'y' => round((float) $point['y'], 5)];
            }, $points);
            $stroke = ['type' => 'stroke', 'points' => $normalized, 'color' => strtolower($event['color']), 'width' => round((float) $width, 2)];
            if ($strokeId !== null) $stroke['stroke_id'] = $strokeId;
            return $stroke;
        }, $events);
    }

    /** Validate only undo requests; normal stroke writes retain their cheap append path. */
    private function validUndoTargets(SketchTurn $turn, array $events): bool
    {
        $active = [];
        foreach ($turn->strokes()->get(['payload']) as $stroke) {
            $event = $stroke->payload;
            if ($event['type'] === 'clear') $active = [];
            elseif ($event['type'] === 'undo') unset($active[$event['stroke_id']]);
            elseif (isset($event['stroke_id'])) $active[$event['stroke_id']] = true;
        }
        foreach ($events as $event) {
            if ($event['type'] === 'clear') $active = [];
            elseif ($event['type'] === 'undo') {
                if (! isset($active[$event['stroke_id']])) return false;
                unset($active[$event['stroke_id']]);
            } elseif (isset($event['stroke_id'])) $active[$event['stroke_id']] = true;
        }
        return true;
    }

    private function eventData($event): array
    {
        return ['seq' => $event->seq, 'request_id' => $event->request_id, 'event_index' => $event->event_index] + $event->payload;
    }

    private function wordBank(Game $game): array
    {
        $words = $game->words()->where('is_active', true)->limit(2001)->get(['word', 'aliases']);
        if ($words->isEmpty() || $words->count() > 2000) {
            $this->fail('words', 'Add between 1 and 2000 active drawing words.');
        }
        foreach ($words as $word) $this->validateWord($word->word, $word->aliases ?? []);

        return $words->shuffle()->map(fn ($word): array => [
            'word' => $word->word, 'aliases' => $word->aliases ?? [],
        ])->values()->all();
    }

    private function validateWord(mixed $word, mixed $aliases): void
    {
        if (! is_string($word) || self::normalizeGuess($word) === '' || Str::length($word) > 100 || ! is_array($aliases) || count($aliases) > 10) $this->fail('words', 'Drawing words must contain 1–100 characters and at most 10 accepted alternatives.');
        foreach ($aliases as $alias) if (! is_string($alias) || self::normalizeGuess($alias) === '' || Str::length($alias) > 100) $this->fail('words', 'Each accepted alternative must contain 1–100 characters.');
    }

    private function validateSettings(Game|GameSession $game): void
    {
        if ($game->min_players < 2 || $game->max_players < $game->min_players || $game->max_players > 200) $this->fail('players', 'Sketch & Guess needs 2–200 players.');
        if ($game->time_limit_per_question < 1 || $game->time_limit_per_question > 300) $this->fail('time_limit_per_question', 'Choose a turn limit between 1 and 300 seconds.');
        if ($game->letter_hint_max < 0 || $game->letter_hint_max > 100) $this->fail('letter_hint_max', 'Choose between 0 and 100 hint letters.');
    }

    private function isOpen(GameSession $session, SketchTurn $turn, CarbonImmutable $now): bool
    {
        return $session->status === GameSession::STATUS_IN_PROGRESS && $turn->position === $session->current_question_position && $turn->opened_at && $turn->deadline_at && ! $turn->closed_at && $now->greaterThanOrEqualTo($turn->opened_at) && $now->lessThan($turn->deadline_at);
    }

    private function openTurn(SketchTurn $turn, CarbonImmutable $at): void
    {
        $turn->fill(['opened_at' => $at, 'deadline_at' => $at->addSeconds($turn->time_limit_seconds), 'closed_at' => null])->save();
    }

    private function lockSession(GameSession $session): GameSession
    {
        $session = GameSession::query()->lockForUpdate()->findOrFail($session->id);
        if (! $session->isSketch()) $this->fail('session', 'This is not a Sketch & Guess session.');
        return $session;
    }

    private function unwrap(array $outcome): array
    {
        if (isset($outcome['error'])) throw ValidationException::withMessages($outcome['error']);
        return $outcome;
    }
    private function now(): CarbonImmutable { return CarbonImmutable::now(); }
    private function milliseconds(CarbonImmutable $time): int { return (int) $time->format('Uv'); }
    private function fail(string $field, string $message): never { throw ValidationException::withMessages([$field => $message]); }
}
