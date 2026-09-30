# UI/UX Rules

Every screen, component, and interaction in Simple Task Tracker must follow these rules. The checklist at the bottom is the gate before any UI work is considered done.

## 1. Layout

A single-page dashboard in a centered container: `max-w-6xl mx-auto`, with gutters `px-4 sm:px-6 lg:px-8`.

- **Order, top to bottom:**
  1. Header: app name and a one-line description.
  2. Stat cards.
  3. Main area.
- **Stat cards:** four cards, shown 2 per row on mobile and 4 per row from `lg`:
  - Total tasks
  - Pending
  - Completed
  - High priority pending
- **Main area from `lg` (1024px):** two columns.
  - Left third: the New task form, `sticky` so it stays in view while scrolling.
  - Right two-thirds: the task panel.
- **Main area below `lg`:** stacked, with the form first.
- **Task panel:** one white card. Its header row holds the "Tasks" heading and the filter. Task rows are separated by dividers.
  - Below `md`, each row stacks: title / description / date, then the badges, then the action buttons at full width.
  - From `md` up, rows line up as table columns: **Task | Priority | Status | Actions**, under a column header row. The column header row is hidden while the list is loading or empty.
- **Mobile-first:** design at 360px wide, then enhance at `sm` (640px), `md` (768px), and `lg` (1024px).
- **No horizontal scroll** at any width. Long titles wrap (`break-words`) and never overflow.

## 2. Visual design

- **Spacing:** use Tailwind's scale only (`2, 3, 4, 6, 8`). No arbitrary values like `mt-[13px]`. The one exception is the task table's column template, which is defined once as the `task-columns` utility in `resources/css/app.css`.
- **Typography:** the `font-sans` theme font. Page title `text-2xl font-semibold`, section headings `text-lg font-medium`, body `text-sm`/`text-base`, secondary text `text-gray-500`.
- **Surfaces:** white cards on a `bg-gray-50` page, `rounded-lg`, `border border-gray-200`, at most `shadow-sm`.
- **Color is meaningful, not decorative.** Keep one accent color (indigo) for primary actions.

### Priority badges

| Priority | Style | Label |
|---|---|---|
| High | `bg-red-100 text-red-700` | High |
| Medium | `bg-amber-100 text-amber-800` | Medium |
| Low | `bg-slate-100 text-slate-700` | Low |

### Status

| Status | Style |
|---|---|
| Pending | `bg-blue-100 text-blue-700` badge |
| Completed | `bg-green-100 text-green-700` badge; title gets `line-through text-gray-400` |

- Badges always include the text label. Never rely on color alone.

## 3. Buttons and actions

| Type | Use | Style |
|---|---|---|
| Primary | Add Task | Solid indigo, white text |
| Secondary | Complete | Outlined or light green |
| Destructive | Delete | Red text or outline, never the most prominent button on the card |

- Every button has explicit `type="button"` or `type="submit"`.
- Minimum touch target of 40×40px (`py-2 px-3` or larger).
- The Complete button is hidden or disabled for tasks that are already completed.
- Delete asks for confirmation (`confirm("Delete \"<title>\"?")` is enough) because it cannot be undone.
- While a request is in flight, disable the button that started it and change its label (`Saving…`, `Deleting…`) to prevent double submits.

## 4. Form

- Every field has a visible `<label>` tied to it with `for`/`id`. Placeholders are not labels.
- Fields: Title (required, marked with `*`), Description (optional `<textarea>`, 3 rows), Priority (`<select>` defaulting to Medium).
- Validate `title` client-side (not empty after trimming), and still rely on the server's 400 response as the source of truth.
- Show field errors directly under the field in `text-sm text-red-600`, and link them with `aria-describedby`.
- On error, keep what the user typed. On success, reset the form and return focus to Title.

## 5. Filter

- A segmented control with three buttons: **All**, **Pending**, **Completed**.
- The active option is visually distinct and marked with `aria-pressed="true"`.
- Changing the filter refetches from `GET /api/tasks?status=...` without reloading the page.

## 6. States

Every data view needs all four states:

| State | Behaviour |
|---|---|
| Loading | "Loading tasks…" text or a skeleton on first load. Never a blank area. |
| Empty | A friendly message per filter: "No tasks yet. Add one above." / "No pending tasks." / "No completed tasks yet." |
| Error | A red inline banner: "Couldn't load tasks. Try again." with a retry button. |
| Success | A short, auto-dismissing toast (about 3s): "Task added", "Task completed", "Task deleted". |

- Action errors show the server's `message` when one exists, otherwise a generic "Something went wrong."

## 7. Accessibility (WCAG 2.1 AA)

- Text contrast of at least 4.5:1. The badge color pairs above meet this.
- Keyboard: everything is reachable with Tab in a logical order, and every interactive element has a visible `focus-visible:ring-2` style.
- Semantic HTML: `<header>`, `<main>`, `<form>`, `<button>`, `<ul>`/`<table>`. No clickable `<div>`s.
- The toast container uses `aria-live="polite"` so screen readers announce results.
- Icon-only buttons (if any) have an `aria-label`.
- `<html lang="en">` and a meaningful `<title>`.

## 8. Content and microcopy

- Sentence case everywhere: "Add task", not "ADD TASK" or "Add Task".
- Buttons say what they do: "Add task", "Complete", "Delete".
- Dates are human-readable (e.g. "Sep 30, 2026, 10:15 AM"), never raw ISO strings.
- The stat cards show live counts from `GET /api/tasks/stats`. They are refreshed after every action, whichever filter is active, and show "–" until the first load finishes.

## 9. Security and performance

- Task text is user input. Render it with `textContent` only, never `innerHTML`.
- Use no extra UI libraries beyond Tailwind. The page should load fast with a single Vite bundle.
- Avoid layout shift: buttons keep their width when the label changes to `Saving…`.

## 10. Scope guard

Build only what the exam asks for. No dark mode, drag-and-drop, edit-task, auth, or animations beyond simple transitions, unless the user asks for them.

---

## UI/UX checklist (run before marking any UI task done)

- [ ] Works at 360px, 768px, and 1280px with no horizontal scroll (cards on mobile, table columns from `md`, two columns from `lg`)
- [ ] Stat cards update after every add, complete, and delete
- [ ] Priority and status badges match the tables above and include text labels
- [ ] Form has visible labels, inline errors, keeps input on error, resets on success
- [ ] Buttons disable while loading; Delete asks for confirmation
- [ ] Complete, Delete, Add, and Filter all work without a page reload
- [ ] Loading, empty (per filter), error, and success states all appear correctly
- [ ] Full keyboard navigation with visible focus rings
- [ ] No `innerHTML` with task data
- [ ] No console errors
