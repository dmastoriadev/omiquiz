@extends('admin.layout')
@section('title', $game->title)
@section('content')
    @php($isSketch = $game->game_type === 'sketch')
    @php($isBingo = $game->game_type === 'bingo')
    <a href="{{ route('admin.games.index') }}" class="text-sm font-medium text-indigo-600">← Games</a>
    <div class="mb-7 mt-4 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0"><span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700">{{ ucfirst($game->status) }}</span><span class="ml-2 text-xs font-semibold text-slate-500">{{ \App\Models\Game::TYPES[$game->game_type] ?? 'Quiz' }}</span><h1 class="mt-3 break-words text-3xl font-semibold tracking-tight">{{ $game->title }}</h1><p class="mt-2 max-w-2xl whitespace-pre-line text-sm leading-6 text-slate-500">{{ $game->description }}</p></div>
        <a href="{{ route('admin.games.edit', $game) }}" class="oq-button-secondary">Edit settings</a>
    </div>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($isBingo)
                <section class="rounded-2xl border border-slate-200 bg-white p-6">
                    <h2 class="text-lg font-semibold">Reverse Bingo rules</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">Every player receives three numbered cards from 1–75 when you start the session. When you reveal a number, every matching card closes. A player stays in the game while they have at least one active card.</p>
                    <dl class="mt-6 grid gap-4 sm:grid-cols-3"><div class="rounded-xl bg-slate-50 p-4"><dt class="text-xs text-slate-500">Cards per player</dt><dd class="mt-2 text-2xl font-semibold">3</dd></div><div class="rounded-xl bg-slate-50 p-4"><dt class="text-xs text-slate-500">First possible elimination</dt><dd class="mt-2 text-2xl font-semibold">Play {{ $game->bingo_min_plays }}</dd></div><div class="rounded-xl bg-slate-50 p-4"><dt class="text-xs text-slate-500">Winning draw</dt><dd class="mt-2 text-2xl font-semibold">Play {{ $game->bingo_max_plays }}</dd></div></dl>
                    <p class="mt-5 text-sm leading-6 text-slate-600">The cards, numbers and elimination order are randomized together at the start. The final draw leaves one lucky player holding one unique uncalled number. You control when each number is revealed; there is no automatic timer.</p>
                    <p class="mt-3 text-sm leading-6 text-slate-500">The session page shows the planned number of survivors for every play. With at least four players, the last three draws start with four, three and two players respectively. Multiple players can be eliminated together earlier; shorter games can require more than two eliminations in one draw.</p>
                    <p class="mt-3 text-xs leading-5 text-slate-500">No CSV upload is needed. Final standings include every player; players eliminated on the same draw use the random tie order fixed when the session started.</p>
                </section>
            @elseif ($isSketch)
                @include('admin.games._words')
            @else
            <section class="rounded-2xl border border-slate-200 bg-white p-6">
                <h2 class="text-lg font-semibold">Upload questions</h2><p class="mt-2 text-sm leading-6 text-slate-500">Import a UTF-8 CSV with up to 500 questions. Columns A and B are required; C and D are optional. Use A, B, C, or D for the correct option.</p>
                <a href="{{ route('admin.games.questions.template', $game) }}" class="mt-3 inline-block text-sm font-semibold text-indigo-600 hover:text-indigo-800">Download sample CSV ↓</a>
                <form method="POST" action="{{ route('admin.games.questions.import', $game) }}" enctype="multipart/form-data" class="mt-5 space-y-4">@csrf
                    <div><label for="csv" class="oq-label">Questions CSV <span class="font-normal text-slate-400">(up to 2 MB)</span></label><input id="csv" type="file" name="csv" accept=".csv,text/csv" required class="block w-full rounded-xl border border-slate-300 p-2 text-sm text-slate-500 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:font-semibold file:text-indigo-700"></div>
                    <button type="submit" class="oq-button-primary">Import questions</button>
                </form>
                <p class="mt-4 text-xs leading-5 text-slate-500">Uploads add to the question bank. An invalid row rejects the entire upload, so you can correct it before trying again.</p>
            </section>
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="border-b border-slate-100 p-6"><h2 class="text-lg font-semibold">Question bank <span class="ml-2 text-sm font-normal text-slate-400">{{ $questions->total() }} questions</span></h2></div>
                @forelse ($questions as $question)
                    <article class="border-b border-slate-100 p-6 last:border-0">
                        <div class="flex items-start justify-between gap-4"><h3 class="min-w-0 break-words whitespace-pre-line font-medium leading-6">{{ $question->question_text }}</h3><span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">{{ $question->points }} pts</span></div>
                        <ul class="mt-4 grid gap-2 sm:grid-cols-2">@foreach ($question->options as $key => $option)<li class="break-words rounded-xl px-3 py-2 text-sm {{ $key === $question->correct_option ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-50 text-slate-500' }}"><span class="mr-2 font-semibold">{{ $key }}</span>{{ $option }}@if($key === $question->correct_option)<span class="sr-only"> (correct answer)</span>@endif</li>@endforeach</ul>
                        <form method="POST" action="{{ route('admin.games.questions.destroy', [$game, $question]) }}" class="mt-4" x-data @submit="if (!confirm('Remove this question from future sessions?')) $event.preventDefault()">@csrf @method('DELETE')<button class="text-xs font-medium text-rose-600 hover:text-rose-800">Remove question</button></form>
                    </article>
                @empty
                    <div class="px-6 py-12 text-center text-sm text-slate-500">Upload a CSV to add your first questions.</div>
                @endforelse
                @if($questions->hasPages())<div class="border-t border-slate-100 p-5">{{ $questions->links() }}</div>@endif
            </section>
            @endif
        </div>
        <div class="space-y-6">
            <section class="rounded-2xl bg-slate-900 p-6 text-white"><p class="text-xs font-semibold uppercase tracking-widest text-indigo-300">Ready to play?</p><h2 class="mt-3 text-xl font-semibold">Create a session</h2><p class="mt-2 text-sm leading-6 text-slate-300">Get a unique join link, gather your players, and start the game.</p><dl class="my-6 space-y-3 text-sm"><div class="flex justify-between"><dt class="text-slate-400">Players</dt><dd>{{ $game->min_players }}–{{ $game->max_players }}</dd></div>@if($isBingo)<div class="flex justify-between"><dt class="text-slate-400">Final draw</dt><dd>Play {{ $game->bingo_max_plays }}</dd></div><div class="flex justify-between"><dt class="text-slate-400">Number reveal</dt><dd>Host controlled</dd></div>@else<div class="flex justify-between"><dt class="text-slate-400">{{ $isSketch ? 'Per drawing turn' : 'Per question' }}</dt><dd>{{ $game->time_limit_per_question }} seconds</dd></div>@endif @if($isSketch)<div class="flex justify-between"><dt class="text-slate-400">Hint letters</dt><dd>{{ $game->letter_hint_max ? 'Up to '.$game->letter_hint_max : 'Off' }}</dd></div>@endif</dl><form method="POST" action="{{ route('admin.games.sessions.store', $game) }}">@csrf<button type="submit" class="oq-button-primary w-full" @disabled($game->status !== 'active' || (! $isBingo && ($isSketch ? $words->total() : $questions->total()) === 0))>Create session</button></form>@if($game->status !== 'active')<p class="mt-3 text-xs leading-5 text-slate-400">Set this game to Active in settings first.</p>@elseif(!$isBingo && ($isSketch ? $words->total() : $questions->total()) === 0)<p class="mt-3 text-xs leading-5 text-slate-400">Add at least one {{ $isSketch ? 'drawing word' : 'active question' }} first.</p>@endif</section>
            <section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="font-semibold">Recent sessions</h2><div class="mt-3 divide-y divide-slate-100">@forelse($sessions as $session)<a href="{{ route('admin.sessions.show', $session) }}" class="block py-3 text-sm hover:text-indigo-600"><span class="font-medium">{{ str($session->status)->replace('_', ' ')->title() }}</span><span class="mt-1 block text-xs text-slate-400">{{ $session->created_at?->utc()->format('M j, Y · H:i') }} UTC</span></a>@empty<p class="py-4 text-sm text-slate-400">No sessions yet.</p>@endforelse</div><a href="{{ route('admin.sessions.index', ['status' => 'all']) }}" class="mt-3 inline-block text-sm font-semibold text-indigo-600">View all sessions →</a></section>
            <form method="POST" action="{{ route('admin.games.destroy', $game) }}" x-data @submit="if (!confirm('Delete this game? Existing sessions and results will be kept.')) $event.preventDefault()">@csrf @method('DELETE')<button class="px-1 text-sm font-medium text-rose-600 hover:text-rose-800">Delete game</button></form>
        </div>
    </div>
@endsection
