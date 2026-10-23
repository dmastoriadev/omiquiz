# Sketch & Guess validation

Validated 23 October 2026 against the isolated test app: PHP 8.3.6, Laravel 12.70.1, SQLite with foreign keys, and Vite 6.4.5.

## Results

- **1,160 automated assertions passed:** 712 PHP/service/HTTP and 448 JavaScript.
- All **70 PHP source files** passed syntax checks; Blade views compiled.
- All **14 migrations**, including the new additive Sketch & Guess migration, applied successfully.
- The additive migration also passed **17 preservation checks** on populated quiz game/session/player/result records, including rollback and reapply.
- Production assets built successfully. The existing campfire image is present and unchanged.

## Coverage

- Admin category/settings validation and CSV word upload, template, deletion and permissions. Parsing covers UTF-8/BOM, aliases, duplicates, malformed rows, atomic failures and the 2,000-word bank limit.
- One drawing turn per player in join order; edited waiting-room timers; fixed running timers; seven-second standings; offline completion and saved result/CSV breakdowns.
- Drawer-only secret prompt, scoped drawing/guess permissions, forged-token rejection and CSRF enforcement. Spectator and guesser payloads do not reveal active words or aliases.
- Server-clock speed scoring, 25% drawer bonuses, single accepted guess per player/turn, immutable retry acknowledgements and no duplicate results.
- Drawing batch/point/body limits, coordinates/colors, ordering, replay cursors, request-id retries, MySQL-style JSON key order and per-turn event limits.
- Canvas pointer/touch cancellation, pen/mouse handling, non-passive touch fallback, high-density scaling, resize restoration, replay caching, retry/late-response behavior and cleanup.
- Existing quiz, name editing, Ready state, final answer submission, copy links, draggable camp and leaderboard regressions.

## Limits and device checks

Actual iPhone/iPad Safari, Android and desktop browsers were not operated. Browser execution is restricted in this environment; canvas events and drawing calls were tested with mocks rather than real pixels. These checks do not guarantee perfect behavior on every device.

After installation, verify finger/pen drawing, eraser, clear, scrolling outside the canvas, portrait/landscape rotation, refresh/rejoin and drawing sync on your target devices. Check a short game using at least three separate browser sessions so every player draws and guesses.

The application was not run on your Windows/Laragon installation or MySQL database. Concurrent large-room performance and end-to-end network latency were not load-tested.

## Installation

This update adds `2026_10_23_000010_add_sketch_games.php`. Use normal `php artisan migrate`, then rebuild frontend assets and restart the existing scheduler. Existing quiz records remain intact.
