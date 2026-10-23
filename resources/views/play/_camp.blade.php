<div x-show="state.status === 'waiting'" x-data="{ queueOpen: false }">
    <section class="camp-stage" x-ref="campViewport" x-init="$nextTick(() => initCamp($refs.campViewport))"
        :class="{ 'camp-stage--small': state.player_count <= 8, 'camp-stage--dense': state.player_count > 16, 'camp-stage--dragging': campDragging }"
        @pointerdown="startCampDrag($event)" @pointermove="moveCampDrag($event)"
        @pointerup="endCampDrag($event)" @pointercancel="endCampDrag($event)" @lostpointercapture="endCampDrag($event)"
        @keydown="onCampKey($event)"
        tabindex="0" aria-label="Waiting lobby campsite. Drag to explore, or use the arrow keys." aria-describedby="camp-navigation-help">
        <div class="camp-world" x-ref="campWorld" :style="campWorldStyle">
            <div class="camp-grid" role="list" aria-label="Joined players">
                <template x-for="(person, index) in state.players" :key="person.public_id">
                    <div class="camp-cell" :class="{ 'camp-cell--you': state.player?.public_id === person.public_id }" :style="campPositionStyle(person.public_id, index)" role="listitem">
                        <div class="camp-floater" :style="campStyle(person.public_id, index)">
                            <span class="camp-ready-status" :class="person.is_ready ? 'camp-ready-status--ready' : ''" :aria-label="person.name + (person.is_ready ? ' is ready' : ' is not ready')" x-text="person.is_ready ? '✓ Ready' : 'Not ready'"></span>
                            <span class="camp-you-marker" x-cloak x-show="state.player?.public_id === person.public_id" aria-hidden="true">You <span>▼</span></span>
                            <img :src="person.profile?.sprite_url || person.profile?.avatar_url" :alt="person.name + ' avatar'" class="camp-sprite" width="180" height="180" draggable="false">
                            <span class="camp-name" :class="{ 'camp-name--you': state.player?.public_id === person.public_id, 'camp-name--ready': person.is_ready }" :title="person.name" x-text="person.name + (state.player?.public_id === person.public_id ? ' (You)' : '')"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
        <div class="camp-heading"><h2>Waiting for other players</h2><p class="camp-wordmark">Camp omiquiz</p></div>
    </section>
    <div class="camp-navigation" @pointerdown.stop @keydown.stop>
        <p id="camp-navigation-help">Drag to explore the camp</p>
        @if ($playerView ?? false)
            <button type="button" @click="focusCampPlayer()" class="camp-focus-button"><span aria-hidden="true">◎</span> Find my character</button>
        @else
            <button type="button" @click="centerCamp()" class="camp-focus-button"><span aria-hidden="true">◎</span> Center camp</button>
        @endif
    </div>

    <div class="camp-footer">
        <div class="flex min-w-0 items-center gap-4"><div class="camp-avatar-stack" aria-hidden="true"><template x-for="person in state.players.slice(0, 5)" :key="person.public_id"><img :src="person.profile?.avatar_url" alt="" width="42" height="42"></template><span x-show="state.player_count > 5" x-text="'+' + (state.player_count - 5)"></span></div><div class="min-w-0"><p class="text-sm font-semibold sm:text-base"><span class="font-bold tabular-nums" x-text="state.player_count + ' / ' + state.max_players"></span> players joined <span class="ml-2 text-xs font-medium text-emerald-700" x-text="(state.ready_count || 0) + ' ready'"></span></p><p class="mt-1 break-words text-xs text-slate-500 sm:text-sm">@if ($playerView ?? false)You joined as <span class="font-semibold" x-text="state.player?.name"></span>.@else The host will start when everyone is ready.@endif</p></div></div>
        <div class="flex shrink-0 flex-wrap gap-3"><button type="button" class="oq-button-secondary" @click="queueOpen = !queueOpen" :aria-expanded="queueOpen" aria-controls="camp-player-queue" x-text="queueOpen ? 'Hide queue' : 'View queue'"></button>@if ($playerView ?? false)<button type="button" class="oq-button-primary" :class="state.player?.is_ready ? 'camp-ready-toggle--set' : ''" @click="toggleReady()" :disabled="readySaving || profileSaving" :aria-pressed="state.player?.is_ready === true" x-text="readySaving ? 'Saving…' : state.player?.is_ready ? 'Not ready' : 'Ready'"></button><button type="button" class="oq-button-secondary" @click="beginNameEdit()" :disabled="profileSaving">Edit name</button><a href="{{ route('public.sessions.show', $session) }}" target="_blank" rel="noopener" class="oq-button-secondary">Spectator view ↗</a>@else<a href="{{ route('play.show', $session) }}" class="oq-button-primary" x-text="state.player_count < state.max_players ? 'Join this game' : 'Open player page'"></a>@endif</div>
    </div>
    @if ($playerView ?? false)
        <p x-show="readyError" x-cloak role="alert" class="mt-3 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="readyError"></p>
        <form x-show="editingName" x-cloak @submit.prevent="saveName()" class="camp-name-editor" aria-label="Edit your player name">
            <div class="min-w-0 flex-1"><label for="player-name-edit" class="oq-label">Your name</label><input id="player-name-edit" x-model="nameDraft" maxlength="120" autocomplete="nickname" required class="oq-input" :disabled="profileSaving"><p x-show="profileError" role="alert" class="mt-2 text-xs text-rose-600" x-text="profileError"></p><p class="mt-2 text-xs text-slate-500">You can change your name while waiting for the game to start.</p></div>
            <div class="flex flex-wrap gap-2"><button type="submit" class="oq-button-primary" :disabled="profileSaving" x-text="profileSaving ? 'Saving…' : 'Save name'"></button><button type="button" class="oq-button-secondary" @click="cancelNameEdit()" :disabled="profileSaving">Cancel</button></div>
        </form>
    @endif
    <div class="camp-caption"><p>@if ($playerView ?? false)Mark yourself ready when you can begin. Your host starts the quiz.@else Watching does not use a player spot.@endif</p><button type="button" @click="toggleMotion()" :aria-pressed="motionPaused" x-text="motionPaused ? 'Resume motion' : 'Pause motion'"></button></div>
    <div id="camp-player-queue" x-show="queueOpen" x-cloak class="mt-5">@include('play._roster')</div>
</div>
