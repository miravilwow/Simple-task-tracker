# Sprint plan — closing the architecture review

One day of work, so that tomorrow is only frontend design. Every sprint names the senior role that
owns it, the exact files it may touch, and the one check that says it is finished.

## Result

All seven ran on 2026-10-01. Every gate passed, and no later sprint sent an earlier one back.

| Sprint | Role | Outcome | Commit |
|---|---|---|---|
| 1 Documentation | Technical Writer | Two endpoints promised but absent, one present but undocumented, a withdrawn feature described nine times. `DocumentedRoutesTest` now compares the tables to the routes both ways. | `f1a467c` |
| 2 Duplicate crash | Backend | The copy-name search ignored deleted projects, which the unique index covers. Now one query instead of one per attempt, and bounded. | `8727fcd` |
| 3 Sleeping index | Database | `EXPLAIN` went from `type: ALL`, no key, 20 rows to `type: range` on `tasks_due_date_index`, 5 rows. | `3021b1f` |
| 4 Request counter | DevOps | Database trips for one task-list call: **12 → 2**. The limit still refuses with 429. | `8e88469` |
| 5 README | Technical Writer | Part 4 written. The gate from Sprint 1 reads its tables too. The seeder is safe to run twice. | `bf1db9f` |
| 6 Final gate | QA, Code Reviewer | Tests, style, lint, build, and `migrate:fresh` on a throwaway MySQL database — never the working one. | — |
| 7 Review leftovers | Backend | A deleted project is refused in all five places. Comment routes scoped by the framework instead of by hand. | *(this commit)* |

**220 tests, 1327 assertions.** Two things still need the user: the AI Disclosure wording, and the
`OWNER/REPO` placeholder in the README's CI badge, which cannot be known without a git remote.

The findings come from the architecture review of the database and the backend. Nothing here is a
new feature: every item is something already built that does not yet hold up.

## How a sprint finishes

A sprint is done when **its own gate passes and every earlier sprint's gate still passes**. That
second half is the point of the order below.

```
Sprint 1 ─ gate 1 ✓
Sprint 2 ─ gate 2 ✓ … and gate 1 again
Sprint 3 ─ gate 3 ✓ … and gates 1–2 again
```

**If a later sprint breaks an earlier gate, we stop and go back to that sprint.** The later work
waits. A fix that quietly undoes an earlier one is not progress, and finding that out on the last
day is the expensive way to find out.

The gates are commands, not opinions, so "it still works" is never a matter of memory.

| Gate | Command |
|---|---|
| Documentation is true | `php artisan test --filter=DocumentedRoutesTest` |
| Behaviour is unbroken | `php artisan test` |
| Style is clean | `./vendor/bin/pint --test` |
| The build works | `npm run build` |
| The schema builds from nothing | `php artisan migrate:fresh` |

---

## Sprint 1 — Make the documentation true

**Role:** Senior Technical Writer
**Why first:** the README is graded and is written from these documents. An endpoint in the README
that does not run is the first thing a grader will try.

**The problem:** the documents promise two endpoints the app does not have, hide one it does, and
describe a feature that was removed.

| | |
|---|---|
| Documented, not in the app | `PATCH /api/categories/{id}/archive`, `PATCH /api/categories/{id}/unarchive` |
| In the app, not documented | `DELETE /api/categories/{id}/force` |
| Removed feature still described | Archive — 9 mentions, including a sidebar section and a checklist line that can never pass |

**Targets**

- `docs/ROLES.md` — drop the two archive rows from the endpoint table, add the permanent-delete row,
  remove the archive notes under the Backend role and the `archived_at` notes under Database.
- `docs/UI_UX_RULES.md` — remove the Archived section of the sidebar and its checklist items.
- `tests/Feature/DocumentedRoutesTest.php` — **new.** Reads the endpoint table out of `ROLES.md` and
  compares it against the application's real routes, both ways.

**Definition of done:** the new test passes, and it fails if a route is added without a line in the
table or a line is left behind after a route goes. The drift cannot come back on its own.

---

## Sprint 2 — Fix the crash when duplicating a project

**Role:** Senior Backend Engineer
**Why second:** it is the only finding that is an actual failure rather than a cost.

**The problem:** duplicating "Work" looks for a free name among the projects you can see. The
database reserves names across the deleted ones too. When a deleted project holds "Work (copy)",
the copy is created anyway and the database refuses it — a server error, not a clear message.

Proven: `UNIQUE constraint failed: categories.name`.

**Targets**

- `app/Http/Controllers/CategoryController.php` — `copyName()` must count the deleted projects, the
  same set the unique index covers.
- The search must also be bounded. An endless loop that asks the database each turn is not something
  to leave in a graded project.
- `tests/Feature/ProjectMenuTest.php` or a new test — a regression test for exactly this case.

**Definition of done:** duplicating works with a deleted project holding the obvious copy name, and a
test fails if the lookup ever stops counting the deleted ones.

---

## Sprint 3 — Wake up the due-date index

**Role:** Senior Database Engineer
**Why third:** it is the only finding where the database is doing many times the work it needs to.

**The problem:** the three busiest views — Today, Overdue, Upcoming — wrap the date column in a
function. A wrapped column cannot be matched against its index, so every one of them reads the whole
table. MySQL's own answer:

| Query | Index | Rows read |
|---|---|---|
| What runs today | none | every row |
| The same question unwrapped | `tasks_due_date_index` | the matching few |

The wrapper is there because the test database stores a date with a time on it and the real one does
not. A range of one day answers the same question, is correct on both, and keeps the index.

**Targets**

- `app/Http/Controllers/TaskController.php` — `applyDueFilter()` and the two date counts in
  `stats()`.
- No migration. The index already exists; nothing has been asking for it.

**Definition of done:** every existing test still passes on the test database, and `EXPLAIN` on MySQL
names the index for Today, Overdue and Upcoming.

---

## Sprint 4 — Stop counting requests in the database

**Role:** Senior DevOps Engineer
**Why fourth:** one line, and three quarters of the database traffic goes away.

**The problem:** measured on one task-list call — **12 database trips, 9 of them the request
counter**. The counter lives in the database, and one click in the app is four or five calls, so an
ordinary click costs over forty extra visits that only ever count.

**Targets**

- `.env`, `.env.example`, and the cache configuration — the counter moves to a store that is not the
  database. The application cache choice must be explained, not just switched.
- `app/Providers/AppServiceProvider.php` if the limiter needs to name its own store.

**Definition of done:** the same measurement shows two data queries and no counter queries, the rate
limit still returns 429 when exceeded, and the existing rate-limit test still passes.

---

## Sprint 5 — The README

**Role:** Senior Technical Writer
**Why last:** it describes everything above, so it is written once, after the above is true.

**The problem:** it is still Laravel's default README. It is Part 4 of the exam and it is graded.

**Targets**

- `README.md` — setup from nothing on a fresh machine, the API reference with example request and
  response bodies, the deliberate deviations from the brief, and the **AI Disclosure**.
- The deviations that must be written down: the status set is wider than the brief's
  `pending|completed`; validation errors answer 400 rather than Laravel's 422; the endpoints beyond
  the brief's four and why each exists.
- The AI Disclosure wording is the one thing only the user can confirm.

**Definition of done:** someone else can clone the repository and run both the app and the tests
using the README alone, and every endpoint in its table is a route that exists.

---

## Sprint 6 — The gate before tomorrow

**Role:** Senior QA / Test Engineer, then Senior Code Reviewer

Not new work. This is the pass that proves the day held together.

**Targets**

- Run every gate in the table at the top, in order, including `migrate:fresh` on MySQL.
- Re-run Sprint 1's documentation check **after** Sprint 5, because the README is the document most
  likely to claim an endpoint that does not exist.
- Read the day's diff once, as a reviewer: no commented-out code, no unused imports, no leftover
  scaffolding, and every line explainable out loud.

**Definition of done:** every gate passes, and the work is committed in one logical change per
sprint rather than one commit at the end.

---

## Not in scope today

Both were considered and deliberately left alone. They are things to **explain**, not change, the day
before a deadline.

- **The task list has no page limit.** It loads every matching task and orders them in PHP. That is
  on purpose: `Src\TaskSorter` is the graded part and it is PHP, so the ordering has to happen there.
  For a single-person tracker the cost is nothing. Say it first in the defence rather than be asked.
- **`TaskController` carries twelve endpoints.** Every one is small and clear, but the class does
  creating, counting, stage changes, scheduling and ordering. The honest split is to move the counting
  out. It is a tidy change with no behaviour behind it, which makes it exactly the wrong thing to
  start on the last day.
