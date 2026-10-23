<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use App\Models\GameSessionPlayer;
use App\Services\QuizSessionService;
use App\Services\QuizStatePresenter;
use App\Services\SketchSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class PlayController extends Controller
{
    public function __construct(
        private readonly QuizSessionService $sessions,
        private readonly QuizStatePresenter $presenter,
    ) {}

    public function show(Request $request, GameSession $session): View
    {
        $player = $this->participant($request, $session);
        if (! $player) {
            return view('play.join', compact('session'));
        }

        $session = $this->sessions->advance($session);
        return view('play.game', [
            'session' => $session,
            'player' => $player,
            'state' => $this->presenter->present($session, $player),
        ]);
    }

    public function join(Request $request, GameSession $session): RedirectResponse
    {
        if ($this->participant($request, $session)) {
            return redirect()->route('play.show', $session);
        }

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'gender' => ['required', Rule::in(array_keys(GameSessionPlayer::GENDERS))],
        ]);

        $token = bin2hex(random_bytes(32));
        $player = $this->sessions->join(
            $session, $validated['display_name'], null, $token, $validated['gender'],
        );
        $request->session()->put('quiz_players.'.$session->id, [
            'id' => $player->id, 'token' => $token,
        ]);

        return redirect()->route('play.show', $session);
    }

    public function state(Request $request, GameSession $session): JsonResponse
    {
        $player = $this->participant($request, $session);
        abort_unless($player, 403, 'Reopen your invitation in the browser you used to join.');
        $session = $this->sessions->advance($session);

        return response()->json($this->presenter->present($session, $player));
    }

    public function profile(Request $request, GameSession $session): JsonResponse
    {
        $player = $this->participant($request, $session);
        abort_unless($player, 403, 'Reopen your invitation in the browser you used to join.');
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
        ]);

        $player = $this->sessions->renamePlayer($session, $player, $validated['display_name']);

        return response()->json($this->presenter->present($session, $player));
    }

    public function ready(Request $request, GameSession $session): JsonResponse
    {
        $player = $this->participant($request, $session);
        abort_unless($player, 403, 'Reopen your invitation in the browser you used to join.');
        $request->validate([
            'is_ready' => ['required', 'boolean'],
        ]);

        $player = $this->sessions->setReady($session, $player, $request->boolean('is_ready'));

        return response()->json($this->presenter->present($session, $player));
    }

    public function answer(Request $request, GameSession $session): JsonResponse
    {
        $player = $this->participant($request, $session);
        abort_unless($player, 403, 'Reopen your invitation in the browser you used to join.');
        $validated = $request->validate([
            'question_id' => ['required', 'integer', 'min:1'],
            'option' => ['required', 'string', 'max:32'],
        ]);

        // IDs, timestamps, correctness and points supplied by clients are never accepted.
        $this->sessions->answer($session, $player, (int) $validated['question_id'], $validated['option']);

        return response()->json($this->presenter->present($session, $player));
    }

    public function guess(Request $request, GameSession $session, SketchSessionService $sketch): JsonResponse
    {
        $player = $this->participant($request, $session);
        abort_unless($player, 403, 'Reopen your invitation in the browser you used to join.');
        $validated = $request->validate([
            'turn_id' => ['required', 'integer', 'min:1'],
            'guess' => ['required', 'string', 'max:100'],
        ]);
        $outcome = $sketch->guess($session, $player, (int) $validated['turn_id'], $validated['guess']);

        return response()->json(['state' => $this->presenter->present($session, $player)] + $outcome);
    }

    public function draw(Request $request, GameSession $session, SketchSessionService $sketch): JsonResponse
    {
        $player = $this->participant($request, $session);
        abort_unless($player, 403, 'Reopen your invitation in the browser you used to join.');
        abort_if(strlen($request->getContent()) > 65536, 413, 'Send a smaller drawing batch.');
        $validated = $request->validate([
            'turn_id' => ['required', 'integer', 'min:1'],
            'request_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{8,64}$/'],
            'events' => ['required', 'array', 'min:1', 'max:8'],
            'events.*.type' => ['required', Rule::in(['stroke', 'clear'])],
            'events.*.points' => ['sometimes', 'array', 'min:1', 'max:100'],
            'events.*.points.*.x' => ['required', 'numeric', 'between:0,1'],
            'events.*.points.*.y' => ['required', 'numeric', 'between:0,1'],
            'events.*.color' => ['sometimes', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'events.*.width' => ['sometimes', 'numeric', 'between:1,20'],
        ]);

        return response()->json($sketch->appendDrawing(
            $session, $player, (int) $validated['turn_id'], $validated['request_id'], $validated['events'],
        ));
    }

    private function participant(Request $request, GameSession $session): ?GameSessionPlayer
    {
        $identity = $request->session()->get('quiz_players.'.$session->id);
        if (! is_array($identity) || ! isset($identity['id'], $identity['token']) || ! is_string($identity['token'])) {
            return null;
        }

        $player = $session->players()->find($identity['id']);
        if (! $player || ! $player->resume_token_hash || ! hash_equals($player->resume_token_hash, hash('sha256', $identity['token']))) {
            return null;
        }

        return $player;
    }
}
