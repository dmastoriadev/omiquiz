# omiquiz schema and model relationships

The existing database name is `omiquiz`. All seven tables below have a bigint
primary key named `id` and Laravel `created_at` / `updated_at` timestamps. Foreign
IDs are unsigned bigints. Session, participant, answer, and result timestamps
preserve milliseconds. Question and result data is stored in relational tables;
Laravel's browser-session table is separate from `game_sessions`.

## 1. games — reusable game definitions

Migration: `2026_10_23_000001_create_games_table.php`

| Column | Type / behavior |
| --- | --- |
| `created_by` | Nullable user foreign key; becomes null if that account is deleted. |
| `title` | String, 180 characters. |
| `slug` | Unique string, 220 characters; generated once by the model. |
| `description` | Nullable text. |
| `min_players` | Unsigned small integer; default 1. |
| `max_players` | Unsigned small integer; default 50. |
| `time_limit_per_question` | Unsigned small integer, in seconds; default 30. |
| `status` | String; default `draft`. Model constants: `draft`, `active`, `archived`. |
| `deleted_at` | Nullable soft-deletion timestamp. |

`Game` uses soft deletes, casts settings to integers, generates a title-based
slug with a UUID suffix, and binds routes by `slug`. Renaming the title does not
regenerate its slug. `active()` filters by the active status.

## 2. questions — the editable question bank

Migration: `2026_10_23_000002_create_questions_table.php`

| Column | Type / behavior |
| --- | --- |
| `game_id` | Game foreign key. A permitted hard deletion of the game cascades to its source questions. |
| `question_text` | Text. |
| `options` | JSON object keyed by answer identifier. |
| `correct_option` | String, 32 characters; identifies an option key. |
| `explanation` | Nullable text. |
| `points` | Unsigned integer; default 1000. |
| `position` | Unsigned integer; default 0; question-bank sort order. |
| `is_active` | Boolean; default true. |
| `deleted_at` | Nullable soft-deletion timestamp. |

Example stored options: `{"A":"Manila","B":"Cebu","C":"Davao"}` with
`correct_option` set to `A`. `Question` casts options to an array and hides
`correct_option` and `explanation` from ordinary array/JSON serialization.
`active()` filters enabled questions. CSV importing will populate these columns
after validating the entire upload; it is not implemented by this model.

## 3. game_sessions — each playable run

Migration: `2026_10_23_000003_create_game_sessions_table.php`

| Column | Type / behavior |
| --- | --- |
| `game_id` | Game foreign key; prevents hard deletion of a game referenced by a session. |
| `hosted_by` | Nullable user foreign key; becomes null if the host account is deleted. |
| `slug` | Unique string, 64 characters; generated as a UUID by the model. |
| `game_title` | Copied title, 180 characters. |
| `min_players`, `max_players` | Copied unsigned small-integer player limits. |
| `time_limit_per_question` | Copied unsigned small-integer time limit, in seconds. |
| `status` | String; default `waiting`. Constants also include `in_progress`, `completed`, `cancelled`. |
| `current_question_position` | Unsigned integer; default 0 means no question has been opened. |
| `total_questions` | Unsigned integer; default 0. |
| `started_at`, `completed_at`, `cancelled_at` | Nullable datetimes with millisecond precision. |

`GameSession` binds routes by its generated slug. `completed()` requires both
`status = completed` and a non-null completion timestamp. Settings are stored on
the session so later edits to the game definition need not affect a running or
historical session. The future session service must actually copy those settings.

## 4. game_session_questions — questions as played

Migration: `2026_10_23_000004_create_game_session_questions_table.php`

| Column | Type / behavior |
| --- | --- |
| `game_session_id` | Session foreign key with restricted deletion. |
| `question_id` | Nullable source-question foreign key; null on source hard deletion. |
| `position` | Unsigned integer; unique within the session. The service should assign positions starting at 1. |
| `question_text`, `options`, `correct_option`, `explanation` | Copies of the question content and answer key. |
| `points` | Copied unsigned integer point value. |
| `time_limit_seconds` | Copied unsigned small-integer timer value. |
| `opened_at`, `deadline_at`, `closed_at` | Nullable datetimes with millisecond precision. |

The model casts JSON and dates and hides the answer key/explanation. These stored
copies preserve what players saw when source questions change. They are not
automatically immutable: the next-phase service must prohibit editing snapshots
after play starts. Deadlines are persisted for server-side timing enforcement.

## 5. game_session_players — guest or registered participants

Migration: `2026_10_23_000005_create_game_session_players_table.php`

| Column | Type / behavior |
| --- | --- |
| `game_session_id` | Session foreign key with restricted deletion. |
| `user_id` | Nullable user foreign key; null on account deletion. |
| `public_id` | Unique UUID; generated by the model. |
| `resume_token_hash` | Nullable unique 64-character hash; never the raw reconnect token. |
| `display_name` | String, 120 characters. |
| `email` | Nullable string, 254 characters. |
| `status` | String; default `joined`. Constants also include `playing`, `finished`, `left`. |
| `joined_at` | Required datetime; initialized by the model when omitted. |
| `last_seen_at`, `finished_at`, `left_at` | Nullable datetimes. |

The composite unique key `(game_session_id, user_id)` limits an account to one
participation per run while nullable user IDs allow multiple guests. Email and
resume-token hashes are hidden from ordinary serialization. Token generation,
verification, session access, and maximum-player enforcement are future service
responsibilities; a UUID alone is not participant authentication.

## 6. game_answers — the answer audit trail

Migration: `2026_10_23_000006_create_game_answers_table.php`

| Column | Type / behavior |
| --- | --- |
| `game_session_id` | Session identity, enforced through the composite foreign keys below. |
| `game_session_player_id` | Participant identity. |
| `game_session_question_id` | Played-question identity. |
| `selected_option` | Nullable string, 32 characters; null can record a timeout. |
| `is_correct` | Boolean; default false. |
| `points_awarded` | Unsigned integer; default 0. |
| `response_time_ms` | Unsigned bigint; default 0. |
| `answered_at` | Nullable datetime; null for a timeout with no submitted answer. |
| `recorded_at` | Required datetime. |

The unique participant/question pair allows only one answer row for that
combination. Composite foreign keys require both the participant and played
question to belong to the answer's session. The database rejects cross-session
associations. Correctness, points, and elapsed time must be computed on the server,
never accepted from a player's request. Timing rules and duplicate-submit behavior
will be implemented by the service.

## 7. game_results — finalized scores and rankings

Migration: `2026_10_23_000007_create_game_results_table.php`

| Column | Type / behavior |
| --- | --- |
| `game_session_id` | Session identity, checked with the participant through a composite foreign key. |
| `game_session_player_id` | Unique participant identity: at most one final result per participation. |
| `player_name`, `player_email` | Copied name (120 characters) and optional email (254 characters). |
| `total_score` | Unsigned bigint; default 0. |
| `correct_answers`, `wrong_answers`, `unanswered_questions` | Unsigned integer counts; default 0. |
| `total_questions` | Unsigned integer; default 0. |
| `total_response_time_ms` | Unsigned bigint; default 0. |
| `rank` | Required unsigned integer; duplicate ranks are permitted for ties. |
| `finalized_at` | Required datetime with millisecond precision. |

`GameResult::winners()` filters rows with rank 1; it does not calculate rankings.
Email is hidden from normal serialization. Session/rank and session/score indexes
support reporting queries. Completion must calculate results for every participant
and mark the session completed within one transaction. That operation is not yet
implemented, so setting a status alone does not generate results.

## Eloquent relationship map

These are the actual methods in the included models.

| Model | Relationships |
| --- | --- |
| `User` | `quizGames()` hasMany Game; `hostedQuizSessions()` hasMany GameSession; `quizParticipations()` hasMany GameSessionPlayer. |
| `Game` | `creator()` belongsTo User; `questions()` hasMany Question; `sessions()` hasMany GameSession. |
| `Question` | `game()` belongsTo Game; `sessionQuestions()` hasMany GameSessionQuestion. |
| `GameSession` | `game()` belongsTo Game; `host()` belongsTo User; `questions()` hasMany GameSessionQuestion; `players()` hasMany GameSessionPlayer; `answers()` hasMany GameAnswer; `results()` hasMany GameResult. |
| `GameSessionQuestion` | `session()` belongsTo GameSession; `question()` belongsTo optional source Question; `answers()` hasMany GameAnswer. |
| `GameSessionPlayer` | `session()` belongsTo GameSession; `user()` belongsTo optional User; `answers()` hasMany GameAnswer; `result()` hasOne GameResult. |
| `GameAnswer` | `session()` belongsTo GameSession; `player()` belongsTo GameSessionPlayer; `question()` belongsTo GameSessionQuestion. |
| `GameResult` | `session()` belongsTo GameSession; `player()` belongsTo GameSessionPlayer. |

Historical parent relationships use `withTrashed()` where a game or source question
can be soft-deleted. Deletion restrictions on session data protect dependent
answers/results. Source-question hard deletion leaves the played snapshot intact.

## Reporting query example

This reads already-finalized records; it does not execute or finish a game.

```php
use App\Models\GameSession;

$session = GameSession::query()
    ->completed()
    ->with('results')
    ->where('slug', $sessionSlug)
    ->firstOrFail();

$rankedResults = $session->results; // Ordered by rank, then result ID.
$winners = $session->results()->winners()->get();
```

The future administrator export can select player name, optional email, score,
rank, answer counts, response time, and finalization time directly from these
records. It must authorize access and explicitly select hidden email fields when
needed. CSV rendering and spreadsheet-formula escaping belong in that export.

## Rules to implement with the controllers and session service

The schema prepares storage and relational constraints. The following business
rules are not enforced by the models alone:

- Require `1 <= min_players <= max_players` and a positive question time limit.
- Validate question options and ensure `correct_option` is an available key.
- Require an appropriate game status and usable questions before creating a run.
- Copy only questions belonging to the session's game into its snapshots.
- Lock the session while admitting players so simultaneous joins cannot exceed capacity.
- Start only when the minimum player count is met and a session is waiting.
- Restrict answers to the authenticated participant, current question, and server deadline.
- Handle repeated answer submissions and repeated finish requests without duplicate scoring.
- Calculate scores and ranks from stored answers and save all results atomically.
- Move `waiting` to `in_progress` and then `completed`, or cancel an unfinished run.
- Restrict reports and exports to authorized administrators and completed sessions.

No new database migration is required to implement these behaviors on the current
schema. Any later schema changes should use new additive migrations rather than
editing already-applied migrations.
