# Omiquiz — silver and bronze leaderboard highlights

Quiz and Sketch & Guess now highlight rank 2 with a silver background, border, glow and rank/score colors, and rank 3 with bronze. The existing gold winner treatment remains. Both between-round and final leaderboards use these styles. Rank-change glows match the medal color, and the player’s own-row outline remains visible.

Colors follow the displayed competition rank, including ties. Bingo styling and all gameplay rules are unchanged.

## Install

Merge the `resources` and `public/build` folders into your existing project, replacing matching files. Compiled frontend assets are included.

```bash
cd ~/htdocs/omi.ahrgrpwebdrafts.com
php artisan optimize:clear
```

Refresh with Ctrl+F5. No migration is required. If deploying source only, run `npm run build` (Windows PowerShell: `npm.cmd run build`).

## Validation

Blade compilation and the production build passed. Source review confirmed medal/rank/category scoping, matching rise-animation colors, retained own-player outlines and unchanged Bingo styling. Browser visuals were not verified here.
