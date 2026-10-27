export default function registerQuiz(Alpine) {
    Alpine.data('quizBoard', (initialState, urls) => ({
        state: initialState,
        urls,
        selectedOption: null,
        submitting: false,
        answerPending: false,
        submittedOption: null,
        pendingQuestionId: null,
        answerRequestSequence: 0,
        error: '',
        connectionError: '',
        copyMessage: '',
        copyMessages: {},
        editingName: false,
        nameDraft: '',
        profileSaving: false,
        profileError: '',
        readySaving: false,
        readyError: '',
        bingoDrawing: false,
        bingoPendingPosition: null,
        bingoError: '',
        bingoPulse: false,
        bingoPulseTimer: null,
        now: Date.now(),
        serverOffset: 0,
        pollTimer: null,
        clockTimer: null,
        stopped: false,
        lastServerTime: 0,
        motionPaused: false,
        motionPreference: null,
        motionListener: null,
        leaderboardRows: [],
        leaderboardReady: false,
        leaderboardKey: null,
        leaderboardFrames: [],
        roundLocalDeadline: 0,
        answerRevealKey: null,
        answerRevealLocalDeadline: 0,
        campElement: null,
        campObserver: null,
        campResizeListener: null,
        campWidth: 1040,
        campHeight: 660,
        campPanelWidth: 1040,
        campViewportWidth: 0,
        campViewportHeight: 0,
        campX: 0,
        campY: 0,
        campDragging: false,
        campPointer: null,
        campDragOrigin: null,
        campManualPan: false,
        campFocusKey: null,
        campSlotSeed: null,
        campSlots: [],

        init() {
            this.motionPreference = window.matchMedia?.('(prefers-reduced-motion: reduce)') || null;
            this.motionPaused = !!this.motionPreference?.matches;
            this.motionListener = (event) => {
                if (event.matches) {
                    this.motionPaused = true;
                    this.finishRankAnimation();
                }
            };
            this.motionPreference?.addEventListener?.('change', this.motionListener);
            this.applyState(initialState);
            this.clockTimer = setInterval(() => { this.now = Date.now(); }, 200);
            this.schedulePoll();
        },

        destroy() {
            this.stopped = true;
            clearTimeout(this.pollTimer);
            clearInterval(this.clockTimer);
            clearTimeout(this.bingoPulseTimer);
            this.motionPreference?.removeEventListener?.('change', this.motionListener);
            this.cancelRankFrames();
            this.destroyCamp();
        },

        get remainingSeconds() {
            const deadline = this.state.game_type === 'sketch' ? this.state.sketch?.deadline_at : this.state.question?.deadline_at;
            if (!deadline) return 0;
            return Math.max(0, Math.ceil((Date.parse(deadline) - (this.now + this.serverOffset)) / 1000));
        },

        get statusLabel() {
            if (this.state.phase === 'answer_reveal') return 'Answer reveal';
            if (this.state.phase === 'leaderboard') return 'Top 5';
            if (this.state.phase === 'drawing') return 'Sketch & Guess';
            if (this.state.phase === 'bingo') return 'Reverse Bingo';
            if (this.state.game_type === 'slots' && this.state.status === 'in_progress') {
                return {slots_ready: 'Ready to roll', slots_countdown: 'Starting soon', slots_spinning: 'Rolling', slots_result: 'Roll results'}[this.state.phase] || 'Astoria Slots';
            }
            return {waiting: 'Lobby', in_progress: 'Live quiz', completed: 'Finished', cancelled: 'Cancelled'}[this.state.status] || '';
        },

        get isQuestionPhase() {
            return !['bingo', 'slots'].includes(this.state.game_type) && this.state.status === 'in_progress' && (!this.state.phase || this.state.phase === 'question');
        },

        get bingoActivePlayers() {
            const active = (this.state.players || []).filter(person => !person.eliminated);
            const ownId = this.state.player?.public_id;
            // Keep your cards easy to find on a small screen; spectators retain join order.
            return ownId ? active.sort((a, b) => Number(b.public_id === ownId) - Number(a.public_id === ownId)) : active;
        },

        get bingoEliminatedPlayers() {
            return (this.state.players || []).filter(person => person.eliminated);
        },

        get canRevealBingo() {
            return !!this.urls.bingoDraw && this.state.game_type === 'bingo'
                && this.state.status === 'in_progress' && !this.bingoDrawing && !this.stopped;
        },

        get bingoWinnerNumber() {
            if (this.state.status !== 'completed') return null;
            const winner = (this.state.results || []).find(result => result.is_winner || result.rank === 1);
            const person = (this.state.players || []).find(entry => entry.public_id === winner?.public_id);
            return (person?.bingo_cards || []).find(card => card.active)?.number ?? null;
        },

        async revealBingo() {
            if (!this.canRevealBingo) return;
            // A timeout may have committed. Retry the same position until polling
            // confirms it, never silently move to the next draw after a failed reply.
            const position = this.bingoPendingPosition ?? Number(this.state.current_position || 0);
            this.bingoPendingPosition = position;
            this.bingoDrawing = true;
            this.bingoError = '';
            try {
                const response = await window.axios.post(this.urls.bingoDraw, {expected_position: position}, {
                    headers: this.requestHeaders(), timeout: 10000,
                });
                if (!this.stopped) this.applyState(response.data);
            } catch (failure) {
                if (!this.stopped && this.state.status === 'in_progress'
                    && Number(this.state.current_position) <= position) {
                    this.bingoError = Object.values(failure.response?.data?.errors || {}).flat()[0]
                        || failure.response?.data?.message
                        || 'The reveal is not confirmed yet. Retry this draw or wait for reconnection.';
                }
            } finally {
                this.bingoDrawing = false;
            }
        },

        get answerLocked() {
            return !!this.state.answered || this.submitting || this.answerPending;
        },

        get canSubmit() {
            return this.isQuestionPhase && !this.answerLocked && this.remainingSeconds > 0
                && Object.prototype.hasOwnProperty.call(this.state.question?.options || {}, this.selectedOption);
        },

        get canRetryAnswer() {
            return this.isQuestionPhase && this.answerPending && !this.submitting && !this.state.answered
                && this.remainingSeconds > 0 && this.pendingQuestionId === this.state.question?.id;
        },

        get answerRevealSeconds() {
            const reveal = this.state.answer_reveal;
            if (this.state.phase !== 'answer_reveal' || !reveal || !this.answerRevealLocalDeadline) return 0;
            const serverRemaining = Date.parse(reveal.ends_at) - (this.now + this.serverOffset);
            if (!Number.isFinite(serverRemaining)) return 0;
            return Math.min(5, Math.max(0, Math.ceil(Math.min(serverRemaining, this.answerRevealLocalDeadline - this.now) / 1000)));
        },

        get showAnswerReveal() {
            return ['quiz', 'sketch'].includes(this.state.game_type) && this.state.status === 'in_progress'
                && this.state.phase === 'answer_reveal' && this.answerRevealSeconds > 0;
        },

        get roundSeconds() {
            const round = this.state.round_leaderboard;
            if (this.state.phase !== 'leaderboard' || !round || !this.roundLocalDeadline) return 0;
            const serverRemaining = Date.parse(round.ends_at) - (this.now + this.serverOffset);
            if (!Number.isFinite(serverRemaining)) return 0;
            return Math.min(7, Math.max(0, Math.ceil(Math.min(serverRemaining, this.roundLocalDeadline - this.now) / 1000)));
        },

        get showRoundLeaderboard() {
            return this.state.status === 'in_progress' && this.state.phase === 'leaderboard' && this.roundSeconds > 0;
        },

        applyState(payload) {
            const next = payload.state || payload;
            const timestamp = Date.parse(next.server_now);
            // Responses from polling and answer submission can arrive out of order.
            if (Number.isFinite(timestamp) && timestamp < this.lastServerTime) return;
            // A late submission/poll cannot reopen a closed round or replay its reveal.
            const phaseOrder = {question: 0, drawing: 0, answer_reveal: 1, leaderboard: 2,
                slots_ready: 0, slots_countdown: 1, slots_spinning: 2, slots_result: 3};
            if (Number(next.current_position) === Number(this.state.current_position)
                && this.state.status === 'in_progress' && next.status === 'in_progress'
                && (phaseOrder[next.phase || 'question'] ?? -1) < (phaseOrder[this.state.phase || 'question'] ?? -1)) return;
            const oldQuestion = this.state.question?.id;
            const campChanged = this.state.status !== next.status
                || this.state.players?.length !== next.players?.length
                || this.state.player?.public_id !== next.player?.public_id;
            if (oldQuestion && oldQuestion === next.question?.id && this.state.answered) {
                next.answered = true;
                next.selected_option = this.state.selected_option ?? this.submittedOption ?? next.selected_option;
            }
            if (Number(next.current_position) < Number(this.state.current_position)) return;
            if (['completed', 'cancelled'].includes(this.state.status) && next.status !== this.state.status) return;
            if (next.game_type === 'bingo') {
                if (Number(next.current_position) > Number(this.state.current_position)) {
                    clearTimeout(this.bingoPulseTimer);
                    this.bingoPulse = !this.motionPaused;
                    this.bingoPulseTimer = setTimeout(() => { this.bingoPulse = false; }, 450);
                }
                if ((this.bingoPendingPosition !== null && Number(next.current_position) > this.bingoPendingPosition)
                    || ['completed', 'cancelled'].includes(next.status)) {
                    this.bingoPendingPosition = null;
                    this.bingoError = '';
                }
            }
            if (oldQuestion && oldQuestion === next.question?.id) {
                const answered = new Set((this.state.players || []).filter(person => person.answered).map(person => person.public_id));
                next.players = (next.players || []).map(person => answered.has(person.public_id) ? {...person, answered: true} : person);
            }
            if (Number.isFinite(timestamp)) {
                this.lastServerTime = timestamp;
                this.serverOffset = timestamp - Date.now();
            }
            this.state = next;
            this.now = Date.now();
            this.syncAnswerReveal(next, timestamp);
            this.syncLeaderboard(next, timestamp);
            if (oldQuestion !== next.question?.id) {
                this.answerRequestSequence++;
                this.submitting = false;
                this.selectedOption = null;
                this.answerPending = false;
                this.submittedOption = null;
                this.pendingQuestionId = null;
                this.error = '';
            }
            if (next.answered && next.question) {
                this.selectedOption = next.selected_option ?? this.submittedOption ?? this.selectedOption;
                this.submittedOption = this.selectedOption;
                this.answerPending = false;
                this.pendingQuestionId = null;
                this.submitting = false;
                this.error = '';
            }
            if (next.status !== 'waiting') {
                this.editingName = false;
                this.profileError = '';
                this.readyError = '';
            }
            if (!this.editingName) this.nameDraft = next.player?.name || '';
            this.connectionError = '';
            if (next.status !== 'waiting') this.endCampDrag();
            if (campChanged && next.status === 'waiting' && this.campElement) {
                if (typeof this.$nextTick === 'function') this.$nextTick(() => this.resizeCamp());
                else this.resizeCamp();
            }
        },

        schedulePoll() {
            if (this.stopped || ['completed', 'cancelled'].includes(this.state.status)) return;
            clearTimeout(this.pollTimer);
            this.pollTimer = setTimeout(() => this.poll(), 1000);
        },

        async poll() {
            if (this.stopped) return;
            try {
                const response = await window.axios.get(this.urls.state, {
                    headers: {Accept: 'application/json'}, timeout: 10000,
                });
                if (!this.stopped) this.applyState(response.data);
            } catch (failure) {
                const status = failure.response?.status;
                this.connectionError = [401, 403, 419].includes(status)
                    ? 'Your session has expired. Refresh this page to reconnect.'
                    : 'Connection interrupted. Reconnecting automatically…';
            } finally {
                this.schedulePoll();
            }
        },

        selectOption(option) {
            if (!this.isQuestionPhase || this.answerLocked || this.remainingSeconds === 0
                || !Object.prototype.hasOwnProperty.call(this.state.question?.options || {}, option)) return;
            this.selectedOption = option;
            this.error = '';
        },

        async submitAnswer() {
            if (!this.canSubmit) return;
            this.submittedOption = this.selectedOption;
            this.pendingQuestionId = this.state.question.id;
            this.answerPending = true;
            return this.sendSubmittedAnswer();
        },

        async retryAnswer() {
            if (!this.canRetryAnswer) return;
            return this.sendSubmittedAnswer();
        },

        async sendSubmittedAnswer() {
            if (!this.canRetryAnswer) return;
            const questionId = this.state.question.id;
            const option = this.submittedOption;
            const requestSequence = ++this.answerRequestSequence;
            this.submitting = true;
            this.error = '';
            try {
                const response = await window.axios.post(this.urls.answer, {
                    question_id: questionId, option,
                }, {
                    headers: this.requestHeaders(), timeout: 10000,
                });
                if (!this.stopped) this.applyState(response.data);
            } catch (failure) {
                const messages = Object.values(failure.response?.data?.errors || {}).flat();
                if (this.state.question?.id === questionId && !this.state.answered) {
                    this.error = messages[0] || failure.response?.data?.message || 'Could not confirm your submission. Retry the same answer, or wait for reconnection.';
                }
                // A failed response can still have committed. Keep the submitted
                // choice locked; retries send that identical option until confirmed.
            } finally {
                if (this.answerRequestSequence === requestSequence) this.submitting = false;
            }
        },

        requestHeaders() {
            return {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            };
        },

        beginNameEdit() {
            if (this.state.status !== 'waiting' || !this.state.player || this.profileSaving) return;
            this.nameDraft = this.state.player.name;
            this.profileError = '';
            this.editingName = true;
        },

        cancelNameEdit() {
            if (this.profileSaving) return;
            this.editingName = false;
            this.nameDraft = this.state.player?.name || '';
            this.profileError = '';
        },

        async saveName() {
            if (!this.editingName || this.profileSaving || this.state.status !== 'waiting' || !this.state.player) return;
            const name = this.nameDraft.trim();
            if (!name || Array.from(name).length > 120) {
                this.profileError = 'Enter a name between 1 and 120 characters.';
                return;
            }
            this.profileSaving = true;
            this.profileError = '';
            try {
                const response = await window.axios.patch(this.urls.profile, {display_name: name}, {
                    headers: this.requestHeaders(), timeout: 10000,
                });
                if (!this.stopped) {
                    this.applyState(response.data);
                    this.editingName = false;
                    this.nameDraft = this.state.player?.name || name;
                }
            } catch (failure) {
                if (this.state.status === 'waiting') {
                    this.profileError = Object.values(failure.response?.data?.errors || {}).flat()[0]
                        || 'Could not save your name. Please try again.';
                }
            } finally {
                this.profileSaving = false;
            }
        },

        async toggleReady() {
            if (this.readySaving || this.state.status !== 'waiting' || !this.state.player) return;
            const ready = !this.state.player.is_ready;
            this.readySaving = true;
            this.readyError = '';
            try {
                const response = await window.axios.post(this.urls.ready, {is_ready: ready}, {
                    headers: this.requestHeaders(), timeout: 10000,
                });
                if (!this.stopped) this.applyState(response.data);
            } catch (failure) {
                if (this.state.status === 'waiting') {
                    this.readyError = Object.values(failure.response?.data?.errors || {}).flat()[0]
                        || 'Could not update your Ready status. Please try again.';
                }
            } finally {
                this.readySaving = false;
            }
        },

        percent(value) {
            const number = Number(value);
            return Number.isFinite(number) ? Math.min(100, Math.max(0, number)) : 0;
        },

        toggleMotion() {
            this.motionPaused = !this.motionPaused;
            if (this.motionPaused) this.finishRankAnimation();
        },

        syncAnswerReveal(next, serverTimestamp) {
            const reveal = next.status === 'in_progress' && next.phase === 'answer_reveal' ? next.answer_reveal : null;
            if (!reveal) {
                this.answerRevealKey = null;
                this.answerRevealLocalDeadline = 0;
                return;
            }
            const key = next.game_type + ':' + String(reveal.question_id);
            const serverNow = Number.isFinite(serverTimestamp) ? serverTimestamp : this.now + this.serverOffset;
            const remaining = Date.parse(reveal.ends_at) - serverNow;
            const duration = Number.isFinite(remaining) ? Math.min(5000, Math.max(0, remaining)) : 0;
            const localDeadline = this.now + duration;
            if (this.answerRevealKey === key) {
                // Repeat polls and delayed responses cannot restart the five seconds.
                this.answerRevealLocalDeadline = Math.min(this.answerRevealLocalDeadline, localDeadline);
                return;
            }
            this.answerRevealKey = key;
            this.answerRevealLocalDeadline = localDeadline;
        },

        syncLeaderboard(next, serverTimestamp) {
            const round = next.status === 'in_progress' && next.phase === 'leaderboard' ? next.round_leaderboard : null;
            if (!round) {
                this.cancelRankFrames();
                this.leaderboardKey = null;
                this.leaderboardRows = [];
                this.leaderboardReady = false;
                this.roundLocalDeadline = 0;
                return;
            }

            const key = String(round.question_id);
            const serverNow = Number.isFinite(serverTimestamp) ? serverTimestamp : this.now + this.serverOffset;
            const remaining = Date.parse(round.ends_at) - serverNow;
            const duration = Number.isFinite(remaining) ? Math.min(7000, Math.max(0, remaining)) : 0;
            const localDeadline = this.now + duration;
            if (this.leaderboardKey === key) {
                // Polling never restarts the seven seconds or repeats the rank animation.
                this.roundLocalDeadline = Math.min(this.roundLocalDeadline, localDeadline);
                return;
            }

            this.cancelRankFrames();
            this.leaderboardKey = key;
            this.roundLocalDeadline = localDeadline;
            this.leaderboardRows = (Array.isArray(round.entries) ? round.entries : []).slice(0, 5);
            this.leaderboardReady = this.motionPaused || duration < 1200 || typeof window.requestAnimationFrame !== 'function';
            if (this.leaderboardReady) return;

            const start = () => {
                if (this.stopped || this.leaderboardKey !== key || this.motionPaused) return;
                const frame = window.requestAnimationFrame(() => {
                    if (this.stopped || this.leaderboardKey !== key) return;
                    const nextFrame = window.requestAnimationFrame(() => {
                        if (!this.stopped && this.leaderboardKey === key) this.leaderboardReady = true;
                        this.leaderboardFrames = [];
                    });
                    this.leaderboardFrames.push(nextFrame);
                });
                this.leaderboardFrames.push(frame);
            };
            // Alpine first inserts the five keyed rows at their former positions.
            if (typeof this.$nextTick === 'function') this.$nextTick(start);
            else start();
        },

        cancelRankFrames() {
            for (const frame of this.leaderboardFrames) window.cancelAnimationFrame?.(frame);
            this.leaderboardFrames = [];
        },

        finishRankAnimation() {
            this.cancelRankFrames();
            this.leaderboardReady = true;
        },

        rankStyle(entry) {
            const clampSlot = (value, fallback) => {
                const slot = Number(value) - 1;
                return Number.isFinite(slot) ? Math.max(0, Math.min(5, slot)) : fallback;
            };
            const target = Math.min(4, clampSlot(entry.position, 0));
            const from = clampSlot(entry.previous_position, target);
            const slot = this.leaderboardReady || this.motionPaused ? target : from;
            return `--rank-from:${from};--rank-to:${target};--rank-slot:${slot};`;
        },

        campHash(value) {
            let seed = 2166136261;
            for (const letter of String(value)) {
                seed = Math.imul(seed ^ letter.charCodeAt(0), 16777619) >>> 0;
            }
            return seed;
        },

        campStyle(publicId, index = 0) {
            // Stable, gently stronger motion; no reshuffling on live polls.
            const seed = this.campHash(publicId || index);
            const duration = 4.4 + (seed % 33) / 10;
            const delay = -((seed >>> 4) % 90) / 10;
            const x = 4 + (seed >>> 8) % 6;
            const y = 6 + (seed >>> 12) % 7;
            const rotation = ((seed >>> 16) % 5 - 2) * 0.45;
            return `--float-duration:${duration}s;--float-delay:${delay}s;--float-x:${x}px;--float-y:${y}px;--float-rotate:${rotation}deg;`;
        },

        get campWorldStyle() {
            return `width:${this.campWidth}px;height:${this.campHeight}px;--camp-panel-width:${this.campPanelWidth}px;transform:translate3d(${this.campX}px,${this.campY}px,0);`;
        },

        campSlotOrder() {
            // Every viewer derives the same layout from the first participant.
            // Shuffle within spacious groups so new arrivals keep existing spots.
            const key = this.state.players?.[0]?.public_id || 'omiquiz';
            if (key === this.campSlotSeed && this.campSlots.length === 40) return this.campSlots;
            let seed = this.campHash(key);
            const nearFire = [18, 21, 25, 30, 34, 37, 16, 23];
            const remaining = Array.from({length: 40}, (_, slot) => slot).filter(slot => !nearFire.includes(slot));
            const shuffle = (slots) => {
                for (let i = slots.length - 1; i > 0; i--) {
                    seed = (Math.imul(seed, 1664525) + 1013904223) >>> 0;
                    const j = seed % (i + 1);
                    [slots[i], slots[j]] = [slots[j], slots[i]];
                }
                return slots;
            };
            this.campSlotSeed = key;
            this.campSlots = [...shuffle(nearFire), ...shuffle(remaining)];
            return this.campSlots;
        },

        campPosition(publicId, index = 0) {
            const ordinal = Math.max(0, Math.floor(Number(index) || 0));
            const slot = this.campSlotOrder()[ordinal % 40];
            const seed = this.campHash(publicId || ordinal);
            const panel = Math.floor(ordinal / 40);
            return {
                x: panel * this.campPanelWidth + ((slot % 8) + .5) * this.campPanelWidth / 8 + (seed % 11 - 5),
                y: 170 + Math.floor(slot / 8) * (this.campHeight - 252) / 4 + ((seed >>> 8) % 7 - 3),
            };
        },

        campPositionStyle(publicId, index = 0) {
            const position = this.campPosition(publicId, index);
            return `left:${position.x}px;top:${position.y}px;`;
        },

        initCamp(element) {
            if (!element || this.stopped) return;
            if (this.campElement === element) { this.resizeCamp(); return; }
            this.destroyCamp();
            this.campElement = element;
            this.campResizeListener = () => this.resizeCamp();
            if (typeof window.ResizeObserver === 'function') {
                this.campObserver = new window.ResizeObserver(this.campResizeListener);
                this.campObserver.observe(element);
            } else window.addEventListener?.('resize', this.campResizeListener);
            this.resizeCamp();
        },

        resizeCamp() {
            if (!this.campElement || this.stopped || this.state.status !== 'waiting') return;
            const width = this.campElement.clientWidth;
            const height = this.campElement.clientHeight;
            if (!width || !height) return;
            const centerX = ((this.campViewportWidth || width) / 2 - this.campX) / this.campPanelWidth;
            const centerY = ((this.campViewportHeight || height) / 2 - this.campY) / this.campHeight;
            this.campViewportWidth = width;
            this.campViewportHeight = height;
            this.campPanelWidth = Math.max(1040, width);
            this.campWidth = this.campPanelWidth * Math.max(1, Math.ceil((this.state.players?.length || 0) / 40));
            this.campHeight = Math.max(660, height);
            const focusKey = this.state.player?.public_id || 'spectator';
            if (focusKey !== this.campFocusKey || !this.campManualPan) {
                this.campFocusKey = focusKey;
                if (this.state.player) this.focusCampPlayer();
                else this.centerCamp();
            } else {
                this.setCampPosition(width / 2 - centerX * this.campPanelWidth, height / 2 - centerY * this.campHeight);
            }
        },

        setCampPosition(x, y) {
            if (!Number.isFinite(x) || !Number.isFinite(y)) return;
            this.campX = Math.max(Math.min(0, this.campViewportWidth - this.campWidth), Math.min(0, x));
            this.campY = Math.max(Math.min(0, this.campViewportHeight - this.campHeight), Math.min(0, y));
        },

        focusCampPlayer() {
            const index = (this.state.players || []).findIndex(person => person.public_id === this.state.player?.public_id);
            if (index < 0) { this.centerCamp(); return; }
            const point = this.campPosition(this.state.player.public_id, index);
            this.campManualPan = false;
            this.setCampPosition(this.campViewportWidth / 2 - point.x, this.campViewportHeight / 2 - point.y);
        },

        centerCamp() {
            this.campManualPan = false;
            // Spectators start at the first campfire; later groups are a drag away.
            this.setCampPosition((this.campViewportWidth - this.campPanelWidth) / 2, this.campViewportHeight / 2 - this.campHeight * .72);
        },

        panCamp(dx, dy) {
            if (!this.campElement || this.state.status !== 'waiting') return;
            this.campManualPan = true;
            this.setCampPosition(this.campX + dx, this.campY + dy);
        },

        onCampKey(event) {
            if (event.target !== this.campElement || event.altKey || event.ctrlKey || event.metaKey) return;
            const offsets = {ArrowLeft: [100, 0], ArrowRight: [-100, 0], ArrowUp: [0, 100], ArrowDown: [0, -100]};
            if (!offsets[event.key]) return;
            event.preventDefault();
            this.panCamp(...offsets[event.key]);
        },

        startCampDrag(event) {
            if (!this.campElement || this.state.status !== 'waiting' || event.isPrimary === false
                || (event.pointerType === 'mouse' && event.button !== 0)
                || event.target?.closest?.('button, a, input, select, textarea')) return;
            this.campPointer = event.pointerId;
            this.campDragOrigin = {x: event.clientX, y: event.clientY, campX: this.campX, campY: this.campY};
            this.campDragging = true;
            try { this.campElement.setPointerCapture?.(event.pointerId); } catch { /* Pointer may already have ended. */ }
        },

        moveCampDrag(event) {
            if (!this.campDragging || event.pointerId !== this.campPointer || !this.campDragOrigin) return;
            const dx = event.clientX - this.campDragOrigin.x;
            const dy = event.clientY - this.campDragOrigin.y;
            if (Math.abs(dx) + Math.abs(dy) < 4 && !this.campManualPan) return;
            this.campManualPan = true;
            this.setCampPosition(this.campDragOrigin.campX + dx, this.campDragOrigin.campY + dy);
        },

        endCampDrag(event) {
            if (event && event.pointerId !== this.campPointer) return;
            const pointer = this.campPointer;
            this.campPointer = null;
            this.campDragOrigin = null;
            this.campDragging = false;
            try {
                if (pointer !== null && this.campElement?.hasPointerCapture?.(pointer)) this.campElement.releasePointerCapture(pointer);
            } catch { /* Browser cancelled capture during a view change. */ }
        },

        destroyCamp() {
            this.endCampDrag();
            this.campObserver?.disconnect();
            if (this.campResizeListener) window.removeEventListener?.('resize', this.campResizeListener);
            this.campObserver = null;
            this.campResizeListener = null;
            this.campElement = null;
        },

        answeredCurrent(person) {
            if (this.state.game_type === 'sketch') return this.state.phase === 'drawing' && !!this.state.sketch?.turn_id && person?.answered === true;
            return this.isQuestionPhase && !!this.state.question?.id && person?.answered === true;
        },

        checkKey(person) {
            if (this.state.game_type === 'sketch') return `sketch:${this.state.sketch?.turn_id || 'none'}:${person.public_id}`;
            return `${this.state.question?.id || 'none'}:${person.public_id}`;
        },

        async copyLink(inputId = 'join-link') {
            const input = document.getElementById(inputId);
            const link = input?.value;
            const announce = (message) => {
                this.copyMessages = {...this.copyMessages, [inputId]: message};
                this.copyMessage = message;
            };
            if (!link) {
                announce('Link unavailable. Refresh this page and try again.');
                return;
            }
            const previousFocus = document.activeElement;
            const selectLink = () => {
                input.focus?.({preventScroll: true});
                input.select();
                input.setSelectionRange?.(0, link.length);
            };
            const legacyCopy = () => {
                try {
                    selectLink();
                    if (document.execCommand?.('copy') === true) {
                        previousFocus?.focus?.({preventScroll: true});
                        return true;
                    }
                } catch { /* Leave the visible input available for manual copying. */ }
                return false;
            };
            // Plain HTTP/LAN pages often have no Clipboard API. Copy during the
            // button gesture instead of only selecting the input as before.
            const clipboard = globalThis.navigator?.clipboard;
            if (window.isSecureContext === false || typeof clipboard?.writeText !== 'function') {
                if (legacyCopy()) { announce('Link copied'); return; }
            } else {
                try {
                    await clipboard.writeText(link);
                    announce('Link copied');
                    return;
                } catch {
                    if (legacyCopy()) { announce('Link copied'); return; }
                }
            }
            selectLink();
            announce('Automatic copy is blocked. Press Ctrl+C / ⌘C, or touch and hold the selected link to copy.');
        },
    }));
}
