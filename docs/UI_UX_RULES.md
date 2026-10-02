# UI/UX Rules

Every screen, component, and interaction in Simple Task Tracker must follow these rules. The checklist at the bottom is the gate before any UI work is considered done.

## 0. Pages and shared layout

| Route | View | Purpose |
|---|---|---|
| `/` | — | Redirects to `/tasks` |
| `/tasks` | `tasks/index.blade.php` | The task tracker, and the whole site |

There was a landing page at `/`. The app is a tracker, not a product with something to sell, so the marketing shell was one page of copy nobody using it would read twice. `/` redirects rather than 404s, because a saved link should still land.

### Tracker shell

The tracker runs the **full-bleed app shell**. `<x-layout :fluid="true">` is what turns it on, and it also opens the `aside` slot that holds the sidebar outside `<main>`, because a sidebar is navigation and not page content. `fluid` stays a prop rather than becoming the only behaviour: the boxed shell is still what any second page would want.

| | Boxed shell | Tracker (`/tasks`) |
|---|---|---|
| Navbar | static, boxed at `max-w-6xl` | `fixed top-0 shadow-sm`, spans the viewport |
| Container | `max-w-6xl` | `app-container`: full width, capped at `--container-app` (112rem) so an ultrawide display does not stretch a task row across a metre of glass |
| Sidebar | none | fixed, full height |
| Gutter | `px-4 sm:px-6 lg:px-8` | `px-4 sm:px-6` |

**A fixed navbar hides whatever the browser scrolls to the top of the viewport**, because the browser cannot know the bar is there. Everything inside the shell carries `scroll-margin-top` for the bar's height, so the skip link's target, the heading focus moves to after a task is removed, and anything else scrolled into view all land below it. `<main>` reserves the same height with padding, since it starts at the top of the document.

**The navbar and the sidebar are one chrome layer, and both are `fixed`.** They must share a positioning scheme, because anything else lets them drift apart: a sticky navbar rides the document, so an overscroll bounce — Chrome's rubber band when you scroll up past the top — slides it down over the fixed sidebar and swallows the sidebar's heading. `<html>` also carries `overscroll-y-none` so the bounce does not happen in the first place, and `<main>` reserves `--header-height` at the top because the navbar no longer takes space in the flow.

The tracker's own shell is three parts: a **sidebar**, a **header** (title and the Display button), and the active **layout**.

- **Sidebar** holds the five smart views and nothing else: All tasks, Today, Upcoming, Overdue and Completed, each with a count. Upcoming is the calendar. The UI says "project"; the API, the table and the model are still `category`. A project is not a place in the sidebar but a label on a task, typed into the task's Project field, so there is no tree to browse and no second kind of selection: the page title always names the current view.
- **There was a project tree, and it is gone.** Favourites, nesting, colours and icons, comments, reactions, an activity feed and a Deleted section were all built around the sidebar rows and were removed with them. A project was only ever a name a task carried, and every one of those features was a way of managing something that did not need managing. The Project field takes a typed name, offering the existing ones as suggestions, and a project exists exactly while a task carries it.

#### Sidebar behaviour

The sidebar follows shadcn/ui's Sidebar (MIT), rebuilt for Blade and vanilla JS. Three widths, set as CSS variables on `:root`:

| Variable | Value | Used when |
|---|---|---|
| `--sidebar-width` | 16rem | expanded, from `lg` |
| `--sidebar-width-icon` | 3.5rem | collapsed to the rail, from `lg` |
| `--sidebar-width-mobile` | 18rem | the drawer, below `lg` |

- From `lg` it is **fixed, not sticky**: it runs from the bottom of the sticky navbar (`--header-height`) to the bottom of the viewport, flush against the left edge, and scrolls on its own rather than with the page.
- **`--header-height` is measured, never assumed.** `--header-min-height` (4rem) is the floor the navbar is laid out against, and `shell.js` writes the bar's real height back into `--header-height` through a `ResizeObserver`. A constant would be wrong the moment the bar grows — under a text-only zoom, a larger default font, or a longer brand name — and the sidebar would then start behind it. The navbar's height also has to include its bottom border, so `app-header` sits on the `<header>` and not on the `<nav>` inside it; a pixel short leaves a hairline of content showing through. `<main>` is pushed clear of it by `--app-shell-offset`, which `<body data-sidebar-state>` swaps between the two widths below. Blade sets that attribute from the cookie and `sidebar.js` keeps it in step, so the content never starts at the wrong width.
- It **collapses to an icon rail**. Collapsing hides everything marked `.sidebar-collapsible` (labels, counts, group headings), centres the icons and the rail button, and the content reclaims the space in the same 200ms. The icons keep a `title` so each button is still identifiable.
- The rail button and **Ctrl/Cmd+B** both toggle it. The state is written to a `sidebar_state` cookie and read back in Blade, so a collapsed sidebar never flashes open on load. That cookie is excluded from Laravel's cookie encryption, because JavaScript writes it; it holds nothing sensitive.
- Below `lg` it is an off-canvas drawer behind a menu button, closed by its own button, the backdrop, Escape, choosing anything inside it, or the viewport growing past `lg`. While closed it is `visibility: hidden`, not merely translated off-screen, so it stays out of the tab order.
- Rows built by JavaScript use the same `sidebar-menu-button` / `sidebar-menu-action` / `sidebar-menu-badge` utilities as the Blade ones, so the two can never drift apart.

#### Icons

Every icon is **Heroicons**, the chrome the app chooses for itself. Where the set has nothing close, a glyph is **drawn to the same grid** — 24px, 1.5 stroke, round caps — and marked as drawn in the map, so the two sit together without reading as two families. The Display button's framed list is the only one so far. There is no second icon set: a project's mark is the same folder icon for all of them, because a project is a name and nothing more.

Heroicons is kept in two maps: `<x-icon>` for markup the server renders, and the `ICONS` map in `resources/js/dom.js` for rows JavaScript builds. **Neither has to hold every icon** — each carries what its own side draws — but an icon asked for and not defined fails quietly: Blade throws only in debug, and `dom.js` writes `d="undefined"`, which draws nothing at all. `IconMapTest` fails instead, in both directions: an icon used but not defined, and an icon defined that nothing draws.

#### Scrollbars

The sidebar, the page and the calendar's no-due-date tray scroll without drawing a scrollbar. `scrollbar-none` in `resources/css/app.css` hides the bar through `scrollbar-width` and the WebKit pseudo-element; **scrolling itself is untouched**, so the wheel, touch, the keyboard and a drag all still work. The tracker's shell puts it on `<html>`, because the page scroll belongs to the root element.

- Both pages use the `<x-layout>` component, which provides the shared structure:
  - a "Skip to content" link
  - the top navbar (logo and app name on the left, one action on the right)
  - the `<main>`
- The navbar's right side carries today's date, not an action. There is no second page to link to, and a button that only leads back to the page you are on is furniture.
- Within a page, the navbar and the content use that page's one container, so their left and right edges line up down the whole screen.
- The tracker's JavaScript is pushed in by `/tasks` through `('scripts')`, not by the layout, so a second page would not inherit it.

## 1. Layout (tracker dashboard)

A dashboard in the `app-container`, beside the fixed sidebar.

**The view decides the layout.** All tasks and Today are the **Board only**: a work queue is read as columns of stages, and the list had nothing to add to it. Upcoming is the calendar. Overdue and Completed keep the user's List or Board choice from the Display panel. While a view forces its layout, both Display mode buttons are disabled rather than accepted and ignored, the same way None is withdrawn from Grouping on the board. Because the list is the only layout with checkboxes, **the data table below lives on Overdue and Completed**.

### Grid alignment

Everything on the tracker resolves to **one gutter and one column grid**. Nothing is aligned by eye.

- **The gutter is 1.5rem from `sm`.** The `app-container`'s `sm:px-6` and the sidebar's `p-3` panel plus each row's own `px-3` both land on it, so the navbar's logo, the sidebar's icons and the page heading all start on the same vertical line.
- **An interactive box may bleed into the gutter; its text may not.** A row's padding is cancelled with an equal negative margin, exactly as the sidebar's `p-3` panel and `px-3` rows do it. The hover and focus surface then has room to breathe while the words stay on the line — the navbar's brand link and its action are built this way.
- **The navbar is read as the top of the sidebar's column**, so its two levels line up with the sidebar's: a 24px mark plus an 8px gap reaches the same 32px as the sidebar's 20px icon plus its 12px gap, which puts the wordmark exactly where the sidebar's labels start. Changing one size means changing the other.
- **The navbar's height comes from the `<header>`, and so does its centring.** A `h-full` child resolves against a height the header does not have — it only sets `min-height` — and silently collapses to its content, leaving the row stuck to the top of the bar.
- **The column grid is 4 columns with `gap-6` from `xl`.** Every layout resolves to it, so one panel's edge is also the next one's.
- **All three layouts share the same outer edges.** List is one panel across the full width; Board is columns across that same width; Calendar keeps the "No due date" tray in column 1 and the month grid in columns 2–4. Switching layouts must not move the outer edges.
- A card that is `sticky` uses `app-sticky-top`, never a hard-coded offset, so it clears the sticky navbar.

- **Order, top to bottom:**
  1. Page heading and a one-line description, with the Display button and the **New task** button on the right.
  2. The row of chips naming every active display setting.
  3. The active layout.
- **There are no stat tiles.** There were four, and three of them printed the same number as a sidebar badge: Total tasks was "All tasks", Done was "Completed", Overdue was "Overdue". A dashboard that says the same figure twice on one screen is not twice as informative.
- **There is no progress meter.** It read the whole database however the page was filtered, so standing in Today with nothing due still showed a bar and a percentage of everything. A number that does not answer the screen it is on is worse than no number. The sidebar badges carry the counts instead, and they count every task rather than the filtered list.
- **Main area:** the task panel, across the full width at every size. The New task form is not on the page at all.
- **Task panel:** one white card. Its header row holds the "Tasks" heading and the filter. Task rows are separated by dividers, and each row carries a 4px priority accent bar down its left edge. The bar is decorative only, because the same priority is already spelled out in its badge.
  - Below `md`, each row stacks: the checkbox and the title on the first line, then description and date, then the badges, then the actions at full width.
  - From `md` up, rows line up as table columns: **select | Task | Priority | Status | Actions**, under a column header row. The column header row is hidden while the list is loading or empty.
- **Mobile-first:** design at 360px wide, then enhance at `sm` (640px), `md` (768px), and `lg` (1024px).
- **No horizontal scroll** at any width. Long titles wrap (`break-words`) and never overflow.

### 1a. The task table

The list is a data table, following shadcn/ui's Data Table (MIT) the way the sidebar follows its
Sidebar: a checkbox column, a toolbar that appears for a selection, and headers that sort. Its code
is React over TanStack Table, which is a state machine for a table that renders its own rows, so
what is ported is the shape and not a line of it. **Column visibility and faceted filters are left
behind on purpose** — they are already the Display panel, and a second control for either is how the
two quietly come to disagree.

#### Selecting rows

- Each row carries a checkbox, and the column header carries the one that selects every row shown.
- **"Every row shown" means exactly that**, not every task in the database. The Display panel can be
  hiding finished work or filtering by priority, and a select-all reaching past the screen would act
  on rows the user has no way to check.
- The header checkbox uses the element's own `indeterminate` property for the half-selected state,
  so the browser announces "mixed" without the app describing it. That is the reason it is a real
  `<input type="checkbox">` rather than a button with `role="checkbox"`.
- **A selection never outlives the rows it named.** The list is rebuilt after every action, and
  anything no longer drawn is dropped from the selection with it. Switching to the board or the
  calendar clears it, because only the list has checkboxes.
- The select-all sits in the column header row, which only appears from `md`. Below that, rows are
  ticked one at a time.

#### The selection toolbar

- It takes over the right of the panel header rather than appearing as a bar of its own, so the rows
  do not move down the moment a checkbox is pressed.
- It reads **"3 selected"**, then Complete, Delete and Clear. The count is `aria-live="polite"`.
- **Complete is disabled when every selected row is already finished.** The endpoint would answer
  that nothing changed, and a button that says so first is better than a toast that says so after.
- Delete asks for confirmation, exactly as a single row's does, and the toast that follows carries
  **Undo**.
- **One request, not one per row.** `POST /api/tasks/bulk` runs the whole selection in a transaction.
  Five rows completed as five requests is five chances to stop half way, and it spends five of the
  rate limiter's allowance on one click.
- **Undo sends back what the server changed, not what was selected.** A row already in the state
  asked for is skipped, so reversing the selection would reopen work nobody touched.

#### Sorting from the headers

- **Task** sorts by name and **Priority** sorts by `Src\TaskSorter`, the order the exam grades. They
  set the same state the Display panel's Sorting select holds, so the two controls can never name
  different orders.
- **A header selects a sort; it does not flip one.** None of this app's sorts has a direction —
  TaskSorter is priority then oldest, Name is A to Z, Due date is soonest first — so the buttons
  carry `aria-pressed` rather than `aria-sort`. Claiming ascending would promise something the API
  does not have.
- **Status has no sort button.** Every order already runs through `byStage()`, so To do always
  precedes In progress and Done; a control there would change nothing.
- On the Board the headers are out of reach, and the Display panel's select is disabled there
  anyway, because the board's order is the one someone arranged by dragging.

#### A row's actions

- **Complete stays a button.** It is the commonest thing anyone does on this page, and putting it
  behind a menu would make one click into two.
- **Everything else sits behind one "…"**, the same `role="menu"` the board card's "…" uses: Open,
  Reschedule or Set a date, Reopen on a finished task, and Delete. Delete inside it is where the
  rules already wanted it — never the most prominent thing on the row — and a table row is the one
  place on the page where horizontal space is genuinely contested.
- **The board card has one button and a "…".** Complete stays on an unfinished card, and everything else sits in the card's own "…" (see the Board layout). A row and a card therefore differ in what they offer: a row has Complete, Open, Reschedule, Reopen and Delete; a card has Complete, Edit and Delete.

## 2. Visual design

- **Spacing:** use Tailwind's scale only (`2, 3, 4, 6, 8`). No arbitrary values like `mt-[13px]`. The one exception is the task table's column template, which is defined once as the `task-columns` utility in `resources/css/app.css`.
- **Typography:** the `font-sans` theme font. Page title `text-2xl font-semibold`, section headings `text-lg font-medium`, body `text-sm`/`text-base`, secondary text `text-gray-500`.
- **Surfaces:** white cards on a `bg-gray-50` page, `rounded-lg`, `border border-gray-200`, at most `shadow-sm`.
- **Color is meaningful, not decorative.** The palette comes from the logo: a red cube on white.

| Role | Colour | Where |
|---|---|---|
| Primary action | `gray-900`, white text | New task, Add task, every dialog's save |
| Accent | `red-600` and its tints | The mark, the selected sidebar row, today in the calendar, a drop target, a held card, every focus ring, the Undo on a toast |
| Destructive | `red-600` | Delete, and only ever behind a confirmation |
| Success | `green-700` | Done, and the Complete button |

- **The accent is red, and so is the destructive colour.** That is only safe because red is never a primary button here: a red button in this app is either Undo on a toast or Delete inside a confirm dialog, and both are things you meant to press. The primary action is near-black instead, so the two can never be mistaken for one another on the same row.
- **The accent is spent sparingly.** Red marks what is selected, what is about to take a drop, and where the keyboard is. Nothing else. A hint, a note or an inactive panel takes grey, because a pale red panel with dark red text is what this app's error banner is, and a hint that looks like an error is worse than no hint.

### Priority badges

| Priority | Style | Label |
|---|---|---|
| High | `bg-red-100 text-red-700` | High |
| Medium | `bg-amber-100 text-amber-800` | Medium |
| Low | `bg-slate-100 text-slate-700` | Low |

### Status

| Status | Label | Style |
|---|---|---|
| Pending | To do | `bg-blue-100 text-blue-700` badge |
| In progress | In progress | `bg-amber-100 text-amber-800` badge |
| In review | In review | `bg-purple-100 text-purple-700` badge |
| Completed | Done | `bg-green-100 text-green-700` badge; title gets `line-through text-gray-400` |

- The three read as stages, not states: a task is picked up, worked on, finished. **Starting a task does not finish it**, so it stays in Today and Overdue, and the sidebar counts keep counting it.

- Badges always include the text label. Never rely on color alone.

## 3. Buttons and actions

**A secondary button carries `gap-2`**, or an icon sits against its label with only the whitespace the template happened to leave between them. It also carries `shadow-sm`, the same faint lift the app's other raised surfaces have.

| Type | Use | Style |
|---|---|---|
| Primary | Add task, New task | Solid near-black (`gray-900`), white text |
| Secondary | Complete | Outlined light green |
| Destructive | Delete | Red text or outline, never the most prominent button on the row. It keeps its natural width, so it never stretches to fill the row when a task is already completed and Delete is the only action left. |

- Every button has explicit `type="button"` or `type="submit"`.
- Buttons that carry a row's main actions, and every control in a form, are at least 40px tall (`min-h-10`).
- Inline controls sitting inside a line of metadata, such as the due-date button on a task row, are at least 32px (`min-h-8`). That stays clear of the 24px WCAG 2.2 AA minimum without making a metadata line as tall as a button bar. Nothing smaller than 32px is ever tappable.
- **Complete is the only stage button, on rows and on cards alike.** Starting a task is the board's drag or the dialog's **Status** field. A finished row keeps **Reopen inside its "…"**: a dead end that could only be left by catching a toast in time or opening the dialog was the one stage change with nowhere to go back to. It is a menu item, not a button, so the row still has exactly one stage button on it.
- **Complete stays on a board card even though dragging also finishes a task.** Dragging a card the width of the board to say "done" is a lot of hand for the commonest action; the drag is there for the steps between.
- Delete asks for confirmation in the `<dialog id="confirm-dialog">` modal, because it throws work away. A native `<dialog>` with `showModal()` traps focus and closes on Escape for free, and unlike `window.confirm` it can be styled and does not freeze the page. Cancelling returns focus to the Delete button that opened it.
- Delete is recoverable, so the dialog must not claim otherwise. `DELETE` soft-deletes and the toast that follows offers **Undo**, which calls `PATCH /api/tasks/{id}/restore`. The confirmation stays in front of it as the cheaper stop: undo asks the user to notice a toast in time, the dialog does not.
- **A button that starts a request goes through three states**: what it does, that it is doing it, and that it is done. `Complete` → a spinner and `Completing…` → a green tick and `Completed`. It stays disabled through all three: it is reporting what happened, not offering to do it again, and most of these buttons are about to be removed by the re-render anyway.
- **The finished state covers work that is happening, not a pause invented to show it.** The reload behind it is several requests; the 700ms floor is only there for when the server answers too quickly for the state to be read.
- **The width is pinned when the first label changes.** `Complete`, `Completing…` and `Completed` are three different widths, and a row of buttons would shuffle under the cursor at each step.
- **The spinner is a ring with a quarter missing, not a drawn icon.** A circle turning is what a spinner looks like everywhere; it is built from a border, so the gap is exact and the ring takes the button's own colour. With motion turned off it is a still ring, which still reads as waiting beside a label ending in three dots.
- **A swapped-in mark takes its classes from the button's resting icon, never from the one it is replacing.** Reading them off whatever is currently there handed the finished tick the spinner's animation, and the tick span.

## 4. Form

**The New task form is a modal, not a column.** It opens from the **New task** button in the page header and lives in `<dialog id="task-dialog">`.

- A form that is only used to add something does not earn a permanent quarter of the screen; the list it feeds does. Behind a dialog it also stops shifting the layout between the List and Calendar views.
- A native `<dialog>` with `showModal()` traps focus and closes on Escape for free, which is the same reason Delete uses one.
- **Its due date is the `:inline` grid, never the popover.** A `<dialog>` is `overflow: auto` in the UA stylesheet, so a floating panel inside one is clipped rather than layered over it. This is the rule under "Date fields" below, and the New task form is the case that most easily forgets it.
- The dialog is capped at `max-h-[calc(100dvh-2rem)]` and scrolls inside, because an inline month grid plus five fields is taller than a short viewport.
- Opening focuses Title. Closing — by Cancel, Escape, the backdrop, or a successful save — clears the form and returns focus to the button that opened it, so a half-typed task is never waiting the next time it opens.

- Every field has a visible `<label>` tied to it with `for`/`id`. Placeholders are not labels.
- Fields: Title (required, marked with `*`), Description (optional `<textarea>`, 3 rows), Priority, Project.
- **Project is a text input, not a select.** A project is created by typing its name: a new name makes the project, an existing one (matched without regard to case) reuses it, and leaving it empty means no project. `<datalist id="project-options">` offers the existing names as suggestions, with the hint "Type a new name to create a project." The task dialog's Project field is the same input on the same datalist and saves on change.
- Priority is a radio group styled as three cards inside a `<fieldset>`, not a `<select>`, so all options are visible at once and each is a single tap. The checked card takes its priority's tint through `has-checked:`. Medium is checked by default.

### Date fields

Every date uses `<x-date-field>`, never a bare `<input type="date">`, because Chrome, Firefox and Safari each draw a different native control and it would be the one field on the form that does not look like the app.

- **The native input stays underneath and stays the source of truth.** It holds the ISO value, it validates, and without JavaScript it is still a working date control. `datepicker.js` only hides the browser's indicator and reveals our button — the swap is all-or-nothing, so there are never two pickers on one field and never zero.
- **The grid is the calendar layout's grid.** `gridStart` and `cellCount` are imported from `calendar.js` rather than rewritten, so "the week starts on Monday" is defined once.
- **Never build the value with `new Date(iso)`.** Use `parseDate` / `toIsoDate`, or "Today" lands on yesterday west of Greenwich.
- **Keyboard:** arrows by day, PageUp/PageDown by month clamped to that month's length, Home/End to the ends of the week, Enter to choose, Escape to dismiss and hand focus back to the button.
- **`:inline` where picking the date is the whole point**, and always inside a `<dialog>`: a `<dialog>` is `overflow: auto` in the UA stylesheet, so a floating panel inside one is clipped rather than layered over it. An inline grid also drops any control the surrounding dialog already offers, so "Clear" never appears twice.
- **The popover hangs off the field's left edge** and flips only to stay inside the viewport. The field can sit in a column narrower than the panel, and growing rightwards keeps it on the page's gutter.
- **A past day is never offered.** The grid draws it grey and disabled, and the native input carries `min` set to today, which matches the server's `after_or_equal:today`. The days are shown rather than hidden: a month with holes in it is harder to read than a month with grey in it.
- Placeholders show an example; they never replace a label.
- Validate `title` client-side (not empty after trimming), and still rely on the server's 400 response as the source of truth.
- Show field errors directly under the field in `text-sm text-red-600`, and link them with `aria-describedby`.
- On error, keep what the user typed and leave the dialog open. On success, the dialog closes, which clears the form and returns focus to the New task button.

## 5. The Display panel

One button in the page header carries **everything that answers "what am I looking at"**: the layout, whether finished work shows, the grouping, the sorting and two filters. They were a segmented layout switch in the header and a segmented status filter inside the task panel; one control is what stops the two disagreeing.

| Group | Items |
|---|---|
| Layout | List, Board, Calendar |
| | Completed tasks (a `role="switch"` toggle) |
| Sort | Grouping, Sorting |
| Filter | Date, Priority |

- **Sort and Filter fold away** behind their headings, with `aria-expanded` on the button and the chevron rotated from it, so the state is never in the icon alone.
- **A row of chips under the page heading names every active setting.** A panel that hides its own settings is how someone ends up staring at an empty list wondering where their tasks went. The empty message says the same thing: "Nothing matches these display settings."
- **Grouping works in both the list and the board.** One function builds the groups, so a board column and a list group always hold the same tasks under the same heading; the list draws each heading as a row of its own, and leaves out a group with nothing in it.
- **The stages always read To do, In progress, In review, Done** — across the board, the list's groups and the list's own order. They are one sequence, and work does not run backwards through it.
- **Grouping has no "None" on the Board**, because a board with nothing to group by is a list. The option is disabled there rather than accepted and quietly ignored.
- **Sorting's "Default" is `Src\TaskSorter`**, the order the exam grades. Due date, Name and Manual replace it, and all four keep unfinished work above finished work. **Manual belongs to the Board**, which selects it and disables the control, because the board's order is the one someone arranged by dragging.
- The panel closes on Escape, on a click outside it, and returns focus to its button.

## 5b. The Board layout

The board's columns **are the grouping**: by status it is To do / In progress / In review / Done, by priority High / Medium / Low, by project one column per project plus "No project". There is no separate table of user-made sections, because the grouping already says what a column is.

- A card carries the title, description, project, due date and priority badge. **It carries no status badge while grouped by status**, because the column it sits in already says that.
- **A card's "…" holds Edit and Delete.** A card has room for one button and not for a row of them, so Delete moved into a 32px "…" at the card's top-right, with Edit (it opens the task dialog) beside it. Delete still asks for confirmation and still offers Undo. It is the same `role="menu"` the table row uses, so arrows, Home/End and Escape behave as they do there, and focus returns to the "…".
- **Grouped by status, dragging is how a task changes stage**, and the keyboard equivalent below is the other way. Complete stays on the card regardless: finishing something is the commonest action on the board, and dragging a card across its whole width to say so is a lot of hand for it.
- **Under any other grouping dragging is switched off.** The API can change a stage, not a priority or a project, so a drop there would have nothing behind it. A gesture that silently does nothing is worse than no gesture.
- **Dragging is never the only way.** A card is focusable and announces it: Enter opens the task, Space picks it up, the left and right arrows move it between columns, **the up and down arrows move it within one column**, Space drops it, Escape cancels. The card that had focus is found again after the move, the same way the card's "…" is.
- A drop runs one request and the board updates only once the server answers, like every other action. The toast carries **Undo**, which is the reverse stage endpoint.
- **A drop does not make the board blink.** The gap stays where the card was let go until the server answers, rather than the card flashing back to its old column first, and only a task that was not on screen before plays the entrance animation. Replaying it on every card after each reload is what made the whole board flicker.
- **A card can be dropped at any height in a column**, not only at its end. The cards part around a gap that shows where it will land: above the first card, between any two, or below the last. The gap is a real element in the list, so the cards are moved by the layout rather than by a measurement, and the column cannot disagree with itself about where the card is going.
- **The drop names the card it landed under, not a position number.** The Display panel can be hiding cards, so counting the ones on screen would land the task somewhere else in the real column.
- **The board's order is the one you arrange**, which is why Sorting reads Manual there and is disabled. A sort would have to throw that arrangement away to mean anything, so it is withdrawn rather than ignored, the same way None is withdrawn from Grouping. Manual is offered nowhere else, because nothing else lets you arrange anything.
- **A drop is accepted anywhere in a column**, not only on the strip its cards happen to cover. The columns stretch to one height and the list inside fills its column, so letting a card go in the empty space under the last one still moves it. Sizing each column to its own cards left that space outside the column, where a drop was refused with nothing on screen to explain why.
- Below `md` the columns stack into one running list rather than scrolling sideways.

## 5c. The task dialog

Clicking a task's name opens `<dialog id="task-detail">`: a checkbox and the name on the left with the description and the sub-task checklist under it, and Project, Date and Priority down the right.

- **The name is a real `<button>`** in both layouts, so a task opens from the keyboard and not only under a pointer.
- **Every field saves on its own request. There is no Save button**, so nothing is lost by closing and nobody has to wonder whether an edit took. Fields save on `change`, not `input`: one request per edit rather than one per keystroke.
- The up and down arrows step through **the list the user is looking at**, so what they walk matches what is on the page behind the dialog.
- **Status is a field here, not a button on the row.** It runs `start`, `complete` and `reopen`, the same three endpoints the board's drag runs, so a stage change has one path however it was made. The circle beside the name stays as the one-click way to finish something, which is the action people take most.
- **A sub-task is a checklist item, not a task.** It never appears in the list, the board or the calendar, and no stat tile counts it. That is what keeps `TaskSorter` and every graded figure exactly as the exam specifies.
- **There is no Reminders, Labels or Location.** The app has no accounts and no mail, so a reminder would be a control that never fires, and a location would mean nothing. A control that cannot work does not go in.

## 6. States

Every data view needs all four states:

| State | Behaviour |
|---|---|
| Loading | Skeleton rows on first load, so the panel keeps its height instead of collapsing. Never a blank area. |
| Empty | A friendly message per filter: "No tasks yet. Use New task to add your first one." / "No pending tasks." / "No completed tasks yet." |
| Error | A red inline banner with a retry button. The sidebar counts and the meter clear, so stale numbers never sit beside an error. |
| Success | A short, auto-dismissing toast (about 3s): "Task added", "Task completed", "Task reopened", "Task deleted". **A toast is a card, not a pill**, following shadcn/ui's Sonner (MIT): a title and a quieter line under it, with no icon. The two lines carry the whole message, and an error is told apart by its title in red rather than by a mark — the title is the server's own words, so the meaning is never in the colour alone. The white surface with a hairline border is what the rest of the app is built from, and `shadow-lg` is what every other floating panel already uses; the dark pill was the one surface belonging to nothing else. |
| Description | **A toast names the thing it happened to, and when.** "Task completed" alone makes someone who clicked the wrong row check the list to find out which one. The second line is the name, then the time, separated by `·`. Names are user input, so the line is set as text and never as markup. |
| The time on a toast | "Today at 9:00 AM" for something that has just happened, and the full `Sunday, December 03, 2026 at 9:00 AM` for a date worth reading in full. Spelling out today's weekday and year to say "a moment ago" is a line of noise that also pushes the name beside it onto a second row. **A reschedule shows the task's new due date, not the moment it was dragged**, because the new date is what the action was for. |
| Stacking | At most three at once. Beyond that the newest is pushing older ones off the screen, which is a tower nobody read, so the **oldest** goes — the newest is what just happened. |
| Undo | A toast carrying an action button lasts 8s, not 3s, because the user has to notice it and then reach it. Complete and Delete both offer **Undo**; it reverses through the API (`reopen`, `restore`) and reports the result in a toast of its own. The button is a real 32px target, so it is tappable and keyboard-reachable. **`#toast-region` is `pointer-events-none`**, so a toast never swallows a click meant for the page behind it; a toast that has something to press must take its own clicks back with `pointer-events-auto`, or the button looks alive and does nothing under the mouse while still working from the keyboard. |

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
- The sidebar badges and the meter show live counts from `GET /api/tasks/stats`. They are refreshed after every action, and they count every task rather than the filtered list: a badge that moved with the Display panel could not be used to decide what to display.

## 9. Security and performance

- Task text is user input. Render it with `textContent` only, never `innerHTML`.
- Use no extra UI libraries beyond Tailwind. The page should load fast with a single Vite bundle.
- Avoid layout shift: buttons keep their width when the label changes to `Saving…`.
- Motion is decoration. Every animation sits inside `@media (prefers-reduced-motion: no-preference)` in `resources/css/app.css`, so nothing moves for anyone who asked it not to.

## 10. Scope guard

Build only what the exam asks for, plus what the user has since asked for: the board, the task dialog and its sub-tasks, and dragging a card between the board's columns. No dark mode, auth, labels, reminders, or animations beyond simple transitions, unless the user asks for them.

---

## UI/UX checklist (run before marking any UI task done)

- [ ] Works at 360px, 768px, and 1280px with no horizontal scroll (cards on mobile, table columns from `md`, board columns side by side from `md`)
- [ ] `/` redirects to `/tasks`
- [ ] "Skip to content" appears on the first Tab press
- [ ] The sidebar counts update after every add, complete, reopen, and delete
- [ ] Skeleton rows show on first load and are gone afterwards
- [ ] Delete dialog confirms, cancels, and closes on Escape without deleting
- [ ] Priority and status badges match the tables above and include text labels
- [ ] Form has visible labels, inline errors, keeps input on error, resets on success
- [ ] New task opens the dialog, Escape and the backdrop close it, and a half-typed task is gone the next time it opens
- [ ] The due date inside the New task dialog is the inline grid and is not clipped
- [ ] Date fields: the grid opens, arrows and PageUp/PageDown move, a chosen day reaches the API unchanged, and with JavaScript off a plain native date input remains
- [ ] Buttons disable while loading, show a spinner, then a green tick and the past tense; Delete asks for confirmation
- [ ] The button does not change width through any of its three states
- [ ] A toast reads as a card with a bold title and a grey line naming the task and the time
- [ ] The toast action is the red button, is at least 32px, and works from the keyboard
- [ ] No primary button is red, and no hint or note wears the error banner's pale red
- [ ] Rescheduling a task shows the new due date in full, not the moment it was dragged
- [ ] Four actions in a row leave at most three toasts on screen, and the newest is one of them
- [ ] Undo on the Complete and Delete toasts restores the task, and the stats and sidebar counts follow
- [ ] Add, Complete, Delete, and every Display setting work without a page reload
- [ ] Upcoming opens the calendar, and the Layout buttons are disabled while it is open
- [ ] The sidebar holds the five smart views with their counts, and nothing about projects
- [ ] Sidebar views filter the list, and the title names the current one
- [ ] Typing a new name in the New task form's Project field creates the project; typing an existing one in any case reuses it
- [ ] The task dialog's Project field saves on change, and clearing it removes the project
- [ ] All tasks and Today show the Board only, with both Display mode buttons disabled
- [ ] Overdue and Completed keep the List and Board choice, and the data table appears there
- [ ] A board card's "…" opens Edit and Delete, arrows and Escape work, and focus returns to it
- [ ] Deleting from the card's "…" asks first, and the toast's Undo brings the task back
- [ ] The sidebar, the page and the calendar tray scroll with the wheel and the keyboard, and draw no scrollbar
- [ ] Drawer opens, closes on Escape/backdrop, and is untabbable while closed
- [ ] Rail and Ctrl+B collapse the sidebar, and the state survives a reload without flashing
- [ ] The sidebar stays put while the page scrolls, reaches the bottom of the viewport, and the content reclaims its space when it collapses
- [ ] Scrolling up and down, including up past the very top, leaves nothing showing above or through the navbar, and the sidebar's top edge meets it with no seam
- [ ] "Skip to content", the heading focus lands on after a delete, and anything else scrolled into view all clear the navbar rather than landing under it
- [ ] Navbar logo, sidebar icons and page heading all start on the same left edge, and the wordmark starts where the sidebar labels do
- [ ] The navbar row is vertically centred in the bar, and its action is a 40px target ending on the right gutter
- [ ] The heading, the chip row and the active layout all sit on the same column edges, and switching layout moves nothing sideways
- [ ] Calendar: drag a chip to a day, drag to the tray to clear, and reschedule from the dialog
- [ ] A past day takes no chip and offers no button, in the calendar and in every date field
- [ ] Month grid from md, agenda below it, with matching hint text
- [ ] Pending tasks appear above completed ones in the All view
- [ ] Ticking a row shows the toolbar, and the count matches what is ticked
- [ ] The header checkbox selects every row shown, goes half-checked for a partial selection, and clears from Clear
- [ ] A select-all with the Completed toggle off or a filter on never touches a row that is not on screen
- [ ] Bulk Complete is disabled when every selected row is already finished
- [ ] Bulk Complete and bulk Delete each run one request, and the toast's Undo puts the rows back
- [ ] Undo after completing a mixed selection leaves the rows that were already done alone
- [ ] A selection does not survive a reload, a filter change, or switching to the board
- [ ] The Task and Priority headers sort, the Display panel's Sorting select follows them, and Status has no sort button
- [ ] A row's "…" opens Open, Reschedule and Delete, with Reopen on a finished row, and focus returns to it
- [ ] An unfinished board card still carries Complete as a button
- [ ] Display opens, closes on Escape and on a click outside, and returns focus to its button
- [ ] Each layout shows only itself, and switching moves nothing sideways
- [ ] Grouping by status, priority and project regroups both the board and the list, and None is disabled on the board
- [ ] To do comes before In progress before In review before Done, in the board's columns and in the list
- [ ] Each Sorting option reorders the list, and unfinished work stays above finished work in all four
- [ ] The Date and Priority filters narrow the list, and the chips name every setting that is on
- [ ] Turning Completed tasks off hides finished work in both the list and the board
- [ ] Dragging a card to another column moves the task, the toast offers Undo, and the stats follow
- [ ] A card dropped in the empty space below a column's last card still moves there
- [ ] Dragging a card opens a gap where it will land, and the cards below it move down
- [ ] A card can be dropped above the first card, between two cards, and below the last, and stays where it was put after the reload
- [ ] A drop lands in the right place while the Completed toggle or a filter is hiding cards
- [ ] The up and down arrows move a held card within its column, and Undo on the toast puts it back exactly where it was
- [ ] Sorting reads Manual and is disabled on the Board, and the chip row says so
- [ ] A card can be moved with Space and the arrows alone, and keeps focus after the move
- [ ] Grouped by priority or project, the cards are not draggable
- [ ] The dialog's Status field moves a task through all four stages, and the board and the counts follow
- [ ] A task's name opens the dialog from a click and from the keyboard, in both the list and the board
- [ ] Each dialog field saves on its own, and the list behind it follows
- [ ] The dialog's up and down arrows step through the list that is on the page
- [ ] A sub-task adds, ticks off, deletes, and never appears as a task row or in a sidebar count
- [ ] Loading, empty (per filter), error, and success states all appear correctly
- [ ] Full keyboard navigation with visible focus rings
- [ ] No `innerHTML` with task data
- [ ] No console errors
