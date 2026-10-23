<section class="sketch-game" x-data="sketchBoard(urls)" x-init="syncSketch(state); $watch('state', value => syncSketch(value))" x-show="state.game_type === 'sketch' && state.phase === 'drawing'" x-cloak aria-label="Sketch and Guess">
    <header class="sketch-heading">
        <div class="min-w-0"><p class="sketch-eyebrow" x-text="'Sketch & Guess · Turn ' + (sketch.position || state.current_position) + ' of ' + state.total_questions"></p><h2 x-text="sketch.is_drawer ? 'Your turn to sketch' : (sketch.drawer_name || 'The next player') + ' is sketching'"></h2><p class="sketch-role" x-text="sketch.is_drawer ? 'Help the party guess your word.' : isMember ? 'Watch the drawing and send your guess.' : 'Watch the party sketch and guess.'"></p></div>
        <div class="sketch-timer" :class="remainingSeconds <= 10 ? 'sketch-timer--urgent' : ''" role="timer" aria-live="off"><span x-text="remainingSeconds"></span><span>seconds</span></div>
    </header>

    <div class="sketch-prompt" x-show="sketch.is_drawer"><span>Your secret word</span><strong x-text="sketch.prompt || 'Preparing your word…'"></strong><p>Draw shapes and objects. Keep the word out of your drawing.</p></div>

    <div class="sketch-tools" x-show="sketch.is_drawer" aria-label="Drawing tools">
        <div class="sketch-palette" role="group" aria-label="Pen color"><template x-for="ink in colors" :key="ink"><button type="button" class="sketch-color" :style="'--ink:' + ink" :class="color === ink && !erasing ? 'sketch-color--selected' : ''" :aria-label="'Use color ' + ink" :aria-pressed="color === ink && !erasing" :disabled="!canDraw" @click="setColor(ink)"><span aria-hidden="true" x-show="color === ink && !erasing">✓</span></button></template></div>
        <div class="sketch-tool-actions"><label class="sketch-brush-label">Brush<select :value="brushWidth" @change="setBrushWidth(Number($event.target.value))" :disabled="!canDraw" aria-label="Brush width"><option value="3">Fine</option><option value="6">Medium</option><option value="12">Bold</option><option value="20">Wide</option></select></label><button type="button" class="sketch-tool" :class="erasing ? 'sketch-tool--active' : ''" :aria-pressed="erasing" @click="toggleEraser()" :disabled="!canDraw">Eraser</button><button type="button" class="sketch-tool" @click="clearDrawing()" :disabled="!canDraw">Clear canvas</button></div>
    </div>

    <div class="sketch-canvas-frame" :class="canDraw ? 'sketch-canvas-frame--drawing' : ''"><canvas x-ref="drawingCanvas" x-init="$nextTick(() => mountCanvas($refs.drawingCanvas))" width="1000" height="625" class="sketch-canvas" :aria-label="sketch.is_drawer ? 'Draw your secret word here using a finger, pen or mouse.' : 'Live drawing by ' + (sketch.drawer_name || 'the current player')">Your browser needs canvas support to display the drawing.</canvas></div>

    <div class="sketch-canvas-status"><p role="status" x-text="drawingStatus"></p><span x-text="(sketch.correct_count || 0) + ' guessed correctly'"></span></div>
    <div x-show="sketchError || drawingError" class="sketch-error" role="alert"><p x-text="sketchError || drawingError"></p><button x-show="drawingError && !drawingBlocked" type="button" class="sketch-tool" @click="retryDrawing()">Retry drawing sync</button></div>

    <div class="sketch-answer-area">
        <form x-show="isMember && !sketch.is_drawer && !sketch.guessed" @submit.prevent="submitGuess()" class="sketch-guess-form"><div><label for="sketch-guess" class="oq-label">Your guess</label><input id="sketch-guess" x-model="guessText" maxlength="100" required autocomplete="off" autocorrect="off" autocapitalize="none" spellcheck="false" enterkeyhint="send" placeholder="What is being drawn?" class="oq-input" :disabled="!canGuess || guessing"></div><button type="submit" class="oq-button-primary" :disabled="!canGuess || guessing || !guessText.trim()" x-text="guessing ? 'Checking…' : 'Send guess'"></button></form>
        <p x-show="guessMessage" class="sketch-feedback" role="status" x-text="guessMessage"></p>
        <p x-show="sketch.guessed && !sketch.is_drawer" class="sketch-success">✓ You guessed it! Watch the rest of the turn.</p>
        <p x-show="sketch.is_drawer" class="sketch-help">Every correct guess gives you a drawing bonus. Your own drawing turn is not a guessing turn.</p>
        <p x-show="!isMember" class="sketch-help">Spectator view · The secret word is revealed when the turn ends.</p>
        <p x-show="isMember && !sketch.is_drawer && !sketch.guessed" class="sketch-help">Faster correct guesses earn more points. You can try again after an incorrect guess.</p>
    </div>

    <details class="sketch-turn-order"><summary><span>Drawing order</span><span>Join order repeats</span></summary><p class="sketch-help px-4 pt-3">Players rotate until every word is played. Recent and upcoming turns are shown below.</p><p class="sketch-help px-4 pb-2" x-show="(sketch.turn_order || []).length > 0 && sketch.turn_order.length < state.total_questions" x-text="'Showing turns ' + (sketch.turn_order?.[0]?.position || 0) + '–' + (sketch.turn_order?.[sketch.turn_order.length - 1]?.position || 0) + ' of ' + state.total_questions"></p><ol><template x-for="person in (sketch.turn_order || [])" :key="person.position"><li :class="person.position === sketch.position ? 'sketch-turn-order--current' : person.position < sketch.position ? 'sketch-turn-order--done' : ''"><span class="sketch-turn-number" x-text="person.position"></span><span class="min-w-0 break-words" x-text="person.name + (state.player?.public_id === person.public_id ? ' (You)' : '')"></span><span class="sketch-turn-tag" x-text="person.position === sketch.position ? 'Drawing' : person.position < sketch.position ? 'Done' : 'Waiting'"></span></li></template></ol></details>
</section>
