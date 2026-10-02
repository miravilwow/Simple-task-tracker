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
- `tasks` table: `id`, `title` (string, required), `description` (text, nullable), `category_id` (nullable FK), `priority` enum `low|medium|high` (default `medium`), `status` enum `pending|in_progress|in_review|completed` (default `pending`), `due_date` (nullable date), `timestamps()`.
- `categories` table: `id`, `name` (unique), `timestamps()`. The UI calls these "projects"; the schema has not been renamed. A project is created by typing its name on a task, never from a form of its own, so the row holds nothing but the name. `Category::named()` looks a name up case-insensitively and creates it only when nothing matches, which is how "Work" and "work" end up as one project.
- `tasks.position` is where a task sits in its board column, backing `TaskSort::Manual` alone. Indexed as `(status, position)`, which is exactly how a column reads. It is not `$fillable`: a client never sets a position directly, it says which card the moved one should follow and the server renumbers the column.
- Index `status` and `due_date`, since the list endpoint filters on both. `deleted_at` is left unindexed: it is NULL for nearly every row, so the index would not pay for itself.
- `tasks` carries `deleted_at` (`softDeletes()`). Deleting is the one irreversible action, so the row is stamped rather than removed and `restore` can bring it back. Every read excludes stamped rows through the trait's global scope, including `stats`, whose `toBase()` applies scopes before dropping to the query builder.
- `tasks.category_id` uses `nullOnDelete`: removing a category row must never delete someone's tasks, it only leaves them uncategorised.
- Never edit a migration that has already run. Schema changes land as a new migration, which is why the category and due-date columns arrive in `add_category_and_due_date_to_tasks_table` rather than in the original `create_tasks_table`.
- Two migrations generated in the same second sort by filename, so a table can end up referenced before it exists. Rename the file rather than rely on luck.
- Allowed values live in PHP backed enums (`App\Enums\TaskPriority`, `App\Enums\TaskStatus`, `App\Enums\TaskSort`, `App\Enums\DueFilter`, `App\Enums\BulkAction`). The model casts to them, and validation uses `Rule::enum()`. Migrations keep literal values, because a migration is a snapshot of the schema at that point in time.
- Model `$fillable` lists the fields a client may send when creating a task: `title`, `description`, `priority`, `due_date`. `category_id` is not fillable: the controller sets it from the typed `category_name`, so a client never sends it. `status` is deliberately absent, because it changes only through the start, complete and reopen endpoints.
- Project features were removed on purpose: favourites, the parent/child tree, colours and icons, comments, reactions and the activity log. The sidebar that reached them is gone, and a project is now just a label a task carries. Their columns and tables left in one migration (`remove_project_features`) rather than by editing the ones that created them, because a migration that has run is a record of what happened.
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
  | PATCH | `/api/tasks/{id}/review` | 200 + updated task | 404 not found |
  | PATCH | `/api/tasks/{id}/complete` | 200 + updated task | 404 not found |
  | PATCH | `/api/tasks/{id}/reopen` | 200 + updated task | 404 not found |
  | PATCH | `/api/tasks/{id}/schedule` | 200 + updated task | 400 bad date, 404 not found |
| PATCH | `/api/tasks/{id}/reorder` | 200 + moved task | 400 validation failure, 404 not found |
  | POST | `/api/tasks/bulk` | 200 + the ids that changed | 400 validation failure |
  | DELETE | `/api/tasks/{id}` | 200 + message | 404 not found |
  | PATCH | `/api/tasks/{id}/restore` | 200 + restored task | 404 not found |
  | POST | `/api/tasks/{id}/subtasks` | 201 + created sub-task | 400 validation failure, 404 not found |
  | PATCH | `/api/tasks/{id}/subtasks/{sub}` | 200 + updated sub-task | 400 validation failure, 404 not found |
  | DELETE | `/api/tasks/{id}/subtasks/{sub}` | 200 + message | 404 not found |
  | GET | `/api/categories` | 200 + projects that have live tasks, with their counts | none |

- `GET /api/tasks` accepts `status`, `due` (`overdue`, `today`, `upcoming`, `none`), a `from`/`to` date window for the calendar, and the Display panel's `sort`, `priority` and `completed`, plus `project` and `search`. Every one of them is used by the UI; do not add a filter nothing calls. `project` (an id, or `none`) and `search` (part of a title, at most 100 characters, `%` and `_` taken literally) back the filter bar on Upcoming, Overdue and Completed and the project boards.
- `sort` is `App\Enums\TaskSort`: `default` is `Src\TaskSorter`, while `due`, `name` and `manual` replace it. All four then pass through `byStage()`, so no sort can bury live work under finished work.
- `manual` is `tasks.position`: the arrangement someone made by dragging cards on the board. It is the one order a sort cannot work out, which is why it is stored rather than computed. The board is the only thing that asks for it, so `GET /api/tasks` with no `sort` is still `TaskSorter` and Part 1 is still what the app runs.
- `completed=0` is the Display panel's toggle in its off position, and asks for `TaskStatus::unfinished()`. Only the off position narrows anything, so `completed=1` is the same list as omitting the key.
- `PATCH /api/tasks/{id}` is the task dialog: name, description, priority and project, each field optional because the dialog saves one at a time. **It cannot change `status`**, which still moves only through start, complete and reopen, so a stage change has one path. The due date keeps `schedule`, because the calendar changes it by dragging.
- `subtasks` is its own table, not a self-referencing `tasks.parent_id`. A sub-task is a checklist item on one task and never a row in the list, the board, the calendar or the stats; a self-reference would have made every read and every count ask whether it meant sub-tasks too. They are returned only by `show`, never by `index`.
- `overdue`, `today` and `upcoming` are **work queues**: each one asks for `status` in `TaskStatus::unfinished()`, so completing a task drops it out of the view while starting one does not — the task someone is in the middle of is the one they are most likely looking for. `stats.due_today` carries the same condition, because it is the badge on the Today view and the two must agree. `none` and the `from`/`to` window are **not** queues: they back the calendar's unscheduled tray and its month grid, which show a completed task where it sits.
- **The project is named, not identified.** `store` and `update` take `category_name` (nullable, at most 40 characters, trimmed) rather than a `category_id`: the form types a name, and the server reuses the project of that name case-insensitively or creates it. A client therefore never needs to know an id, and a typo cannot point a task at a project that does not exist. `update` acts on it only when the key is present, because the dialog saves one field at a time; null or an empty string clears the project.
- `GET /api/categories` returns `[{id, name, task_count}]` for projects with at least one live task, ordered by name. A project whose last task was deleted or moved away drops out of the list, so the suggestions on the task forms never offer a name nothing uses. It is the only project endpoint: everything else about a project is the name on a task.

- `schedule` takes `due_date` as `present|nullable`, so sending `null` is how the UI clears a date, while omitting the key is a 400 rather than a silent no-op.
- `reorder` backs a board drop, which says two things at once: which column the card landed in and where in that column. It takes `status` (required) and `after` (`present|nullable`), the id of the task the moved one should follow, with `null` for the top of the column.
- **`after` names a task rather than counting one.** An index would be counted against the cards the client drew, and a filter can hide some of them, so index 1 on screen is not position 1 in the column. An anchor the column no longer holds — another tab finished it between the drag and the drop — puts the card at the end rather than losing the move.
- The whole column is **renumbered** on every drop rather than the neighbours being nudged, so positions cannot drift into ties or gaps however many times a card is dragged. The siblings move through the query builder, so a renumber does not stamp every one of them as updated.
- `POST /api/tasks/bulk` is the task table's selection toolbar: one `action` (`complete`, `reopen`, `delete`, `restore`) across up to 100 `ids`, in one transaction. One request rather than one per row, because a selection applied half way is a state the user has to work out for themselves, and five rows completed as five requests also spends five of the rate limiter's allowance on one click.
- **It answers with the ids that actually changed**, not the ids it was sent. A task already in the state being asked for is skipped rather than refused: selecting five rows of which two are already done and pressing Complete should finish the other three. Undo sends back exactly that list, which is why it cannot simply reuse the selection. The response also names the action that reverses it, so the client does not keep a second copy of that mapping.
- **A deleted id is a 400 for every action except `restore`**, whose rows are the stamped ones. That is the same refusal of a silent no-op the rest of the API makes, and the reason the `exists` rule is built from the action rather than being one rule for all four.
- Bulk saves each task on its own rather than running one `UPDATE`, because a bulk stage change is still a stage change and goes through `setStage()` like the single ones. The cap of 100 is what keeps that loop bounded.
- **`reorder` changes a stage through `setStage()`**, the same private method `start`, `complete` and `reopen` use. There is still one place a status changes, so widening a drop into a reorder did not give stage changes a fourth route. A drop inside one column changes no status, because rearranging is not a stage change.
- A new task joins the **end** of its column. That happens in the model rather than the controller, so the factory and the seeder are covered too and no creation path leaves a column with several tasks all claiming position 0.
- **A due date cannot be set in the past.** Both `store` and `schedule` carry `after_or_equal:today`, because a due date is a promise about work still ahead. `schedule` needs it as much as `store` does: without it, creating a task with no date and dragging it onto a past day would be the way around the rule. A task still becomes overdue the ordinary way, by the day arriving and passing, and a row that is already overdue can still be moved forward. Factories and the seeder write the model directly, so they can still place a task in the past for the Overdue view to have something to show.

- Validation errors return **400** because the exam rubric lists 400. Laravel's default is 422, so override it in one place (a Form Request `failedValidation`, or the exception handler) and document the choice in the README.
- Every error is JSON: `{ "message": "...", "errors": { ... } }`. No HTML error pages from `/api/*`.
- Validation: `title` required|string|max:255, trimmed (whitespace-only is empty); `description` nullable|string; `priority` required|in:low,medium,high.
- Use a Form Request for create validation and route model binding for `{task}` (gives 404 for free).
- **The nested sub-task routes carry `scopeBindings()`.** A sub-task id from another task is then a 404 from the binding, in one declaration on the route, rather than a check written out again in each controller method. Two ways to answer one question is how the two quietly come to disagree.
- `GET /api/tasks` returns tasks ordered with `Src\TaskSorter`, so Part 1 is actually used by the app. The controller then moves pending tasks ahead of completed ones, keeping each group in TaskSorter's order. `TaskSorter` itself stays exactly as the exam specifies (priority, then oldest first) and must never learn about status.
- **The status set is wider than the brief.** The exam pins `pending|completed`; this adds `in_progress` and `in_review` so the board's middle columns have something to hold. It is a deliberate deviation and belongs in the README's disclosure. Both original values keep their meaning: `pending` is still the default a task is created with, and `completed` is still the end. `start` moves a task into the middle and `review` on to the check before Done; `reopen` is the way back out of either later stage.
- Status is never part of `PATCH /api/tasks/{id}`. The board's drag and the dialog's Status field both call `start`, `complete` or `reopen`, so there is one path to a stage change.
- `TaskStatus::unfinished()` is what every "not done yet" condition asks, rather than each one naming `pending` itself. The stats' `pending` count, the three work queues and the list's ordering all use it, so none of them can disagree about what unfinished means.
- `byStage()` orders the list To do, then In progress, then In review, then Done, keeping each group in TaskSorter's order. That is the same order the board's columns read left to right: they are the same four stages, and work does not run backwards through them. **`TaskSorter` still knows nothing about status** — the split is in the controller, so Part 1 stays exactly as the exam specifies.
- `reopen` is beyond the exam's four endpoints. It exists because completing a task by mistake would otherwise be a dead end, with deleting and retyping the only way back.
- `restore` is the same argument carried to its end. `DELETE` soft-deletes, so the row survives and the delete toast can offer Undo. Its route is bound `->withTrashed()`, because the default binding hides exactly the task it needs to reach. Deleting an already-deleted task is a 404, since the binding no longer finds it.
- Controllers stay thin. No business logic in routes.
- Responses go through `TaskResource` / `CategoryResource`, so the JSON shape is explicit and separate from the database columns.
- Any endpoint returning tasks eager-loads `category`. A test pins the list to two queries so an N+1 cannot creep back in.
- **Never wrap `due_date` in `DATE()`, and never use `whereDate()`.** A column inside a function cannot be matched against its own index, and MySQL then reads every row: `EXPLAIN` reported `type: ALL` with no key for Today, Overdue and Upcoming, the three busiest views in the app. The same comparison written plainly reports `type: range` on `tasks_due_date_index`.
- The reason the wrapper was there is real: the test database stores a cast date as `'Y-m-d 00:00:00'`, so `due_date = 'Y-m-d'` matches nothing on SQLite while passing on MySQL. **"Due today" is therefore written as the day's range** — `>= today AND < tomorrow` — which is correct on both, because the stored `00:00:00` falls inside it. `< today` and `>= today` need nothing special: a stored `'2026-10-01 00:00:00'` already sorts after `'2026-10-01'` and before `'2026-10-02'`.
- All API routes are rate limited to 300 requests per minute per IP (the `api` limiter in `AppServiceProvider`). Going over the limit returns 429. **The counter lives in a file, not the database** (`cache.limiter`): Laravel hands the limiter the default store, which here is the database, and one call to the task list then cost twelve database trips with nine of them only counting. The count has to outlive the request, so an in-memory store would leave the limit never limiting anything, and it is worth nothing if lost, so it has no business beside the tasks. The tests set it to `array` on purpose, or a file would carry a count from one run of the suite into the next. The ceiling is deliberately generous: one user action costs four requests (the action, then a refresh of the stats, the projects and the task list), so a tighter limit locks out ordinary clicking. The number grows every time the page learns to show something new, which is the reason to keep the ceiling well clear of it rather than tuned to it.

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
  | `dom.js` | element/icon/badge builders, busy states, visibility, shared date helpers |
  | `dialogs.js` | the confirm and reschedule modals |
  | `shell.js` | keeps `--header-height` matched to the navbar's real height |
  | `datepicker.js` | the month grid over each `<input type="date">` |
  | `sidebar.js` | drawer, icon rail, Ctrl/Cmd+B, cookie persistence for the five smart views |
  | `menu.js` | the overflow menu behind a row's or a board card's "…" |
  | `calendar.js` | month grid, agenda, chips, drag-and-drop |
  | `display.js` | the Display panel on the board views: completed tasks, grouping, date and priority filters, and the chips that name them |
  | `filterbar.js` | the search, priority and project filters on Upcoming, Overdue and Completed |
  | `board.js` | the board's columns, and dragging a card between columns by pointer or keyboard |
  | `table.js` | the list's row selection, its header checkbox and its sortable column headers (Overdue and Completed) |
  | `taskdialog.js` | the task dialog: its fields and its sub-task checklist |
  | `toast.js` | the toast region, its three variants, and the action a toast can carry |
  | `app.js` | state, data loading, list rendering, wiring |

- Never build a `Date` from an ISO date string with `new Date('2026-10-05')`: that parses as UTC midnight and shows the previous day west of Greenwich. Use `parseDate` / `toIsoDate` from `dom.js`.
- Never use `innerHTML` with task data. Build nodes with `textContent` / `createElement` to prevent XSS from task titles.
- Send `Accept: application/json` on every request, and `Content-Type: application/json` when there is a body. No CSRF token is needed, because `/api/*` routes are stateless and have no CSRF middleware.
- Complete, delete, create, and filter all update the DOM without a full page reload.
- Update the UI only after the API confirms success (no optimistic updates), and show the server's error message on failure.
- Tailwind v4 is configured in CSS (`resources/css/app.css`). There is no `tailwind.config.js`, so don't create one.
- Repeated class strings that both Blade and JavaScript need become an `@utility` in `app.css` rather than being copied into each. The sidebar's utilities work this way, and so do `btn-secondary`, `table-sort` and `board-card`. A string copied into six dialogs is how the copies quietly drift apart: one of the six had picked up a stray focus ring nobody had asked for.
- Third-party designs that get ported (shadcn/ui's Sidebar and its Sonner toast, both MIT) are credited in the code comment where they land and in the README. **A port is a rebuild, not an install.** Sonner needs React, and the shadcn wrapper's classes (`bg-background`, `text-muted-foreground`) need a token layer this project's Tailwind does not have, so dropping them in renders nothing at all. What carries over is the design and the call shape; what does not is anything the app has no use for, which is why the theme hook and `toast.promise` were left behind.
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
