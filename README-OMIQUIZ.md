# Omiquiz — podium crowns and decoration cleanup

Merge this changed-source update into your existing Omiquiz project. Upload `resources` and `public/build`, replacing matching files.

## Changes

- Quiz and Sketch & Guess: rank 1 has a gold crown, rank 2 silver, and rank 3 bronze.
- Applies to both the seven-second leaderboard between rounds and the completed-game standings, for host, player and spectator views.
- Crowns follow the displayed rank. Existing exact ties keep the same rank and crown color; scoring and tie rules are unchanged.
- Bingo keeps its existing winner-only crown.
- Removed the four-point star decorations from the camp lobby, between-round background and transition, and final results in every category.

## Apply

```bash
cd ~/htdocs/omi.ahrgrpwebdrafts.com
php artisan optimize:clear
```

Compiled frontend assets are included in `public/build`, including the updated stylesheet. Refresh with Ctrl+F5. No migration is needed. If you deploy only source files, run `npm run build` (or `npm.cmd run build` in Windows PowerShell).

## Validation

Blade compilation and the Vite production build passed. A source audit confirmed the crown colors and category/rank conditions, retained Bingo's winner-only crown, and verified that all five decorative star occurrences were removed. Actual browser rendering was not verified in this environment.
