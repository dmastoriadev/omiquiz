# Validation — 5×5 Reverse Bingo

## Game and mathematical checks

- Independently simulated 4,172 schedules across 2–32 players, 6–50 maximum plays, early/middle/latest permitted first eliminations, and repeated dense edge cases.
- Checked all 24 numbers on every card: standard B/I/N/G/O column ranges, unique numbers, FREE center, distinct complete number sets, and actual first-match card closures.
- Confirmed 32-player/20-play counts: four enter play 18, three enter 19, two enter 20, one wins afterward with exactly one card containing no called number.
- 290 focused service checks: 96 persisted boards, retries, actual cell intersections, unchanged cards across reads, all 32 saved results, and legacy 74-draw completion.
- 130 existing Bingo service regression checks adapted to full-card matching.

## HTTP, presentation and database

- 118 Bingo HTTP checks: admin configuration, 32-player limit, permissions, CSRF, idempotent draws, immutable active settings, results and CSV exports.
- 1,212 grid visibility HTTP checks: 32 players and 96 real server-rendered cards, each with 25 cells, escaped names, labeled host form, native redirects, AJAX and public-view restrictions.
- 82 client behavior/markup checks, including the unchanged reveal/retry behavior.
- 19 additive migration checks verified original game/session/player/card/draw fields and row counts survive the new nullable cells column and its rollback/reapply.
- All 32 existing quiz, sketch, camp, readiness, hint, avatar and frontend regression scripts passed.
- Changed PHP files passed syntax checks; Blade templates compiled successfully.
- Vite production build succeeded. The generated manifest and all referenced JavaScript, CSS and Whitney fonts are included in the patch.

## Environment limits

Validation used PHP 8.3, Laravel 12 and isolated SQLite databases. No live Hostinger or user database was modified. MySQL lock contention was not exercised. The local browser process was blocked by a socket permission restriction, so screenshots, physical iOS Safari/Android Chrome gestures and final visual layout could not be verified here.
