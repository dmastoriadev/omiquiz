# Whitney, shared guesses and Undo validation

Validated 23 October 2026 using PHP 8.3.6, Laravel 12.70.1, SQLite with foreign keys, Node and Vite 6.4.5.

## Results

- **1,552 automated assertions passed:** 1,005 PHP/service/HTTP assertions and 547 JavaScript assertions. The PHP total includes one check confirming that the upgrade fixture contains saved quiz results and active sketch data.
- New focused coverage includes **97 social sketch HTTP checks**, **14 message-migration checks** and **99 Undo/feed UI checks**. The existing 121-check canvas suite also passed.
- All **72 PHP files** passed syntax checks. Blade views compiled and the Vite production build completed.
- All **15 migrations** applied in a fresh isolated database. The new message migration also applied alone to the previous schema through Artisan; all 19 existing tables and their 16 seeded rows remained byte-for-byte identical in the database snapshot comparison.
- The avatar routes passed 75 checks and were rechecked successfully with cached routes. The repeated check is not counted twice in the total.
- Source and test-fixture app, resources, routes, database source and config files match byte for byte. The campfire image also matches.

## Shared guessing

- Submitted wrong guesses appear with the player's display name for the drawer, guessers, spectator and host.
- Correct guesses create a public success announcement with no prompt or accepted-alias text. Correct-answer retries create no extra message or score.
- Feed responses contain only public message fields and the latest 50 current-turn entries in chronological order. The full timeline remains stored. A new turn starts an empty feed.
- Anonymous, drawer, future-turn, oversized, expired and stale guesses make no unauthorized timeline writes.
- HTML-like player input remains plain text in the feed. The templates use Alpine `x-text`, not HTML injection.
- Feed scrolling follows the newest message when at the bottom, preserves the reading position when scrolled up, and provides a way to return to the latest guesses.
- Existing guessing points, drawer bonuses, all-word completion, saved results and CSV export regressions passed.

## Undo

- Undo removes a complete brush or eraser gesture, including all streamed chunks, across local display, other viewers and reconnection replay.
- Pending requests, acknowledgements arriving out of order, lost-response retries, resize and duplicate polling are covered.
- A late chunk from another drawer tab cannot make an undone gesture reappear.
- Only the active drawer can send Undo. Unknown, malformed, already-undone and cleared targets are rejected; an invalid mixed batch makes no partial writes.
- Clear starts a new Undo history. New gestures can be undone afterwards. Existing untagged strokes remain visible and compatible but cannot be undone.
- Cached canvas painting remains incremental during ordinary drawing; no-op polls do not repeatedly repaint old drawing history.

## Whitney font

- Eight supplied Whitney WOFF files were verified against the original uploaded font bytes: Book, Medium, Semibold and Bold, each normal and italic.
- Vite emitted all eight hashed font assets and referenced them in the compiled CSS. All emitted font bytes match their source files.
- The shared font stack covers the page body, headings, form controls and `font-sans` utilities. Previous Georgia heading overrides were removed.
- Font assets build from `resources/views/fonts/Whitney`; they are not requested directly from the private resources directory.

## Limits and installation

No real browser or physical iPhone, iPad or Android device was operated. Canvas and interaction checks use mocked events and drawing calls; they do not certify visual rendering on every device. Windows/Laragon, live Hostinger and MySQL were not executed.

This update needs the new additive migration, a frontend build, Laravel cache clearing and a page refresh. Existing game data is preserved. Follow `README-OMIQUIZ.md`; do not run a database reset on the live project.
