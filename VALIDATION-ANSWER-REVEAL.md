# Answer-reveal validation

- 95 focused service assertions: exact five-second reveal, seven-second leaderboard, answer secrecy during open rounds, full correct answer/word, immutable completed sketches, late action rejection, accepted-action retries, final-round timing, offline catch-up and unchanged scoring/response-time calculations.
- 186 HTTP assertions across Quiz and Sketch: matching reveal timing and answer data for hosts, both players and spectators; actual saved drawing retrieval; single canvas per Sketch page; read-only reveal; no early results; exact five-plus-seven transitions.
- 75 focused JavaScript assertions: maximum display time, no extension on polling, stale-phase rejection, reload and clock-offset handling, final-round sequence, Bingo exclusion, retained accepted strokes, discarded unsaved previews, read-only controls, late accepted drawing acknowledgements and turn isolation.
- All 28 regression scripts passed, covering existing Quiz, Sketch, all-word rotation, hints, camp, readiness, score/export flows, Bingo and client behavior. Older timing fixtures were updated for the new five-second interval; submission duration and score assertions remain unchanged.
- Changed PHP sources passed syntax checks, Blade compilation passed and the Vite production build succeeded.

Validation used PHP8.3, Laravel12 and isolated SQLite fixtures. No live user database or hosting configuration was changed. Browser rendering remains unavailable in this environment, so physical Safari/iOS/Android layout and gesture checks remain unverified.
