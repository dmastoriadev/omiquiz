<section x-show="state.game_type === 'bingo' && state.status === 'completed'" @if (($state['status'] ?? '') !== 'completed') style="display: none" @endif class="final-camp" aria-label="Reverse Bingo final standings">
    <header class="final-camp-hero"><div class="final-camp-hero-copy"><p class="final-camp-eyebrow">Reverse Bingo · Final standings</p><h2>One lucky survivor</h2><p class="final-camp-subtitle" x-text="'Final number called: ' + (state.bingo?.last_number ?? '—')"></p><span class="final-camp-count">One untouched card remains</span></div></header>
    <div class="final-camp-body">
        <div class="final-camp-intro"><div><h3>Top 5 survivors</h3><p>The last active card wins. The full party standings follow below.</p></div></div>
        @php
            $bingoWinnerId = collect($state['results'] ?? [])->firstWhere('rank', 1)['public_id'] ?? null;
            $bingoWinner = collect($state['players'] ?? [])->firstWhere('public_id', $bingoWinnerId);
            $bingoWinningCard = collect($bingoWinner['bingo_cards'] ?? [])->firstWhere('active', true);
        @endphp
        @if ($bingoWinningCard)
            <div x-show="false" class="bingo-winning-card" aria-label="Winning Bingo card">
                @include('play._bingo-card', ['dynamic' => false, 'card' => $bingoWinningCard])
            </div>
        @endif
        <template x-for="person in (state.players || []).filter(person => person.public_id === (state.results || []).find(result => result.is_winner || result.rank === 1)?.public_id)" :key="person.public_id">
            <div class="bingo-winning-card" aria-label="Winning Bingo card">
                <template x-for="card in (person.bingo_cards || []).filter(card => card.active)" :key="card.slot">@include('play._bingo-card', ['dynamic' => true])</template>
            </div>
        </template>
        <div class="final-camp-list" role="list" aria-label="Top five Reverse Bingo players">
            <template x-for="result in (state.results || []).slice(0, 5)" :key="result.public_id">
                <article class="final-camp-row" :class="{ 'final-camp-row--winner': result.is_winner, 'final-camp-row--you': state.player?.public_id === result.public_id }" role="listitem" :aria-label="'Rank ' + result.rank + ', ' + result.name">
                    <div class="final-camp-rank"><span x-show="result.is_winner" aria-hidden="true" class="final-camp-crown">♛</span><span x-text="'#' + result.rank"></span></div>
                    <div class="final-camp-avatar"><img :src="result.profile?.sprite_url || result.profile?.avatar_url" alt="" width="76" height="76" class="party-float" :style="campStyle(result.public_id)"></div>
                    <div class="final-camp-person"><h4 x-text="result.name"></h4><div class="final-camp-badges"><span x-show="result.is_winner" class="final-camp-winner">Winner</span><span x-show="state.player?.public_id === result.public_id" class="final-camp-you">You</span></div></div>
                    <div class="final-camp-score"><strong x-text="result.is_winner ? 'Last card' : 'Play ' + result.bingo_eliminated_on_draw"></strong><span x-text="result.is_winner ? 'Survived the final play' : 'Eliminated'"></span></div>
                </article>
            </template>
        </div>
        <div x-show="(state.results || []).length > 5" class="mt-7">
            <h3 class="mb-3 px-1 text-base font-semibold text-amber-100">The rest of the party</h3>
            <ol class="space-y-2" start="6" aria-label="Remaining player standings"><template x-for="result in (state.results || []).slice(5)" :key="result.public_id"><li class="flex min-w-0 items-center gap-3 rounded-xl border border-emerald-100/[.15] bg-black/10 px-3 py-3" :class="state.player?.public_id === result.public_id ? 'ring-1 ring-amber-200/60' : ''"><span class="w-7 shrink-0 text-xs font-semibold tabular-nums text-emerald-100/70" x-text="'#' + result.rank"></span><img :src="result.profile?.avatar_url" alt="" width="36" height="36" class="h-9 w-9 shrink-0 rounded-lg"><p class="min-w-0 flex-1 break-words text-sm font-semibold text-emerald-50" x-text="result.name + (state.player?.public_id === result.public_id ? ' (You)' : '')"></p><p class="shrink-0 text-xs text-emerald-100/[.65]" x-text="'Out on play ' + result.bingo_eliminated_on_draw"></p></li></template></ol>
        </div>
        <footer class="final-camp-footer"><p>Surviving longer places you higher. Players eliminated on the same play are ordered by the random tie order fixed at the start. The host cannot select the winner.</p><button type="button" @click="toggleMotion()" :aria-pressed="motionPaused" class="min-h-11" x-text="motionPaused ? 'Resume motion' : 'Pause motion'"></button></footer>
    </div>
</section>
