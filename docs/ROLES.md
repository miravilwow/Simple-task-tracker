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
- `tasks` table: `id`, `title` (string, required), `description` (text, nullable), `category_id` (nullable FK), `priority` enum `low|medium|high` (default `medium`), `status` enum `pending|completed` (default `pending`), `due_date` (nullable date), `timestamps()`.
- `categories` table: `id`, `name` (unique), `icon` (varchar 40, default `folder`), `timestamps()`. The UI calls these "projects"; the schema has not been renamed.
- Index `status` and `due_date`, since the list endpoint filters on both. `deleted_at` is left unindexed: it is NULL for nearly every row, so the index would not pay for itself.
- `tasks` carries `deleted_at` (`softDeletes()`). Deleting is the one irreversible action, so the row is stamped rather than removed and `restore` can bring it back. Every read excludes stamped rows through the trait's global scope, including `stats`, whose `toBase()` applies scopes before dropping to the query builder.
- `tasks.category_id` uses `nullOnDelete`: deleting a category must never delete someone's tasks, it only leaves them uncategorised.
- Never edit a migration that has already run. Schema changes land as a new migration, which is why the category and due-date columns arrive in `add_category_and_due_date_to_tasks_table` rather than in the original `create_tasks_table`.
- Two migrations generated in the same second sort by filename, so a table can end up referenced before it exists. Rename the file rather than rely on luck.
- Allowed values live in PHP backed enums (`App\Enums\TaskPriority`, `App\Enums\TaskStatus`, `App\Enums\CategoryIcon`). The model casts to them, and validation uses `Rule::enum()`. Migrations keep literal values, because a migration is a snapshot of the schema at that point in time.
- `categories.icon` is closed by `App\Enums\CategoryIcon`, never free text: its value goes straight into a CSS class name. The column itself is a `varchar`, because a 224-value database enum would be unreadable, so the application is what enforces the set.
- Model `$fillable` lists the fields a client may send when creating a task: `title`, `description`, `priority`, `category_id`, `due_date`. Every one of them is validated by `StoreTaskRequest`. `status` is deliberately absent, because it changes only through the complete and reopen endpoints.
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
  | PATCH | `/api/tasks/{id}/complete` | 200 + updated task | 404 not found |
  | PATCH | `/api/tasks/{id}/reopen` | 200 + updated task | 404 not found |
  | PATCH | `/api/tasks/{id}/schedule` | 200 + updated task | 400 bad date, 404 not found |
  | DELETE | `/api/tasks/{id}` | 200 + message | 404 not found |
  | PATCH | `/api/tasks/{id}/restore` | 200 + restored task | 404 not found |
  | GET | `/api/categories` | 200 + task counts | none |
  | POST | `/api/categories` | 201 + created category | 400 validation failure |
  | DELETE | `/api/categories/{id}` | 200 + message | 404 not found |

- `GET /api/tasks` accepts `status`, `category_id`, `due` (`overdue`, `today`, `upcoming`, `none`), and a `from`/`to` date window for the calendar. Every one of them is used by the UI; do not add a filter nothing calls.
- `overdue`, `today` and `upcoming` are **work queues**: each one adds `status = pending`, so completing a task drops it out of the view. `stats.due_today` carries the same condition, because it is the badge on the Today view and the two must agree. `none` and the `from`/`to` window are **not** queues: they back the calendar's unscheduled tray and its month grid, which show a completed task where it sits.
- `schedule` takes `due_date` as `present|nullable`, so sending `null` is how the UI clears a date, while omitting the key is a 400 rather than a silent no-op.

- Validation errors return **400** because the exam rubric lists 400. Laravel's default is 422, so override it in one place (a Form Request `failedValidation`, or the exception handler) and document the choice in the README.
- Every error is JSON: `{ "message": "...", "errors": { ... } }`. No HTML error pages from `/api/*`.
- Validation: `title` required|string|max:255, trimmed (whitespace-only is empty); `description` nullable|string; `priority` required|in:low,medium,high.
- Use a Form Request for create validation and route model binding for `{task}` (gives 404 for free).
- `GET /api/tasks` returns tasks ordered with `Src\TaskSorter`, so Part 1 is actually used by the app. The controller then moves pending tasks ahead of completed ones, keeping each group in TaskSorter's order. `TaskSorter` itself stays exactly as the exam specifies (priority, then oldest first) and must never learn about status.
- `reopen` is beyond the exam's four endpoints. It exists because completing a task by mistake would otherwise be a dead end, with deleting and retyping the only way back.
- `restore` is the same argument carried to its end. `DELETE` soft-deletes, so the row survives and the delete toast can offer Undo. Its route is bound `->withTrashed()`, because the default binding hides exactly the task it needs to reach. Deleting an already-deleted task is a 404, since the binding no longer finds it.
- Controllers stay thin. No business logic in routes.
- Responses go through `TaskResource` / `CategoryResource`, so the JSON shape is explicit and separate from the database columns.
- Any endpoint returning tasks eager-loads `category`. A test pins the list to two queries so an N+1 cannot creep back in.
- Date comparisons in raw SQL wrap the column in `DATE()`. SQLite stores a cast date with a `00:00:00` time, so a bare comparison against `'Y-m-d'` matches nothing there while passing on MySQL.
- All API routes are rate limited to 300 requests per minute per IP (the `api` limiter in `AppServiceProvider`). Going over the limit returns 429. The ceiling is deliberately generous: one user action costs three requests (the action, then a list and a stats refresh), so a tighter limit locks out ordinary clicking.

**Definition of Done:** every row in the table above is verified by a feature test.

## 5. Senior Frontend Engineer

**Owns:** Part 3 implementation.

**Stack:** Blade + vanilla JS (`fetch`) + Tailwind CSS v4 through Vite, which is already configured. No extra framework unless the user asks for one.

**Standards**
- The New task form is a modal (`<dialog id="task-dialog">`) opened from the page header, not a column in the page. Its date field must be the `:inline` grid, because a `<dialog>` clips a floating popover.
- Two pages share the `<x-layout>` component (`resources/views/components/layout.blade.php`): the landing page `/` (`home.blade.php`) and the tracker `/tasks` (`tasks/index.blade.php`). All task data flows through the JSON API.
- Page-specific JS is added with `@push('scripts')` only on the page that needs it, so the landing page loads no tracker JS.
- JS lives in `resources/js/`, split by job, not in inline `<script>` blocks:

  | File | Responsibility |
  |---|---|
  | `api.js` | `fetch` wrapper, `ApiError`, query-string building |
  | `dom.js` | element/icon/badge builders, toasts, busy states, local-time date helpers |
  | `dialogs.js` | the confirm and reschedule modals |
  | `shell.js` | keeps `--header-height` matched to the navbar's real height |
  | `datepicker.js` | the month grid over each `<input type="date">` |
  | `sidebar.js` | drawer, icon rail, Ctrl/Cmd+B, cookie persistence |
  | `calendar.js` | month grid, agenda, chips, drag-and-drop |
  | `app.js` | state, data loading, list rendering, wiring |

- Never build a `Date` from an ISO date string with `new Date('2026-10-05')`: that parses as UTC midnight and shows the previous day west of Greenwich. Use `parseDate` / `toIsoDate` from `dom.js`.
- Never use `innerHTML` with task data. Build nodes with `textContent` / `createElement` to prevent XSS from task titles.
- Send `Accept: application/json` on every request, and `Content-Type: application/json` when there is a body. No CSRF token is needed, because `/api/*` routes are stateless and have no CSRF middleware.
- Complete, delete, create, and filter all update the DOM without a full page reload.
- Update the UI only after the API confirms success (no optimistic updates), and show the server's error message on failure.
- Tailwind v4 is configured in CSS (`resources/css/app.css`). There is no `tailwind.config.js`, so don't create one.
- Repeated class strings that both Blade and JavaScript need become an `@utility` in `app.css` rather than being copied into each. The sidebar's utilities work this way.
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
