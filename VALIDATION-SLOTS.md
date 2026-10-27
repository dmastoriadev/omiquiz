# Slots unique-winner restoration — validation

The update was checked with PHP 8.3, Laravel 12, isolated SQLite databases and Node.js. No live application, database or server settings were changed.

- **150,159 independent assertions** passed across 120 plans for 6, 35 and 200 players (48,200 player-roll outcomes). They verify exactly five rolls, prize counts `[1,1,1,2,1]`, six unique recipients, three Black prizes, one medal of each color, no accidental triples, rejection of fewer than six players and invalid/repeated saved awards, and historical plan compatibility.
- **543 service/presenter checks** passed for complete six/35-player lifecycles, exact Ready and five-second phases, minimum/capacity repair, every type of duplicate-prize corruption, and old session behavior. Active two-through-five-player v4 games stop through presenter, advance and Ready paths; cancellation persists before an error response, retains prior reveals, freezes visibility, blocks future Ready/reveals/finalization, and never rerolls. Completed old histories and compatible running plans remain intact.
- **15 additional independent migration checks** passed for old small-session cancellation, immutable plans/timestamps, stable cancelled_at and no later award leaks.
- **38 HTTP checks** passed for admin settings, old two-player waiting-room repair, five-player start rejection, six-player start, six unique planned winners, active-plan preservation, invalid capacities, and clean retirement of an old repeating game. Host, public and participant pages remain readable after retirement.
- **321 HTTP checks** passed through a complete six-player/five-roll game; **930 HTTP checks** passed through a complete 35-player/five-roll game. They cover all Ready gates, timing, public machines, no future-result leaks, exact prize counts, six distinct winners, one final Gold champion, persistence, CSV export and sixth-roll rejection. These CLI suites use extra memory for repeated Laravel kernel boots; they are not load tests.
- **261 Slots interface assertions and 85 lobby assertions** passed for restored minimum labels/inputs, maximum-capacity floors, count updates, winner/history keys, the three restart-notice surfaces, and retained completed historical results. Nine related Blade templates compiled and passed PHP syntax checks.
- All four changed production PHP files passed syntax checks. The Vite production build passed. The archive was compared with the tested source and current build manifest.

No new database migration is needed. New and waiting rooms require six players. Old running small-group games are stopped rather than continuing their repeating-award schedule. Completed records are preserved.

Mutations retain game-before-session lock ordering and current locking roster reads. SQLite tests do not simulate simultaneous MySQL requests. Browser/mobile rendering remains unverified because the environment blocks the installed browser's socket access.

The Black logo is included. Gold, Silver and Bronze use the requested existing image paths with the existing tinted Black-logo fallback.
