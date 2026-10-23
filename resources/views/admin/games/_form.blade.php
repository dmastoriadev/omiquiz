<form method="POST" action="{{ $action }}" class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 sm:p-8" x-data="{ gameType: @js($game->exists ? $game->game_type : old('game_type', 'quiz')), minPlayers: @js((int) old('min_players', $game->min_players ?? 1)) }">
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    <div class="space-y-6">
        <div><label for="game_type" class="oq-label">Game category</label>
            @if ($game->exists)
                <input type="hidden" name="game_type" value="{{ $game->game_type }}"><p class="rounded-xl bg-slate-50 px-4 py-3 font-semibold">{{ $game->game_type === 'sketch' ? 'Sketch & Guess' : 'Quiz' }}</p><p class="mt-2 text-xs text-slate-500">Create a separate game to use another category.</p>
            @else
                <select id="game_type" name="game_type" x-model="gameType" @change="if ($event.target.value === 'sketch' && minPlayers < 2) minPlayers = 2" class="oq-input"><option value="quiz">Quiz</option><option value="sketch">Sketch & Guess</option></select>
            @endif
            <p class="mt-2 text-xs leading-5 text-slate-500" x-text="gameType === 'sketch' ? 'Play every uploaded word. Players take drawing turns in join order, repeating the order as needed.' : 'Players answer multiple-choice questions together.'"></p>
        </div>
        <div><label for="title" class="oq-label">Game title</label><input id="title" name="title" value="{{ old('title', $game->title) }}" maxlength="180" required autofocus class="oq-input" placeholder="Friday trivia night"></div>
        <div><label for="description" class="oq-label">Description <span class="font-normal text-slate-400">(optional)</span></label><textarea id="description" name="description" rows="3" maxlength="10000" class="oq-input">{{ old('description', $game->description) }}</textarea></div>
        <div class="grid gap-5 sm:grid-cols-3">
            <div><label for="min_players" class="oq-label">Minimum players</label><input id="min_players" type="number" name="min_players" value="{{ old('min_players', $game->min_players ?? 1) }}" x-model.number="minPlayers" :min="gameType === 'sketch' ? 2 : 1" max="200" required class="oq-input"></div>
            <div><label for="max_players" class="oq-label">Maximum players</label><input id="max_players" type="number" name="max_players" value="{{ old('max_players', $game->max_players ?? 50) }}" :min="gameType === 'sketch' ? 2 : 1" max="200" required class="oq-input"></div>
            <div><label for="time_limit_per_question" class="oq-label" x-text="gameType === 'sketch' ? 'Seconds per drawing turn' : 'Seconds per question'">Seconds per question</label><input id="time_limit_per_question" type="number" name="time_limit_per_question" value="{{ old('time_limit_per_question', $game->time_limit_per_question ?? 30) }}" min="1" max="300" required class="oq-input"><p class="mt-2 text-xs leading-5 text-slate-500">Applies to new and waiting sessions. Timing stays fixed once a session starts.</p></div>
        </div>
        <p class="text-xs leading-5 text-slate-500">The host can start when the minimum number of players has joined. Each question or drawing turn runs for the full time limit.</p>
        <div><label for="status" class="oq-label">Status</label><select id="status" name="status" class="oq-input">@foreach (['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $game->status ?? 'draft') === $value)>{{ $label }}</option>@endforeach</select><p class="mt-2 text-xs text-slate-500">Set the game to Active when you are ready to create sessions.</p></div>
        @if ($method !== 'POST')<p class="rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-500">Time-limit edits also apply to waiting sessions. Other changes apply to new sessions. Existing sessions retain their copied questions or words and player limits.</p>@endif
    </div>
    <div class="mt-8 flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-6"><a href="{{ $game->exists ? route('admin.games.show', $game) : route('admin.games.index') }}" class="oq-button-secondary">Cancel</a><button type="submit" class="oq-button-primary">{{ $method === 'POST' ? 'Create game' : 'Save changes' }}</button></div>
</form>
