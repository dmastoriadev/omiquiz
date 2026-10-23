@extends('admin.layout')
@section('title', 'Games')
@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div><h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Games</h1><p class="mt-2 text-sm text-slate-500">Choose Quiz, Sketch & Guess or Reverse Bingo, then invite your players.</p></div>
        <a href="{{ route('admin.games.create') }}" class="oq-button-primary">Create game</a>
    </div>
    @if ($games->isEmpty())
        <section class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
            <p class="text-lg font-semibold">Your first game starts here</p><p class="mt-2 text-sm text-slate-500">Create a game, choose its settings, and share a join link.</p>
            <a href="{{ route('admin.games.create') }}" class="oq-button-primary mt-6">Create a game</a>
        </section>
    @else
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($games as $game)
                <article class="flex flex-col rounded-2xl border border-slate-200 bg-white p-6">
                    <div class="mb-5"><span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700">{{ ucfirst($game->status) }}</span><span class="ml-2 text-xs font-semibold text-slate-500">{{ \App\Models\Game::TYPES[$game->game_type] ?? 'Quiz' }}</span></div>
                    <h2 class="break-words text-lg font-semibold"><a href="{{ route('admin.games.show', $game) }}" class="hover:text-indigo-600">{{ $game->title }}</a></h2>
                    <p class="mt-2 line-clamp-2 text-sm leading-6 text-slate-500">{{ $game->description ?: 'Ready for your next game night.' }}</p>
                    <dl class="my-6 grid grid-cols-2 gap-4 text-sm">
                        <div><dt class="text-slate-400">{{ $game->game_type === 'bingo' ? 'Final draw' : ($game->game_type === 'sketch' ? 'Words' : 'Questions') }}</dt><dd class="mt-1 font-semibold">{{ $game->game_type === 'bingo' ? 'Play '.$game->bingo_max_plays : ($game->game_type === 'sketch' ? $game->words_count : $game->questions_count) }}</dd></div>
                        <div><dt class="text-slate-400">Players</dt><dd class="mt-1 font-semibold">{{ $game->min_players }}–{{ $game->max_players }}</dd></div>
                        <div><dt class="text-slate-400">{{ $game->game_type === 'bingo' ? 'Number reveal' : ($game->game_type === 'sketch' ? 'Per drawing turn' : 'Per question') }}</dt><dd class="mt-1 font-semibold">{{ $game->game_type === 'bingo' ? 'Host controlled' : $game->time_limit_per_question.' seconds' }}</dd></div>
                        <div><dt class="text-slate-400">Sessions</dt><dd class="mt-1 font-semibold">{{ $game->sessions_count }}</dd></div>
                    </dl>
                    <a href="{{ route('admin.games.show', $game) }}" class="oq-button-secondary mt-auto">Manage game</a>
                </article>
            @endforeach
        </div>
        <div class="mt-6">{{ $games->links() }}</div>
    @endif
@endsection
