# omiquiz — database and Eloquent model review

This package contains the seven quiz migrations and eight Eloquent models from
the cleaned omiquiz application, plus a field and relationship reference.

## Your current installation

These PHP files are already included in the cleaned folders you installed.
**There are no new schema or model changes in this review package.** You do not
need to copy these files again or rerun the cleanup installer.

From `C:\laragon\www\omiquiz`, check the existing migrations:

```powershell
php artisan migrate:status
```

The quiz migration filenames start with `2026_10_23_000001` through
`2026_10_23_000007`. If they are marked `Ran`, this phase is installed. If they are
still pending and your connection points to the existing `omiquiz` database, run:

```powershell
php artisan migrate
```

Do not rename the migration files, create another copy under a nested migrations
folder, or use `migrate:fresh`. This package does not create the database, drop old
tables, seed accounts, or alter your `.env`.

## Included files

| Location | Contents |
| --- | --- |
| `database/migrations/` | The seven existing quiz migrations, with their original filenames. |
| `app/Models/` | `Game`, `Question`, `GameSession`, `GameSessionQuestion`, `GameSessionPlayer`, `GameAnswer`, `GameResult`, and the current `User`. |
| `SCHEMA-AND-RELATIONSHIPS.md` | Tables, columns, relationships, constraints, and the rules needed by the next implementation phase. |

The existing `users` table and its role column are prerequisites. Their framework
migrations are already in your project and are not duplicated here. The included
`User.php` is a reference copy of the cleaned model; preserve any subsequent
customizations you have made to your installed model.

## Scope of this phase

The models generate unique game/session identifiers, define relationships and
casts, and provide query scopes. The schema stores the data needed for game
settings, imported questions, live sessions, participants, answers, and results.

This package does not yet start games, accept players or answers, enforce timers,
calculate scores, render game-management screens, import CSV files, or export
results. Those behaviors need the admin controllers, routes, authorization,
validated requests, and transactional game service in the next phase. A stored
session slug becomes a working join URL when the player route is implemented.

The storage design assumes single-answer multiple-choice questions. Players may
join as guests; accounts are optional. Scores and question deadlines will be
controlled by the server. Results retain rank and player details for reporting;
equal ranks are allowed so the scoring rules can represent ties.

## Verification

All 15 PHP files in this review package are byte-identical to the current cleaned
application and its previously validated fixture. The existing 77 schema/model
assertions therefore remain applicable; they were not rerun for this unchanged
copy. The original checks used PHP 8.3.6, Laravel 12.70.1, and SQLite 3.45.1 with
foreign keys enabled. They covered relationship mappings, casts, identifier
generation, duplicate prevention, session boundaries, snapshots, deletion rules,
hidden fields, and ties.

MySQL and your exact installed dependency versions have not been executed here.
Gameplay concurrency and business-rule enforcement belong to the next phase and
have not yet been implemented or tested.
