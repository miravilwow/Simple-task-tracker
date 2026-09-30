# Senior Roles

Every task in this project is handled by the senior role that owns that area. Pick the role from the routing table, work to its standards, and check its Definition of Done before calling the task finished.

When a task spans more than one area (e.g. a new endpoint plus the UI that calls it), work through the roles in order: **Database → Backend → QA → Frontend → UI/UX → Code Review → Docs**.

Start every response that changes code with one line naming the active role(s), e.g. `Role: Senior Backend Engineer`.

## Routing table

| Task / files touched | Role |
|---|---|
| `src/TaskSorter.php`, framework-free PHP logic | [Senior PHP Engineer](#1-senior-php-engineer) |
| `tests/**`, `phpunit.xml` | [Senior QA / Test Engineer](#2-senior-qa--test-engineer) |
| `database/migrations/**`, `app/Models/**`, seeders, factories | [Senior Database Engineer](#3-senior-database-engineer) |
| `routes/api.php`, `app/Http/**`, `bootstrap/app.php` | [Senior Backend Engineer (Laravel API)](#4-senior-backend-engineer-laravel-api) |
| `resources/views/**`, `resources/js/**`, `resources/css/**` | [Senior Frontend Engineer](#5-senior-frontend-engineer) |
| Layout, visual design, states, accessibility, copy | [Senior UI/UX Designer](#6-senior-uiux-designer) |
| Commits, formatting, reviews, removing dead code | [Senior Code Reviewer](#7-senior-code-reviewer) |
| `README.md`, setup steps, AI Disclosure | [Senior Technical Writer](#8-senior-technical-writer) |

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
- `tasks` table: `id`, `title` (string, required), `description` (text, nullable), `priority` enum `low|medium|high` (default `medium`), `status` enum `pending|completed` (default `pending`), `timestamps()`.
- Index `status`, since the list endpoint filters on it.
- Keep the allowed enum values in one place on the model (constants) and reuse them in validation.
- Model `$fillable` lists only `title`, `description`, `priority`. `status` changes only through the complete endpoint.
- Migrations must also run on SQLite, because tests use it. `enum()` works on both.

**Definition of Done:** `php artisan migrate:fresh` runs cleanly on MySQL and in tests.

## 4. Senior Backend Engineer (Laravel API)

**Owns:** Part 2, the REST API.

**Standards**
- Laravel 12 ships without `routes/api.php`. Register it manually in `bootstrap/app.php` (`api: __DIR__.'/../routes/api.php'`) instead of `php artisan install:api`, which pulls in Sanctum, a dependency this project doesn't use.
- Endpoints and status codes:

  | Method | Path | Success | Errors |
  |---|---|---|---|
  | GET | `/api/tasks?status=` | 200 | 400 invalid `status` |
  | POST | `/api/tasks` | 201 + created task | 400 validation failure |
  | PATCH | `/api/tasks/{id}/complete` | 200 + updated task | 404 not found |
  | DELETE | `/api/tasks/{id}` | 200 + message | 404 not found |

- Validation errors return **400** because the exam rubric lists 400. Laravel's default is 422, so override it in one place (a Form Request `failedValidation`, or the exception handler) and document the choice in the README.
- Every error is JSON: `{ "message": "...", "errors": { ... } }`. No HTML error pages from `/api/*`.
- Validation: `title` required|string|max:255, trimmed (whitespace-only is empty); `description` nullable|string; `priority` required|in:low,medium,high.
- Use a Form Request for create validation and route model binding for `{task}` (gives 404 for free).
- `GET /api/tasks` returns tasks ordered with `Src\TaskSorter`, so Part 1 is actually used by the app.
- Controllers stay thin. No business logic in routes.

**Definition of Done:** every row in the table above is verified by a feature test.

## 5. Senior Frontend Engineer

**Owns:** Part 3 implementation.

**Stack:** Blade + vanilla JS (`fetch`) + Tailwind CSS v4 through Vite, which is already configured. No extra framework unless the user asks for one.

**Standards**
- One page (`resources/views/tasks/index.blade.php`) loaded by a web route. All data flows through the JSON API.
- JS lives in `resources/js/`, not inline `<script>` blocks.
- Never use `innerHTML` with task data. Build nodes with `textContent` / `createElement` to prevent XSS from task titles.
- Send `Accept: application/json` on every request, and `Content-Type: application/json` when there is a body. No CSRF token is needed, because `/api/*` routes are stateless and have no CSRF middleware.
- Complete, delete, create, and filter all update the DOM without a full page reload.
- Update the UI only after the API confirms success (no optimistic updates), and show the server's error message on failure.
- Tailwind v4 is configured in CSS (`resources/css/app.css`). There is no `tailwind.config.js`, so don't create one.
- Follow every rule in [UI_UX_RULES.md](UI_UX_RULES.md).

**Definition of Done:** each action works in the browser with no console errors, at both mobile and desktop widths.

## 6. Senior UI/UX Designer

**Owns:** how the app looks, feels, and communicates.

**Standards:** [UI_UX_RULES.md](UI_UX_RULES.md) is the source of truth. Review every UI change against its checklist.

**Definition of Done:** every item in the UI/UX checklist passes.

## 7. Senior Code Reviewer

**Owns:** code quality and Git history (10 rubric points).

**Standards**
- PSR-12, enforced with `./vendor/bin/pint` before each commit.
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
