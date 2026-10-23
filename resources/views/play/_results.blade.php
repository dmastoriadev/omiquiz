<section x-show="state.status === 'completed'" x-cloak class="final-camp" aria-label="Completed quiz standings">
    <header class="final-camp-hero">
        <div class="final-camp-hero-copy"><p class="final-camp-eyebrow">Final standings</p><h2>Quiz complete</h2><p class="final-camp-subtitle">Your party's adventure is complete.</p><span class="final-camp-count" x-text="state.results.length + (state.results.length === 1 ? ' player finished' : ' players finished')"></span></div>
    </header>
    <div class="final-camp-body">
        <div class="final-camp-intro"><div><h3>The final leaderboard</h3><p>Highest score wins. Faster response time breaks a tie.</p></div><span class="final-camp-flourish" aria-hidden="true">✦</span></div>
        <div class="final-camp-list" role="list" aria-label="All player results">
            <template x-for="result in state.results" :key="result.public_id">
                <article class="final-camp-row" :class="{ 'final-camp-row--winner': result.is_winner, 'final-camp-row--you': state.player?.public_id === result.public_id }" role="listitem" :aria-label="'Rank ' + result.rank + ', ' + result.name + ', ' + result.score + ' points'">
                    <div class="final-camp-rank"><span x-show="result.is_winner" aria-hidden="true" class="final-camp-crown">♛</span><span x-text="'#' + result.rank"></span></div>
                    <div class="final-camp-avatar"><img :src="result.profile?.sprite_url || result.profile?.avatar_url" alt="" width="76" height="76" class="party-float" :style="campStyle(result.public_id)"></div>
                    <div class="final-camp-person"><h4 x-text="result.name"></h4><div class="final-camp-badges"><span x-show="result.is_winner" class="final-camp-winner">Winner</span><span x-show="state.player?.public_id === result.public_id" class="final-camp-you">You</span></div></div>
                    <div class="final-camp-score"><strong x-text="Number(result.score).toLocaleString()"></strong><span>points</span></div>
                    <dl class="final-camp-stats"><div><dt>Correct</dt><dd x-text="result.correct + ' / ' + state.total_questions"></dd></div><div><dt>Wrong</dt><dd x-text="result.wrong"></dd></div><div><dt>Unanswered</dt><dd x-text="result.unanswered"></dd></div><div><dt>Response time</dt><dd x-text="(Number(result.total_response_time_ms) / 1000).toFixed(2) + ' s'"></dd></div></dl>
                </article>
            </template>
            <p x-show="state.results.length === 0" class="final-camp-empty">No player results were recorded for this session.</p>
        </div>
        <footer class="final-camp-footer"><p>Identical scores and response times share a rank. Unanswered questions earn zero points and use the full question time.</p><button type="button" @click="toggleMotion()" :aria-pressed="motionPaused" x-text="motionPaused ? 'Resume motion' : 'Pause motion'"></button></footer>
    </div>
</section>
