# Omiquiz — Drawer-controlled letter hints

This incremental update contains only changed/new files. Merge its folders into the existing project, replacing matching files. Keep the previous Sketch & Guess, avatar, Whitney, public-guesses and Undo updates installed.

## Apply on Hostinger / CloudPanel

```bash
cd ~/htdocs/omi.ahrgrpwebdrafts.com
php artisan migrate --force
php artisan optimize:clear
npm run build
```

Refresh the host, player and spectator tabs with Ctrl+F5. The new additive migration stores the hint setting, the saved word mask and the reveal time. Existing game data is retained.

If you build locally, upload the newly generated public/build folder together with the updated source, then run the migration and cache-clear commands on the live server.

## Apply locally with Laragon

```powershell
cd C:\laragon\www\omiquiz
php artisan migrate
php artisan optimize:clear
npm.cmd run build
```

## Set the maximum letters

1. Open Admin → Games and edit your Sketch & Guess game.
2. Set Maximum hint letters to 2, or another whole number from 0 to 100. A value of 0 disables hints.
3. Save, then start a new or waiting session.

The setting applies to all words in that sketch game. Waiting rooms use the latest saved setting when started. Active and completed sessions retain their existing settings; start a new session to use hints for a game already in progress.

## Share a hint

The player who is drawing has a small Hint button. They can press it before their first stroke, while sketching, or after finishing their drawing, while the turn timer is still running. It does not require a drawing to exist or the canvas to finish loading.

Pressing Hint shares the same masked word with everyone through the existing state polling. For Shark with a maximum of 2, one possible hint is S_a__. The chosen positions are random and stay fixed for that turn. Repeated clicks, retries and refreshes do not reveal extra letters or change the hint.

At least one letter stays hidden, so short words reveal fewer letters than the configured maximum. Spaces and punctuation remain readable. Accented letters and other Unicode letters are handled as whole characters. Prompts with no letters or numbers have no letter hint.

The hint stays private until the drawer presses the button. Only the current drawer can reveal it; guessers, spectators and the host cannot trigger it. When the timer expires, the usual answer reveal and leaderboard continue.

## Verification

See VALIDATION-SKETCH-HINTS.md for test results and environment limits.
