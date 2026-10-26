<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameAnswer;
use App\Models\GameSession;
use App\Models\GameSessionPlayer;
use App\Models\GameSessionQuestion;
use App\Models\Question;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuizSessionService
{
    public const ANSWER_REVEAL_SECONDS = 5;
    public const LEADERBOARD_SECONDS = 7;

    public function create(Game $game, User $host): GameSession
    {
        if ($game->isBingo()) return app(BingoSessionService::class)->create($game, $host);
        if ($game->isSketch()) return app(SketchSessionService::class)->create($game, $host);
        return DB::transaction(function () use ($game, $host): GameSession {
            $game = Game::query()->whereKey($game->getKey())->lockForUpdate()->first();

            if (! $game || $game->status !== Game::STATUS_ACTIVE) {
                $this->fail('game', 'Only an active game can host a new session.');
            }

            $this->validateSettings($game);

            // Question content is copied once; only a waiting session's timer can change.
            $questions = $game->questions()->active()->limit(501)->lockForUpdate()->get();

            if ($questions->isEmpty() || $questions->count() > 500) {
                $this->fail('game', 'A game must have between 1 and 500 active questions.');
            }

            foreach ($questions as $question) {
                $this->validateQuestion($question);
            }

            $session = $game->sessions()->create([
                'hosted_by' => $host->getKey(),
                'game_title' => $game->title,
                'min_players' => $game->min_players,
                'max_players' => $game->max_players,
                'time_limit_per_question' => $game->time_limit_per_question,
                'total_questions' => $questions->count(),
            ]);

            foreach ($questions->values() as $index => $question) {
                $session->questions()->create([
                    'question_id' => $question->getKey(),
                    'position' => $index + 1,
                    'question_text' => $question->question_text,
                    'options' => $question->options,
                    'correct_option' => $question->correct_option,
                    'explanation' => $question->explanation,
                    'points' => $question->points,
                    'time_limit_seconds' => $game->time_limit_per_question,
                ]);
            }

            return $session->refresh();
        }, 3);
    }

    public function join(
        GameSession $session,
        string $name,
        ?string $email,
        string $token,
        string $gender = '',
    ): GameSessionPlayer
    {
        $name = trim($name);
        $email = $email === null ? null : trim($email);
        $email = $email === '' ? null : $email;

        if ($name === '' || Str::length($name) > 120) {
            $this->fail('name', 'Enter a player name between 1 and 120 characters.');
        }

        if ($email !== null && (Str::length($email) > 254 || ! filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $this->fail('email', 'Enter a valid email address, or leave it blank.');
        }

        if ($token === '') {
            $this->fail('token', 'A player reconnect token is required.');
        }

        return DB::transaction(function () use ($session, $name, $email, $token, $gender): GameSessionPlayer {
            $session = $this->lockSession($session);
            $tokenHash = hash('sha256', $token);
            $existing = GameSessionPlayer::query()->where('resume_token_hash', $tokenHash)->first();

            if ($existing) {
                if ((int) $existing->game_session_id !== (int) $session->getKey()) {
                    $this->fail('token', 'This player token belongs to another session.');
                }

                // An existing membership can be resumed without consuming another place.
                return $existing;
            }

            if (! array_key_exists($gender, GameSessionPlayer::GENDERS)) {
                $this->fail('gender', 'Choose Male or Female.');
            }

            if ($session->status !== GameSession::STATUS_WAITING) {
                $this->fail('session', 'This session is no longer accepting new players.');
            }

            if ($session->players()->count() >= $session->max_players) {
                $this->fail('players', 'This session has reached its maximum number of players.');
            }

            $usedProfiles = $session->players()->whereNotNull('rpg_profile_id')->pluck('rpg_profile_id')->all();
            $availableProfiles = array_values(array_diff(range(1, RpgProfileCatalog::CAPACITY), $usedProfiles));

            if ($availableProfiles === []) {
                $this->fail('players', 'This session has no remaining player profiles.');
            }

            $now = $this->now();
            $player = $session->players()->make([
                'user_id' => null,
                'display_name' => $name,
                'email' => $email,
                'gender' => $gender,
                'joined_at' => $now,
                'last_seen_at' => $now,
            ]);
            // Only the server assigns profiles; the session lock plus database
            // uniqueness prevents simultaneous joins from sharing a profile.
            $player->rpg_profile_id = $availableProfiles[random_int(0, count($availableProfiles) - 1)];
            $player->resume_token_hash = $tokenHash;
            $player->save();

            return $player->refresh();
        }, 3);
    }

    public function renamePlayer(GameSession $session, GameSessionPlayer $player, string $name): GameSessionPlayer
    {
        $name = trim($name);

        if ($name === '' || Str::length($name) > 120) {
            $this->fail('display_name', 'Enter a player name between 1 and 120 characters.');
        }

        return DB::transaction(function () use ($session, $player, $name): GameSessionPlayer {
            $session = $this->lockSession($session);
            $player = $this->lockWaitingPlayer($session, $player);
            $player->display_name = $name;
            $player->last_seen_at = $this->now();
            $player->save();

            return $player->refresh();
        }, 3);
    }

    public function setReady(GameSession $session, GameSessionPlayer $player, bool $ready): GameSessionPlayer
    {
        return DB::transaction(function () use ($session, $player, $ready): GameSessionPlayer {
            $session = $this->lockSession($session);
            $player = $this->lockWaitingPlayer($session, $player);
            // Assign the requested value instead of toggling: retries stay safe.
            $player->is_ready = $ready;
            $player->last_seen_at = $this->now();
            $player->save();

            return $player->refresh();
        }, 3);
    }

    public function updateWaitingTimeLimits(Game $game): void
    {
        DB::transaction(function () use ($game): void {
            // Match create/start lock order so an edit cannot race with starting a quiz.
            $game = Game::query()->whereKey($game->getKey())->lockForUpdate()->firstOrFail();
            $sessions = $game->sessions()->where('status', GameSession::STATUS_WAITING)
                ->orderBy('id')->lockForUpdate()->get();

            foreach ($sessions as $session) {
                $this->setWaitingTimeLimit($session, $game->time_limit_per_question);
            }
        }, 3);
    }

    public function start(GameSession $session): GameSession
    {
        if ($session->isBingo()) return app(BingoSessionService::class)->start($session);
        if ($session->isSketch()) return app(SketchSessionService::class)->start($session);
        return DB::transaction(function () use ($session): GameSession {
            $gameId = GameSession::query()->whereKey($session->getKey())->value('game_id');
            $game = $gameId === null ? null
                : Game::withTrashed()->whereKey($gameId)->lockForUpdate()->first();
            $session = $this->lockSession($session);

            if ($session->status !== GameSession::STATUS_WAITING) {
                $this->fail('session', 'Only a waiting session can be started.');
            }

            if ($game && (int) $session->game_id === (int) $game->getKey()) {
                // Also repair waiting sessions created before timer edits were synced.
                $this->setWaitingTimeLimit($session, $game->time_limit_per_question);
            }

            $this->validateSettings($session);
            $players = $session->players()->where('status', GameSessionPlayer::STATUS_JOINED)->count();

            if ($players < $session->min_players || $players > $session->max_players) {
                $this->fail('players', "This session needs between {$session->min_players} and {$session->max_players} joined players.");
            }

            $questions = $session->questions()->get();

            if ($questions->isEmpty() || $questions->count() > 500) {
                $this->fail('session', 'This session must contain between 1 and 500 questions.');
            }

            $now = $this->now();
            $first = $questions->first();
            $this->openQuestion($first, $now);

            $session->status = GameSession::STATUS_IN_PROGRESS;
            $session->started_at = $now;
            $session->current_question_position = $first->position;
            $session->total_questions = $questions->count();
            $session->save();

            $session->players()->where('status', GameSessionPlayer::STATUS_JOINED)->update([
                'status' => GameSessionPlayer::STATUS_PLAYING,
                'last_seen_at' => $now->format('Y-m-d H:i:s.v'),
            ]);

            return $session->refresh();
        }, 3);
    }

    public function advance(GameSession $session): GameSession
    {
        // Bingo has no clock: reads and scheduler ticks must never reveal a number.
        if ($session->isBingo()) return $session->refresh();
        if ($session->isSketch()) return app(SketchSessionService::class)->advance($session);
        return DB::transaction(function () use ($session): GameSession {
            $session = $this->lockSession($session);
            $this->advanceLocked($session, $this->now());

            return $session->refresh();
        }, 3);
    }

    public function answer(GameSession $session, GameSessionPlayer $player, int $questionId, string $option): GameAnswer
    {
        if ($session->isBingo()) $this->fail('session', 'Reverse Bingo cards are closed only by the host revealing a number.');
        if ($session->isSketch()) $this->fail('session', 'Use the guessing controls for this Sketch & Guess game.');
        $outcome = DB::transaction(function () use ($session, $player, $questionId, $option): array {
            $session = $this->lockSession($session);
            $now = $this->now();
            $this->advanceLocked($session, $now);

            $player = $session->players()->whereKey($player->getKey())->first();
            $question = $session->questions()->whereKey($questionId)->first();

            if (! $player) {
                return ['error' => ['player' => 'This player does not belong to the session.']];
            }

            if (! $question) {
                return ['error' => ['question_id' => 'This question does not belong to the session.']];
            }

            $existing = $player->answers()->where('game_session_question_id', $questionId)->first();

            if ($existing) {
                if ($existing->selected_option === $option) {
                    return ['answer' => $existing];
                }

                return ['error' => ['option' => 'Your answer has already been recorded and cannot be changed.']];
            }

            if ($session->status !== GameSession::STATUS_IN_PROGRESS || $player->status !== GameSessionPlayer::STATUS_PLAYING) {
                return ['error' => ['session' => 'This session is not accepting answers from this player.']];
            }

            if ($question->position !== $session->current_question_position
                || ! $question->opened_at || ! $question->deadline_at || $question->closed_at
                || $now->lessThan($question->opened_at) || $now->greaterThanOrEqualTo($question->deadline_at)) {
                return ['error' => ['question_id' => 'The time to answer this question has ended, or the question is not open.']];
            }

            if (! array_key_exists($option, $question->options ?? [])) {
                return ['error' => ['option' => 'Choose one of the options for this question.']];
            }

            $correct = $option === $question->correct_option;
            $answer = $player->answers()->create([
                'game_session_id' => $session->getKey(),
                'game_session_question_id' => $question->getKey(),
                'selected_option' => $option,
                'is_correct' => $correct,
                'points_awarded' => $correct ? $question->points : 0,
                'response_time_ms' => max(0, $this->milliseconds($now) - $this->milliseconds($question->opened_at)),
                'answered_at' => $now,
                'recorded_at' => $now,
            ]);

            $player->last_seen_at = $now;
            $player->save();

            // The session lock makes the final accepted submission close this
            // question atomically, including when that submission is incorrect.
            $this->closeQuestionWhenAnswered($session, $question, $now);

            return ['answer' => $answer];
        }, 3);

        // Throw after commit: rejecting a late answer must not undo deadline catch-up.
        if (isset($outcome['error'])) {
            throw ValidationException::withMessages($outcome['error']);
        }

        return $outcome['answer'];
    }

    public function cancel(GameSession $session): GameSession
    {
        if ($session->isBingo()) return app(BingoSessionService::class)->cancel($session);
        if ($session->isSketch()) return app(SketchSessionService::class)->cancel($session);
        $outcome = DB::transaction(function () use ($session): array {
            $session = $this->lockSession($session);
            $now = $this->now();
            $this->advanceLocked($session, $now);

            if ($session->status === GameSession::STATUS_COMPLETED) {
                return ['error' => ['session' => 'A completed session cannot be cancelled.']];
            }

            if ($session->status === GameSession::STATUS_CANCELLED) {
                return ['session' => $session];
            }

            $current = $session->questions()->where('position', $session->current_question_position)->first();

            if ($current && $current->opened_at && ! $current->closed_at) {
                $current->closed_at = $now;
                $current->save();
            }

            $session->status = GameSession::STATUS_CANCELLED;
            $session->cancelled_at = $now;
            $session->save();
            $session->players()->whereIn('status', [GameSessionPlayer::STATUS_JOINED, GameSessionPlayer::STATUS_PLAYING])->update([
                'status' => GameSessionPlayer::STATUS_LEFT,
                'left_at' => $now->format('Y-m-d H:i:s.v'),
            ]);

            return ['session' => $session->refresh()];
        }, 3);

        if (isset($outcome['error'])) {
            throw ValidationException::withMessages($outcome['error']);
        }

        return $outcome['session'];
    }

    private function advanceLocked(GameSession $session, CarbonImmutable $now): void
    {
        if ($session->status !== GameSession::STATUS_IN_PROGRESS) {
            return;
        }

        while (true) {
            $current = $session->questions()->where('position', $session->current_question_position)->first();

            if (! $current || ! $current->opened_at || ! $current->deadline_at) {
                $this->fail('session', 'The current question timeline is incomplete.');
            }

            if (! $current->closed_at) {
                $this->closeQuestionWhenAnswered($session, $current, $now);

                if (! $current->closed_at) {
                    if ($now->lessThan($current->deadline_at)) return;
                    $current->closed_at = $current->deadline_at;
                    $current->save();
                }
            }

            // The actual close time anchors the answer reveal and leaderboard.
            // Polling, reconnecting and scheduler delays cannot restart either interval.
            $leaderboardEndsAt = $current->closed_at->addSeconds(self::ANSWER_REVEAL_SECONDS + self::LEADERBOARD_SECONDS);

            if ($now->lessThan($leaderboardEndsAt)) {
                return;
            }

            $next = $session->questions()->where('position', '>', $current->position)->first();

            if (! $next) {
                $this->finalize($session, $leaderboardEndsAt, $now);

                return;
            }

            // Catch up to the original schedule, including both post-answer intervals.
            $this->openQuestion($next, $leaderboardEndsAt);
            $session->current_question_position = $next->position;
            $session->save();
        }
    }

    /** All players must submit; correctness does not affect early completion. */
    private function closeQuestionWhenAnswered(GameSession $session, GameSessionQuestion $question, CarbonImmutable $now): void
    {
        if ($question->closed_at) return;

        $playerIds = $session->players()->where('status', GameSessionPlayer::STATUS_PLAYING)->pluck('id');
        if ($playerIds->isEmpty()) return;

        $answers = $question->answers()->whereIn('game_session_player_id', $playerIds)
            ->whereNotNull('selected_option')->whereNotNull('answered_at')
            ->get(['game_session_player_id', 'answered_at']);
        if ($answers->pluck('game_session_player_id')->unique()->count() !== $playerIds->count()) return;

        // The saved final submission also repairs a fully answered question
        // encountered by a later poll without starting a fresh reveal window.
        $closedAt = $answers->sortByDesc('answered_at')->first()->answered_at;
        if ($closedAt->lessThan($question->opened_at) || $closedAt->greaterThanOrEqualTo($question->deadline_at)
            || $closedAt->greaterThan($now)) return;
        $question->closed_at = $closedAt;
        $question->save();
    }

    private function finalize(GameSession $session, CarbonImmutable $completedAt, CarbonImmutable $now): void
    {
        $questions = $session->questions()->get();
        $players = $session->players()->orderBy('id')->get();
        $standings = [];

        foreach ($players as $player) {
            // Keep at most one player's question set in memory, even for a full session.
            $answers = $player->answers()->where('game_session_id', $session->getKey())
                ->get()->keyBy('game_session_question_id');
            $score = $correct = $wrong = $unanswered = $responseTime = 0;

            foreach ($questions as $question) {
                $answer = $answers->get($question->getKey());
                $fullDuration = $question->time_limit_seconds * 1000;

                if (! $answer || $answer->selected_option === null) {
                    $unanswered++;
                    $responseTime += $fullDuration;

                    continue;
                }

                $answer->is_correct ? $correct++ : $wrong++;
                $score += $answer->points_awarded;
                $responseTime += min($fullDuration, max(0, $answer->response_time_ms));
            }

            $standings[] = [
                'player' => $player,
                'total_score' => $score,
                'correct_answers' => $correct,
                'wrong_answers' => $wrong,
                'unanswered_questions' => $unanswered,
                'total_response_time_ms' => $responseTime,
            ];
        }

        usort($standings, static function (array $left, array $right): int {
            return ($right['total_score'] <=> $left['total_score'])
                ?: ($left['total_response_time_ms'] <=> $right['total_response_time_ms'])
                ?: ($left['player']->getKey() <=> $right['player']->getKey());
        });

        $rank = 0;
        $previous = null;

        foreach ($standings as $index => $standing) {
            if ($previous === null || $standing['total_score'] !== $previous['total_score']
                || $standing['total_response_time_ms'] !== $previous['total_response_time_ms']) {
                $rank = $index + 1;
            }

            $player = $standing['player'];
            $player->result()->updateOrCreate([], [
                'game_session_id' => $session->getKey(),
                'player_name' => $player->display_name,
                'player_email' => $player->email,
                'total_score' => $standing['total_score'],
                'correct_answers' => $standing['correct_answers'],
                'wrong_answers' => $standing['wrong_answers'],
                'unanswered_questions' => $standing['unanswered_questions'],
                'total_questions' => $questions->count(),
                'total_response_time_ms' => $standing['total_response_time_ms'],
                'rank' => $rank,
                'finalized_at' => $now,
            ]);

            $player->status = GameSessionPlayer::STATUS_FINISHED;
            $player->finished_at = $completedAt;
            $player->save();
            $previous = $standing;
        }

        $session->status = GameSession::STATUS_COMPLETED;
        $session->completed_at = $completedAt;
        $session->save();
    }

    private function setWaitingTimeLimit(GameSession $session, int $seconds): void
    {
        if ($seconds < 1 || $seconds > 300) {
            $this->fail('time_limit_per_question', 'The question time limit must be between 1 and 300 seconds.');
        }

        $session->time_limit_per_question = $seconds;
        if ($session->isDirty('time_limit_per_question')) {
            $session->save();
        }
        $session->questions()->whereNull('opened_at')->whereNull('closed_at')
            ->where('time_limit_seconds', '!=', $seconds)->update(['time_limit_seconds' => $seconds]);
    }

    private function openQuestion(GameSessionQuestion $question, CarbonImmutable $openedAt): void
    {
        if ($question->time_limit_seconds < 1) {
            $this->fail('session', 'Each question must have a positive time limit.');
        }

        $question->opened_at = $openedAt;
        $question->deadline_at = $openedAt->addSeconds($question->time_limit_seconds);
        $question->closed_at = null;
        $question->save();
    }

    private function lockSession(GameSession $session): GameSession
    {
        $locked = GameSession::query()->whereKey($session->getKey())->lockForUpdate()->first();

        if (! $locked) {
            $this->fail('session', 'This game session is no longer available.');
        }

        return $locked;
    }

    private function lockWaitingPlayer(GameSession $session, GameSessionPlayer $player): GameSessionPlayer
    {
        if ($session->status !== GameSession::STATUS_WAITING) {
            $this->fail('session', 'Name and ready status can only be changed before the game starts.');
        }

        $member = $session->players()->whereKey($player->getKey())->lockForUpdate()->first();

        if (! $member || $member->status !== GameSessionPlayer::STATUS_JOINED) {
            $this->fail('player', 'This player is not in the waiting lobby.');
        }

        return $member;
    }

    private function validateSettings(Game|GameSession $game): void
    {
        if ($game->min_players < 1 || $game->max_players < $game->min_players || $game->max_players > 200) {
            $this->fail('players', 'Player limits must satisfy 1 ≤ minimum ≤ maximum ≤ 200.');
        }

        if ($game->time_limit_per_question < 1 || $game->time_limit_per_question > 300) {
            $this->fail('time_limit_per_question', 'The question time limit must be between 1 and 300 seconds.');
        }
    }

    private function validateQuestion(Question $question): void
    {
        $options = $question->options;

        if (trim((string) $question->question_text) === '' || ! is_array($options) || count($options) < 2
            || ! array_key_exists((string) $question->correct_option, $options)) {
            $this->fail('questions', "Question {$question->getKey()} needs text, at least two options and a matching correct option.");
        }

        foreach ($options as $key => $value) {
            if ((string) $key === '' || Str::length((string) $key) > 32 || ! is_string($value) || trim($value) === '') {
                $this->fail('questions', "Question {$question->getKey()} contains an invalid option.");
            }
        }

        if ($question->points < 0 || $question->points > 4294967295) {
            $this->fail('questions', "Question {$question->getKey()} has an invalid point value.");
        }
    }

    private function now(): CarbonImmutable
    {
        $now = CarbonImmutable::now();

        return $now->setTime($now->hour, $now->minute, $now->second, intdiv($now->microsecond, 1000) * 1000);
    }

    private function milliseconds(CarbonImmutable $date): int
    {
        return $date->getTimestamp() * 1000 + (int) $date->format('v');
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
