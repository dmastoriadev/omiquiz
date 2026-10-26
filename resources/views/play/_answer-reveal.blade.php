<section x-show="state.game_type === 'quiz' && showAnswerReveal" x-cloak class="answer-reveal" aria-label="Correct answer reveal">
    @include('play._answer-reveal-heading')
    <p class="answer-reveal-footer" x-text="'Top 5 leaderboard in ' + answerRevealSeconds + 's'"></p>
</section>
<section x-show="['quiz', 'sketch'].includes(state.game_type) && state.phase === 'answer_reveal' && !showAnswerReveal" x-cloak class="round-board round-board--transition" role="status"><p>Preparing the leaderboard…</p></section>
