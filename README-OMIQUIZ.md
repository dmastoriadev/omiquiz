# Omiquiz — 5×5 Reverse Bingo update

Merge this ZIP into your existing Omiquiz project, replacing matching files. It assumes the earlier Reverse Bingo update is installed. This is a changed-files patch, not a full project.

## Apply on Hostinger / CloudPanel

```bash
cd ~/htdocs/omi.ahrgrpwebdrafts.com
php artisan migrate --force
php artisan optimize:clear
```

Compiled frontend assets are included in `public/build`; upload that folder along with the other files. If you build from source instead, run `npm run build` after merging the source. Refresh host, player and spectator tabs after deployment. The migration adds nullable card cells and preserves existing data.

## Apply locally with Laragon

```powershell
cd C:\laragon\www\omiquiz
php artisan migrate
php artisan optimize:clear
npm.cmd run build
```

## Configure and play

1. Open Admin → Games and create or edit a Reverse Bingo game.
2. Set minimum players from 2 upward and maximum players up to 32. Existing limits are retained until you edit them.
3. Set First possible elimination to 3 and Maximum plays to 20 for the requested sequence.
4. Create a session, share its player and spectator links, and wait for the players.
5. Start the game from the admin session page. Three distinct 5×5 BINGO cards per player are generated and saved at this point.
6. Press Reveal first number, then Reveal next number for each subsequent play. The host controls every reveal. The form also works when the latest JavaScript has not loaded.

## Full cards and elimination rules

Each card has 24 distinct numbers and a FREE center. Columns follow B=1–15, I=16–30, N=31–45, G=46–60 and O=61–75. The cards use the existing emerald-and-gold interface and Whitney font.

Any called number anywhere on a card closes the **entire card**. Matching cells are marked, the card is visibly disabled, and that player stays active until all three cards close. The FREE center cannot eliminate a card. Numbers can appear on several players’ cards, but the complete number set of every card is distinct within the session.

The server randomly assigns the cards, draw sequence and elimination order together when the game starts. The host controls when to reveal a number, without selecting the number or winner. This is a planned survival game; independent random card generation and independent draws could not guarantee the requested finish.

For 32 players and 20 plays:

| Play | Players entering | Players after the reveal |
| --- | ---: | ---: |
| 18 | 4 | 3 |
| 19 | 3 | 2 |
| 20 | 2 | 1 winner |

At the default earliest elimination of 3, earlier player eliminations are spread across plays 3–17 in groups of one or two. Shorter games or a later first elimination may require larger groups. The admin forecast displays the counts.

Exactly one untouched 5×5 card survives after the final draw. None of its 24 numbers has been called; every other card is closed. A single winning number is no longer the win condition. Final results show the surviving board, the top five and every other placement. The existing CSV export and saved scoring remain available.

Maximum plays for new 5×5 sessions is 50. This reserves at least five uncalled numbers per column so a complete untouched winning card can exist. The configured first elimination must be at least 3 and at most maximum plays minus 3. Existing games configured above 50 need their setting reduced before starting a new session; running legacy sessions keep their original sequence.

## Existing rooms and mobile layout

New sessions and waiting sessions started after this update receive full cards. Already-running games keep their original single-number cards and finish normally; create a new session to use 5×5 cards. Completed records are preserved.

Own cards appear first and are highlighted. On phones, swipe horizontally within each player’s row to see all three readable cards; scroll vertically to see the other players. Spectators see the same cards and live closures. The host controls and numbers are present in server-rendered HTML as a fallback.

Validation details are in VALIDATION-BINGO-GRID.md. Browser rendering is blocked in this environment, so physical iOS Safari and Android Chrome layout/gesture checks remain unverified.
