# UI/UX Rules

Every screen, component, and interaction in Simple Task Tracker must follow these rules. The checklist at the bottom is the gate before any UI work is considered done.

## 0. Pages and shared layout

| Route | View | Purpose |
|---|---|---|
| `/` | `home.blade.php` | Public landing page that explains the app and links to it |
| `/tasks` | `tasks/index.blade.php` | The task tracker dashboard |

### Tracker shell

The tracker is a three-part shell: a **sidebar**, a **header** (title, layout switch), and the active **layout**.

- **Sidebar** holds the smart views (All tasks, Today, Upcoming, Overdue, Completed) and the category list. Views and categories are alternative selections: picking one clears the other, and the page title always names the current one.

#### Sidebar behaviour

The sidebar follows shadcn/ui's Sidebar (MIT), rebuilt for Blade and vanilla JS. Three widths, set as CSS variables on `:root`:

| Variable | Value | Used when |
|---|---|---|
| `--sidebar-width` | 16rem | expanded, from `lg` |
| `--sidebar-width-icon` | 3.5rem | collapsed to the rail, from `lg` |
| `--sidebar-width-mobile` | 18rem | the drawer, below `lg` |

- From `lg` it is a sticky column that **collapses to an icon rail**. Collapsing hides everything marked `.sidebar-collapsible` (labels, counts, group headings, the category form) and centres the icons, which keep a `title` so each button is still identifiable.
- The rail button and **Ctrl/Cmd+B** both toggle it. The state is written to a `sidebar_state` cookie and read back in Blade, so a collapsed sidebar never flashes open on load. That cookie is excluded from Laravel's cookie encryption, because JavaScript writes it; it holds nothing sensitive.
- Below `lg` it is an off-canvas drawer behind a menu button, closed by its own button, the backdrop, Escape, choosing anything inside it, or the viewport growing past `lg`. While closed it is `visibility: hidden`, not merely translated off-screen, so it stays out of the tab order.
- Rows built by JavaScript use the same `sidebar-menu-button` / `sidebar-menu-action` / `sidebar-menu-badge` utilities as the Blade ones, so the two can never drift apart.
- **Layout switch** toggles between List and Calendar. Only one is in the DOM flow at a time.
- Counts beside a view come from `/api/tasks/stats`; a zero renders as nothing rather than "0".

### Calendar

- The month grid starts on Monday and always renders whole weeks, so it reaches into the neighbouring months; those days are dimmed. Today's date sits in a filled circle.
- Tasks appear as chips coloured by priority, with a `title` attribute because day cells truncate.
- **Rescheduling has two paths, and both must keep working:**
  1. Dragging a chip onto a day, or into the "No due date" tray to clear the date. Pointer only.
  2. Opening any chip, or the due-date button on a list row, which opens the reschedule dialog. This is the keyboard and touch path, and it is the baseline — dragging is the enhancement on top, never the only way.
- Below `md` the month grid is replaced by an agenda grouped by day, because seven columns on a phone leave about 50px per day. The hint text changes with it: no "drag" instruction where dragging does not exist.

### Shared components

Everything reusable lives in `resources/views/components/`. Add to these rather than repeating markup.

| Component | Purpose |
|---|---|
| `<x-layout>` | Page shell: skip link, navbar, `<main>`, footer |
| `<x-icon name="…">` | Inline Heroicons v2 (MIT) outline SVG. The only icon source; never paste raw `<path>` data into a page |
| `<x-stat-card>` | One KPI tile: icon, label, value |
| `<x-sidebar>` | Sidebar shell, with `header` and `footer` slots |
| `<x-sidebar.group>` | A labelled section, with an optional `action` slot |
| `<x-sidebar.menu-button>` | One sidebar row: icon, label, optional count |
| `<x-sidebar.trigger>` | Opens the drawer below `lg` |
| `<x-sidebar.rail>` | Collapses and expands the sidebar from `lg` |

Icons in JavaScript-rendered rows come from the `ICONS` map in `resources/js/app.js`, which mirrors the Blade component. Keep the two in sync.

- Both pages use the `<x-layout>` component, which provides the shared structure:
  - a "Skip to content" link
  - the top navbar (logo and app name on the left, one action on the right)
  - the `<main>`
  - the footer
- The navbar action depends on the page. On the landing page it is an **Open app** primary button. On the tracker it is a **Home** text link.
- Content inside the navbar, footer, and page sections uses the same container: `max-w-6xl mx-auto px-4 sm:px-6 lg:px-8`.
- The tracker's JavaScript loads only on `/tasks`. The landing page loads CSS only.

## 1. Layout (tracker dashboard)

A dashboard in the shared container.

- **Order, top to bottom:**
  1. Page heading "Your tasks" and a one-line description.
  2. Stat cards.
  3. Main area.
- **Stat cards:** four `<x-stat-card>` tiles, 2 per row on mobile and 4 per row from `lg`. Each pairs a tinted icon with its number: Total tasks, Pending, Completed, High priority pending. Below `sm` the icon stacks above the label so the text never gets squeezed.
- **Progress meter:** a ratio against a total belongs in a meter, not a fifth number. One bar on a single-hue track, with the label "N of M done (P%)" always visible, so the value never depends on the bar's colour alone. It carries `role="progressbar"` with `aria-valuenow`. With no tasks it reads "No tasks yet"; on a load failure, "Unavailable".
- **Main area from `lg` (1024px):** two columns.
  - Left third: the New task form, `sticky` so it stays in view while scrolling.
  - Right two-thirds: the task panel.
- **Main area below `lg`:** stacked, with the form first.
- **Task panel:** one white card. Its header row holds the "Tasks" heading and the filter. Task rows are separated by dividers, and each row carries a 4px priority accent bar down its left edge. The bar is decorative only, because the same priority is already spelled out in its badge.
  - Below `md`, each row stacks: title / description / date, then the badges, then the action buttons at full width.
  - From `md` up, rows line up as table columns: **Task | Priority | Status | Actions**, under a column header row. The column header row is hidden while the list is loading or empty.
- **Mobile-first:** design at 360px wide, then enhance at `sm` (640px), `md` (768px), and `lg` (1024px).
- **No horizontal scroll** at any width. Long titles wrap (`break-words`) and never overflow.

## 1b. Landing page

Sections, top to bottom:

1. **Hero**
   - Headline and one supporting sentence.
   - A primary CTA **Open task tracker** (`/tasks`) and a secondary CTA **See how it works** (scrolls to that section).
   - From `lg` up, the hero is two columns, with a static, decorative preview of the dashboard on the right (`aria-hidden="true"`). Below `lg`, the preview sits under the text.
2. **Features:** four cards (prioritize, complete, filter, stats), shown 1 per row on mobile, 2 per row from `sm`, and 4 per row from `lg`. Each card has an icon, a title, and one sentence.
3. **How it works:** three numbered steps in an `<ol>`.
4. **Closing CTA:** an indigo band with one sentence and an **Open task tracker** button.

Additional rules:

- The hero headline uses `text-4xl sm:text-5xl font-semibold tracking-tight`. It is the only heading larger than `text-3xl` in the app.
- Anchor scrolling uses `motion-safe:scroll-smooth`, so it respects reduced-motion settings.
- **Honest copy only.** No invented testimonials, user counts, ratings, or pricing. Describe only features the app actually has.

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
| Primary | Add task, Open app, Open task tracker | Solid indigo, white text |
| Secondary | Complete | Outlined light green |
| Secondary | Reopen | Outlined neutral grey, shown in Complete's place once a task is done |
| Destructive | Delete | Red text or outline, never the most prominent button on the row. It keeps its natural width, so it never stretches to fill the row when a task is already completed and Delete is the only action left. |

- Every button has explicit `type="button"` or `type="submit"`.
- Buttons that carry a row's main actions, and every control in a form, are at least 40px tall (`min-h-10`).
- Inline controls sitting inside a line of metadata, such as the due-date button on a task row, are at least 32px (`min-h-8`). That stays clear of the 24px WCAG 2.2 AA minimum without making a metadata line as tall as a button bar. Nothing smaller than 32px is ever tappable.
- A completed task shows Reopen in place of Complete, so an accidental completion is always reversible.
- Delete asks for confirmation in the `<dialog id="confirm-dialog">` modal, because it cannot be undone. A native `<dialog>` with `showModal()` traps focus and closes on Escape for free, and unlike `window.confirm` it can be styled and does not freeze the page. Cancelling returns focus to the Delete button that opened it.
- While a request is in flight, disable the button that started it and change its label (`Saving…`, `Deleting…`) to prevent double submits.

## 4. Form

- Every field has a visible `<label>` tied to it with `for`/`id`. Placeholders are not labels.
- Fields: Title (required, marked with `*`), Description (optional `<textarea>`, 3 rows), Priority.
- Priority is a radio group styled as three cards inside a `<fieldset>`, not a `<select>`, so all options are visible at once and each is a single tap. The checked card takes its priority's tint through `has-checked:`. Medium is checked by default.
- Placeholders show an example; they never replace a label.
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
| Loading | Skeleton rows on first load, so the panel keeps its height instead of collapsing. Never a blank area. |
| Empty | A friendly message per filter: "No tasks yet. Use the form to add your first one." / "No pending tasks." / "No completed tasks yet." |
| Error | A red inline banner with a retry button. The stat cards reset to "–" so stale counts never sit beside an error. |
| Success | A short, auto-dismissing toast (about 3s): "Task added", "Task completed", "Task reopened", "Task deleted". |

- Action errors show the server's `message` when one exists, otherwise a generic "Something went wrong."
- A 429 always shows "Too many requests. Please wait a moment and try again.", never Laravel's raw "Too Many Attempts."
- Completing or deleting a task destroys the button that was clicked. When that button held focus, move focus to the "Tasks" heading so keyboard users keep their place.

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
- Motion is decoration. Every animation sits inside `@media (prefers-reduced-motion: no-preference)` in `resources/css/app.css`, so nothing moves for anyone who asked it not to.

## 10. Scope guard

Build only what the exam asks for, plus the landing page the user requested. No dark mode, drag-and-drop, edit-task, auth, or animations beyond simple transitions, unless the user asks for them.

---

## UI/UX checklist (run before marking any UI task done)

- [ ] Works at 360px, 768px, and 1280px with no horizontal scroll (cards on mobile, table columns from `md`, two columns from `lg`)
- [ ] Landing page: both CTAs work, "See how it works" scrolls to the section, and the navbar action matches the page
- [ ] "Skip to content" appears on the first Tab press
- [ ] Stat cards and the progress meter update after every add, complete, reopen, and delete
- [ ] Skeleton rows show on first load and are gone afterwards
- [ ] Delete dialog confirms, cancels, and closes on Escape without deleting
- [ ] Priority and status badges match the tables above and include text labels
- [ ] Form has visible labels, inline errors, keeps input on error, resets on success
- [ ] Buttons disable while loading; Delete asks for confirmation
- [ ] Add, Complete, Reopen, Delete, and Filter all work without a page reload
- [ ] Sidebar views and categories filter the list, and the title names the current one
- [ ] Drawer opens, closes on Escape/backdrop, and is untabbable while closed
- [ ] Rail and Ctrl+B collapse the sidebar, and the state survives a reload without flashing
- [ ] Calendar: drag a chip to a day, drag to the tray to clear, and reschedule from the dialog
- [ ] Month grid from md, agenda below it, with matching hint text
- [ ] Pending tasks appear above completed ones in the All view
- [ ] Loading, empty (per filter), error, and success states all appear correctly
- [ ] Full keyboard navigation with visible focus rings
- [ ] No `innerHTML` with task data
- [ ] No console errors
