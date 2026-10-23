// Logical coordinates keep every device on the same 1000 x 625 drawing surface.
const WIDTH = 1000;
const HEIGHT = 625;
const MAX_POINTS = 100;
const MAX_UNSENT = 160;
const lastItem = items => items?.length ? items[items.length - 1] : undefined;

export default function registerSketch(Alpine) {
    Alpine.data('sketchBoard', (urls = {}) => ({
        sketchUrls: urls,
        sketch: {},
        sketchPhase: 'waiting',
        sketchStatus: 'waiting',
        isMember: false,
        now: Date.now(),
        serverOffset: 0,
        colors: ['#172033', '#e11d48', '#f97316', '#eab308', '#16a34a', '#0284c7', '#7c3aed', '#a16207'],
        color: '#172033',
        brushWidth: 6,
        erasing: false,
        guessText: '',
        guessing: false,
        hintSharing: false,
        hintError: '',
        guessMessage: '',
        sketchError: '',
        drawingError: '',
        drawingReady: false,
        drawingBlocked: false,
        canvas: null,
        context: null,
        confirmedCanvas: null,
        confirmedContext: null,
        paintedCursor: 0,
        paintedUndoCursor: 0,
        unreadGuesses: false,
        undoCandidateId: null,
        undoneStrokeIds: new Set(),
        observer: null,
        listeners: [],
        activeStroke: null,
        confirmed: [],
        unsent: [],
        batches: [],
        cursor: 0,
        generation: 0,
        sending: false,
        reading: false,
        retryAttempt: 0,
        readTimer: null,
        sendTimer: null,
        chunkTimer: null,
        clockTimer: null,
        paintFrame: null,
        destroyed: false,

        init() {
            this.clockTimer = setInterval(() => {
                this.now = Date.now();
                if (this.activeStroke && this.remainingSeconds === 0) this.finishStroke();
                this.updateCanvasInput();
            }, 200);
        },

        get remainingSeconds() {
            const deadline = Date.parse(this.sketch?.deadline_at || '');
            return Number.isFinite(deadline) ? Math.max(0, Math.ceil((deadline - this.now - this.serverOffset) / 1000)) : 0;
        },

        get canDraw() {
            return this.sketchStatus === 'in_progress' && this.sketchPhase === 'drawing'
                && !!this.sketch?.is_drawer && this.sketch?.can_draw !== false
                && this.remainingSeconds > 0 && this.drawingReady && !this.drawingBlocked
                && this.unsent.length + this.batches.reduce((sum, batch) => sum + batch.events.length, 0) < MAX_UNSENT;
        },

        get canGuess() {
            return this.isMember && this.sketchStatus === 'in_progress' && this.sketchPhase === 'drawing'
                && !this.sketch?.is_drawer && !this.sketch?.guessed && this.remainingSeconds > 0;
        },

        get canRevealHint() {
            return this.isMember && this.sketchStatus === 'in_progress' && this.sketchPhase === 'drawing'
                && !!this.sketch?.is_drawer && !!this.sketch?.hint_available && !this.sketch?.hint_revealed
                && this.sketch?.can_reveal_hint === true && this.remainingSeconds > 0
                && !!this.sketchUrls.hint && !this.hintSharing;
        },

        get undoStrokeId() { return this.undoCandidateId; },

        refreshUndoState() {
            const timeline = this.drawingTimeline();
            this.undoneStrokeIds = new Set(timeline.filter(event => event.type === 'undo').map(event => event.stroke_id));
            const stack = [];
            const seen = new Set();
            for (const event of timeline) {
                if (event.type === 'clear') { stack.length = 0; seen.clear(); }
                else if (event.type === 'undo') {
                    const index = stack.indexOf(event.stroke_id);
                    if (index !== -1) stack.splice(index, 1);
                } else if (event.type === 'stroke' && event.stroke_id && !seen.has(event.stroke_id) && !this.undoneStrokeIds.has(event.stroke_id)) {
                    seen.add(event.stroke_id);
                    stack.push(event.stroke_id);
                }
            }
            this.undoCandidateId = lastItem(stack) || null;
        },

        get canUndo() { return this.canDraw && !!this.undoStrokeId; },

        get drawingStatus() {
            if (!this.sketch?.turn_id) return '';
            if (this.drawingError) return this.drawingError;
            if (!this.drawingReady) return 'Loading the sketch…';
            if (this.unsent.length || this.batches.length || this.sending) return 'Syncing your sketch…';
            return this.sketch.is_drawer ? 'Sketch synced' : 'Live sketch';
        },

        syncSketch(state) {
            if (this.destroyed) return;
            const next = state?.game_type === 'sketch' ? state.sketch : null;
            const changed = (next?.turn_id ?? null) !== (this.sketch?.turn_id ?? null);
            const previousGuess = lastItem(this.sketch?.guesses)?.id;
            const revealedHint = !changed && this.sketch?.hint_revealed ? this.sketch.hint : null;
            const feed = this.$refs?.guessFeed;
            const followGuesses = changed || !feed || feed.scrollHeight - feed.scrollTop - feed.clientHeight < 48;
            const timestamp = Date.parse(state?.server_now || '');
            if (Number.isFinite(timestamp)) this.serverOffset = timestamp - Date.now();
            this.now = Date.now();
            this.sketchPhase = state?.phase || 'waiting';
            this.sketchStatus = state?.status || 'waiting';
            this.isMember = !!state?.player;
            if (changed) {
                this.releaseStroke(false);
                this.generation++;
                clearTimeout(this.readTimer);
                clearTimeout(this.sendTimer);
                this.confirmed = [];
                this.unsent = [];
                this.batches = [];
                this.cursor = 0;
                this.resetConfirmedLayer();
                this.sending = false;
                this.reading = false;
                this.retryAttempt = 0;
                this.drawingReady = false;
                this.drawingBlocked = false;
                this.drawingError = '';
                this.sketchError = '';
                this.guessMessage = '';
                this.guessText = '';
                this.guessing = false;
                this.hintSharing = false;
                this.hintError = '';
                this.unreadGuesses = false;
                this.undoCandidateId = null;
                this.undoneStrokeIds = new Set();
            }
            // A delayed poll must not hide a hint that this turn already revealed.
            this.sketch = next ? {...next, ...(revealedHint ? {hint: revealedHint, hint_revealed: true, can_reveal_hint: false} : {})} : {};
            if (this.sketch.hint_revealed) this.hintError = '';
            if (changed || previousGuess !== lastItem(this.sketch.guesses)?.id) {
                if (followGuesses) this.scrollGuesses();
                else this.unreadGuesses = true;
            }
            if (this.sketchPhase !== 'drawing' && this.activeStroke) this.finishStroke();
            this.updateCanvasInput();
            if (changed) {
                this.requestPaint();
                if (next?.turn_id) this.scheduleRead(0);
            }
            // A waiting component can be hidden when initially mounted.
            if (this.canvas && this.sketchPhase === 'drawing') {
                if (typeof this.$nextTick === 'function') this.$nextTick(() => this.resizeCanvas());
                else this.resizeCanvas();
            }
        },

        mountCanvas(element) {
            if (!element || this.destroyed || this.canvas === element) return;
            this.unmountCanvas();
            this.canvas = element;
            this.context = element.getContext('2d', {alpha: false});
            if (!this.context) {
                this.drawingBlocked = true;
                this.drawingError = 'This browser could not open the drawing canvas. Refresh to try again.';
                return;
            }
            element.style.width = '100%';
            element.style.display = 'block';
            element.style.userSelect = 'none';
            element.style.webkitUserSelect = 'none';
            element.style.webkitTouchCallout = 'none';
            const on = (target, type, handler, options = false) => {
                target.addEventListener(type, handler, options);
                this.listeners.push({target, type, handler, options});
            };
            if (typeof window.PointerEvent === 'function') {
                on(element, 'pointerdown', event => this.pointerDown(event), {passive: false});
                on(element, 'pointermove', event => this.pointerMove(event), {passive: false});
                on(window, 'pointerup', event => this.pointerEnd(event), {passive: false});
                on(window, 'pointercancel', event => this.pointerEnd(event), {passive: false});
                on(element, 'lostpointercapture', event => this.pointerEnd(event));
            } else {
                // Older mobile engines need explicitly non-passive Touch Events.
                on(element, 'touchstart', event => this.touchStart(event), {passive: false});
                on(element, 'touchmove', event => this.touchMove(event), {passive: false});
                on(element, 'touchend', event => this.touchEnd(event), {passive: false});
                on(element, 'touchcancel', event => this.touchEnd(event), {passive: false});
                on(element, 'mousedown', event => this.pointerDown({...this.mousePoint(event), original: event}), {passive: false});
                on(window, 'mousemove', event => this.pointerMove({...this.mousePoint(event), original: event}), {passive: false});
                on(window, 'mouseup', event => this.pointerEnd({...this.mousePoint(event), original: event}), {passive: false});
            }
            on(element, 'contextmenu', event => { if (this.sketch?.is_drawer) event.preventDefault(); });
            on(window, 'blur', () => this.finishStroke());
            on(document, 'visibilitychange', () => {
                if (document.hidden) this.finishStroke();
                else if (this.sketch?.turn_id) this.scheduleRead(0);
            });
            const resize = () => this.resizeCanvas();
            if (typeof window.ResizeObserver === 'function') {
                this.observer = new window.ResizeObserver(resize);
                this.observer.observe(element);
            } else on(window, 'resize', resize);
            this.resizeCanvas();
            this.updateCanvasInput();
        },

        updateCanvasInput() {
            if (!this.canvas) return;
            this.canvas.style.touchAction = this.canDraw ? 'none' : 'pan-y pinch-zoom';
            this.canvas.style.cursor = this.canDraw ? 'crosshair' : 'default';
        },

        resizeCanvas() {
            if (!this.canvas || !this.context || this.destroyed) return;
            const rect = this.canvas.getBoundingClientRect();
            if (!(rect.width > 0)) return;
            const ratio = Math.min(2, Math.max(1, Number(window.devicePixelRatio) || 1));
            const width = Math.round(rect.width * ratio);
            const height = Math.round(rect.width * HEIGHT / WIDTH * ratio);
            this.canvas.style.height = `${rect.width * HEIGHT / WIDTH}px`;
            if (this.canvas.width === width && this.canvas.height === height) return;
            this.canvas.width = width;
            this.canvas.height = height;
            this.requestPaint();
        },

        point(event) {
            const rect = this.canvas?.getBoundingClientRect();
            if (!rect?.width || !rect?.height || !Number.isFinite(event.clientX) || !Number.isFinite(event.clientY)) return null;
            return {
                x: Math.round(Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width)) * 100000) / 100000,
                y: Math.round(Math.max(0, Math.min(1, (event.clientY - rect.top) / rect.height)) * 100000) / 100000,
            };
        },

        preventInput(event) {
            const original = event.original || event;
            if (original.cancelable !== false) original.preventDefault?.();
        },

        pointerDown(event) {
            if (!this.canDraw || !this.context || this.activeStroke || event.isPrimary === false
                || (event.pointerType === 'mouse' && event.button !== 0)) return;
            const point = this.point(event);
            if (!point) return;
            this.preventInput(event);
            this.activeStroke = {
                pointerId: event.pointerId, type: 'stroke', stroke_id: this.newRequestId(), points: [point], dirty: true,
                color: this.erasing ? '#ffffff' : this.color, width: this.erasing ? Math.max(12, this.brushWidth) : this.brushWidth,
            };
            this.undoCandidateId = this.activeStroke.stroke_id;
            try { if (typeof event.pointerId === 'number') this.canvas.setPointerCapture?.(event.pointerId); } catch { /* Window end handlers remain active. */ }
            this.drawEvent(this.activeStroke);
            this.chunkTimer = setInterval(() => this.flushStrokeChunk(), 120);
        },

        pointerMove(event) {
            if (!this.activeStroke || event.pointerId !== this.activeStroke.pointerId) return;
            if (!this.canDraw || (event.pointerType === 'mouse' && event.buttons === 0)) { this.finishStroke(); return; }
            this.preventInput(event);
            // Coalesced input is optional, including on HTTP and Safari versions.
            let samples = [];
            try { samples = event.getCoalescedEvents?.() || []; } catch { /* Fall back to the dispatched point. */ }
            if (!samples.length) samples = [event];
            for (const sample of samples) {
                const point = this.point(sample);
                if (!point || !this.activeStroke) continue;
                const previous = this.activeStroke.points[this.activeStroke.points.length - 1];
                if (previous.x === point.x && previous.y === point.y) continue;
                this.activeStroke.points.push(point);
                this.activeStroke.dirty = true;
                this.drawEvent({...this.activeStroke, points: [previous, point]});
                if (this.activeStroke.points.length >= MAX_POINTS) this.flushStrokeChunk();
            }
        },

        pointerEnd(event) {
            if (!this.activeStroke || event.pointerId !== this.activeStroke.pointerId) return;
            this.preventInput(event);
            // Cancellation retains the segment already drawn, with no invented endpoint.
            if (!['pointercancel', 'touchcancel', 'lostpointercapture'].includes(event.type) && this.canDraw) {
                this.pointerMove({pointerId: event.pointerId, pointerType: event.pointerType, buttons: 1,
                    clientX: event.clientX, clientY: event.clientY, original: event.original || event});
            }
            this.finishStroke();
        },

        mousePoint(event) {
            return {pointerId: 'mouse', pointerType: 'mouse', isPrimary: true, button: event.button,
                buttons: event.buttons, clientX: event.clientX, clientY: event.clientY, type: event.type};
        },

        touchStart(event) {
            if (!this.canDraw || this.activeStroke || event.touches?.length !== 1) return;
            const touch = event.changedTouches?.[0];
            if (touch) this.pointerDown({pointerId: `touch-${touch.identifier}`, pointerType: 'touch', isPrimary: true,
                clientX: touch.clientX, clientY: touch.clientY, original: event});
        },

        touchMove(event) {
            const touch = Array.from(event.changedTouches || []).find(item => `touch-${item.identifier}` === this.activeStroke?.pointerId);
            if (touch) this.pointerMove({pointerId: `touch-${touch.identifier}`, pointerType: 'touch',
                clientX: touch.clientX, clientY: touch.clientY, original: event});
        },

        touchEnd(event) {
            const touch = Array.from(event.changedTouches || []).find(item => `touch-${item.identifier}` === this.activeStroke?.pointerId);
            if (touch) this.pointerEnd({pointerId: `touch-${touch.identifier}`, pointerType: 'touch',
                clientX: touch.clientX, clientY: touch.clientY, original: event, type: event.type});
        },

        flushStrokeChunk() {
            if (!this.activeStroke?.dirty) return;
            const stroke = this.activeStroke;
            this.unsent.push({type: 'stroke', stroke_id: stroke.stroke_id, points: stroke.points.map(point => ({...point})), color: stroke.color, width: stroke.width});
            const last = stroke.points[stroke.points.length - 1];
            stroke.points = [{...last}];
            stroke.dirty = false;
            this.scheduleSend();
        },

        releaseStroke(save = true) {
            if (save) this.flushStrokeChunk();
            const pointerId = this.activeStroke?.pointerId;
            this.activeStroke = null;
            clearInterval(this.chunkTimer);
            this.chunkTimer = null;
            try {
                if (typeof pointerId === 'number' && this.canvas?.hasPointerCapture?.(pointerId)) this.canvas.releasePointerCapture(pointerId);
            } catch { /* The browser may cancel capture during rotation. */ }
        },

        finishStroke() { this.releaseStroke(true); },

        setColor(color) {
            if (!/^#[0-9a-f]{6}$/i.test(color)) return;
            this.finishStroke();
            this.color = color.toLowerCase();
            this.erasing = false;
        },

        setBrushWidth(width) {
            const value = Number(width);
            if (!Number.isFinite(value)) return;
            this.finishStroke();
            this.brushWidth = Math.max(1, Math.min(20, Math.round(value)));
        },

        toggleEraser() { this.finishStroke(); this.erasing = !this.erasing; },

        clearDrawing() {
            if (!this.canDraw) return;
            this.finishStroke();
            this.unsent.push({type: 'clear'});
            this.refreshUndoState();
            this.requestPaint();
            this.scheduleSend();
        },

        undoDrawing() {
            if (!this.canDraw) return;
            this.finishStroke();
            const strokeId = this.undoStrokeId;
            if (!strokeId) return;
            this.unsent.push({type: 'undo', stroke_id: strokeId});
            this.refreshUndoState();
            this.requestPaint();
            this.scheduleSend();
        },

        drawingTimeline() {
            return [...this.confirmed, ...this.batches.flatMap(batch => batch.events), ...this.unsent,
                ...(this.activeStroke ? [this.activeStroke] : [])];
        },

        visibleDrawingEvents(events) {
            const undone = new Set(events.filter(event => event.type === 'undo').map(event => event.stroke_id));
            return events.filter(event => event.type !== 'undo'
                && !(event.type === 'stroke' && event.stroke_id && undone.has(event.stroke_id)));
        },

        scrollGuesses() {
            const scroll = () => {
                const feed = this.$refs?.guessFeed;
                if (feed) feed.scrollTop = feed.scrollHeight;
                this.unreadGuesses = false;
            };
            if (typeof this.$nextTick === 'function') this.$nextTick(scroll);
            else scroll();
        },

        trackGuessScroll() {
            const feed = this.$refs?.guessFeed;
            if (feed && feed.scrollHeight - feed.scrollTop - feed.clientHeight < 48) this.unreadGuesses = false;
        },

        requestPaint() {
            if (this.destroyed || !this.context || this.paintFrame !== null) return;
            if (typeof window.requestAnimationFrame !== 'function') { this.paint(); return; }
            this.paintFrame = window.requestAnimationFrame(() => { this.paintFrame = null; this.paint(); });
        },

        paint() {
            if (!this.canvas || !this.context) return;
            const context = this.context;
            context.setTransform(this.canvas.width / WIDTH, 0, 0, this.canvas.height / HEIGHT, 0, 0);
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, WIDTH, HEIGHT);
            const pending = [...this.confirmed.filter(event => event.seq > this.cursor),
                ...this.batches.flatMap(batch => batch.events), ...this.unsent];
            // An unacknowledged undo must also hide ink already in the cached layer.
            // Rebuild the visible timeline until that undo joins the confirmed cache.
            if (pending.some(event => event.type === 'undo')) {
                for (const event of this.visibleDrawingEvents(this.drawingTimeline())) this.drawEvent(event);
                return;
            }
            const layer = this.confirmedLayer();
            if (layer) {
                context.drawImage(layer, 0, 0, WIDTH, HEIGHT);
                // Acknowledgements can arrive ahead of an earlier replay page.
                for (const event of this.confirmed) if (event.seq > this.cursor) this.drawEvent(event);
            } else for (const event of this.visibleDrawingEvents(this.confirmed)) this.drawEvent(event);
            for (const batch of this.batches) for (const event of batch.events) this.drawEvent(event);
            for (const event of this.unsent) this.drawEvent(event);
            if (this.activeStroke) this.drawEvent(this.activeStroke);
        },

        drawEvent(event) {
            if (!this.context || !this.canvas) return;
            this.renderEvent(event, this.context, this.canvas.width, this.canvas.height);
        },

        renderEvent(event, context, width, height) {
            // A second drawer tab can deliver an old gesture's chunks after Undo.
            // Keep incremental painting consistent with a full reconnect replay.
            if (event.type === 'stroke' && event.stroke_id && this.undoneStrokeIds.has(event.stroke_id)) return;
            context.setTransform(width / WIDTH, 0, 0, height / HEIGHT, 0, 0);
            if (event.type === 'clear') {
                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, WIDTH, HEIGHT);
                return;
            }
            if (event.type !== 'stroke' || !event.points?.length) return;
            context.lineCap = 'round';
            context.lineJoin = 'round';
            context.strokeStyle = event.color;
            context.fillStyle = event.color;
            context.lineWidth = event.width;
            const [first, ...rest] = event.points;
            context.beginPath();
            if (!rest.length) {
                context.arc(first.x * WIDTH, first.y * HEIGHT, event.width / 2, 0, Math.PI * 2);
                context.fill();
            } else {
                context.moveTo(first.x * WIDTH, first.y * HEIGHT);
                for (const point of rest) context.lineTo(point.x * WIDTH, point.y * HEIGHT);
                context.stroke();
            }
        },

        resetConfirmedLayer() {
            this.confirmedCanvas = null;
            this.confirmedContext = null;
            this.paintedCursor = 0;
            this.paintedUndoCursor = 0;
        },

        confirmedLayer() {
            // A regular detached HTML canvas works on Safari without requiring
            // OffscreenCanvas. The historical drawing is rasterized only once.
            if (!this.confirmedCanvas) {
                const layer = document.createElement?.('canvas');
                const context = layer?.getContext?.('2d', {alpha: false});
                if (!context) return null;
                this.confirmedCanvas = layer;
                this.confirmedContext = context;
            }
            const layer = this.confirmedCanvas;
            const context = this.confirmedContext;
            const events = this.confirmed.filter(event => event.seq <= this.cursor);
            const undoCursor = lastItem(events.filter(event => event.type === 'undo'))?.seq || 0;
            if (layer.width !== this.canvas.width || layer.height !== this.canvas.height
                || !this.paintedCursor || undoCursor !== this.paintedUndoCursor) {
                layer.width = this.canvas.width;
                layer.height = this.canvas.height;
                context.setTransform(layer.width / WIDTH, 0, 0, layer.height / HEIGHT, 0, 0);
                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, WIDTH, HEIGHT);
                for (const event of this.visibleDrawingEvents(events)) this.renderEvent(event, context, layer.width, layer.height);
                this.paintedCursor = this.cursor;
                this.paintedUndoCursor = undoCursor;
            } else {
                for (const event of events) {
                    if (event.seq > this.paintedCursor) {
                        this.renderEvent(event, context, layer.width, layer.height);
                        this.paintedCursor = event.seq;
                    }
                }
            }
            return layer;
        },

        newRequestId() {
            if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
            const bytes = new Uint8Array(16);
            if (globalThis.crypto?.getRandomValues) globalThis.crypto.getRandomValues(bytes);
            else for (let i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
            bytes[6] = (bytes[6] & 15) | 64;
            bytes[8] = (bytes[8] & 63) | 128;
            const hex = Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('');
            return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
        },

        headers() {
            return {Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''};
        },

        scheduleSend(delay = 80) {
            if (this.destroyed || this.drawingBlocked || this.sending || !this.sketch?.turn_id) return;
            clearTimeout(this.sendTimer);
            this.sendTimer = setTimeout(() => this.sendDrawing(), delay);
        },

        async sendDrawing() {
            if (this.destroyed || this.sending || this.drawingBlocked || !this.sketch?.turn_id) return;
            let batch = this.batches.find(item => !item.accepted);
            if (!batch && this.unsent.length) {
                batch = {request_id: this.newRequestId(), events: this.unsent.splice(0, 8), accepted: false};
                this.batches.push(batch);
            }
            if (!batch) return;
            const generation = this.generation;
            const turnId = this.sketch.turn_id;
            this.sending = true;
            let retry = 0;
            try {
                const response = await window.axios.post(this.sketchUrls.drawingWrite, {
                    turn_id: turnId, request_id: batch.request_id, events: batch.events,
                }, {headers: this.headers(), timeout: 10000});
                if (generation !== this.generation || this.destroyed) return;
                batch.accepted = true;
                this.retryAttempt = 0;
                this.drawingError = '';
                this.acceptEvents(response.data, turnId);
                this.scheduleRead(0);
            } catch (failure) {
                if (generation !== this.generation || this.destroyed) return;
                // A concurrent read may already have confirmed a lost write response.
                if (!this.batches.some(item => item.request_id === batch.request_id)) return;
                const status = failure.response?.status;
                if ([400, 401, 403, 404, 409, 419, 422].includes(status)) {
                    this.drawingBlocked = true;
                    this.finishStroke();
                    this.drawingError = Object.values(failure.response?.data?.errors || {}).flat()[0]
                        || failure.response?.data?.message || 'Drawing could not be saved. Refresh to reconnect.';
                } else {
                    this.retryAttempt++;
                    retry = Math.min(5000, 500 * 2 ** Math.min(4, this.retryAttempt - 1));
                    this.drawingError = 'Connection interrupted. Your sketch is waiting to sync…';
                }
            } finally {
                if (generation === this.generation && !this.destroyed) {
                    this.sending = false;
                    if (retry) this.scheduleSend(retry);
                    else if (!this.drawingBlocked && (this.unsent.length || this.batches.some(item => !item.accepted))) this.scheduleSend();
                }
            }
        },

        acceptEvents(payload, turnId) {
            if (Number(payload?.turn_id) !== Number(turnId) || Number(this.sketch?.turn_id) !== Number(turnId)) return;
            const events = Array.isArray(payload.events) ? payload.events : [];
            if (!events.length) return;
            const bySequence = new Map(this.confirmed.map(event => [event.seq, event]));
            let changed = false;
            for (const event of events) {
                if (Number.isInteger(event.seq) && event.seq > 0 && !bySequence.has(event.seq)) {
                    bySequence.set(event.seq, event);
                    changed = true;
                }
            }
            if (!changed) return;
            this.confirmed = [...bySequence.values()].sort((a, b) => a.seq - b.seq);
            const confirmedCounts = new Map();
            for (const event of this.confirmed) {
                if (event.request_id) confirmedCounts.set(event.request_id, (confirmedCounts.get(event.request_id) || 0) + 1);
            }
            this.batches = this.batches.filter(batch => (confirmedCounts.get(batch.request_id) || 0) < batch.events.length);
            while (bySequence.has(this.cursor + 1)) this.cursor++;
            this.refreshUndoState();
            this.requestPaint();
        },

        scheduleRead(delay = 750) {
            if (this.destroyed || !this.sketch?.turn_id || this.reading) return;
            clearTimeout(this.readTimer);
            this.readTimer = setTimeout(() => this.readDrawing(), delay);
        },

        async readDrawing() {
            if (this.destroyed || this.reading || !this.sketch?.turn_id) return;
            const generation = this.generation;
            const turnId = this.sketch.turn_id;
            this.reading = true;
            let nextDelay = 750;
            try {
                const response = await window.axios.get(this.sketchUrls.drawingRead, {
                    params: {turn_id: turnId, after: this.cursor}, headers: {Accept: 'application/json'}, timeout: 10000,
                });
                if (generation !== this.generation || this.destroyed) return;
                if (Number(response.data?.turn_id) !== Number(turnId)) return;
                this.acceptEvents(response.data, turnId);
                if (response.data.has_more) nextDelay = 30;
                else this.drawingReady = true;
                if (!this.batches.length && !this.unsent.length) this.drawingError = '';
                this.updateCanvasInput();
            } catch (failure) {
                if (generation !== this.generation || this.destroyed) return;
                nextDelay = 1500;
                if (!this.drawingBlocked) this.drawingError = 'Reconnecting to the live sketch…';
            } finally {
                if (generation === this.generation && !this.destroyed) {
                    this.reading = false;
                    if (!['completed', 'cancelled'].includes(this.sketchStatus) || !this.drawingReady) this.scheduleRead(nextDelay);
                }
            }
        },

        retryDrawing() {
            if (this.destroyed || !this.sketch?.turn_id) return;
            // A definitive server rejection must not be bypassed by a local retry.
            if (!this.drawingBlocked) this.scheduleSend(0);
            this.scheduleRead(0);
        },

        async revealHint() {
            if (!this.canRevealHint) return;
            const turnId = this.sketch.turn_id;
            const generation = this.generation;
            this.hintSharing = true;
            this.hintError = '';
            // Hint sharing never flushes, clears or resets an in-progress gesture.
            try {
                const response = await window.axios.post(this.sketchUrls.hint, {turn_id: turnId}, {headers: this.headers(), timeout: 10000});
                if (generation !== this.generation || this.destroyed) return;
                const next = response.data?.state;
                if (Number(next?.sketch?.turn_id) !== Number(turnId)) return;
                if (next.phase === 'drawing' && (this.sketchPhase !== 'drawing' || this.sketchStatus !== 'in_progress')) return;
                if (next.sketch.hint_revealed && next.sketch.hint) {
                    this.sketch = {...this.sketch, hint: next.sketch.hint, hint_revealed: true, can_reveal_hint: false};
                }
                this.$dispatch?.('sketch-state', next);
            } catch (failure) {
                if (generation !== this.generation || this.destroyed || this.sketch?.hint_revealed) return;
                this.hintError = Object.values(failure.response?.data?.errors || {}).flat()[0]
                    || failure.response?.data?.message || 'Could not share the hint. You can try again.';
            } finally {
                if (generation === this.generation && !this.destroyed) this.hintSharing = false;
            }
        },

        async submitGuess() {
            const guess = this.guessText.trim();
            if (!this.canGuess || this.guessing || !guess) return;
            if (Array.from(guess).length > 100) { this.sketchError = 'Keep your guess to 100 characters.'; return; }
            const turnId = this.sketch.turn_id;
            const generation = this.generation;
            this.guessing = true;
            this.sketchError = '';
            try {
                const response = await window.axios.post(this.sketchUrls.guess, {turn_id: turnId, guess}, {headers: this.headers(), timeout: 10000});
                if (generation !== this.generation || this.destroyed) return;
                if (response.data.state) this.$dispatch?.('sketch-state', response.data.state);
                const correct = response.data.correct || response.data.already_guessed || response.data.state?.sketch?.guessed;
                this.guessMessage = correct ? 'Correct! Your points have been saved.' : 'Not quite. Try another guess.';
                if (correct) this.sketch = {...this.sketch, guessed: true};
                this.guessText = '';
            } catch (failure) {
                if (generation !== this.generation || this.destroyed) return;
                if (this.sketch?.guessed) {
                    this.guessMessage = 'Correct! Your points have been saved.';
                    this.guessText = '';
                    return;
                }
                this.sketchError = Object.values(failure.response?.data?.errors || {}).flat()[0]
                    || failure.response?.data?.message || 'Could not confirm your guess. You can submit it again.';
            } finally {
                if (generation === this.generation && !this.destroyed) this.guessing = false;
            }
        },

        unmountCanvas() {
            this.releaseStroke(false);
            this.observer?.disconnect();
            this.observer = null;
            for (const {target, type, handler, options} of this.listeners) target.removeEventListener(type, handler, options);
            this.listeners = [];
            if (this.paintFrame !== null) window.cancelAnimationFrame?.(this.paintFrame);
            this.paintFrame = null;
            this.canvas = null;
            this.context = null;
            this.resetConfirmedLayer();
        },

        destroy() {
            this.destroyed = true;
            this.generation++;
            clearInterval(this.clockTimer);
            clearTimeout(this.readTimer);
            clearTimeout(this.sendTimer);
            this.unmountCanvas();
        },
    }));
}
