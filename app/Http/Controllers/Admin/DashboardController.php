<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameSession;
use App\Models\Question;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'games' => Game::query()->count(),
                'questions' => Question::query()->count(),
                'sessions' => GameSession::query()->count(),
                'completed_sessions' => GameSession::completed()->count(),
            ],
            'recentSessions' => GameSession::query()->latest()->limit(5)->get(),
        ]);
    }
}
