<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameSession;
use App\Services\QuizSessionService;
use App\Services\QuizStatePresenter;
use App\Services\ResultsCsvExporter;
use App\Services\BingoSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GameSessionController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'status' => ['sometimes', 'required', Rule::in([
                'all', GameSession::STATUS_WAITING, GameSession::STATUS_IN_PROGRESS,
                GameSession::STATUS_COMPLETED, GameSession::STATUS_CANCELLED,
            ])],
        ]);
        $status = $data['status'] ?? GameSession::STATUS_COMPLETED;
        $query = GameSession::query()->with(['game', 'host'])->withCount(['players', 'results']);

        if ($status === GameSession::STATUS_COMPLETED) {
            $query->completed()->orderByDesc('completed_at');
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        return view('admin.sessions.index', [
            'sessions' => $query->orderByDesc('id')->paginate(20)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function store(Request $request, Game $game, QuizSessionService $quiz): RedirectResponse
    {
        $session = $quiz->create($game, $request->user());

        return redirect()->route('admin.sessions.show', $session)
            ->with('success', 'Session created. Share its join link, then start when enough players have joined.');
    }

    public function show(GameSession $session, QuizSessionService $quiz, QuizStatePresenter $presenter): View
    {
        $session = $quiz->advance($session);
        $session->load(['game', 'host', 'players', 'results.player']);

        return view('admin.sessions.show', [
            'session' => $session,
            'state' => $presenter->present($session),
        ]);
    }

    public function start(GameSession $session, QuizSessionService $quiz): RedirectResponse
    {
        $session = $quiz->start($session);

        return redirect()->route('admin.sessions.show', $session)
            ->with('success', 'The game has started.');
    }

    public function cancel(GameSession $session, QuizSessionService $quiz): RedirectResponse
    {
        $session = $quiz->cancel($session);

        return redirect()->route('admin.sessions.show', $session)
            ->with('success', 'The session has been cancelled.');
    }

    public function state(GameSession $session, QuizSessionService $quiz, QuizStatePresenter $presenter): JsonResponse
    {
        $session = $quiz->advance($session);

        return response()->json($presenter->present($session))->header('Cache-Control', 'private, no-store');
    }

    public function export(GameSession $session, ResultsCsvExporter $exporter): StreamedResponse
    {
        return $exporter->download($session);
    }

    public function bingoDraw(Request $request, GameSession $session, BingoSessionService $bingo, QuizStatePresenter $presenter): JsonResponse
    {
        abort_unless($session->isBingo(), 404);
        $data = $request->validate([
            'expected_position' => ['required', 'integer', 'min:0', 'max:74'],
        ]);
        $session = $bingo->revealNext($session, (int) $data['expected_position']);

        return response()->json(['state' => $presenter->present($session)])
            ->header('Cache-Control', 'private, no-store');
    }
}
