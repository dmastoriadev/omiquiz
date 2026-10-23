@php
    $bingoLive = ($state['game_type'] ?? '') === 'bingo' && ($state['status'] ?? '') === 'in_progress';
    $bingoOwnId = $state['player']['public_id'] ?? null;
    $bingoInitialPlayers = collect($state['players'] ?? [])->sortByDesc(fn ($person) => $person['public_id'] === $bingoOwnId)->values();
@endphp
<section x-show="state.game_type === 'bingo' && state.status === 'in_progress'" @if (!$bingoLive) style="display: none" @endif class="bingo-game mb-6 overflow-hidden rounded-3xl border border-emerald-900 bg-[#092b2b] text-emerald-50" aria-label="Reverse Bingo live game">
    <header class="border-b border-emerald-100/[.15] bg-[#103534] p-5 sm:p-7">
        <div class="flex flex-wrap items-center justify-between gap-5">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-[.16em] text-amber-200">Reverse Bingo</p>
                <h2 class="mt-2 text-2xl font-bold leading-tight sm:text-3xl" x-text="state.current_position ? 'Keep your last card alive' : 'The cards are ready'">{{ ($state['current_position'] ?? 0) ? 'Keep your last card alive' : 'The cards are ready' }}</h2>
                <p class="mt-2 text-sm leading-6 text-emerald-100/80">A called number anywhere on a card closes that whole card. Lose all three and you are out.</p>
                <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                    <span class="rounded-full border border-emerald-100/20 px-3 py-2" x-text="'Play ' + state.current_position + ' / ' + (state.bingo?.max_plays || state.total_questions)">Play {{ $state['current_position'] ?? 0 }} / {{ $state['bingo']['max_plays'] ?? $state['total_questions'] ?? 0 }}</span>
                    <span class="rounded-full bg-emerald-100/10 px-3 py-2" x-text="(state.bingo?.active_count || 0) + ' players remain'">{{ $state['bingo']['active_count'] ?? 0 }} players remain</span>
                </div>
            </div>
            <div class="flex shrink-0 flex-col items-center gap-2" role="status" aria-live="polite" aria-atomic="true">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-emerald-100/70">Called number</span>
                <div class="flex h-24 w-24 items-center justify-center rounded-full border-4 border-amber-200 bg-amber-100 text-5xl font-bold tabular-nums text-[#183b32] shadow-lg transition-transform duration-300 motion-reduce:transition-none sm:h-28 sm:w-28 sm:text-6xl" :class="typeof bingoPulse !== 'undefined' && bingoPulse && !motionPaused ? 'scale-110' : 'scale-100'"><span x-text="state.bingo?.last_number ?? '—'">{{ $state['bingo']['last_number'] ?? '—' }}</span></div>
            </div>
        </div>
        @if ($hostView ?? false)
            <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-emerald-100/[.15] pt-5">
                <form method="POST" action="{{ route('admin.sessions.bingo-draw', $session) }}" @submit="if (typeof revealBingo === 'function') { $event.preventDefault(); revealBingo(); }">
                    @csrf
                    <input type="hidden" name="expected_position" value="{{ $state['current_position'] ?? 0 }}" :value="typeof bingoPendingPosition !== 'undefined' && bingoPendingPosition !== null ? bingoPendingPosition : state.current_position">
                    <button type="submit" :disabled="state.status !== 'in_progress' || (typeof bingoDrawing !== 'undefined' && bingoDrawing)" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-amber-200 px-6 py-3 text-sm font-semibold text-[#183b32] transition hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-amber-100 disabled:cursor-wait disabled:opacity-60" x-text="typeof bingoDrawing !== 'undefined' && bingoDrawing ? 'Revealing…' : typeof bingoPendingPosition !== 'undefined' && bingoPendingPosition !== null ? 'Retry this reveal' : state.current_position ? 'Reveal next number' : 'Reveal first number'">{{ ($state['current_position'] ?? 0) ? 'Reveal next number' : 'Reveal first number' }}</button>
                </form>
                <p class="text-xs leading-5 text-emerald-100/75">Only this button advances the game. Take as long as you need between plays.</p>
            </div>
            <p x-show="typeof bingoError !== 'undefined' && bingoError" x-cloak role="alert" class="mt-3 rounded-lg bg-rose-100 px-4 py-3 text-sm text-rose-800" x-text="typeof bingoError !== 'undefined' ? bingoError : ''"></p>
        @else
            <p class="mt-5 text-xs font-medium text-amber-100" x-text="state.player?.eliminated ? 'Your cards are closed. Stay to watch the final survivor!' : 'Waiting for the host to reveal the next number.'">Waiting for the host to reveal the next number.</p>
        @endif
    </header>

    <div class="p-3 sm:p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 px-1">
            <div><h3 class="text-base font-semibold text-amber-100">Active players &amp; cards</h3><p class="bingo-swipe-note">Swipe across a player’s cards to see all three.</p></div>
            <button type="button" @click="toggleMotion()" :aria-pressed="motionPaused" class="min-h-11 rounded-lg px-3 text-xs font-semibold text-emerald-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber-100" x-text="motionPaused ? 'Resume motion' : 'Pause motion'">Pause motion</button>
        </div>
        {{-- Real HTML is present immediately, including when a browser has an older JS bundle. --}}
        <div x-show="false" class="bingo-player-grid" role="list" aria-label="Initial player cards">
            @foreach ($bingoInitialPlayers as $person)
                @if (!$person['eliminated'])
                    <article class="bingo-player-panel {{ $person['public_id'] === $bingoOwnId ? 'bingo-player-panel--you' : '' }}" data-bingo-player="{{ $person['public_id'] }}" role="listitem">
                        <div class="bingo-player-heading"><img src="{{ $person['profile']['sprite_url'] ?? $person['profile']['avatar_url'] }}" alt="" width="64" height="64"><div><h4>{{ $person['name'] }}</h4><p>{{ $person['public_id'] === $bingoOwnId ? 'You · ' : '' }}{{ $person['cards_remaining'] }} {{ $person['cards_remaining'] === 1 ? 'card left' : 'cards left' }}</p></div></div>
                        <div class="bingo-card-strip" tabindex="0" role="group" aria-label="{{ $person['name'] }} cards">
                            @foreach ($person['bingo_cards'] as $card)@include('play._bingo-card', ['dynamic' => false, 'card' => $card])@endforeach
                        </div>
                    </article>
                @endif
            @endforeach
        </div>
        <div class="bingo-player-grid" role="list" aria-label="Players with active cards">
            <template x-for="person in (state.players || []).filter(person => !person.eliminated).sort((a, b) => Number(b.public_id === state.player?.public_id) - Number(a.public_id === state.player?.public_id))" :key="person.public_id">
                <article class="bingo-player-panel" :class="state.player?.public_id === person.public_id ? 'bingo-player-panel--you' : ''" :data-bingo-player="person.public_id" role="listitem">
                    <div class="bingo-player-heading"><img :src="person.profile?.sprite_url || person.profile?.avatar_url" alt="" width="64" height="64" class="party-float" :style="campStyle(person.public_id)"><div><h4 x-text="person.name"></h4><p x-text="(state.player?.public_id === person.public_id ? 'You · ' : '') + person.cards_remaining + (person.cards_remaining === 1 ? ' card left' : ' cards left')"></p></div></div>
                    <div class="bingo-card-strip" tabindex="0" role="group" :aria-label="person.name + ' cards, scroll horizontally to see all three'">
                        <template x-for="card in (person.bingo_cards || [])" :key="person.public_id + '-' + card.slot">@include('play._bingo-card', ['dynamic' => true])</template>
                    </div>
                </article>
            </template>
        </div>

        <details x-show="(state.players || []).some(person => person.eliminated)" @if (!$bingoInitialPlayers->contains('eliminated', true)) style="display:none" @endif class="mt-5 rounded-xl border border-emerald-100/[.15] bg-black/10">
            <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-emerald-100/80" x-text="'Eliminated players (' + (state.players || []).filter(person => person.eliminated).length + ')'">Eliminated players</summary>
            <div class="bingo-player-grid border-t border-emerald-100/10 p-3">
                <template x-for="person in (state.players || []).filter(person => person.eliminated)" :key="person.public_id">
                    <article class="bingo-player-panel bingo-player-panel--out" :class="state.player?.public_id === person.public_id ? 'bingo-player-panel--you' : ''">
                        <div class="bingo-player-heading"><img :src="person.profile?.avatar_url" alt="" width="44" height="44"><div><h4 x-text="person.name + (state.player?.public_id === person.public_id ? ' (You)' : '')"></h4><p>All three cards closed</p></div></div>
                        <div class="bingo-card-strip" tabindex="0" role="group" :aria-label="person.name + ' eliminated cards'"><template x-for="card in (person.bingo_cards || [])" :key="card.slot">@include('play._bingo-card', ['dynamic' => true])</template></div>
                    </article>
                </template>
            </div>
        </details>

        <details class="mt-4 rounded-xl border border-emerald-100/[.15]">
            <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-emerald-100/80">Numbers called</summary>
            <div class="flex flex-wrap gap-2 border-t border-emerald-100/10 p-4"><template x-for="draw in (state.bingo?.draws || [])" :key="draw.position"><span class="flex h-10 w-10 items-center justify-center rounded-full border text-sm font-semibold tabular-nums" :class="draw.position === state.current_position ? 'border-amber-200 bg-amber-100 text-[#183b32]' : 'border-emerald-100/20 text-emerald-100/80'" :aria-label="'Play ' + draw.position + ': number ' + draw.number" x-text="draw.number"></span></template><p x-show="!(state.bingo?.draws || []).length" class="text-xs text-emerald-100/60">The first number is waiting for the host.</p></div>
        </details>
        <p class="mt-4 px-1 text-[11px] leading-5 text-emerald-100/[.65]" x-text="state.bingo?.rules || 'Each player has three 5×5 cards with a FREE center. The host reveals one planned number per play; one untouched card remains after the final play.'">{{ $state['bingo']['rules'] ?? 'Each player has three 5×5 cards with a FREE center. The host reveals one planned number per play; one untouched card remains after the final play.' }}</p>
    </div>
</section>
