# Sketch & Guess all-word validation

Validated 23 October 2026 using PHP 8.3.6, Laravel 12.70.1 and an isolated SQLite database with foreign keys.

## Results

- **1,266 automated assertions passed:** 818 PHP/service/HTTP and 448 JavaScript. Every PHP suite must emit its successful completion marker; exit code alone is insufficient.
- The focused all-word service suite passed **88 assertions**; the Sketch & Guess HTTP suite passed **103**.
- All **70 PHP source files** passed syntax checks. Blade templates compiled and Vite 6.4.5 built production assets successfully.
- All **14 existing migrations** applied. This fix adds or changes **no migration**.
- Source and test-fixture `app`, `resources` and `routes` files match byte for byte. The campfire image also matches. Canvas JavaScript is unchanged.

## Behavior verified

- Every active uploaded word is copied into a new room and played exactly once. Player capacity does not truncate the word bank.
- Seven words with three players produce drawers A, B, C, A, B, C, A. One word with four players produces one turn, without repeating prompts to fill a rotation.
- Final results remain absent until every word and the final seven-second leaderboard finish. Repeated completed polling preserves saved totals.
- A 2,000-word room with two players retains and schedules every word; delayed polling completes every turn before finalizing. Public drawing-order responses stay bounded at 12 rows and retain absolute turn positions.
- Old waiting rooms recover their previously sampled bank from the game. New waiting rooms preserve their full snapshot when the source bank changes. Empty legacy banks reject safely without partial writes.
- Current and future words and aliases remain hidden from guessers and spectators. Editing source words does not replace an active schedule.
- Existing quiz, CSV upload, permissions, CSRF, profile/Ready, final Submit, copy links, camp movement, leaderboard and drawing transport regressions passed.

## Score proof

In the seven-word test, each eligible player guessed at one second of a ten-second turn. Each guess earned 910 points and its drawer earned 227 bonus points.

| Player | Drawing turns | Eligible guesses | Guess points | Drawing bonuses | Final total | Rank |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| Alpha | 3 | 4 | 3,640 | 1,362 | 5,002 | 3 |
| Beta | 2 | 5 | 4,550 | 908 | 5,458 | 1 |
| Gamma | 2 | 5 | 4,550 | 908 | 5,458 | 1 |

A separate HTTP test played five words with three players, continued past the first full rotation, and verified saved/exported totals of **1,501**, **2,047** and **550**. The CSV correctly identifies the second player as the winner.

## Limits

Real iPhone/iPad Safari, Android and desktop browsers were not operated. Browser execution remains restricted in this environment. Canvas checks use mocked events and drawing calls; they do not constitute visual verification or guarantee every device.

The application was not run on the user's Windows/Laragon installation or MySQL database. The 2,000-word case verifies session correctness, not concurrent production load or network latency.

## Installation

This fix requires no new migration. Apply the updated files, clear Laravel caches, rebuild frontend assets, and restart the existing scheduler. Already active and completed sessions keep their existing schedules.
