# Reverse Bingo validation

Validated against the updated source on 23 October 2026 using the isolated PHP 8.3/Laravel fixture and SQLite. No user database was changed.

## Results

- **2,103 application assertions passed:** the previous 1,762 checks plus 341 new Bingo migration, service, HTTP and JavaScript checks.
- **5,916 generated game schedules passed 747,951 separate property assertions.** Coverage includes every player count from 2–30 and every final draw from 6–74, with the earliest, midpoint and latest permitted elimination settings.
- **80 PHP files passed syntax checks.** All Blade templates compiled, and the Vite production build succeeded.
- **17 migrations applied successfully.** Applying only migration `000013` to the previous schema preserved all prior column values across 20 existing tables and 16 seeded records.
- The tested fixture matches all delivered application source files. All eight Whitney fonts remain unchanged and match the emitted build files byte for byte.

## Verified behavior

- Three distinct numbers from 1–75 per player; one called number per host action. All matching cards close, and players with zero remaining cards are eliminated.
- The complete random schedule is saved at start. It cannot reroll on refresh, retries or a second start request.
- For four or more players, four enter the third-last draw, three enter the second-last draw, two enter the final draw, and exactly one player survives the final draw with one unique uncalled card number.
- Earlier eliminations follow the configured minimum and are distributed over the available draws. Short schedules can eliminate more than two players on a draw.
- The host alone can reveal numbers. Authentication, admin authorization, CSRF and expected-position validation protect the endpoint. Duplicate requests cannot consume another draw.
- Public polling and the scheduler never reveal a number. Expired network responses cannot roll the UI backward or reopen a completed game. A lost-response retry keeps the original expected position.
- Future draw order and player rankings are excluded from public state. Spectators see players, current cards, prior calls and survival-count forecasts only.
- Finalization saves all player results, exactly one winner and the eliminated-draw/remaining-card metrics. Inconsistent final card data rolls back the final draw and every result write.
- The final view includes the last called number, the winning card, top five and remaining standings. CSV exports every result and neutralizes spreadsheet formula prefixes.
- Existing avatars, readiness, name edits, quizzes, edited timers, seven-second leaderboards, all-word sketch sessions, public guesses, Undo and manual hints continue to pass their regression checks.

## UI checks and practical limits

Responsive markup uses two/three/five columns for player and spectator views, with fewer columns in the narrower host panel. The reveal control has a touch-friendly minimum height, own-player cards appear first, names wrap, and reduced-motion preferences are respected. JavaScript checks cover double taps, retries, stale responses, terminal states and read-only polling.

These are source, server and JavaScript checks—not physical-device tests. Mobile Safari, Android Chrome, Windows/Laragon, Hostinger and MySQL were not executed here. The production build retains the existing `/images/omiquiz/camp-lobby.png` runtime URL; keep the application's existing public image assets when merging this patch.

Build outputs verified: `app-DTzJBx-m.css` and `app-DTrUAzy9.js`. Rebuild on the target installation using the commands in `README-OMIQUIZ.md`.
