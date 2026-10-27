<section class="slots-game" x-data="slotsBoard(urls)" x-init="syncSlots(state); $watch('state', value => syncSlots(value))" x-show="state.game_type === 'slots' && ['in_progress', 'completed'].includes(state.status)" x-cloak aria-label="Astoria Slots" :data-slot-phase="slotPhase">
    <header class="slots-heading">
        <div><p class="slots-eyebrow">Astoria Slots</p><h2 x-text="slotCompleted ? 'A golden finish' : 'Roll ' + slotPosition + ' of ' + slotTotalRolls"></h2><p class="slots-roll-label" x-text="slotCompleted ? 'All ' + slotTotalRolls + ' rolls complete' : slotTotalRolls + ' rolls. One jackpot.'"></p></div>
        <div class="slots-heading-actions"><button x-show="slotOwnId" type="button" @click="focusMyMachine()" class="slots-small-button">My machine ↓</button><button type="button" @click="toggleMotion()" class="slots-small-button" :aria-pressed="motionPaused" x-text="motionPaused ? 'Resume motion' : 'Pause motion'"></button></div>
    </header>

    <div class="slots-roll-progress" aria-label="Roll progress"><template x-for="roll in slotRollSteps" :key="roll"><span :class="slotCompleted || roll < slotPosition ? 'slots-roll-progress--done' : roll === slotPosition ? 'slots-roll-progress--current' : ''"><span x-text="'Roll ' + roll"></span><span x-show="slotCompleted || roll < slotPosition" aria-hidden="true">✓</span></span></template></div>

    <section class="slots-stage" :class="slotPhase === 'slots_countdown' ? 'slots-stage--countdown' : ''" aria-label="Current roll status">
        <div class="slots-stage-copy"><p class="slots-eyebrow" x-text="slotCompleted ? 'Final results' : slotPhase === 'slots_ready' ? 'Gather the party' : slotPhase === 'slots_countdown' ? 'Spin begins in' : slotPhase === 'slots_spinning' ? 'Reels in motion' : 'Roll revealed'"></p><h3 x-text="slotStageTitle"></h3><p x-text="slotStageHelp"></p></div>
        <div x-show="slotPhase === 'slots_countdown'" class="slots-countdown" role="timer" aria-live="off"><strong x-text="slotSeconds || 'GO'" :class="!slotSeconds ? 'slots-countdown--go' : ''"></strong><span x-show="slotSeconds > 0">seconds</span></div>
        <div x-show="slotPhase === 'slots_spinning'" class="slots-spin-clock" role="timer" aria-live="off"><span class="slots-spin-orbit" aria-hidden="true"></span><strong x-text="slotSeconds || '…'"></strong><span>Rolling</span></div>
        <div x-show="slotPhase === 'slots_ready'" class="slots-ready-count"><strong><span x-text="slotData.ready_count || 0"></span><span class="slots-ready-divider">/</span><span x-text="slotData.player_count || 0"></span></strong><span>players ready</span></div>
    </section>

    <div class="slots-ready-panel" x-show="slotOwnId && slotPhase === 'slots_ready'">
        <div><p x-text="slotState.player?.slot_ready ? 'You’re ready for this roll.' : 'Your machine is waiting for you.'"></p><span x-text="slotState.player?.slot_ready ? 'The spin starts when the whole party is ready.' : 'Press the button to join the next spin.'"></span></div>
        <button type="button" @click="readyForSlot()" class="slots-ready-button" :disabled="!slotCanReady" :aria-pressed="slotState.player?.slot_ready === true" x-text="slotReadyLabel"></button>
    </div>
    <p x-show="slotReadyError" class="slots-error" role="alert" x-text="slotReadyError"></p>

    <div class="slots-winner-announcement" x-show="slotPhase === 'slots_result' && slotCurrentWinners.length" aria-live="polite" aria-atomic="true"><template x-for="winner in slotCurrentWinners" :key="slotAwardKey(winner)"><div :class="prizeClass(winner.prize)"><span class="slot-announcement-logo slot-symbol"><img :src="symbol(winner.prize).image_url" :alt="symbol(winner.prize).label" :data-symbol="winner.prize" x-on:error="symbolImageError($event, winner.prize)"><span class="slot-symbol-label" x-text="symbol(winner.prize).label"></span></span><div><p x-text="symbol(winner.prize).label"></p><h3 x-text="winner.name"></h3><span>Three matching logos. Congratulations!</span></div></div></template></div>

    <section x-show="slotCompleted" class="slots-final" aria-label="Prize winners">
        <div class="slots-section-heading"><h3>The winners’ circle</h3><p x-text="slotFinalWinners.length + ' prizes awarded. Congratulations to our winners.'"></p></div>
        <div class="slots-prize-grid" :style="'--slot-prize-columns:' + (slotFinalWinners.length > 4 ? 3 : 4)"><template x-for="winner in slotFinalWinners" :key="slotAwardKey(winner)"><article class="slots-prize-card" :class="prizeClass(winner.prize)"><div class="slots-prize-emblem slot-symbol"><img :src="symbol(winner.prize).image_url" :alt="symbol(winner.prize).label" :data-symbol="winner.prize" x-on:error="symbolImageError($event, winner.prize)"><span class="slot-symbol-label" x-text="symbol(winner.prize).label"></span></div><p class="slots-prize-name" x-text="symbol(winner.prize).label"></p><img class="slots-prize-avatar" :src="winner.profile?.sprite_url || winner.profile?.avatar_url" alt="" width="100" height="100"><h4 x-text="winner.name"></h4><p class="slots-prize-roll" x-text="'Roll ' + winner.position"></p></article></template></div>
    </section>

    <div class="slots-section-heading slots-machines-heading"><div><h3 x-text="slotCompleted ? 'The final machines' : 'The whole party is rolling'"></h3><p x-text="(slotData.player_count || 0) + ' players · ' + (slotOwnId ? 'Your machine is shown first.' : 'Every player’s machine, live.')"></p></div><span class="slots-results-countdown" x-show="slotPhase === 'slots_result'" x-text="slotSeconds ? (slotPosition >= slotTotalRolls ? 'Final results in ' : 'Next Ready check in ') + slotSeconds + 's' : 'Updating…'"></span></div>

    <div class="slots-machines" x-ref="slotMachines">
        <template x-for="person in slotMachines" :key="person.public_id">
            <article class="slot-machine" :class="[person.public_id === slotOwnId ? 'slot-machine--you' : '', hasReels(person) && person.slot_prize ? 'slot-machine--winner ' + prizeClass(person.slot_prize) : '', slotPhase === 'slots_spinning' ? 'slot-machine--spinning' : '']" :data-player-id="person.public_id">
                <div class="slot-machine-top"><span class="slot-machine-number" x-text="'#' + person.queue_position"></span><span class="slot-machine-you" x-show="person.public_id === slotOwnId">Your machine</span><span class="slot-machine-ready" x-show="slotPhase === 'slots_ready'" :class="person.slot_ready ? 'slot-machine-ready--yes' : ''" x-text="person.slot_ready ? 'Ready ✓' : 'Waiting'"></span></div>
                <div class="slot-machine-body">
                    <div class="slot-machine-marquee"><span aria-hidden="true"></span><p>ASTORIA</p><span aria-hidden="true"></span></div>
                    <div class="slot-reels" role="img" :aria-label="person.name + ': ' + reelLabel(person)">
                        <template x-for="reel in [0, 1, 2]" :key="reel">
                            <div class="slot-reel" :style="'--reel-index:' + reel">
                                <div class="slot-spin-strip" x-show="slotPhase === 'slots_spinning'" aria-hidden="true"><template x-for="(symbolKey, index) in spinSymbols(reel)" :key="index"><div class="slot-symbol"><img :src="symbol(symbolKey).image_url" alt="" :data-symbol="symbolKey" x-on:error="symbolImageError($event, symbolKey)"><span class="slot-symbol-label" x-text="symbol(symbolKey).label"></span></div></template></div>
                                <div class="slot-symbol slot-result-symbol" x-show="hasReels(person)"><template x-if="hasReels(person)"><img :src="symbol(person.slot_reels[reel]).image_url" :alt="symbol(person.slot_reels[reel]).label" :data-symbol="person.slot_reels[reel]" x-on:error="symbolImageError($event, person.slot_reels[reel])"></template><span class="slot-symbol-label" x-text="hasReels(person) ? symbol(person.slot_reels[reel]).label : ''"></span></div>
                                <div class="slot-reel-wait" x-show="slotPhase !== 'slots_spinning' && !hasReels(person)" aria-hidden="true">?</div>
                            </div>
                        </template>
                    </div>
                    <div class="slot-machine-payout"><span x-text="machineLabel(person)"></span></div>
                    <div class="slot-lever" aria-hidden="true"><span class="slot-lever-arm"><span></span></span><i></i></div>
                </div>
                <div class="slot-player"><div class="slot-pilot"><img :src="person.profile?.sprite_url || person.profile?.avatar_url" alt="" width="84" height="84" loading="lazy"><span class="slot-pilot-hand" aria-hidden="true"></span></div><div><h4 :title="person.name" x-text="person.name"></h4><p x-show="hasReels(person) && person.slot_prize" class="slot-player-prize" x-text="person.slot_prize ? symbol(person.slot_prize).label : ''"></p><p x-show="!(hasReels(person) && person.slot_prize)" x-text="slotCompleted ? 'Thanks for playing' : slotPhase === 'slots_spinning' ? 'Pulling for a match…' : 'Three reels. One spin.'"></p></div></div>
            </article>
        </template>
    </div>

    <section x-show="!slotCompleted && slotWinners.length" class="slots-history" aria-label="Winners so far"><h3>Winners so far</h3><div><template x-for="winner in slotWinners" :key="slotAwardKey(winner)"><article :class="prizeClass(winner.prize)"><img :src="winner.profile?.avatar_url" alt="" width="36" height="36"><div><h4 x-text="winner.name"></h4><p x-text="symbol(winner.prize).label + ' · Roll ' + winner.position"></p></div></article></template></div></section>
</section>
