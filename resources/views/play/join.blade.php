@extends('play.layout')
@section('title', $session->game_title)
@section('content')
    <div class="mx-auto max-w-lg">
        <a href="{{ route('public.sessions.show', $session) }}" class="mb-6 inline-block text-sm font-semibold text-indigo-600">← View player queue</a><p class="text-xs font-semibold uppercase tracking-widest text-indigo-600">You're invited · {{ \App\Models\Game::TYPES[$session->game_type] ?? 'Quiz' }}</p><h1 class="mt-3 break-words text-3xl font-semibold leading-tight tracking-tight sm:text-4xl">{{ $session->game_title }}</h1>
        <div class="mb-7 mt-5 flex flex-wrap gap-2 text-xs text-slate-500"><span class="rounded-full border border-slate-200 bg-white px-3 py-2">{{ $session->game_type === 'bingo' ? '3 numbered cards per player' : ($session->game_type === 'sketch' ? ($session->total_questions > 0 ? $session->total_questions.' words to play' : 'All uploaded words') : $session->total_questions.' questions') }}</span><span class="rounded-full border border-slate-200 bg-white px-3 py-2">{{ $session->game_type === 'bingo' ? $session->bingo_max_plays.' host-controlled draws' : $session->time_limit_per_question.' seconds each' }}</span><span class="rounded-full border border-slate-200 bg-white px-3 py-2">Up to {{ $session->max_players }} players</span></div>
        @if ($session->game_type === 'sketch')<p class="mb-6 text-sm leading-6 text-slate-500">Players take drawing turns in join order, repeating until every word is played. Guess faster to earn more points; correct guesses also give the sketcher a bonus. Final scores total the points from all words.</p>@endif
        @if ($session->game_type === 'bingo')<p class="mb-6 text-sm leading-6 text-slate-500">Three cards, one lucky survivor. Cards and the elimination order are randomized when the host starts. Each revealed number closes matching cards; your last active card keeps you in the game. One winner remains on the final draw.</p>@endif
        @if ($session->status === 'waiting')
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"><h2 class="text-xl font-semibold">{{ $session->game_type === 'bingo' ? 'Join Reverse Bingo' : ($session->game_type === 'sketch' ? 'Join Sketch & Guess' : 'Join the quiz') }}</h2><p class="mt-2 text-sm leading-6 text-slate-500">Pick the name your fellow players will see. Your avatar will be assigned automatically when you join.</p>
                <form method="POST" action="{{ route('play.join', $session) }}" class="mt-6 space-y-5">@csrf
                    <div><label for="display_name" class="oq-label">Your name</label><input id="display_name" name="display_name" value="{{ old('display_name') }}" maxlength="120" autocomplete="nickname" required autofocus class="oq-input" placeholder="Your player name"></div>
                    <div><label for="gender" class="oq-label">Gender</label><select id="gender" name="gender" required class="oq-input"><option value="" disabled @selected(! old('gender'))>Choose your gender</option>@foreach (['male' => 'Male', 'female' => 'Female'] as $value => $label)<option value="{{ $value }}" @selected(old('gender') === $value)>{{ $label }}</option>@endforeach</select><p class="mt-2 text-xs leading-5 text-slate-400">Gender is not shown in the public player queue.</p></div>
                    <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-4"><p class="text-sm font-semibold text-indigo-700">Your place around the campfire</p><p class="mt-1 text-xs leading-5 text-indigo-600">A unique avatar will represent you in this session. Your avatar does not affect scoring.</p></div>
                    <button class="oq-button-primary w-full" type="submit">Join game →</button>
                </form><p class="mt-5 text-xs leading-5 text-slate-400">Stay in this browser to keep your player identity. Refreshing the page will reconnect you.</p>
            </section>
        @else
            <section class="rounded-2xl border border-slate-200 bg-white p-8"><h2 class="text-xl font-semibold">{{ $session->status === 'in_progress' ? 'This game has started' : 'This session is closed' }}</h2><p class="mt-3 text-sm leading-6 text-slate-500">{{ $session->status === 'in_progress' ? 'New players can no longer join. If you already joined, return using the same browser.' : 'Ask your host for a new session link to play again.' }}</p></section>
        @endif
        <a href="{{ route('public.sessions.show', $session) }}" class="mt-5 block text-center text-sm font-semibold text-indigo-600">Just watching? Open spectator view →</a>
    </div>
@endsection
