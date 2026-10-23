<section x-show="state.game_type === 'bingo' && state.status === 'in_progress'" x-cloak class="mb-6 overflow-hidden rounded-3xl border border-emerald-900 bg-[#092b2b] text-emerald-50" aria-label="Reverse Bingo live game">
    <header class="border-b border-emerald-100/[.15] bg-[#103534] p-5 sm:p-7">
        <div class="flex flex-wrap items-center justify-between gap-5">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-[.16em] text-amber-200">Reverse Bingo</p>
                <h2 class="mt-2 text-2xl font-bold leading-tight sm:text-3xl" x-text="state.current_position ? 'Keep your last card alive' : 'The cards are ready'"></h2>
                <p class="mt-2 text-sm leading-6 text-emerald-100/80">Each called number closes its matching cards. Lose all three and you are out.</p>
                <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                    <span class="rounded-full border border-emerald-100/20 px-3 py-2" x-text="'Play ' + state.current_position + ' / ' + (state.bingo?.max_plays || state.total_questions)"></span>
                    <span class="rounded-full bg-emerald-100/10 px-3 py-2" x-text="(state.bingo?.active_count || 0) + ' players remain'"></span>
                </div>
            </div>
            <div class="flex shrink-0 flex-col items-center gap-2" role="status" aria-live="polite" aria-atomic="true">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-emerald-100/70">Called number</span>
                <div class="flex h-24 w-24 items-center justify-center rounded-full border-4 border-amber-200 bg-amber-100 text-5xl font-bold tabular-nums text-[#183b32] shadow-lg transition-transform duration-300 motion-reduce:transition-none sm:h-28 sm:w-28 sm:text-6xl" :class="bingoPulse && !motionPaused ? 'scale-110' : 'scale-100'"><span x-text="state.bingo?.last_number ?? '—'"></span></div>
            </div>
        </div>
        @if ($hostView ?? false)
            <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-emerald-100/[.15] pt-5">
                <button type="button" @click="revealBingo()" :disabled="!canRevealBingo" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-amber-200 px-6 py-3 text-sm font-semibold text-[#183b32] transition hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-amber-100 disabled:cursor-wait disabled:opacity-60" x-text="bingoDrawing ? 'Revealing…' : bingoPendingPosition !== null ? 'Retry this reveal' : state.current_position ? 'Reveal next number' : 'Reveal first number'"></button>
                <p class="text-xs leading-5 text-emerald-100/75">Only this button advances the game. Take as long as you need between plays.</p>
            </div>
            <p x-show="bingoError" x-cloak role="alert" class="mt-3 rounded-lg bg-rose-100 px-4 py-3 text-sm text-rose-800" x-text="bingoError"></p>
        @else
            <p class="mt-5 text-xs font-medium text-amber-100" x-text="state.player?.eliminated ? 'Your cards are closed. Stay to watch the final survivor!' : 'Waiting for the host to reveal the next number.'"></p>
        @endif
    </header>

    <div class="p-3 sm:p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 px-1">
            <h3 class="text-base font-semibold text-amber-100">Active players &amp; cards</h3>
            <button type="button" @click="toggleMotion()" :aria-pressed="motionPaused" class="min-h-11 rounded-lg px-3 text-xs font-semibold text-emerald-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber-100" x-text="motionPaused ? 'Resume motion' : 'Pause motion'"></button>
        </div>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 {{ ($hostView ?? false) ? 'lg:grid-cols-2 2xl:grid-cols-3' : 'xl:grid-cols-5' }}" role="list" aria-label="Players with active cards">
            <template x-for="person in bingoActivePlayers" :key="person.public_id">
                <article class="min-w-0 rounded-2xl border p-3 sm:p-4" :class="state.player?.public_id === person.public_id ? 'border-amber-200 bg-[#374332] ring-1 ring-amber-200/50' : 'border-emerald-100/20 bg-[#123c36]'" role="listitem">
                    <div class="flex flex-col items-center gap-1 text-center">
                        <img :src="person.profile?.sprite_url || person.profile?.avatar_url" alt="" width="72" height="72" class="party-float h-16 w-16 object-contain sm:h-[72px] sm:w-[72px]" :style="campStyle(person.public_id)">
                        <h4 class="w-full break-words text-sm font-semibold leading-5 text-emerald-50" x-text="person.name"></h4>
                        <span class="min-h-4 text-[10px] font-semibold uppercase tracking-wide text-amber-200" x-text="state.player?.public_id === person.public_id ? 'You' : ''"></span>
                    </div>
                    <div class="mt-2 grid grid-cols-3 gap-1.5" :aria-label="person.name + ' cards'">
                        <template x-for="card in (person.bingo_cards || [])" :key="person.public_id + '-' + card.slot">
                            <div class="relative flex min-h-12 min-w-0 items-center justify-center rounded-lg border px-1 py-3 text-xl font-bold tabular-nums transition-colors duration-300 motion-reduce:transition-none sm:text-2xl" :class="card.active ? 'border-amber-200/60 bg-amber-50 text-[#183b32]' : 'border-emerald-100/10 bg-black/20 text-emerald-100/[.35] line-through'" :aria-label="'Number ' + card.number + (card.active ? ', active' : ', closed')"><span x-text="card.number"></span><span x-show="!card.active" class="absolute bottom-0.5 right-1 text-[9px] leading-none" aria-hidden="true">×</span></div>
                        </template>
                    </div>
                    <p class="mt-2 text-center text-[11px] text-emerald-100/70" x-text="person.cards_remaining + (person.cards_remaining === 1 ? ' card left' : ' cards left')"></p>
                </article>
            </template>
        </div>

        <details x-show="bingoEliminatedPlayers.length" class="mt-5 rounded-xl border border-emerald-100/[.15] bg-black/10">
            <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-emerald-100/80" x-text="'Eliminated players (' + bingoEliminatedPlayers.length + ')'"></summary>
            <div class="grid gap-2 border-t border-emerald-100/10 p-3 sm:grid-cols-2 xl:grid-cols-3">
                <template x-for="person in bingoEliminatedPlayers" :key="person.public_id">
                    <div class="flex min-w-0 items-center gap-3 rounded-lg bg-black/10 p-2" :class="state.player?.public_id === person.public_id ? 'ring-1 ring-amber-200/60' : ''"><img :src="person.profile?.avatar_url" alt="" width="40" height="40" class="h-10 w-10 shrink-0 rounded-lg"><div class="min-w-0 flex-1"><p class="break-words text-xs font-semibold text-emerald-100/80" x-text="person.name + (state.player?.public_id === person.public_id ? ' (You)' : '')"></p><p class="mt-1 text-[10px] text-emerald-100/50">All three cards closed</p></div><div class="flex shrink-0 gap-1"><template x-for="card in (person.bingo_cards || [])" :key="card.slot"><span class="flex h-7 w-6 items-center justify-center rounded bg-black/20 text-[11px] tabular-nums text-emerald-100/40 line-through" x-text="card.number"></span></template></div></div>
                </template>
            </div>
        </details>

        <details class="mt-4 rounded-xl border border-emerald-100/[.15]">
            <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-emerald-100/80">Numbers called</summary>
            <div class="flex flex-wrap gap-2 border-t border-emerald-100/10 p-4"><template x-for="draw in (state.bingo?.draws || [])" :key="draw.position"><span class="flex h-10 w-10 items-center justify-center rounded-full border text-sm font-semibold tabular-nums" :class="draw.position === state.current_position ? 'border-amber-200 bg-amber-100 text-[#183b32]' : 'border-emerald-100/20 text-emerald-100/80'" :aria-label="'Play ' + draw.position + ': number ' + draw.number" x-text="draw.number"></span></template><p x-show="!(state.bingo?.draws || []).length" class="text-xs text-emerald-100/60">The first number is waiting for the host.</p></div>
        </details>
        <p class="mt-4 px-1 text-[11px] leading-5 text-emerald-100/[.65]">Cards and the elimination order are randomized when the game starts. The host reveals one planned number per play. One player keeps a single card after the final play.</p>
    </div>
</section>
