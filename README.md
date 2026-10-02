# Simple Task Tracker

<!-- Replace OWNER/REPO with your GitHub path once the repository is pushed. The workflow it
     points at is .github/workflows/ci.yml, which already runs style, tests and the build. -->
[![CI](https://github.com/OWNER/REPO/actions/workflows/ci.yml/badge.svg)](https://github.com/OWNER/REPO/actions/workflows/ci.yml)

A task tracker built on Laravel 12 and MySQL: a sortable task list, a drag-and-drop board, a
calendar, and a project tree, all driven by a JSON API.

The sorting rule that the brief specifies lives in `src/TaskSorter.php` as plain PHP with no
framework behind it, and the application really uses it — `GET /api/tasks` is ordered by it.

## Requirements

| | |
|---|---|
| PHP | 8.2 or newer, with `pdo_mysql` and `mbstring` |
| Composer | 2.x |
| Node | 20.19+ or 22.12+, with npm (what Vite 7 requires) |
| MySQL | 5.7 / 8.x, or MariaDB 10.4 or newer |

## Setup

```bash
git clone <repository-url> simple-task-tracker
cd simple-task-tracker

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Create the database and point `.env` at it:

```sql
CREATE DATABASE task_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=task_tracker
DB_USERNAME=root
DB_PASSWORD=
```

`utf8mb4` is not optional: a reaction is stored as the emoji itself, and MySQL's older `utf8`
holds three bytes per character, which truncates a four-byte emoji.

Then build the schema and the front end:

```bash
php artisan migrate
php artisan db:seed      # optional: realistic demo tasks and projects
npm run build            # or: npm run dev
php artisan serve
```

Open <http://127.0.0.1:8000>. `/` redirects to `/tasks`, which is the whole application.

## Tests

```bash
php artisan test         # the full suite
./vendor/bin/pint --test # PSR-12 style check
```

Tests run against an in-memory SQLite database configured in `phpunit.xml`, so they need no MySQL
and leave your data alone. The suite covers every endpoint's success path and its 400 and 404
paths, plus the sorting rule on its own.

`tests/TaskSorterTest.php` sits at that exact path because the brief asks for it, and it extends
PHPUnit's own `TestCase` rather than Laravel's, because the class it tests has no framework behind
it.

## API

Every endpoint lives under `/api`, answers JSON, and expects `Accept: application/json`. There is no
authentication: the application has no accounts.

An error is always shaped the same way:

```json
{ "message": "The title field is required.", "errors": { "title": ["The title field is required."] } }
```

### Tasks

| Method | Path | Success | Errors |
|---|---|---|---|
| GET | `/api/tasks` | 200 | 400 invalid filter |
| GET | `/api/tasks/stats` | 200 + counts | none |
| POST | `/api/tasks` | 201 + created task | 400 validation failure |
| GET | `/api/tasks/{id}` | 200 + task with its sub-tasks | 404 not found |
| PATCH | `/api/tasks/{id}` | 200 + updated task | 400 validation failure, 404 not found |
| PATCH | `/api/tasks/{id}/start` | 200 + updated task | 404 not found |
| PATCH | `/api/tasks/{id}/complete` | 200 + updated task | 404 not found |
| PATCH | `/api/tasks/{id}/reopen` | 200 + updated task | 404 not found |
| PATCH | `/api/tasks/{id}/reorder` | 200 + moved task | 400 validation failure, 404 not found |
| POST | `/api/tasks/bulk` | 200 + the ids that changed | 400 validation failure |
| PATCH | `/api/tasks/{id}/schedule` | 200 + updated task | 400 bad date, 404 not found |
| DELETE | `/api/tasks/{id}` | 200 + message | 404 not found |
| PATCH | `/api/tasks/{id}/restore` | 200 + restored task | 404 not found |
| POST | `/api/tasks/{id}/subtasks` | 201 + created sub-task | 400 validation failure, 404 not found |
| PATCH | `/api/tasks/{id}/subtasks/{sub}` | 200 + updated sub-task | 400 validation failure, 404 not found |
| DELETE | `/api/tasks/{id}/subtasks/{sub}` | 200 + message | 404 not found |

### Projects

The interface calls these "projects". The table, the model and the API still say `category`; it is a
rename worth finishing in one pass rather than half-doing.

| Method | Path | Success | Errors |
|---|---|---|---|
| GET | `/api/categories` | 200 + task counts | none |
| POST | `/api/categories` | 201 + created project | 400 validation failure |
| PATCH | `/api/categories/{id}` | 200 + updated project | 400 validation failure, 404 not found |
| PATCH | `/api/categories/{id}/move` | 200 + moved project | 400 bad parent, 404 not found |
| PATCH | `/api/categories/{id}/favorite` | 200 + updated project | 400 validation failure, 404 not found |
| POST | `/api/categories/{id}/duplicate` | 201 + the copy | 404 not found |
| GET | `/api/categories/{id}/activity` | 200 + recent entries | 404 not found |
| DELETE | `/api/categories/{id}` | 200 + message | 404 not found |
| PATCH | `/api/categories/{id}/restore` | 200 + restored project | 404 not found |
| DELETE | `/api/categories/{id}/force` | 200 + message | 404 not found |
| GET | `/api/categories/{id}/comments` | 200 + thread | 404 not found |
| POST | `/api/categories/{id}/comments` | 201 + created comment | 400 validation failure, 404 not found |
| PATCH | `/api/categories/{id}/comments/{comment}/reactions` | 200 + updated comment | 400 validation failure, 404 not found |
| DELETE | `/api/categories/{id}/comments/{comment}` | 200 + message | 404 not found |

A test compares this table against the application's real routes in both directions, so a line
cannot outlive the endpoint it describes.

### Examples

**Create a task**

```http
POST /api/tasks
Content-Type: application/json

{ "title": "Book dentist appointment", "description": null, "priority": "medium", "due_date": "2026-10-08", "category_id": 10 }
```

```json
{
  "data": {
    "id": 7,
    "title": "Book dentist appointment",
    "description": null,
    "priority": "medium",
    "status": "pending",
    "position": 3,
    "due_date": "2026-10-08",
    "is_overdue": false,
    "category": { "id": 10, "name": "Basketball", "icon": "sport-basketball", "color": "slate" },
    "created_at": "2026-09-29T21:19:16+00:00",
    "updated_at": "2026-09-29T21:19:16+00:00"
  }
}
```

`title` is required and trimmed, so a title of only spaces is empty. `priority` must be `low`,
`medium` or `high`. `due_date` is `YYYY-MM-DD` and **cannot be in the past**: a due date is a promise
about work still ahead. A task becomes overdue the ordinary way, by the day arriving and passing.

`status` is deliberately not accepted here — it changes only through the stage endpoints below.

**List tasks**

```http
GET /api/tasks?due=today&priority=high&sort=default&completed=0
```

| Filter | Values |
|---|---|
| `status` | `pending`, `in_progress`, `completed` |
| `category_id` | a project id |
| `due` | `overdue`, `today`, `upcoming`, `none` |
| `from` / `to` | `YYYY-MM-DD`, the calendar's month window |
| `priority` | `low`, `medium`, `high` |
| `sort` | `default`, `due`, `name`, `manual` |
| `completed` | `0` hides finished work; `1` is the same as leaving it out |

`sort=default` is `Src\TaskSorter`. `due`, `name` and `manual` replace it. All four then put To do
before In progress before Done, which is the order the board's columns read, so no sort can bury
live work under finished work.

`status` and `completed` cannot be sent together: "give me completed tasks, but hide completed
tasks" can only ever answer nothing, and an empty list is a worse reply than an error.

**The counts behind the sidebar**

```http
GET /api/tasks/stats
```

```json
{ "data": { "total": 12, "pending": 1, "completed": 11, "high_priority_pending": 0, "overdue": 0, "due_today": 0 } }
```

`pending` counts everything unfinished, To do and In progress alike: starting a task is not
finishing it, so it must not move the number that says how much is left.

**Move a task through its stages**

```http
PATCH /api/tasks/7/start      → status becomes in_progress
PATCH /api/tasks/7/complete   → status becomes completed
PATCH /api/tasks/7/reopen     → status becomes pending
```

Each one writes an entry to its project's activity log. Stage changes have exactly one path through
the code, so a change cannot reach the database without reaching the log.

**Move a card on the board**

```http
PATCH /api/tasks/7/reorder
Content-Type: application/json

{ "status": "in_progress", "after": 12 }
```

A drop says two things: which column the card landed in, and where in that column. `after` is the id
of the task this one should follow, and `null` means the top of the column. It names a task rather
than counting positions, because the display panel can be hiding cards and an index counted on
screen is not a position in the real column.

**Act on a selection of rows**

```http
POST /api/tasks/bulk
Content-Type: application/json

{ "action": "complete", "ids": [7, 12, 19] }
```

```json
{ "message": "2 tasks updated.", "count": 2, "ids": [7, 19], "undo": "reopen" }
```

One action across up to 100 tasks, in one transaction. The actions are `complete`, `reopen`, `delete`
and `restore`.

It answers with the ids that **actually changed**, which is not always the ids it was sent: a task
already in the state being asked for is skipped rather than refused, so selecting three rows where
one is already done completes the other two. Undo sends that shorter list back under the action named
in `undo`, so it never reopens work the user did not touch.

**Schedule or clear a due date**

```http
PATCH /api/tasks/7/schedule
{ "due_date": "2026-10-08" }   or   { "due_date": null }
```

`due_date` must be **present** but may be null: sending null is how the calendar clears a date, while
leaving the key out is a 400 rather than a silent no-op.

**Delete, and undo**

```http
DELETE /api/tasks/7           → { "message": "Task deleted." }
PATCH  /api/tasks/7/restore   → the task again
```

A delete stamps the row rather than removing it, so the toast that follows can offer Undo. Deleting a
project takes its tasks with it, and restoring the project brings them back.

## Choices worth explaining

**Validation errors answer 400, not 422.** The rubric lists 400, and Laravel's default is 422. It is
overridden in one place, `bootstrap/app.php`, rather than per controller.

**The status set is wider than the brief.** The brief pins `pending|completed`; this adds
`in_progress`, so the board's middle column has something to hold. Both original values keep their
meaning: `pending` is still what a task is created with, and `completed` is still the end.

**Four endpoints became more.** `reopen` exists because completing a task by mistake would otherwise
be a dead end, with deleting and retyping the only way back. `restore` is that argument carried to
its end. `start`, `reorder` and `schedule` back the board and the calendar.

**`TaskSorter` knows nothing about status.** It ranks by priority and then by age, exactly as the
brief specifies, and nothing else. Splitting the sorted list into stages happens in the controller,
so the graded class stays untouched while the list still reads To do, In progress, Done.

**The task list is not paginated.** It loads every matching task and orders them in PHP. That is on
purpose: `TaskSorter` is the graded part and it is plain PHP, so the ordering has to happen there
rather than in SQL. For a single-person tracker the cost is nothing; for a shared one it would be the
first thing to change.

**A sub-task is a checklist item, not a task.** It has its own table rather than being a task with a
parent, so no list, count or sort had to learn whether it meant sub-tasks too.

## Project layout

```
src/TaskSorter.php          the brief's sorting rule, plain PHP, no framework
tests/TaskSorterTest.php    its test, at the path the brief asks for
app/Enums/                  every closed set of values: priority, status, colours, icons
app/Http/Requests/          validation, one class per shape of input
app/Http/Resources/         the JSON shape, kept separate from the database columns
resources/js/               vanilla JS, one file per job, no framework
docs/ROLES.md               which senior role owns what, and the standards each works to
docs/UI_UX_RULES.md         the interface rules and the checklist run before any UI change
docs/SPRINT.md              the plan that closed the architecture review
```

## Credits

The sidebar's structure and behaviour follow [shadcn/ui](https://ui.shadcn.com)'s Sidebar (MIT), and
the toasts follow its [Sonner](https://sonner.emilkowal.ski) wrapper (MIT) — both rebuilt for Blade
and vanilla JS rather than installed, since each ships as a React component. Project icons come from Iconify's Fluent UI set (MIT) through
`@iconify/tailwind4`. Everything else is Heroicons or hand-written.

## AI Disclosure

> **This section is a draft and needs your confirmation before you submit.** It describes what
> happened as accurately as I can state it, but you are the only person who can confirm it. Read it,
> correct anything that does not match your memory, and delete this quote block.

**Tool used:** Claude (Anthropic), through Claude Code in the editor.

**What was AI-generated.** Most of the code in this repository was written by Claude during a
conversation in which I described what I wanted, reviewed what came back, and asked for changes.
That covers the Laravel controllers, form requests, resources, models, migrations and enums; the
vanilla JavaScript in `resources/js/`; the Blade views and the Tailwind CSS; the test suite; and the
documents in `docs/`.

**What I directed.** Every feature in the application exists because I asked for it, and several
exist in the shape they do because I disagreed with the first suggestion and said so. Examples: I
rejected a table layout and asked for a kanban board; I asked for the stage buttons to be removed so
that dragging a card is how a task moves; I decided the calendar belongs in the Upcoming view rather
than in the layout switch; I asked for the landing page and the progress meter to be removed; I asked
that a task cannot be created with a due date in the past; and I asked that dropping a card anywhere
in a column, at any height, must work.

**What was found by review rather than written straight.** I asked Claude to review the database and
the backend as a senior architect. That review found a crash when duplicating a project whose copy
name was held by a deleted project, a due-date index that was never used because the column was
wrapped in a function, a request counter kept in the database at the cost of nine extra queries per
call, and documentation describing a feature that had been removed. Each was then fixed, measured and
covered by a test. `docs/SPRINT.md` is the plan those fixes were worked through.

**What I verified by hand.** I ran the application in a browser throughout and reported the problems
I found there — a task that could not be opened from the board, a board whose columns read in the
wrong order, grouping that did nothing in the list layout, and cards that could only be dropped in
one place. Several of those were real defects that the tests had not caught.

**What I understand.** I can explain what every part of this codebase does and why it is written that
way. Where a decision was deliberate and arguable — the wider status set, the 400 instead of 422, the
list that is not paginated — it is written down above rather than left to be discovered.
