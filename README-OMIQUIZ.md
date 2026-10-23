# Omiquiz — Reverse Bingo

This update adds Reverse Bingo beside Quiz and Sketch & Guess. It contains only changed/new files and assumes the earlier gameplay updates, including sketch hints, are already installed. Merge the folders into your existing Omiquiz project and replace matching files.

## Apply on Hostinger / CloudPanel

```bash
cd ~/htdocs/omi.ahrgrpwebdrafts.com
php artisan migrate --force
php artisan optimize:clear
npm run build
```

The new migration is additive. Existing games, sessions and results are retained. Refresh host, player and spectator tabs after deployment. If building locally, upload the generated public/build folder with the source, then run the migration and cache-clear commands on the server.

## Apply locally with Laragon

```powershell
cd C:\laragon\www\omiquiz
php artisan migrate
php artisan optimize:clear
npm.cmd run build
```

## Create and host a game

1. In Admin → Games → Create, choose Reverse Bingo.
2. Set minimum and maximum players, from 2 to 30. The latest requested cap of 30 applies to this category.
3. Set First possible elimination (play), default 3 and Maximum plays (default 20). The first setting controls the earliest player knockout; the maximum is the exact final draw. Maximum plays can be 6–74, and the minimum must be at least 3 and at most maximum minus 3.
4. Set the game to Active and create a session. No question or word upload is required.
5. Share the player and spectator links. Players join with a name, gender and generated avatar, and can edit their name or mark Ready in the existing camp lobby.
6. Start when enough players have joined. Three numbered cards per player are assigned and saved at this point.
7. Press Reveal next number in the admin session to advance each play. There is no automatic draw, countdown or scheduled advancement. The saved game resumes at the same draw after a refresh or reconnection.

## Cards, forecast and winner

Each card holds one number from 1–75, rather than a traditional 5×5 grid. A player's three numbers are distinct; numbers can be shared between different players. A revealed number closes every matching card. A player is eliminated when all three cards are closed.

The server randomly assigns the complete draw order and player elimination order when the session starts. The host controls when the next number is revealed, without choosing its number or winner. This is a planned survival game: the outcome is randomized at the start, rather than each draw being independently random.

Exactly one player retains exactly one card after the final draw. That remaining number belongs only to the winner and is never called. Every other player's cards are closed. Numbers are not repeated. New arrivals cannot join after start.

For at least four players and a maximum of 20 plays:

| Draw | Players entering the draw | Players after the draw |
| --- | ---: | ---: |
| 18 | 4 | 3 |
| 19 | 3 | 2 |
| 20 | 2 | 1 winner |

With 30 players, minimum 3 and maximum 20, the earlier knockouts are spread across draws 3–17 in groups of one or two. Shorter schedules or a later first knockout can require larger groups. The admin forecast shows the exact projected player counts for the joined roster, without exposing future numbers or player identities. Two- and three-player games also finish with one winner on the configured final draw.

Spectators and players see the revealed number, draw history, active players and their three cards. Players can identify their own highlighted avatar and cards. Closed cards are visibly disabled. The final screen features the top five and all remaining placements.

Placements follow survival order. Players eliminated on the same draw use a random tie order fixed when the game starts. Survival points equal the elimination draw; the winner gets maximum plays plus one. Results are stored for every player and are available in the admin reports and CSV export, including cards remaining and elimination draw.

## Mobile and verification

The board uses responsive layouts, touch-sized controls, text labels alongside color, and reduced-motion support. No hover, dragging or canvas interaction is needed to play Bingo.

See VALIDATION-REVERSE-BINGO.md for automated checks and environment limits. Physical iOS Safari and Android Chrome testing is not available in this workspace.
