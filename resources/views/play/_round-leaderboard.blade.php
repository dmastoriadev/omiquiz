<section x-show="showRoundLeaderboard" x-cloak class="round-board" aria-label="Top five leaderboard" :data-rank-ready="leaderboardReady ? 'true' : 'false'">
    <header class="round-board-heading">
        <div><p class="round-board-eyebrow" x-text="(state.game_type === 'sketch' ? 'Turn ' : 'Question ') + state.round_leaderboard?.question_position + ' complete'"></p><h2>Top 5 adventurers</h2></div>
        <div class="round-board-timer" role="timer" aria-live="off"><span x-text="roundSeconds"></span><span>sec</span></div>
    </header>
    <p x-show="state.game_type === 'sketch' && state.sketch?.revealed_word" class="sketch-word-reveal">The word was <strong x-text="state.sketch?.revealed_word"></strong></p>
    <div class="round-rank-list" :style="'--rank-count:' + Math.max(1, Math.min(5, leaderboardRows.length))" role="list" aria-label="Highest scoring players">
        <template x-for="entry in leaderboardRows.slice(0, 5)" :key="entry.public_id">
            <article class="round-rank-row" :class="{ 'round-rank-row--first': entry.position === 1, 'round-rank-row--rising': entry.movement > 0, 'round-rank-row--you': state.player?.public_id === entry.public_id }" :style="rankStyle(entry)" role="listitem" :aria-label="'Rank ' + entry.rank + ', ' + entry.name + ', ' + entry.score + ' points'">
                <div class="round-rank-place"><span x-show="['quiz', 'sketch'].includes(state.game_type) && [1, 2, 3].includes(Number(entry.rank))" aria-hidden="true" class="round-rank-crown" :class="{ 'podium-crown--gold': Number(entry.rank) === 1, 'podium-crown--silver': Number(entry.rank) === 2, 'podium-crown--bronze': Number(entry.rank) === 3 }">♛</span><span x-text="'#' + entry.rank"></span></div>
                <div class="round-rank-avatar"><img :src="entry.profile?.sprite_url || entry.profile?.avatar_url" alt="" width="68" height="68" class="party-float" :style="campStyle(entry.public_id)"></div>
                <div class="round-rank-person"><h3 :title="entry.name" x-text="entry.name"></h3><p :class="entry.movement > 0 ? 'round-rank-gain' : entry.movement < 0 ? 'round-rank-loss' : ''"><span x-show="entry.movement !== 0" aria-hidden="true" x-text="entry.movement > 0 ? '↑ ' : '↓ '"></span><span x-text="entry.movement > 0 ? 'Climbed ' + entry.movement + (entry.movement === 1 ? ' place' : ' places') : entry.movement < 0 ? 'Down ' + Math.abs(entry.movement) + (entry.movement === -1 ? ' place' : ' places') : 'Holding position'"></span><span x-show="state.player?.public_id === entry.public_id"> · You</span></p></div>
                <div class="round-rank-score"><span x-text="Number(entry.score).toLocaleString()"></span><span>points</span></div>
            </article>
        </template>
    </div>
    <footer class="round-board-footer"><p x-text="state.round_leaderboard?.is_final ? 'Final results in ' + roundSeconds + 's' : (state.game_type === 'sketch' ? 'Next drawing turn in ' : 'Next question in ') + roundSeconds + 's'"></p><button type="button" @click="toggleMotion()" :aria-pressed="motionPaused" x-text="motionPaused ? 'Resume motion' : 'Pause motion'"></button></footer>
</section>
<section x-show="state.phase === 'leaderboard' && !showRoundLeaderboard" x-cloak class="round-board round-board--transition" role="status"><p x-text="state.round_leaderboard?.is_final ? 'Preparing final results…' : state.game_type === 'sketch' ? 'Preparing the next drawing turn…' : 'Preparing the next question…'"></p></section>
