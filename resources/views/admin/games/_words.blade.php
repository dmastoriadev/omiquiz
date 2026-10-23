<section class="rounded-2xl border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-semibold">Upload drawing words</h2>
    <p class="mt-2 text-sm leading-6 text-slate-500">Import a UTF-8 CSV with a <strong class="font-semibold">word</strong> column. The optional <strong class="font-semibold">aliases</strong> column accepts alternative answers separated by |, such as Plane|Aeroplane. Keep up to 2,000 words in this game.</p>
    <a href="{{ route('admin.games.words.template', $game) }}" class="mt-3 inline-block text-sm font-semibold text-indigo-600 hover:text-indigo-800">Download sample CSV ↓</a>
    <form method="POST" action="{{ route('admin.games.words.import', $game) }}" enctype="multipart/form-data" class="mt-5 space-y-4">@csrf
        <div><label for="words_csv" class="oq-label">Words CSV <span class="font-normal text-slate-400">(up to 2 MB)</span></label><input id="words_csv" type="file" name="csv" accept=".csv,text/csv" required class="block w-full rounded-xl border border-slate-300 p-2 text-sm text-slate-500 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:font-semibold file:text-indigo-700"></div>
        <button type="submit" class="oq-button-primary">Import words</button>
    </form>
    <p class="mt-4 text-xs leading-5 text-slate-500">Duplicate words are skipped. Invalid rows reject the entire upload. Words and aliases can contain up to 100 characters, with up to 10 different aliases per word. Existing sessions keep their copied word bank.</p>
</section>
<section class="rounded-2xl border border-amber-100 bg-amber-50 p-6">
    <h2 class="font-semibold text-amber-950">How Sketch & Guess works</h2>
    <p class="mt-2 text-sm leading-6 text-amber-900">At least two players are needed. Every uploaded word gets one drawing turn. Players rotate from first joined to last, repeating that order until all words are played. Only the current drawer sees the prompt. Everyone else can keep guessing until correct or time runs out.</p>
    <p class="mt-2 text-sm leading-6 text-amber-900">A correct guess earns 100–1,000 points based on the time remaining. The drawer also receives 25% of each correct guess's points. A seven-second top-five leaderboard follows each turn. The game ends and saves final totals after the last word.</p>
</section>
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
    <div class="border-b border-slate-100 p-6"><h2 class="text-lg font-semibold">Word bank <span class="ml-2 text-sm font-normal text-slate-400">{{ $words->total() }} words</span></h2></div>
    @forelse ($words as $word)
        <article class="flex items-start justify-between gap-4 border-b border-slate-100 p-6 last:border-0">
            <div class="min-w-0"><h3 class="break-words font-semibold">{{ $word->word }}</h3>@if ($word->aliases)<p class="mt-2 break-words text-sm leading-6 text-slate-500">Accepted alternatives: {{ implode(', ', $word->aliases) }}</p>@endif</div>
            <form method="POST" action="{{ route('admin.games.words.destroy', [$game, $word]) }}" x-data @submit="if (!confirm('Remove this word from future sessions?')) $event.preventDefault()">@csrf @method('DELETE')<button class="shrink-0 text-xs font-medium text-rose-600 hover:text-rose-800">Remove word</button></form>
        </article>
    @empty
        <div class="px-6 py-12 text-center text-sm text-slate-500">Upload a CSV to add your first drawing words.</div>
    @endforelse
    @if ($words->hasPages())<div class="border-t border-slate-100 p-5">{{ $words->links() }}</div>@endif
</section>
