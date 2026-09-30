# Simple Task Tracker

Laravel 12 + MySQL task tracker built for a 3-day full-stack exam. The exam grades sorting logic and tests, the REST API, the UI, code quality and Git history, and the AI disclosure.

Apply these on every prompt:

- Senior roles and routing: @docs/ROLES.md
- UI/UX rules and checklist: @docs/UI_UX_RULES.md

## Key constraints

- `src/TaskSorter.php` is plain PHP (namespace `Src`) with no framework helpers. Its test lives at `tests/TaskSorterTest.php`.
- API validation errors return 400, not Laravel's default 422.
- Local DB is MySQL `task_tracker` (XAMPP). Tests use in-memory SQLite (`phpunit.xml`).
- Run `./vendor/bin/pint` (PSR-12 preset) and `php artisan test` before each commit. CI runs the same checks.
