<header class="answer-reveal-heading" x-show="showAnswerReveal">
    <div class="answer-reveal-topline">
        <p class="answer-reveal-eyebrow" x-text="state.game_type === 'sketch' ? 'The word was' : (state.answer_reveal?.option_key ? 'Answer ' + state.answer_reveal.option_key + ' · Correct answer' : 'Correct answer')"></p>
        <div class="answer-reveal-timer" role="timer" aria-label="Seconds until the leaderboard" aria-live="off"><span x-text="answerRevealSeconds"></span><span>sec</span></div>
    </div>
    <h2 class="answer-reveal-title" x-text="state.answer_reveal?.answer || ''"></h2>
</header>
