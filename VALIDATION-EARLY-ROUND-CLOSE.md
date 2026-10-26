# Early round completion validation

Quiz closes when every playing participant has submitted a valid answer, regardless of correctness. Sketch closes when every playing participant except the current drawer has guessed correctly. A participant who disconnects still counts until the normal timeout; incorrect guesses do not satisfy Sketch completion.

The final accepted answer or correct guess closes the round within the existing session-locked transaction. The persisted close time starts the five-second answer reveal and seven-second leaderboard. The original deadline and configured scoring duration are retained. The next round gets its full configured time. The final round follows the same display sequence before saving final results.

- 46 focused Quiz service checks passed, covering wrong answers, 32-player rooms, single-player rooms, partial completion, timer fallback, scoring, duplicate submissions, early final results, recovered completed ledgers and empty-roster protection.
- 86 focused Sketch service checks passed, covering every distinct guesser, drawer exclusion, wrong attempts, 32-player rooms, speed points and drawer bonuses, saved drawing retrieval, read-only closed turns, retries, timer fallback, recovery and offline catch-up.
- 186 HTTP checks passed across Quiz and Sketch with three players each and two rounds: immediate final-submission response, shared host/player/spectator timing, hidden answers before completion, five-second answer reveal, seven-second leaderboard, original deadlines retained, full next-round timers, unchanged scores, saved sketches and final results.
- 54 focused JavaScript checks passed for early answer reveal while the original deadline remains in the future, submission-response transitions, stale-response protection, next/final transitions and retained drawing with read-only controls.
- All 32 regression scripts passed, covering Quiz, Sketch, all uploaded words, hints, readiness, camp state, score exports, timed answer reveal, Bingo and client behavior. Legacy tests that required waiting after everyone answered were updated to the new behavior without removing scoring or privacy assertions.
- All four changed production PHP files passed syntax checks and match the tested application fixture. No frontend source changes were necessary for early completion. The archive includes the previously built answer-reveal UI and its matching compiled assets; asset hashes and archive contents were verified.

Tests used PHP 8.3, Laravel 12 and isolated SQLite fixtures. No live database or hosting configuration was changed. Database concurrency was reviewed through the existing session-lock transaction design; these checks do not simulate parallel MySQL requests. Browser and physical mobile-device behavior were not manually tested.

No migration is required. Upload the included application, resource and compiled-build files; run `php artisan optimize:clear`, restart any running `schedule:work` process and refresh open game tabs.
