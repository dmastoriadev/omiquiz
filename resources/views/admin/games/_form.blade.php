<form method="POST" action="{{ $action }}" class="max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    <div class="space-y-6">
        <div><label for="title" class="oq-label">Game title</label><input id="title" name="title" value="{{ old('title', $game->title) }}" maxlength="180" required autofocus class="oq-input" placeholder="Friday trivia night"></div>
        <div><label for="description" class="oq-label">Description <span class="font-normal text-slate-400">(optional)</span></label><textarea id="description" name="description" rows="3" maxlength="10000" class="oq-input">{{ old('description', $game->description) }}</textarea></div>
        <div class="grid gap-5 sm:grid-cols-3">
            <div><label for="min_players" class="oq-label">Minimum players</label><input id="min_players" type="number" name="min_players" value="{{ old('min_players', $game->min_players ?? 1) }}" min="1" max="200" required class="oq-input"></div>
            <div><label for="max_players" class="oq-label">Maximum players</label><input id="max_players" type="number" name="max_players" value="{{ old('max_players', $game->max_players ?? 50) }}" min="1" max="200" required class="oq-input"></div>
            <div><label for="time_limit_per_question" class="oq-label">Seconds per question</label><input id="time_limit_per_question" type="number" name="time_limit_per_question" value="{{ old('time_limit_per_question', $game->time_limit_per_question ?? 30) }}" min="1" max="300" required class="oq-input"><p class="mt-2 text-xs leading-5 text-slate-500">Applies to new and waiting sessions. Timing stays fixed once a quiz starts.</p></div>
        </div>
        <p class="text-xs leading-5 text-slate-500">The host can start when the minimum number of players has joined. Each question runs for the full time limit.</p>
        <div><label for="status" class="oq-label">Status</label><select id="status" name="status" class="oq-input">@foreach (['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $game->status ?? 'draft') === $value)>{{ $label }}</option>@endforeach</select><p class="mt-2 text-xs text-slate-500">Set the game to Active when you are ready to create sessions.</p></div>
        @if ($method !== 'POST')<p class="rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-500">Changes apply to new sessions. Existing sessions keep the settings and questions they were created with.</p>@endif
    </div>
    <div class="mt-8 flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-6"><a href="{{ $game->exists ? route('admin.games.show', $game) : route('admin.games.index') }}" class="oq-button-secondary">Cancel</a><button type="submit" class="oq-button-primary">{{ $method === 'POST' ? 'Create game' : 'Save changes' }}</button></div>
</form>
