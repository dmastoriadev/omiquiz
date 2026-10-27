const SYMBOLS = ['black', 'bronze', 'silver', 'gold'];
const PHASES = {slots_ready: 0, slots_countdown: 1, slots_spinning: 2, slots_result: 3};
const DEFAULT_SYMBOLS = {
    black: {label: 'Black consolation', image_url: '/images/logos/AHR-logo-square.webp'},
    bronze: {label: 'Bronze consolation', image_url: '/images/logos/AHR-logo-bronze.webp'},
    silver: {label: 'Silver consolation', image_url: '/images/logos/AHR-logo-silver.webp'},
    gold: {label: 'Gold jackpot', image_url: '/images/logos/AHR-logo-gold.webp'},
};

export default function registerSlots(Alpine) {
    Alpine.data('slotsBoard', (urls = {}) => ({
        slotUrls: urls,
        slotState: {},
        slotNow: Date.now(),
        slotServerOffset: 0,
        slotLastServerTime: 0,
        slotPhaseKey: null,
        slotLocalDeadline: 0,
        slotClock: null,
        slotDestroyed: false,
        slotSaving: false,
        slotReadyError: '',
        slotPendingPosition: null,
        slotRequestSequence: 0,

        init() {
            this.slotClock = setInterval(() => { this.slotNow = Date.now(); }, 100);
        },

        destroy() {
            this.slotDestroyed = true;
            clearInterval(this.slotClock);
            this.slotRequestSequence++;
        },

        syncSlots(next) {
            if (this.slotDestroyed || next?.game_type !== 'slots') return;
            const timestamp = Date.parse(next.server_now || '');
            if (Number.isFinite(timestamp) && timestamp < this.slotLastServerTime) return;
            const position = Number(next.current_position || 0);
            const oldPosition = Number(this.slotState.current_position || 0);
            if (position < oldPosition) return;
            if (position === oldPosition && this.slotState.status === 'in_progress' && next.status === 'in_progress'
                && (PHASES[next.phase] ?? -1) < (PHASES[this.slotState.phase] ?? -1)) return;
            if (['completed', 'cancelled'].includes(this.slotState.status) && next.status !== this.slotState.status) return;
            const changed = position !== oldPosition;
            this.slotState = next;
            this.slotNow = Date.now();
            if (Number.isFinite(timestamp)) {
                this.slotLastServerTime = timestamp;
                this.slotServerOffset = timestamp - this.slotNow;
            }
            const end = Date.parse(next.slots?.phase_ends_at || '');
            const key = `${position}:${next.phase}:${next.slots?.phase_ends_at || ''}`;
            if (next.status === 'in_progress' && Number.isFinite(end) && next.phase !== 'slots_ready') {
                const remaining = Math.min(5000, Math.max(0, end - (Number.isFinite(timestamp) ? timestamp : this.slotNow)));
                const deadline = this.slotNow + remaining;
                this.slotLocalDeadline = key === this.slotPhaseKey ? Math.min(this.slotLocalDeadline, deadline) : deadline;
            } else {
                this.slotLocalDeadline = 0;
            }
            this.slotPhaseKey = key;
            if (changed || next.status !== 'in_progress' || next.phase !== 'slots_ready' || next.player?.slot_ready) {
                this.slotReadyError = '';
                this.slotPendingPosition = null;
                if (changed) {
                    this.slotRequestSequence++;
                    this.slotSaving = false;
                }
            }
        },

        get slotData() { return this.slotState.slots || {}; },
        get slotPhase() { return this.slotState.phase || 'waiting'; },
        // Existing started sessions keep their saved length; every new game has five rolls.
        get slotTotalRolls() { return Number(this.slotData.total_rolls) === 15 ? 15 : 5; },
        get slotPosition() { return Number(this.slotData.position ?? this.slotState.current_position) || 1; },
        get slotRollSteps() { return Array.from({length: this.slotTotalRolls}, (_, i) => i + 1); },
        get slotOwnId() { return this.slotState.player?.public_id || null; },
        get slotCompleted() { return this.slotState.status === 'completed'; },
        get slotMachines() {
            const people = [...(this.slotState.players || [])];
            return this.slotOwnId ? people.sort((a, b) => Number(b.public_id === this.slotOwnId) - Number(a.public_id === this.slotOwnId)) : people;
        },
        get slotWinners() { return this.slotData.winners || []; },
        get slotCurrentWinners() { return this.slotData.current_winners || []; },
        get slotFinalWinners() {
            const order = {gold: 0, silver: 1, bronze: 2, black: 3};
            return [...this.slotWinners].sort((a, b) => (order[a.prize] ?? 4) - (order[b.prize] ?? 4));
        },
        slotAwardKey(winner) { return `${winner.public_id}:${winner.position}:${winner.prize}`; },
        get slotSeconds() {
            if (!this.slotLocalDeadline || this.slotState.status !== 'in_progress') return 0;
            const end = Date.parse(this.slotData.phase_ends_at || '');
            if (!Number.isFinite(end)) return 0;
            return Math.min(5, Math.max(0, Math.ceil(Math.min(end - this.slotNow - this.slotServerOffset, this.slotLocalDeadline - this.slotNow) / 1000)));
        },
        get slotCanReady() {
            return !!this.slotUrls.slotsReady && !!this.slotOwnId && !this.slotDestroyed && !this.slotSaving
                && this.slotState.status === 'in_progress' && this.slotPhase === 'slots_ready'
                && this.slotPosition >= 1 && this.slotPosition <= this.slotTotalRolls
                && this.slotData.can_ready !== false && this.slotState.player?.can_ready !== false
                && !this.slotState.player?.slot_ready;
        },
        get slotReadyLabel() {
            return this.slotSaving ? 'Confirming…' : this.slotState.player?.slot_ready ? 'Ready ✓'
                : this.slotPendingPosition !== null ? 'Retry Ready' : 'Ready to roll';
        },
        get slotStageTitle() {
            if (this.slotCompleted) return 'The winners’ circle';
            return {slots_ready: 'Ready for the next roll?', slots_countdown: 'Everyone is ready', slots_spinning: 'Let them roll!', slots_result: this.slotCurrentWinners.length > 1 ? 'We have winners!' : this.slotCurrentWinners.length ? 'We have a winner!' : 'No match this time'}[this.slotPhase] || 'Astoria Slots';
        },
        get slotStageHelp() {
            if (this.slotCompleted) return `All ${this.slotTotalRolls} rolls are complete. Congratulations to our winners.`;
            if (this.slotPhase === 'slots_ready') return this.slotOwnId ? 'Press Ready. The countdown begins when everyone is ready.' : 'Waiting for every player to press Ready on their own screen.';
            if (this.slotPhase === 'slots_countdown') return this.slotSeconds ? 'The whole party spins together.' : 'Starting the reels…';
            if (this.slotPhase === 'slots_spinning') return this.slotSeconds ? 'Three matching logos make a winning combination.' : 'Confirming the result…';
            return this.slotPosition >= this.slotTotalRolls ? 'The final results are coming up.' : 'Get ready for the next roll.';
        },
        symbol(key) {
            return this.slotData.symbolcatalog?.[key] || DEFAULT_SYMBOLS[key] || DEFAULT_SYMBOLS.black;
        },
        prizeClass(key) { return SYMBOLS.includes(key) ? `slot-prize--${key}` : ''; },
        spinSymbols(reel) {
            // Decorative strips only. The server never sends an outcome before reveal.
            const offset = Number(reel) % SYMBOLS.length;
            const cycle = SYMBOLS.map((_, i) => SYMBOLS[(i + offset) % SYMBOLS.length]);
            return [...cycle, ...cycle, cycle[0]];
        },
        hasReels(person) {
            return (this.slotPhase === 'slots_result' || this.slotCompleted)
                && Array.isArray(person.slot_reels) && person.slot_reels.length === 3;
        },
        machineLabel(person) {
            if (this.hasReels(person)) return person.slot_prize ? `${this.symbol(person.slot_prize).label} winner` : 'No match';
            if (this.slotPhase === 'slots_ready') return person.slot_ready ? 'Ready ✓' : 'Getting ready';
            if (this.slotPhase === 'slots_countdown') return 'Ready to spin';
            return 'Rolling…';
        },
        reelLabel(person) {
            return this.hasReels(person) ? person.slot_reels.map(key => this.symbol(key).label).join(', ') : 'Result pending';
        },
        symbolImageError(event, key) {
            const img = event?.target;
            if (!img) return;
            if (key !== 'black' && !img.dataset.slotFallback) {
                img.dataset.slotFallback = key;
                img.src = DEFAULT_SYMBOLS.black.image_url;
                return;
            }
            img.hidden = true;
            img.parentElement?.classList?.add('slot-symbol--text');
        },
        focusMyMachine() {
            if (!this.slotOwnId) return;
            const card = [...(this.$refs?.slotMachines?.querySelectorAll('[data-player-id]') || [])]
                .find(element => element.getAttribute('data-player-id') === this.slotOwnId);
            card?.scrollIntoView?.({block: 'center', behavior: window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ? 'auto' : 'smooth'});
        },
        async readyForSlot() {
            if (!this.slotCanReady) return;
            const position = this.slotPendingPosition ?? Number(this.slotState.current_position);
            if (position < 1 || position > this.slotTotalRolls) return;
            const sequence = ++this.slotRequestSequence;
            this.slotPendingPosition = position;
            this.slotSaving = true;
            this.slotReadyError = '';
            try {
                const response = await window.axios.post(this.slotUrls.slotsReady, {expected_position: position, is_ready: true}, {
                    headers: {Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''}, timeout: 10000,
                });
                if (!this.slotDestroyed) this.$dispatch('slots-state', response.data);
            } catch (failure) {
                if (!this.slotDestroyed && Number(this.slotState.current_position) === position
                    && this.slotPhase === 'slots_ready' && !this.slotState.player?.slot_ready) {
                    this.slotReadyError = Object.values(failure.response?.data?.errors || {}).flat()[0]
                        || failure.response?.data?.message || 'Ready is not confirmed yet. Retry or wait for reconnection.';
                }
            } finally {
                if (sequence === this.slotRequestSequence) this.slotSaving = false;
            }
        },
    }));
}
