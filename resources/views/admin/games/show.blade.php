@extends('admin.layout')
@section('title', $game->title)
@section('content')
    <a href="{{ route('admin.games.index') }}" class="text-sm font-medium text-indigo-600">← Games</a>
    <div class="mb-7 mt-4 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0"><span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700">{{ ucfirst($game->status) }}</span><h1 class="mt-3 break-words text-3xl font-semibold tracking-tight">{{ $game->title }}</h1><p class="mt-2 max-w-2xl whitespace-pre-line text-sm leading-6 text-slate-500">{{ $game->description }}</p></div>
        <a href="{{ route('admin.games.edit', $game) }}" class="oq-button-secondary">Edit settings</a>
    </div>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
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
        </div>
        <div class="space-y-6">
            <section class="rounded-2xl bg-slate-900 p-6 text-white"><p class="text-xs font-semibold uppercase tracking-widest text-indigo-300">Ready to play?</p><h2 class="mt-3 text-xl font-semibold">Create a session</h2><p class="mt-2 text-sm leading-6 text-slate-300">Get a unique join link, gather your players, and start the quiz.</p><dl class="my-6 space-y-3 text-sm"><div class="flex justify-between"><dt class="text-slate-400">Players</dt><dd>{{ $game->min_players }}–{{ $game->max_players }}</dd></div><div class="flex justify-between"><dt class="text-slate-400">Per question</dt><dd>{{ $game->time_limit_per_question }} seconds</dd></div></dl><form method="POST" action="{{ route('admin.games.sessions.store', $game) }}">@csrf<button type="submit" class="oq-button-primary w-full" @disabled($game->status !== 'active' || $questions->total() === 0)>Create session</button></form>@if($game->status !== 'active')<p class="mt-3 text-xs leading-5 text-slate-400">Set this game to Active in settings first.</p>@elseif($questions->total() === 0)<p class="mt-3 text-xs leading-5 text-slate-400">Add at least one active question first.</p>@endif</section>
            <section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="font-semibold">Recent sessions</h2><div class="mt-3 divide-y divide-slate-100">@forelse($sessions as $session)<a href="{{ route('admin.sessions.show', $session) }}" class="block py-3 text-sm hover:text-indigo-600"><span class="font-medium">{{ str($session->status)->replace('_', ' ')->title() }}</span><span class="mt-1 block text-xs text-slate-400">{{ $session->created_at?->utc()->format('M j, Y · H:i') }} UTC</span></a>@empty<p class="py-4 text-sm text-slate-400">No sessions yet.</p>@endforelse</div><a href="{{ route('admin.sessions.index', ['status' => 'all']) }}" class="mt-3 inline-block text-sm font-semibold text-indigo-600">View all sessions →</a></section>
            <form method="POST" action="{{ route('admin.games.destroy', $game) }}" x-data @submit="if (!confirm('Delete this game? Existing sessions and results will be kept.')) $event.preventDefault()">@csrf @method('DELETE')<button class="px-1 text-sm font-medium text-rose-600 hover:text-rose-800">Delete game</button></form>
        </div>
    </div>
@endsection
