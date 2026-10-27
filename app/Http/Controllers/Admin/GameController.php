<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\QuizSessionService;
use App\Services\SlotsPlanGenerator;
use App\Services\SlotsSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GameController extends Controller
{
    public function index(): View
    {
        return view('admin.games.index', [
            'games' => Game::query()
                ->with('creator')
                ->withCount(['questions', 'words', 'sessions'])
                ->latest()
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.games.create', ['game' => new Game]);
    }

    public function store(Request $request): RedirectResponse
    {
        $game = $request->user()->quizGames()->create($this->validated($request));

        return redirect()->route('admin.games.show', $game)
            ->with('success', $game->isSlots()
                ? 'Slot Machine created. Set the game to Active, then create a session and share the join and public display links.'
                : ($game->isBingo()
                    ? 'Reverse Bingo created. Set the game to Active, then create a session. Cards are assigned when you start.'
                    : 'Game created. Upload '.($game->game_type === 'sketch' ? 'drawing words' : 'questions').' to prepare a session.'));
    }

    public function show(Game $game): View
    {
        return view('admin.games.show', [
            'game' => $game->load('creator')->loadCount(['questions', 'words', 'sessions']),
            'questions' => $game->questions()->paginate(20)->withQueryString(),
            'words' => $game->words()->orderBy('id')->paginate(20, ['*'], 'words_page')->withQueryString(),
            'sessions' => $game->sessions()
                ->withCount(['players', 'results'])
                ->latest()
                ->limit(15)
                ->get(),
        ]);
    }

    public function edit(Game $game): View
    {
        return view('admin.games.edit', compact('game'));
    }

    public function update(Request $request, Game $game, QuizSessionService $quiz): RedirectResponse
    {
        $data = $this->validated($request, $game);

        DB::transaction(function () use ($game, $data, $quiz): void {
            $lockedGame = Game::query()->whereKey($game->getKey())->lockForUpdate()->firstOrFail();
            $lockedGame->update($data);
            $quiz->updateWaitingTimeLimits($lockedGame);
            if ($lockedGame->isSketch()) {
                // The game lock also serializes start/create against this edit.
                $lockedGame->sessions()->where('status', 'waiting')->where('game_type', Game::TYPE_SKETCH)
                    ->update(['letter_hint_max' => $lockedGame->letter_hint_max]);
            }
            if ($lockedGame->isBingo()) {
                $lockedGame->sessions()->where('status', 'waiting')->where('game_type', Game::TYPE_BINGO)
                    ->update([
                        'bingo_min_plays' => $lockedGame->bingo_min_plays,
                        'bingo_max_plays' => $lockedGame->bingo_max_plays,
                        'total_questions' => $lockedGame->bingo_max_plays,
                    ]);
            }
            if ($lockedGame->isSlots()) {
                $waiting = $lockedGame->sessions()->where('status', 'waiting')->where('game_type', Game::TYPE_SLOTS)
                    ->orderBy('id')->lockForUpdate()->get();
                foreach ($waiting as $session) {
                    app(SlotsSessionService::class)->normalizeWaiting($session, $lockedGame);
                }
            }
        }, 3);

        return redirect()->route('admin.games.show', $game)
            ->with('success', 'Game updated. Timer, hint and Reverse Bingo draw settings also apply to waiting sessions. Waiting Slot Machine sessions use updated player limits and the fixed five-roll schedule. Games already in progress keep their original settings.');
    }

    public function destroy(Game $game): RedirectResponse
    {
        DB::transaction(function () use ($game): void {
            Game::query()->whereKey($game->getKey())->lockForUpdate()->firstOrFail()->delete();
        });

        return redirect()->route('admin.games.index')
            ->with('success', 'Game removed. Its session history and results have been retained.');
    }

    private function validated(Request $request, ?Game $game = null): array
    {
        // Older quiz forms can omit the category; an update must never reset it.
        if (! $request->exists('game_type')) {
            $request->merge(['game_type' => $game?->game_type ?? 'quiz']);
        }
        $type = $request->input('game_type');
        if ($type !== Game::TYPE_SKETCH) {
            $request->merge(['letter_hint_max' => 0]);
        } elseif (! $request->exists('letter_hint_max')) {
            $request->merge(['letter_hint_max' => $game?->letter_hint_max ?? 0]);
        }
        if ($type === Game::TYPE_BINGO) {
            // Reverse Bingo advances only when the host reveals another number.
            $request->merge(['time_limit_per_question' => $game?->time_limit_per_question ?? 30]);
        }
        if ($type === Game::TYPE_SLOTS) {
            $request->merge(['time_limit_per_question' => 5, 'min_players' => 6]);
        }
        foreach (['bingo_min_plays' => 3, 'bingo_max_plays' => 20] as $field => $default) {
            if ($type !== Game::TYPE_BINGO || ! $request->exists($field)) {
                $request->merge([$field => $game?->{$field} ?? $default]);
            }
        }

        $rules = [
            'game_type' => ['required', 'string', Rule::in($game ? [$game->game_type] : array_keys(Game::TYPES))],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'min_players' => ['required', 'integer', $type === Game::TYPE_SLOTS ? 'min:6' : ($type === 'quiz' ? 'min:1' : 'min:2'), $type === Game::TYPE_BINGO ? 'max:32' : 'max:200'],
            'max_players' => ['required', 'integer', $type === Game::TYPE_SLOTS ? 'min:6' : ($type === 'quiz' ? 'min:1' : 'min:2'), $type === Game::TYPE_BINGO ? 'max:32' : 'max:200', 'gte:min_players'],
            'time_limit_per_question' => ['required', 'integer', 'min:1', 'max:300'],
            'letter_hint_max' => ['required', 'integer', 'min:0', 'max:100'],
            'bingo_min_plays' => ['required', 'integer', 'min:3', 'max:47'],
            'bingo_max_plays' => ['required', 'integer', 'min:6', 'max:50'],
            'status' => ['required', Rule::in([
                Game::STATUS_DRAFT,
                Game::STATUS_ACTIVE,
                Game::STATUS_ARCHIVED,
            ])],
        ];
        $data = $request->validate($rules);
        if ($type === Game::TYPE_SLOTS) {
            // The five-roll sequence is fixed. Old forms cannot restore the
            // previous fifteen-roll schedule or change a prize position.
            $data['slot_prize_positions'] = SlotsPlanGenerator::DEFAULT_PRIZE_POSITIONS;
        }

        if ($type === Game::TYPE_BINGO && $data['bingo_min_plays'] > $data['bingo_max_plays'] - 3) {
            throw ValidationException::withMessages([
                'bingo_min_plays' => 'The first possible elimination must be at least three draws before the final draw.',
            ]);
        }

        return $data;
    }
}
