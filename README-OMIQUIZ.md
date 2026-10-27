# Omiquiz — Slots: six players, no repeat winners

Astoria Slots has a fixed **minimum of six joined players**. The five-roll schedule awards six prizes to **six different people**. Once a participant wins any prize, they cannot receive another prize in that session. The maximum remains configurable, up to 200 players (default 100).

## Prize schedule

| Roll | Award |
| --- | --- |
| 1 | One Black consolation |
| 2 | One Bronze consolation |
| 3 | One Black consolation |
| 4 | One Silver consolation and one Black consolation, to different players |
| 5 | One Gold jackpot |

The three Black recipients and the Bronze, Silver and Gold recipients are all distinct. No prizes are skipped, and no participant receives two awards. The server randomly assigns the six recipients once when the session starts. Refreshes and repeated requests cannot change the saved outcomes. All non-winning machines have non-matching combinations; winning machines have three matching logos.

There are exactly **five rolls**, with no rounds. Every roll waits for everyone to press Ready, then runs a **5-second countdown → 5-second animated spin → 5-second result display**. A winning participant keeps their normal machine, Ready button and animations but receives no additional prize. There is no eligibility notice.

## Existing sessions

Existing waiting rooms keep their join links and participants and now require six players. Previous capacities of two through five are raised to six so enough participants can join. Higher maximum capacities are preserved, including places already occupied by joined participants. Old saved minimums of two or thirty-five do not override the fixed six-player requirement.

An already-running session from the previous small-group release with fewer than six participants cannot satisfy the no-repeat rule. It is stopped on its next poll, Ready action or scheduler update. The host and players receive a notice to create a new session with at least six participants. Its plan and previously revealed history are retained; future reveals and finalization are blocked.

Completed session history remains readable. Already-running older sessions with unique prize recipients continue with their saved outcomes. Other game categories retain their existing rules.

## Links and results

Create an active Astoria Slots game and a session, then share the player join link. The host screen provides a spectator link showing everyone's machine, current winners and award history. Your installed app generates the actual `/watch/{session-slug}` URL.

Each new prize recipient has one final award card and one saved result. The CSV retains `slot_prize`, `slot_won_position` and `slot_awards` for prize details and historical compatibility.

## Install

This archive contains the complete Slots patch, including the original migration and current compiled assets. Merge the included `app`, `database`, `resources`, `routes`, and `public` folders into the existing application. Include `public/build` and its manifest. In the application directory, run:

```bash
php artisan migrate --force
php artisan optimize:clear
```

There is no new database migration beyond the original Slots migration. If Slots was already installed, the migration command has nothing new to apply. Restart any running `php artisan schedule:work` process and refresh open tabs with Ctrl+F5.

On Hostinger/CloudPanel, the project directory is `~/htdocs/omi.ahrgrpwebdrafts.com`. Compiled assets are included; a local build is optional (`npm run build`, or `npm.cmd run build` in PowerShell).

## Logos

The Black logo is included at `/images/logos/AHR-logo-square.webp`. Keep the Gold, Silver and Bronze logos at `/images/logos/AHR-logo-gold.webp`, `/images/logos/AHR-logo-silver.webp` and `/images/logos/AHR-logo-bronze.webp`. The existing tinted Black-logo fallback remains available when a medal image cannot load.

See `VALIDATION-SLOTS.md` for verification and environment limits.
