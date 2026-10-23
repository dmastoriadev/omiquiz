<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use App\Services\QuizSessionService;
use App\Services\QuizStatePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PublicGameController extends Controller
{
    public function index(): View
    {
        return view('public.index', [
            'sessions' => GameSession::query()->withCount('players')
                ->whereIn('status', [GameSession::STATUS_WAITING, GameSession::STATUS_IN_PROGRESS])
                ->orderByRaw("CASE WHEN status = 'waiting' THEN 0 ELSE 1 END")
                ->orderByDesc('id')->paginate(12),
            'recentCompleted' => GameSession::completed()->withCount('players')
                ->orderByDesc('completed_at')->orderByDesc('id')->limit(6)->get(),
        ]);
    }

    public function show(GameSession $session, QuizSessionService $quiz, QuizStatePresenter $presenter): View
    {
        $session = $quiz->advance($session);
        return view('public.session', ['session' => $session, 'state' => $presenter->spectator($session)]);
    }

    public function state(GameSession $session, QuizSessionService $quiz, QuizStatePresenter $presenter): JsonResponse
    {
        return response()->json($presenter->spectator($quiz->advance($session)));
    }
}
