# Senior Roles

Every task in this project is handled by the senior role that owns that area. Pick the role from the routing table, work to its standards, and check its Definition of Done before calling the task finished.

When a task spans more than one area (e.g. a new endpoint plus the UI that calls it), work through the roles in order: **Database → Backend → QA → Frontend → UI/UX → DevOps → Code Review → Docs**.

Start every response that changes code with one line naming the active role(s), e.g. `Role: Senior Backend Engineer`.

## Routing table

| Task / files touched | Role |
|---|---|
| `src/TaskSorter.php`, framework-free PHP logic | [Senior PHP Engineer](#1-senior-php-engineer) |
| `app/Enums/**` | [Senior Database Engineer](#3-senior-database-engineer) |
| `tests/**`, `phpunit.xml` | [Senior QA / Test Engineer](#2-senior-qa--test-engineer) |
| `database/migrations/**`, `app/Models/**`, seeders, factories | [Senior Database Engineer](#3-senior-database-engineer) |
| `routes/api.php`, `app/Http/**`, `bootstrap/app.php` | [Senior Backend Engineer (Laravel API)](#4-senior-backend-engineer-laravel-api) |
| `resources/views/**`, `resources/js/**`, `resources/css/**` | [Senior Frontend Engineer](#5-senior-frontend-engineer) |
| Layout, visual design, states, accessibility, copy | [Senior UI/UX Designer](#6-senior-uiux-designer) |
| Commits, formatting, reviews, removing dead code | [Senior Code Reviewer](#7-senior-code-reviewer) |
| `README.md`, setup steps, AI Disclosure | [Senior Technical Writer](#8-senior-technical-writer) |
| `.github/workflows/**`, `pint.json`, build and CI tooling | [Senior DevOps Engineer](#9-senior-devops-engineer) |

---

## 1. Senior PHP Engineer

**Owns:** Part 1, `src/TaskSorter.php`.

**Standards**
- Plain PHP only. No Laravel helpers (`collect()`, `Carbon`, `Arr::`, `Str::`) inside `src/`.
- Namespace `Src`, which is autoloaded from `src/` through `composer.json`.
- Signature exactly: `public function sortTasks(array $tasks): array`.
- Priority order: `high` > `medium` > `low`, using a single constant map (e.g. `private const PRIORITY_WEIGHT = [...]`) instead of scattered `if`s.
- Tie-break: older `created_at` first. Compare parsed timestamps (`strtotime`), not raw strings.
- Do not mutate the input array; return a new sorted array. `usort` works on a copy because PHP passes arrays by value.
- `declare(strict_types=1);`, typed properties and returns, PSR-12.

**Definition of Done:** the class has no framework dependency, and every rule above is covered by a test.

## 2. Senior QA / Test Engineer

**Owns:** all tests.

**Standards**
- The exam requires the path `tests/TaskSorterTest.php` (not `tests/Unit/`). Register it in `phpunit.xml` so `php artisan test` picks it up.
- `TaskSorterTest` extends `PHPUnit\Framework\TestCase`, not Laravel's `Tests\TestCase`, because the sorter is framework-free.
- Test names describe behaviour: `test_sorts_high_before_medium_before_low`.
- Arrange / Act / Assert, with small fixtures written inline in the test.
- Assert on the resulting order of IDs or titles so a failure message is readable.
- API feature tests (`tests/Feature/`) use `RefreshDatabase` on the in-memory SQLite DB from `phpunit.xml`, and cover every endpoint's success path plus 400/404 paths.
- Remove the Laravel `ExampleTest` placeholders once real tests exist.

**Definition of Done:** `php artisan test` is green, and each test fails if the behaviour it names breaks.

## 3. Senior Database Engineer

**Owns:** schema, `Task` model.

**Standards**
- `tasks` table: `id`, `title` (string, required), `description` (text, nullable), `category_id` (nullable FK), `priority` enum `low|medium|high` (default `medium`), `status` enum `pending|in_progress|completed` (default `pending`), `due_date` (nullable date), `timestamps()`.
- `categories` table: `id`, `name` (unique), `icon` (varchar 40, default `folder`), `timestamps()`. The UI calls these "projects"; the schema has not been renamed.
- `tasks.position` is where a task sits in its board column, backing `TaskSort::Manual` alone. Indexed as `(status, position)`, which is exactly how a column reads. It is not `$fillable`: a client never sets a position directly, it says which card the moved one should follow and the server renumbers the column.
- Index `status` and `due_date`, since the list endpoint filters on both. `deleted_at` is left unindexed: it is NULL for nearly every row, so the index would not pay for itself.
- `tasks` carries `deleted_at` (`softDeletes()`). Deleting is the one irreversible action, so the row is stamped rather than removed and `restore` can bring it back. Every read excludes stamped rows through the trait's global scope, including `stats`, whose `toBase()` applies scopes before dropping to the query builder.
- `tasks.category_id` uses `nullOnDelete`: deleting a category must never delete someone's tasks, it only leaves them uncategorised.
- Never edit a migration that has already run. Schema changes land as a new migration, which is why the category and due-date columns arrive in `add_category_and_due_date_to_tasks_table` rather than in the original `create_tasks_table`.
- Two migrations generated in the same second sort by filename, so a table can end up referenced before it exists. Rename the file rather than rely on luck.
- Allowed values live in PHP backed enums (`App\Enums\TaskPriority`, `App\Enums\TaskStatus`, `App\Enums\CategoryIcon`). The model casts to them, and validation uses `Rule::enum()`. Migrations keep literal values, because a migration is a snapshot of the schema at that point in time.
- `categories.icon` is closed by `App\Enums\CategoryIcon`, never free text: its value goes straight into a CSS class name. The column itself is a `varchar`, because a 224-value database enum would be unreadable, so the application is what enforces the set.
- Model `$fillable` lists the fields a client may send when creating a task: `title`, `description`, `priority`, `category_id`, `due_date`. Every one of them is validated by `StoreTaskRequest`. `status` is deliberately absent, because it changes only through the complete and reopen endpoints.
- `categories` carries `deleted_at` (`softDeletes()`) for the same reason `tasks` does, and `deleted_at` is left unindexed for the same reason too: it is NULL for nearly every row.
- `categories` also carries `description`, `color` (closed by `App\Enums\CategoryColor`), `is_favorite` and a self-referencing `parent_id`. The parent FK is `nullOnDelete`, so deleting a project promotes its children rather than taking them with it.
- There was an `archived_at` column and a pair of endpoints behind it. Both are gone: a soft delete that the sidebar always shows a way back to does the same job, and two kinds of "out of the way" was one too many for a tracker to explain. The column arrives and leaves in two migrations rather than one edited migration, because a migration that has run is a record of what happened.
- A folder and a parent project are **one tree**, not two. A folder is simply a project with children, so "move into folder" and "set parent" are the same operation; building both would be two ways to say the same thing.
- `category_comments` is named for what it comments on, because a bare `comments` table would be ambiguous the day a task gets comments too. It cascades with its project.
- `comment_reactions` holds one row per (comment, emoji, browser). The app has no accounts, so `reactor` is a random token from the client's `localStorage`: it identifies a browser, not a person. The unique index across the three is what makes the count honest and lets the endpoint be retried safely. The emoji column is a `varchar(16)` and needs `utf8mb4` to hold a four-byte emoji at all; MySQL's older `utf8` would truncate it.
- Activity entries live in `activities`: `category_id`, a snapshotted `task_title`, the `action`, and `created_at` alone, because an entry is a fact about a moment and is never edited. The index is `(category_id, created_at)`, which is exactly how the feed reads. The foreign key is `cascadeOnDelete`, not `nullOnDelete` as `tasks.category_id` is: the log is only ever reached through its project, so an orphaned entry could never be read again.
- `TaskSeeder` provides realistic demo data (`php artisan db:seed`).
- Migrations must also run on SQLite, because tests use it. `enum()` works on both.

**Definition of Done:** `php artisan migrate:fresh` runs cleanly on MySQL and in tests.

## 4. Senior Backend Engineer (Laravel API)

**Owns:** Part 2, the REST API.

**Standards**
- Laravel 12 ships without `routes/api.php`. Register it manually in `bootstrap/app.php` (`api: __DIR__.'/../routes/api.php'`) instead of `php artisan install:api`, which pulls in Sanctum, a dependency this project doesn't use.
- Endpoints and status codes:

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
  | PATCH | `/api/tasks/{id}/schedule` | 200 + updated task | 400 bad date, 404 not found |
| PATCH | `/api/tasks/{id}/reorder` | 200 + moved task | 400 validation failure, 404 not found |
  | DELETE | `/api/tasks/{id}` | 200 + message | 404 not found |
  | PATCH | `/api/tasks/{id}/restore` | 200 + restored task | 404 not found |
  | POST | `/api/tasks/{id}/subtasks` | 201 + created sub-task | 400 validation failure, 404 not found |
  | PATCH | `/api/tasks/{id}/subtasks/{sub}` | 200 + updated sub-task | 400 validation failure, 404 not found |
  | DELETE | `/api/tasks/{id}/subtasks/{sub}` | 200 + message | 404 not found |
  | GET | `/api/categories` | 200 + task counts | none |
  | POST | `/api/categories` | 201 + created category | 400 validation failure |
  | PATCH | `/api/categories/{id}` | 200 + updated category | 400 validation failure, 404 not found |
  | PATCH | `/api/categories/{id}/move` | 200 + moved category | 400 bad parent, 404 not found |
  | PATCH | `/api/categories/{id}/favorite` | 200 + updated category | 400 validation failure, 404 not found |
  | POST | `/api/categories/{id}/duplicate` | 201 + the copy | 404 not found |
  | GET | `/api/categories/{id}/comments` | 200 + thread | 404 not found |
  | POST | `/api/categories/{id}/comments` | 201 + created comment | 400 validation failure, 404 not found |
  | PATCH | `/api/categories/{id}/comments/{comment}/reactions` | 200 + updated comment | 400 validation failure, 404 not found |
  | DELETE | `/api/categories/{id}/comments/{comment}` | 200 + message | 404 not found |
  | GET | `/api/categories/{id}/activity` | 200 + recent entries | 404 not found |
  | DELETE | `/api/categories/{id}` | 200 + message | 404 not found |
  | PATCH | `/api/categories/{id}/restore` | 200 + restored project | 404 not found |
  | DELETE | `/api/categories/{id}/force` | 200 + message | 404 not found |

- `GET /api/tasks` accepts `status`, `category_id`, `due` (`overdue`, `today`, `upcoming`, `none`), a `from`/`to` date window for the calendar, and the Display panel's `sort`, `priority` and `completed`. Every one of them is used by the UI; do not add a filter nothing calls.
- `sort` is `App\Enums\TaskSort`: `default` is `Src\TaskSorter`, while `due`, `name` and `manual` replace it. All four then pass through `byStage()`, so no sort can bury live work under finished work.
- `manual` is `tasks.position`: the arrangement someone made by dragging cards on the board. It is the one order a sort cannot work out, which is why it is stored rather than computed. The board is the only thing that asks for it, so `GET /api/tasks` with no `sort` is still `TaskSorter` and Part 1 is still what the app runs.
- `completed=0` is the Display panel's toggle in its off position, and asks for `TaskStatus::unfinished()`. Only the off position narrows anything, so `completed=1` is the same list as omitting the key.
- `PATCH /api/tasks/{id}` is the task dialog: name, description, priority and project, each field optional because the dialog saves one at a time. **It cannot change `status`**, which still moves only through start, complete and reopen, so every stage change stays in the activity log. The due date keeps `schedule`, because the calendar changes it by dragging.
- `subtasks` is its own table, not a self-referencing `tasks.parent_id`. A sub-task is a checklist item on one task and never a row in the list, the board, the calendar or the stats; a self-reference would have made every read and every count ask whether it meant sub-tasks too. They are returned only by `show`, never by `index`.
- `overdue`, `today` and `upcoming` are **work queues**: each one asks for `status` in `TaskStatus::unfinished()`, so completing a task drops it out of the view while starting one does not — the task someone is in the middle of is the one they are most likely looking for. `stats.due_today` carries the same condition, because it is the badge on the Today view and the two must agree. `none` and the `from`/`to` window are **not** queues: they back the calendar's unscheduled tray and its month grid, which show a completed task where it sits.
- `PATCH /api/categories/{id}` replaces the whole project: name, description, colour, icon and parent. Its unique rule ignores the row being edited, or changing only the icon would be a 400 against the project's own name.
- `GET /api/categories` returns the live projects; `?deleted=1` returns the soft-deleted ones instead. The sidebar asks for both, because it shows both and could not tell them apart in one list.
- `favorite` takes the value it is setting rather than toggling, so two clicks racing each other cannot undo one another. The reactions endpoint has the same shape for the same reason.
- The comment thread reads its `reactor` token from the query string and a reaction sends it in the body, so `CategoryCommentResource` uses `input()` rather than `query()`. With `query()` the reply to a reaction reports it as not mine, and only a reload corrects the button.
- Any endpoint returning comments eager-loads `reactions`. A test pins the thread to three queries so an N+1 cannot creep back in.
- `move` takes `parent_id` as `present|nullable`, the same shape as `schedule`: sending `null` moves a project to the top level, while omitting the key is a 400 rather than a silent no-op. `App\Rules\NotItsOwnDescendant` refuses a move that would cut a branch off the tree.
- `DELETE /api/categories/{id}` soft deletes, so the delete toast can offer Undo through `restore`, whose route is bound `->withTrashed()`. Deleting a project used to be the one irreversible action in the app, while deleting a single task inside it could be undone; a project holds tasks, comments, activity and children, so it was the worst thing to lose and the least protected.
- **A project's tasks are soft deleted with it**, in one transaction, and `restore` brings them back. Stamping only the project left its tasks in every list with nothing left to reach them from: duplicating a project and deleting the copy put its tasks — already `completed`, because `duplicate` keeps status — into All tasks as rows nobody had created. Undo restores every task in the project, including one deleted by hand just before it; telling the two deletes apart needs a column remembering which took which row, and restoring one row too many is the kinder way to be wrong than leaving work destroyed.
- No foreign key fires either way, because nothing is removed: the tasks keep their `category_id` and the children keep their `parent_id` while the rows are hidden. That is what makes a restore put the branch back exactly where it was. The children are not deleted with the project — they are separate projects, drawn as roots while their parent is hidden.
- A deleted project keeps its **name reserved**, because the unique index spans the stamped rows. That is the cost of the guarantee that Undo always works: the alternative is a restore that fails because something else took the name in the meantime.
- `duplicate` copies tasks with `replicate()`, not `create()`: `status` is deliberately not fillable, and a copy has to keep it. The copy is top-level and never a favourite, because both describe where a project sits rather than what it holds.
- Activity is written from the task endpoints, not from model events, so seeding does not fill the log. A task with no project records nothing: the feed is only reachable from a project's menu, so the entry could never be read. The task's title is snapshotted onto the entry, because "Deleted X" has to still read correctly once the task is gone.

- `schedule` takes `due_date` as `present|nullable`, so sending `null` is how the UI clears a date, while omitting the key is a 400 rather than a silent no-op.
- `reorder` backs a board drop, which says two things at once: which column the card landed in and where in that column. It takes `status` (required) and `after` (`present|nullable`), the id of the task the moved one should follow, with `null` for the top of the column.
- **`after` names a task rather than counting one.** An index would be counted against the cards the client drew, and a filter can hide some of them, so index 1 on screen is not position 1 in the column. An anchor the column no longer holds — another tab finished it between the drag and the drop — puts the card at the end rather than losing the move.
- The whole column is **renumbered** on every drop rather than the neighbours being nudged, so positions cannot drift into ties or gaps however many times a card is dragged. The siblings move through the query builder, so a renumber does not stamp every one of them as updated.
- **`reorder` changes a stage through `setStage()`**, the same private method `start`, `complete` and `reopen` use. There is still one path to a stage change, so widening a drop into a reorder did not give the activity log a fourth way to miss one. A drop inside one column records nothing, because rearranging is not a stage change.
- A new task joins the **end** of its column. That happens in the model rather than the controller, so the factory and the seeder are covered too and no creation path leaves a column with several tasks all claiming position 0.
- **A due date cannot be set in the past.** Both `store` and `schedule` carry `after_or_equal:today`, because a due date is a promise about work still ahead. `schedule` needs it as much as `store` does: without it, creating a task with no date and dragging it onto a past day would be the way around the rule. A task still becomes overdue the ordinary way, by the day arriving and passing, and a row that is already overdue can still be moved forward. Factories and the seeder write the model directly, so they can still place a task in the past for the Overdue view to have something to show.

- Validation errors return **400** because the exam rubric lists 400. Laravel's default is 422, so override it in one place (a Form Request `failedValidation`, or the exception handler) and document the choice in the README.
- Every error is JSON: `{ "message": "...", "errors": { ... } }`. No HTML error pages from `/api/*`.
- Validation: `title` required|string|max:255, trimmed (whitespace-only is empty); `description` nullable|string; `priority` required|in:low,medium,high.
- Use a Form Request for create validation and route model binding for `{task}` (gives 404 for free).
- `GET /api/tasks` returns tasks ordered with `Src\TaskSorter`, so Part 1 is actually used by the app. The controller then moves pending tasks ahead of completed ones, keeping each group in TaskSorter's order. `TaskSorter` itself stays exactly as the exam specifies (priority, then oldest first) and must never learn about status.
- **The status set is wider than the brief.** The exam pins `pending|completed`; this adds `in_progress` so the board's middle column has something to hold. It is a deliberate deviation and belongs in the README's disclosure. Both original values keep their meaning: `pending` is still the default a task is created with, and `completed` is still the end. `start` moves a task into the middle; `reopen` is the way back out of either later stage.
- Status is never part of `PATCH /api/tasks/{id}`. The board's drag and the dialog's Status field both call `start`, `complete` or `reopen`, so there is one path to a stage change and the activity log cannot miss one.
- `TaskStatus::unfinished()` is what every "not done yet" condition asks, rather than each one naming `pending` itself. The stats' `pending` count, the three work queues and the list's ordering all use it, so none of them can disagree about what unfinished means.
- `byStage()` orders the list To do, then In progress, then Done, keeping each group in TaskSorter's order. That is the same order the board's columns read left to right: they are the same three stages, and work does not run backwards through them. **`TaskSorter` still knows nothing about status** — the split is in the controller, so Part 1 stays exactly as the exam specifies.
- `reopen` is beyond the exam's four endpoints. It exists because completing a task by mistake would otherwise be a dead end, with deleting and retyping the only way back.
- `restore` is the same argument carried to its end. `DELETE` soft-deletes, so the row survives and the delete toast can offer Undo. Its route is bound `->withTrashed()`, because the default binding hides exactly the task it needs to reach. Deleting an already-deleted task is a 404, since the binding no longer finds it.
- Controllers stay thin. No business logic in routes.
- Responses go through `TaskResource` / `CategoryResource`, so the JSON shape is explicit and separate from the database columns.
- Any endpoint returning tasks eager-loads `category`. A test pins the list to two queries so an N+1 cannot creep back in.
- Date comparisons in raw SQL wrap the column in `DATE()`. SQLite stores a cast date with a `00:00:00` time, so a bare comparison against `'Y-m-d'` matches nothing there while passing on MySQL.
- All API routes are rate limited to 300 requests per minute per IP (the `api` limiter in `AppServiceProvider`). Going over the limit returns 429. The ceiling is deliberately generous: one user action costs five requests (the action, then a refresh of the stats, the projects, the deleted projects and the task list), so a tighter limit locks out ordinary clicking. The number grows every time the sidebar learns to show something new, which is the reason to keep the ceiling well clear of it rather than tuned to it.

**Definition of Done:** every row in the table above is verified by a feature test.

## 5. Senior Frontend Engineer

**Owns:** Part 3 implementation.

**Stack:** Blade + vanilla JS (`fetch`) + Tailwind CSS v4 through Vite, which is already configured. No extra framework unless the user asks for one.

**Standards**
- The New task form is a modal (`<dialog id="task-dialog">`) opened from the page header, not a column in the page. Its date field must be the `:inline` grid, because a `<dialog>` clips a floating popover.
- One page, `/tasks` (`tasks/index.blade.php`), rendered through the `<x-layout>` component. `/` redirects to it. All task data flows through the JSON API.
- Page-specific JS is added with `@push('scripts')` by the page itself, not by the layout, so a second page would not inherit the tracker's bundle.
- JS lives in `resources/js/`, split by job, not in inline `<script>` blocks:

  | File | Responsibility |
  |---|---|
  | `api.js` | `fetch` wrapper, `ApiError`, query-string building |
  | `dom.js` | element/icon/badge builders, toasts, busy states, visibility, shared date helpers |
  | `dialogs.js` | the confirm and reschedule modals |
  | `shell.js` | keeps `--header-height` matched to the navbar's real height |
  | `datepicker.js` | the month grid over each `<input type="date">` |
  | `sidebar.js` | drawer, icon rail, Ctrl/Cmd+B, cookie persistence |
  | `menu.js` | the overflow menu behind a sidebar row's "…", including its submenus |
  | `calendar.js` | month grid, agenda, chips, drag-and-drop |
  | `display.js` | the Display panel: layout, grouping, sorting, filters, and the chips that name them |
  | `board.js` | the board's columns, and dragging a card between them by pointer or keyboard |
  | `taskdialog.js` | the task dialog: its fields and its sub-task checklist |
  | `app.js` | state, data loading, list rendering, wiring |

- Never build a `Date` from an ISO date string with `new Date('2026-10-05')`: that parses as UTC midnight and shows the previous day west of Greenwich. Use `parseDate` / `toIsoDate` from `dom.js`.
- Never use `innerHTML` with task data. Build nodes with `textContent` / `createElement` to prevent XSS from task titles.
- Send `Accept: application/json` on every request, and `Content-Type: application/json` when there is a body. No CSRF token is needed, because `/api/*` routes are stateless and have no CSRF middleware.
- Complete, delete, create, and filter all update the DOM without a full page reload.
- Update the UI only after the API confirms success (no optimistic updates), and show the server's error message on failure.
- Tailwind v4 is configured in CSS (`resources/css/app.css`). There is no `tailwind.config.js`, so don't create one.
- Repeated class strings that both Blade and JavaScript need become an `@utility` in `app.css` rather than being copied into each. The sidebar's utilities work this way, and so do `btn-secondary`, `panel-tab` and `reaction-chip`. A string copied into six dialogs is how the copies quietly drift apart: one of the six had picked up a stray focus ring nobody had asked for.
- Third-party designs that get ported (currently shadcn/ui's Sidebar, MIT) are credited in the code comment where they land and in the README.
- Follow every rule in [UI_UX_RULES.md](UI_UX_RULES.md).

**Definition of Done:** each action works in the browser with no console errors, at both mobile and desktop widths.

## 6. Senior UI/UX Designer

**Owns:** how the app looks, feels, and communicates.

**Standards:** [UI_UX_RULES.md](UI_UX_RULES.md) is the source of truth. Review every UI change against its checklist.

**Definition of Done:** every item in the UI/UX checklist passes.

## 7. Senior Code Reviewer

**Owns:** code quality and Git history (10 rubric points).

**Standards**
- PSR-12, enforced with `./vendor/bin/pint` (`psr12` preset in `pint.json`) before each commit.
- Clear names (`$pendingTasks`, not `$data`). No commented-out code, no unused imports or files, no leftover scaffolding.
- One logical change per commit, in the imperative mood: `Add TaskSorter with priority and date ordering`.
- Commit after each working milestone rather than in one large commit at the end.
- Every line must be explainable in the Phase 2 defense. If code is clever but unclear, simplify it.

**Definition of Done:** Pint passes, tests pass, and the diff contains only what the task needs.

## 8. Senior Technical Writer

**Owns:** Part 4, `README.md`.

**Standards**
- Replace the default Laravel README entirely.
- Setup steps copy-paste cleanly on a fresh machine: requirements (PHP 8.2+, Composer, Node, MySQL), clone, `composer install`, `npm install`, `.env` + DB creation, `php artisan key:generate`, `migrate`, `npm run build` / `npm run dev`, `php artisan serve`, `php artisan test`.
- An API reference table with example request and response bodies.
- An **AI Disclosure** section that honestly states which tools were used, which parts were AI-generated or AI-assisted, and what was reviewed, fixed, or changed by hand. Only the user can confirm the final wording.

**Definition of Done:** someone else can clone the repo and run the app plus tests using only the README.

## 9. Senior DevOps Engineer

**Owns:** CI and tooling configuration.

**Standards**
- GitHub Actions (`.github/workflows/ci.yml`) runs on every push and pull request, in this order: install dependencies, check PSR-12 style (`pint --test`), run `php artisan test`, then run `npm run build`.
- CI uses the same in-memory SQLite test database as local runs, so it needs no MySQL service.
- Pint uses the `psr12` preset (`pint.json`) because the rubric grades against PSR-12, and Laravel's default preset differs from it (for example, it drops the parentheses in `new Foo()`).
- Keep the workflow minimal: one job, and no deployment steps unless the user asks for them.

**Definition of Done:** CI passes on the default branch, and the README shows its status badge.
