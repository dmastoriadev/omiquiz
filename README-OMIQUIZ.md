# Omiquiz — finish rounds when everyone is done

Merge this changed-files update into your existing Omiquiz project. It includes the five-second answer reveal update and the early-finish change together. Upload the `app`, `resources` and `public/build` folders, replacing matching files. Compiled JavaScript, CSS and their font assets are included.

## New round sequence: Quiz and Sketch only

1. Quiz closes as soon as every player has submitted an answer, whether correct or incorrect. Sketch closes as soon as every guesser (excluding the current drawer) has guessed correctly. If someone has not finished, the configured timer still closes the round.
2. The correct answer appears for five seconds.
3. The existing top-five leaderboard appears for seven seconds.
4. The next question/word starts. After the last question/word, final results are saved and displayed instead.

Quiz shows the full correct answer in a large heading, with its option letter above it. Sketch shows the word in a large heading above the actual completed drawing. The same canvas is retained, including accepted strokes, eraser changes and undo history. Drawing tools and guessing controls are hidden during the reveal.

The reveal is shared by player, spectator and host screens. It keeps the emerald/gold theme and Whitney font. Gold, silver and bronze crowns and row highlights remain. Bingo remains manually controlled and has no answer-reveal step.

The server anchors the five-second reveal and seven-second leaderboard to the saved actual close time, including early finishes. The final submission receives the answer reveal immediately; other open screens receive it on their next state poll (normally within one second). The next round receives its full configured timer. Refreshes and repeat polls do not restart either timer. Late submissions cannot score after the answer becomes public. A reconnect receives the remaining portion of the current interval. Final scores and response-time calculations exclude both display intervals.

## Apply on Hostinger / CloudPanel

```bash
cd ~/htdocs/omi.ahrgrpwebdrafts.com
php artisan optimize:clear
```

Refresh all game tabs with Ctrl+F5. If `php artisan schedule:work` is running, stop it and restart it to load the updated timing code. Normal cron invocations load the new code on their next run.

No migration is required. If deploying only the source files instead of the included build, run `npm run build` after merging (Windows PowerShell: `npm.cmd run build`). Existing completed results are retained; in-progress Quiz/Sketch rounds use the new reveal once the update is active and clients are refreshed.

## Validation

See VALIDATION-EARLY-ROUND-CLOSE.md and VALIDATION-ANSWER-REVEAL.md. Timing, secrecy, scoring and drawing persistence passed automated checks; final browser visuals and physical mobile devices were not tested here.
