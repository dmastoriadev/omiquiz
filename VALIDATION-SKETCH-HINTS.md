# Sketch letter hint validation

Validated 23 October 2026 using PHP 8.3.6, Laravel 12.70.1, SQLite with foreign keys, Node and Vite 6.4.5.

## Results

- **1,762 automated assertions passed:** 1,137 PHP/service/HTTP assertions and 625 JavaScript assertions. The PHP total includes one check confirming that the upgrade fixture contains saved quiz results and active sketch data.
- New focused coverage: **13 migration checks, 41 service/generator checks, 78 HTTP checks and 78 hint UI checks**. Every PHP test suite reached its required completion marker.
- All **74 PHP files** passed syntax checks. Blade views compiled and the Vite production build completed.
- All **16 migrations** applied in a fresh isolated database. The new hint migration also applied alone to the previous schema through Artisan. Across 20 existing tables, all 16 seeded rows retained exactly the same values in every previously existing column.
- Source and test-fixture app, resources, routes, database source and config files match byte for byte. The campfire image also matches.

## Settings and migration

- Admins can set an integer maximum from 0 to 100 for sketch games; 0 disables hints. Invalid, fractional, nonnumeric and empty values are rejected. An omitted field preserves the existing setting on edit and defaults to 0 on creation. Quiz games keep hints disabled.
- Waiting sessions follow edited settings, and starting a waiting session reads the latest setting. Active sessions keep their saved maximum even if the original game is edited.
- The additive migration defaults existing games and sessions to 0 and existing turns to null hints. Applying, rolling back and reapplying it preserves the existing game, session, player and turn records.

## Manual hint timing and privacy

- The current drawer can press Hint before the first stroke, during a streamed gesture or after drawing, while the turn is still open. The button can work before the canvas has finished loading.
- The stored random mask remains private until the drawer presses Hint. Drawer, guessers, spectators and host then receive the same mask. Reloading or pressing again does not reroll it or change its reveal time.
- New turns begin with their own hidden hint. Future words, aliases and masks remain private. Current prompt text remains available only to the drawer until the normal turn reveal.
- Anonymous, non-drawer, future-turn, previous-turn, disabled-hint and invalid requests cannot reveal a hint. Production CSRF rejection is covered. A request at the exact deadline is rejected and the expired turn advances normally.
- Hint requests do not change the timer, strokes, score or drawer bonus. Correct guesses after a hint retain the normal timing-based points.
- Frontend checks cover double taps, retries, lost responses, delayed responses from old turns, closed turns and stale polls. Revealing a hint leaves active pointer state, queued strokes and canvas history intact.

## Mask generation and regressions

- Random choices use distinct character positions, reveal no more than the configured maximum and always leave at least one letter or digit hidden. Repeated letters do not reveal every matching occurrence.
- Aggregate repeated-generation checks cover accented and combining characters, Chinese text, spaces, hyphens, digits, repeated letters, one-letter words, emoji and punctuation. Grapheme clusters are not split. Symbol-only prompts have no hint; one-letter prompts stay hidden.
- Existing all-word completion, final scores, CSV export, public guesses, Undo, quiz submit/ready behavior, edited timers, camp state and avatar checks passed.
- The 121-check canvas suite and 99-check social/Undo UI suite passed. Eight Whitney WOFF assets match the uploaded source bytes, were emitted by Vite and are referenced by the compiled CSS.

## Build fingerprints

- Hint generator: `3129a1371b9294a54a163f6157d4542e6b2d4f5f0b75f762623a27069e499cd0`
- Hint migration: `5ac9a4637aad88f91e3f03b2e01f4ea6a3fb144c985ac9f03704d84664a2710e`
- Sketch client source: `cc7bf2712759b3654ddfb31d5cacae22290be188d4b16e777d825a6535e974b1`
- assets/app-BsEg8qKi.css: `c17eaf61be93363a557855c6418f60c8dcfc3d1fa3d2cceba2bf9b3b3ef1e54d`
- assets/app-BcejR93-.js: `4e012a1307cce4519eab5e12216d455fa8197cd68b31fb514bd3acb3abfc1f83`

## Limits and installation

No real browser or physical iPhone, iPad or Android device was operated. Canvas and interaction checks use mocked events and drawing calls; they do not certify visual rendering on every device. Windows/Laragon, live Hostinger and MySQL were not executed.

Install the changed folders into the existing project, apply the new additive migration, rebuild frontend assets and clear Laravel caches as described in `README-OMIQUIZ.md`. Existing game data is preserved; do not reset the database. Hints are disabled until an admin configures them. Start a new session to use hints after upgrading an already active game.
