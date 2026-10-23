<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\QuizSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GameController extends Controller
{
    public function index(): View
    {
        return view('admin.games.index', [
            'games' => Game::query()
                ->with('creator')
                ->withCount(['questions', 'sessions'])
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
            ->with('success', 'Game created. Add its questions to prepare a session.');
    }

    public function show(Game $game): View
    {
        return view('admin.games.show', [
            'game' => $game->load('creator')->loadCount(['questions', 'sessions']),
            'questions' => $game->questions()->paginate(20)->withQueryString(),
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
        $data = $this->validated($request);

        DB::transaction(function () use ($game, $data, $quiz): void {
            $lockedGame = Game::query()->whereKey($game->getKey())->lockForUpdate()->firstOrFail();
            $lockedGame->update($data);
            $quiz->updateWaitingTimeLimits($lockedGame);
        }, 3);

        return redirect()->route('admin.games.show', $game)
            ->with('success', 'Game updated. Waiting sessions now use the saved question time limit. Games already in progress keep their original timing.');
    }

    public function destroy(Game $game): RedirectResponse
    {
        DB::transaction(function () use ($game): void {
            Game::query()->whereKey($game->getKey())->lockForUpdate()->firstOrFail()->delete();
        });

        return redirect()->route('admin.games.index')
            ->with('success', 'Game removed. Its session history and results have been retained.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'min_players' => ['required', 'integer', 'min:1', 'max:200'],
            'max_players' => ['required', 'integer', 'min:1', 'max:200', 'gte:min_players'],
            'time_limit_per_question' => ['required', 'integer', 'min:1', 'max:300'],
            'status' => ['required', Rule::in([
                Game::STATUS_DRAFT,
                Game::STATUS_ACTIVE,
                Game::STATUS_ARCHIVED,
            ])],
        ]);
    }
}
