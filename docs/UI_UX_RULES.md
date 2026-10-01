# UI/UX Rules

Every screen, component, and interaction in Simple Task Tracker must follow these rules. The checklist at the bottom is the gate before any UI work is considered done.

## 0. Pages and shared layout

| Route | View | Purpose |
|---|---|---|
| `/` | `home.blade.php` | Public landing page that explains the app and links to it |
| `/tasks` | `tasks/index.blade.php` | The task tracker dashboard |

### Tracker shell

The tracker runs the **full-bleed app shell**; the landing page keeps the boxed marketing shell. `<x-layout :fluid="true">` is what switches between them, and it also opens the `aside` slot that holds the sidebar outside `<main>`, because a sidebar is navigation and not page content.

| | Landing (`/`) | Tracker (`/tasks`) |
|---|---|---|
| Navbar | static, boxed at `max-w-6xl` | `fixed top-0 shadow-sm`, spans the viewport |
| Container | `max-w-6xl` | `app-container`: full width, capped at `--container-app` (112rem) so an ultrawide display does not stretch a task row across a metre of glass |
| Sidebar | none | fixed, full height |
| Gutter | `px-4 sm:px-6 lg:px-8` | `px-4 sm:px-6` |

**A fixed navbar hides whatever the browser scrolls to the top of the viewport**, because the browser cannot know the bar is there. Everything inside the shell carries `scroll-margin-top` for the bar's height, so the skip link's target, the heading focus moves to after a task is removed, and anything else scrolled into view all land below it. `<main>` reserves the same height with padding, since it starts at the top of the document.

**The navbar and the sidebar are one chrome layer, and both are `fixed`.** They must share a positioning scheme, because anything else lets them drift apart: a sticky navbar rides the document, so an overscroll bounce — Chrome's rubber band when you scroll up past the top — slides it down over the fixed sidebar and swallows the sidebar's heading. `<html>` also carries `overscroll-y-none` so the bounce does not happen in the first place, and `<main>` reserves `--header-height` at the top because the navbar no longer takes space in the flow.

The tracker's own shell is three parts: a **sidebar**, a **header** (title, layout switch), and the active **layout**.

- **Sidebar** holds the smart views (All tasks, Today, Upcoming, Overdue, Completed), a **Favorites** group, and the **My projects** tree. The UI says "project"; the API, the table and the model are still `category`, which is a rename worth finishing in one pass rather than half-doing. Views and categories are alternative selections: picking one clears the other, and the page title always names the current one.
  - **Favorites** appears only when something is in it, and it is flat: the point of the group is to skip the tree, not to repeat it. A favourite still appears in My projects, because removing it from there would make the tree lie about what is in it.
  - **My projects** is a tree. A child is indented under its parent with a rule down the left edge. A project whose parent is not in the list — archived, say — is drawn as a root, so a branch can never disappear from the sidebar because of where its parent happens to be.
  - **Archived** sits at the foot of My projects and appears only when something is archived. It is the way back: archiving is reversible, so the undo cannot be hidden somewhere the user has to remember.

#### Sidebar behaviour

The sidebar follows shadcn/ui's Sidebar (MIT), rebuilt for Blade and vanilla JS. Three widths, set as CSS variables on `:root`:

| Variable | Value | Used when |
|---|---|---|
| `--sidebar-width` | 16rem | expanded, from `lg` |
| `--sidebar-width-icon` | 3.5rem | collapsed to the rail, from `lg` |
| `--sidebar-width-mobile` | 18rem | the drawer, below `lg` |

- From `lg` it is **fixed, not sticky**: it runs from the bottom of the sticky navbar (`--header-height`) to the bottom of the viewport, flush against the left edge, and scrolls on its own rather than with the page.
- **`--header-height` is measured, never assumed.** `--header-min-height` (4rem) is the floor the navbar is laid out against, and `shell.js` writes the bar's real height back into `--header-height` through a `ResizeObserver`. A constant would be wrong the moment the bar grows — under a text-only zoom, a larger default font, or a longer brand name — and the sidebar would then start behind it. The navbar's height also has to include its bottom border, so `app-header` sits on the `<header>` and not on the `<nav>` inside it; a pixel short leaves a hairline of content showing through. `<main>` is pushed clear of it by `--app-shell-offset`, which `<body data-sidebar-state>` swaps between the two widths below. Blade sets that attribute from the cookie and `sidebar.js` keeps it in step, so the content never starts at the wrong width.
- It **collapses to an icon rail**. Collapsing hides everything marked `.sidebar-collapsible` (labels, counts, group headings, the category form), centres the icons and the rail button, and the content reclaims the space in the same 200ms. The icons keep a `title` so each button is still identifiable.
- The rail button and **Ctrl/Cmd+B** both toggle it. The state is written to a `sidebar_state` cookie and read back in Blade, so a collapsed sidebar never flashes open on load. That cookie is excluded from Laravel's cookie encryption, because JavaScript writes it; it holds nothing sensitive.
- Below `lg` it is an off-canvas drawer behind a menu button, closed by its own button, the backdrop, Escape, choosing anything inside it, or the viewport growing past `lg`. While closed it is `visibility: hidden`, not merely translated off-screen, so it stays out of the tab order.
- Rows built by JavaScript use the same `sidebar-menu-button` / `sidebar-menu-action` / `sidebar-menu-badge` utilities as the Blade ones, so the two can never drift apart.

#### A project's actions

Every action a project offers sits behind one **"…"** at the end of its row, so the row carries a single control however many it has. Delete is inside it too: throwing work away is not a button to be brushed past on the way to selecting a project.

The items are grouped by what they are for, with a rule between the groups:

| Group | Items |
|---|---|
| The project itself | Edit, Add to favorites / Remove from favorites |
| Where it sits | Project actions ▸ (Move, Duplicate) |
| What is on it | Comments, View activity |
| Getting rid of it | Archive, Delete |

- The menu is appended to `<body>` and positioned `fixed`. The sidebar scrolls on its own, so a panel placed inside it would be clipped by that overflow; the cost is that a panel does not follow the page, so any scroll closes the whole menu.
- It is a real `role="menu"`: arrows move between items and wrap, Home and End reach the ends, and clicking or tabbing away dismisses it without stealing focus.
- **A submenu opens to the side**, flipping to the other side rather than hanging off the viewport. ArrowRight and a click both open it, hovering opens it for a pointer, and ArrowLeft steps back out. **Escape closes one level at a time**, so a submenu opened by mistake is cheap to undo.
- Focus follows the row through a re-render: the list is rebuilt after every action, and the "…" that had focus is found again by its project id.
- **Move and "move into folder" are the same operation.** A folder here is simply a project with children, so there is one tree and not two. The reference app keeps folders and parent projects apart; building both would be two ways to say the same thing.
- **Favourite is set, not toggled.** The menu already knows which of the two labels it is showing, and two clicks racing each other would otherwise undo one another.
- **Archive is not delete.** An archived project keeps its tasks and is only out of the way, so the sidebar always shows the way back to it.
- **Delete is recoverable, and the toast says so.** Deleting a project soft deletes it and the toast carries **Undo**, exactly as deleting a task does. A project holds more than a task does, so it cannot be the one thing in the app that is thrown away for good. The confirmation stays in front of it as the cheaper stop: undo asks the user to notice a toast in time, the dialog does not.
- A deleted project's name stays reserved while it can still be restored, so creating another project with that name is a 400 until the old one is gone for good.

#### The project dialog

Add and Edit are **one** `<dialog>`. Two would mean two copies of the 224-option icon grid in the page, and two places for the picker's behaviour to drift apart.

- Fields: Name (required, with a live `n/40` count because the server's limit is otherwise invisible), Description, Color, Parent project, Icon.
- **There is no Access or Move-to-team field.** The reference app has both; this app has no accounts, so a "Restricted" control would be decoration that does nothing. A field that cannot work does not go in.
- **The parent select leaves out the project itself and its own children**, because choosing one would cut the branch off the tree. The server refuses it as well; leaving the options out is what stops the user reaching for something that cannot work.
- Opening focuses and selects Name. Closing — by Cancel, the close button, Escape, the backdrop, or a successful save — clears the form and returns focus to whatever opened it, which is the "…" when the dialog was opened from a row.

#### Comments and activity

They are **one dialog with a tab between them**, because both answer the same question: what has happened on this project. The menu keeps two entries so either one is a single click; they differ only in which tab opens.

- The header is `#` and the project's name, and the tab switch is a segmented control with `aria-pressed` on the active tab.
- **Comments are oldest first**, unlike the activity feed: a thread is read from the top, while a feed is scanned for the latest thing that happened.
- **Activity is grouped by day**, newest day first, each day headed by its date, "Today" when it is, the weekday, and how many things happened. Times are relative ("2 hours ago") through `Intl.RelativeTimeFormat`, which is built into the browser; anything over a week old falls back to the date, because "23 days ago" is harder to place than the day it happened.
- The empty state is an illustration with a sentence under it. The SVG is `aria-hidden` and is never the only thing there, because a picture alone says nothing to a screen reader.
- Comment text is user input and is rendered with `textContent` only.
- All four states in both tabs: "Loading…", the content, an empty message, and the server's message on failure.
- **The Comment button is indigo, not the red of the app this is modelled on.** Red is this app's destructive colour, and posting a comment is the least destructive thing on the screen.
- **The compose toolbar carries the emoji button and nothing else.** An attachment or a microphone that does nothing is the same decoration the project dialog already refused for Access.

#### Reactions

- **Nothing is installed for these.** An emoji is Unicode text, so the eight in `App\Enums\Reaction` need no package and no dataset. A searchable picker over 1,900 emoji would need one, and it would have been the project's first UI dependency; the fixed row is what reactions are actually used for.
- A chip shows the emoji and the count, and carries `aria-pressed` for "I reacted", so the state is in the markup rather than in the colour alone. Its `aria-label` names the emoji and the count, because an emoji on its own is read out inconsistently.
- The button beside the chips offers **only the emoji nobody has used yet**, so the same one is never offered twice.
- **A reaction belongs to a browser, not a person**, because the app has no accounts: a random token in `localStorage`. That is the honest limit of what "who reacted" can mean here, and it means reactions are anonymous — a count, never a name. Losing the token loses only which reactions were mine, never the counts.
- `mine` is worked out on the server from the token the request carries, never trusted from the client, so a button's pressed state and the row in the database cannot disagree.

#### Project icons

The app has two icon sources and they do not overlap. **Heroicons** is the chrome: every icon the app chooses for itself. **Iconify's Fluent UI set** (MIT, via `@iconify/tailwind4`) is only for the icon a user picks for their own category, where a handful of outline glyphs would not be enough to tell a category apart.

Heroicons is kept in two maps: `<x-icon>` for markup the server renders, and the `ICONS` map in `resources/js/dom.js` for rows JavaScript builds. **Neither has to hold every icon** — each carries what its own side draws — but an icon asked for and not defined fails quietly: Blade throws only in debug, and `dom.js` writes `d="undefined"`, which draws nothing at all. `IconMapTest` fails instead, in both directions: an icon used but not defined, and an icon defined that nothing draws.

- A project carries **an icon and a colour**. The colour was dropped once, on the grounds that two marks for one project is one decision too many; it came back when projects gained a tree. Down a nested list the icon says what the project is and the colour is what tells two similar icons apart at a glance.
- The colours are the seven in `App\Enums\CategoryColor`, not a free colour field, so nobody can pick one that fails contrast against the panel. The matching Tailwind classes are literal strings in `PROJECT_COLORS` in `resources/js/app.js`, the same way `PRIORITY_BADGES` works, and a test fails if the two drift apart.
- The 224 choices are fixed in `App\Enums\CategoryIcon`, not free text, so the class name can never come from user input. The column is a `varchar`, not a database enum, because 224 values would make the schema unreadable; `Rule::enum()` closes the set instead.
- **The project's name is the search; there is no second box to fill in.** Nothing is shown until something is typed, because 224 icons in a sidebar is a wall rather than a choice. A typed word matches the start of an icon's whole name or of any word in it, so "music" offers the music notes and "m" offers mail, money and music alike.
- Fluent names its icons after the picture, not after the word a person would type: "Work" does not contain "briefcase" and "Gym" does not contain "dumbbell". `ICON_ALIASES` in `resources/js/app.js` carries the common words across, and a test checks every target is a real `CategoryIcon`, since a typo there would simply never show a suggestion.
- The closest match is checked automatically, so picking one is a glance rather than a step. An icon that stops matching is unchecked with it, because the picker must never save something it is no longer showing; the form falls back to `folder`, which is what the note under it promises.
- The class is `icon-[fluent--{value}-24-regular]` and it is built at runtime, which **Tailwind cannot see**. Every value is therefore repeated in `@source inline(...)` in `resources/css/app.css`. A case added to the enum without that line renders as an empty box, and a test fails if the two drift apart.
- The icon is always `aria-hidden`, because the project's name sits right beside it.

- Both pages use the `<x-layout>` component, which provides the shared structure:
  - a "Skip to content" link
  - the top navbar (logo and app name on the left, one action on the right)
  - the `<main>`
- The navbar action depends on the page. On the landing page it is an **Open app** primary button. On the tracker it is a **Home** text link.
- Within a page, the navbar and the content use that page's one container, so their left and right edges line up down the whole screen.
- The tracker's JavaScript loads only on `/tasks`. The landing page loads CSS only.

## 1. Layout (tracker dashboard)

A dashboard in the `app-container`, beside the fixed sidebar.

### Grid alignment

Everything on the tracker resolves to **one gutter and one column grid**. Nothing is aligned by eye.

- **The gutter is 1.5rem from `sm`.** The `app-container`'s `sm:px-6` and the sidebar's `p-3` panel plus each row's own `px-3` both land on it, so the navbar's logo, the sidebar's icons and the page heading all start on the same vertical line.
- **An interactive box may bleed into the gutter; its text may not.** A row's padding is cancelled with an equal negative margin, exactly as the sidebar's `p-3` panel and `px-3` rows do it. The hover and focus surface then has room to breathe while the words stay on the line — the navbar's brand link and its action are built this way.
- **The navbar is read as the top of the sidebar's column**, so its two levels line up with the sidebar's: a 24px mark plus an 8px gap reaches the same 32px as the sidebar's 20px icon plus its 12px gap, which puts the wordmark exactly where the sidebar's labels start. Changing one size means changing the other.
- **The navbar's height comes from the `<header>`, and so does its centring.** A `h-full` child resolves against a height the header does not have — it only sets `min-height` — and silently collapses to its content, leaving the row stuck to the top of the bar.
- **The column grid is 4 columns with `gap-6` from `xl`.** The stat row, the list layout and the calendar layout all use it, so a tile's edge is also a panel's edge.
- **The two layouts share the same outer edges.** List is one panel across the full width; Calendar keeps the "No due date" tray in column 1 and the month grid in columns 2–4. Switching layouts must not move the outer edges.
- A card that is `sticky` uses `app-sticky-top`, never a hard-coded offset, so it clears the sticky navbar.

- **Order, top to bottom:**
  1. Page heading and a one-line description, with the layout switch and the **New task** button on the right.
  2. Stat cards.
  3. Main area.
- **Stat cards:** four `<x-stat-card>` tiles, 2 per row on mobile and 4 per row from `lg`. Each pairs a tinted icon with its number: Total tasks, Pending, Completed, High priority pending. Below `sm` the icon stacks above the label so the text never gets squeezed.
- **Progress meter:** a ratio against a total belongs in a meter, not a fifth number. One bar on a single-hue track, with the label "N of M done (P%)" always visible, so the value never depends on the bar's colour alone. It carries `role="progressbar"` with `aria-valuenow`. With no tasks it reads "No tasks yet"; on a load failure, "Unavailable".
- **Main area:** the task panel, across the full width at every size. The New task form is not on the page at all.
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

| Status | Label | Style |
|---|---|---|
| Pending | To do | `bg-blue-100 text-blue-700` badge |
| In progress | In progress | `bg-amber-100 text-amber-800` badge |
| Completed | Done | `bg-green-100 text-green-700` badge; title gets `line-through text-gray-400` |

- The three read as stages, not states: a task is picked up, worked on, finished. **Starting a task does not finish it**, so it stays in Today, Upcoming and Overdue, and the "Not done" tile keeps counting it.
- The tile is called **Not done**, not "Pending", because it adds To do and In progress together and a count must not name one of the two things it is adding up.

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
- A task that has not been picked up also shows **Start**, in amber, before Complete. It is only on that row: a task already in progress or already done has nothing to start, and a disabled button on those rows would say less than no button at all. Undo on its toast is `reopen`, so starting something by mistake costs one click, the same as finishing it by mistake.
- Delete asks for confirmation in the `<dialog id="confirm-dialog">` modal, because it throws work away. A native `<dialog>` with `showModal()` traps focus and closes on Escape for free, and unlike `window.confirm` it can be styled and does not freeze the page. Cancelling returns focus to the Delete button that opened it.
- Delete is recoverable, so the dialog must not claim otherwise. `DELETE` soft-deletes and the toast that follows offers **Undo**, which calls `PATCH /api/tasks/{id}/restore`. The confirmation stays in front of it as the cheaper stop: undo asks the user to notice a toast in time, the dialog does not.
- While a request is in flight, disable the button that started it and change its label (`Saving…`, `Deleting…`) to prevent double submits.

## 4. Form

**The New task form is a modal, not a column.** It opens from the **New task** button in the page header and lives in `<dialog id="task-dialog">`.

- A form that is only used to add something does not earn a permanent quarter of the screen; the list it feeds does. Behind a dialog it also stops shifting the layout between the List and Calendar views.
- A native `<dialog>` with `showModal()` traps focus and closes on Escape for free, which is the same reason Delete uses one.
- **Its due date is the `:inline` grid, never the popover.** A `<dialog>` is `overflow: auto` in the UA stylesheet, so a floating panel inside one is clipped rather than layered over it. This is the rule under "Date fields" below, and the New task form is the case that most easily forgets it.
- The dialog is capped at `max-h-[calc(100dvh-2rem)]` and scrolls inside, because an inline month grid plus five fields is taller than a short viewport.
- Opening focuses Title. Closing — by Cancel, Escape, the backdrop, or a successful save — clears the form and returns focus to the button that opened it, so a half-typed task is never waiting the next time it opens.

- Every field has a visible `<label>` tied to it with `for`/`id`. Placeholders are not labels.
- Fields: Title (required, marked with `*`), Description (optional `<textarea>`, 3 rows), Priority.
- Priority is a radio group styled as three cards inside a `<fieldset>`, not a `<select>`, so all options are visible at once and each is a single tap. The checked card takes its priority's tint through `has-checked:`. Medium is checked by default.

### Date fields

Every date uses `<x-date-field>`, never a bare `<input type="date">`, because Chrome, Firefox and Safari each draw a different native control and it would be the one field on the form that does not look like the app.

- **The native input stays underneath and stays the source of truth.** It holds the ISO value, it validates, and without JavaScript it is still a working date control. `datepicker.js` only hides the browser's indicator and reveals our button — the swap is all-or-nothing, so there are never two pickers on one field and never zero.
- **The grid is the calendar layout's grid.** `gridStart` and `cellCount` are imported from `calendar.js` rather than rewritten, so "the week starts on Monday" is defined once.
- **Never build the value with `new Date(iso)`.** Use `parseDate` / `toIsoDate`, or "Today" lands on yesterday west of Greenwich.
- **Keyboard:** arrows by day, PageUp/PageDown by month clamped to that month's length, Home/End to the ends of the week, Enter to choose, Escape to dismiss and hand focus back to the button.
- **`:inline` where picking the date is the whole point**, and always inside a `<dialog>`: a `<dialog>` is `overflow: auto` in the UA stylesheet, so a floating panel inside one is clipped rather than layered over it. An inline grid also drops any control the surrounding dialog already offers, so "Clear" never appears twice.
- **The popover hangs off the field's left edge** and flips only to stay inside the viewport. The field can sit in a column narrower than the panel, and growing rightwards keeps it on the page's gutter.
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
- **Grouping has no "None" on the Board**, because a board with nothing to group by is a list. The option is disabled there rather than accepted and quietly ignored.
- **Sorting's "Default" is `Src\TaskSorter`**, the order the exam grades. Due date and Name replace it, and all three keep unfinished work above finished work.
- The panel closes on Escape, on a click outside it, and returns focus to its button.

## 5b. The Board layout

The board's columns **are the grouping**: by status it is To do / In progress / Done, by priority High / Medium / Low, by project one column per project plus "No project". There is no separate table of user-made sections, because the grouping already says what a column is.

- A card carries the title, description, project, due date and priority badge. **It carries no status badge while grouped by status**, because the column it sits in already says that.
- **Grouped by status, dragging is the move**: a card has no Start, Complete or Reopen, because the drag does that work and two ways to do one thing is how they drift apart. Delete stays, since no column means "gone".
- **Under any other grouping the stage buttons come back.** The API can change a task's stage, not its priority or its project, so a drop there would have nothing behind it. A gesture that silently does nothing is worse than no gesture.
- **Dragging is never the only way.** A card is focusable and announces it: Enter opens the task, Space picks it up, the arrows move it between columns, Space drops it, Escape cancels. The card that had focus is found again after the move, the same way the sidebar's "…" is.
- A drop runs one request and the board updates only once the server answers, like every other action. The toast carries **Undo**, which is the reverse stage endpoint.
- Below `md` the columns stack into one running list rather than scrolling sideways.

## 5c. The task dialog

Clicking a task's name opens `<dialog id="task-detail">`: a checkbox and the name on the left with the description and the sub-task checklist under it, and Project, Date and Priority down the right.

- **The name is a real `<button>`** in both layouts, so a task opens from the keyboard and not only under a pointer.
- **Every field saves on its own request. There is no Save button**, so nothing is lost by closing and nobody has to wonder whether an edit took. Fields save on `change`, not `input`: one request per edit rather than one per keystroke.
- The up and down arrows step through **the list the user is looking at**, so what they walk matches what is on the page behind the dialog.
- **A sub-task is a checklist item, not a task.** It never appears in the list, the board or the calendar, and no stat tile counts it. That is what keeps `TaskSorter` and every graded figure exactly as the exam specifies.
- **There is no Reminders, Labels or Location.** The app has no accounts and no mail, so a reminder would be a control that never fires, and a location would mean nothing. This is the same rule that kept Access out of the project dialog and the paperclip out of the comment box.

## 6. States

Every data view needs all four states:

| State | Behaviour |
|---|---|
| Loading | Skeleton rows on first load, so the panel keeps its height instead of collapsing. Never a blank area. |
| Empty | A friendly message per filter: "No tasks yet. Use the form to add your first one." / "No pending tasks." / "No completed tasks yet." |
| Error | A red inline banner with a retry button. The stat cards reset to "–" so stale counts never sit beside an error. |
| Success | A short, auto-dismissing toast (about 3s): "Task added", "Task completed", "Task reopened", "Task deleted". |
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
- The stat cards show live counts from `GET /api/tasks/stats`. They are refreshed after every action, whichever filter is active, and show "–" until the first load finishes.

## 9. Security and performance

- Task text is user input. Render it with `textContent` only, never `innerHTML`.
- Use no extra UI libraries beyond Tailwind. The page should load fast with a single Vite bundle.
- Avoid layout shift: buttons keep their width when the label changes to `Saving…`.
- Motion is decoration. Every animation sits inside `@media (prefers-reduced-motion: no-preference)` in `resources/css/app.css`, so nothing moves for anyone who asked it not to.

## 10. Scope guard

Build only what the exam asks for, plus what the user has since asked for: the landing page, the project tree, the board, the task dialog and its sub-tasks, and dragging a card between the board's columns. No dark mode, auth, labels, reminders, or animations beyond simple transitions, unless the user asks for them.

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
- [ ] New task opens the dialog, Escape and the backdrop close it, and a half-typed task is gone the next time it opens
- [ ] The due date inside the New task dialog is the inline grid and is not clipped
- [ ] Date fields: the grid opens, arrows and PageUp/PageDown move, a chosen day reaches the API unchanged, and with JavaScript off a plain native date input remains
- [ ] Buttons disable while loading; Delete asks for confirmation
- [ ] Undo on the Complete and Delete toasts restores the task, and the stats and sidebar counts follow
- [ ] Add, Complete, Reopen, Delete, and Filter all work without a page reload
- [ ] Sidebar views and categories filter the list, and the title names the current one
- [ ] A project's "…" opens with its four groups; arrows, Home/End and Escape work, and focus returns to it
- [ ] Project actions opens to the side, ArrowRight/ArrowLeft walk in and out, and Escape closes one level at a time
- [ ] Edit opens the dialog filled in, Cancel leaves the project untouched, and a duplicate name shows the error under the field
- [ ] Editing a project and retyping its name keeps the icon it already had
- [ ] The parent select never offers the project itself or one of its children
- [ ] Favorites group appears only when something is in it, and a favourite still shows in the tree
- [ ] Archive removes the project from the tree, the Archived section shows it, and Unarchive brings it back
- [ ] Undo on the delete toast brings a project back with its tasks, comments, activity and children intact
- [ ] Duplicate copies the tasks and lands as "<name> (copy)"
- [ ] Comments add, list oldest first, delete, and refuse an empty comment
- [ ] The Comments / Activity tabs switch without closing the dialog, and the menu's two entries open the right one
- [ ] A reaction adds, shows its count, and the chip reports itself pressed straight away
- [ ] Pressing the same reaction again takes it back, and the chip disappears at zero
- [ ] The add-reaction button stops offering an emoji once it is on the comment
- [ ] Activity is grouped by day with relative times, and the empty state has a sentence, not only a picture
- [ ] Activity lists what happened to that project, and reads correctly for a task that was deleted
- [ ] Drawer opens, closes on Escape/backdrop, and is untabbable while closed
- [ ] Rail and Ctrl+B collapse the sidebar, and the state survives a reload without flashing
- [ ] The sidebar stays put while the page scrolls, reaches the bottom of the viewport, and the content reclaims its space when it collapses
- [ ] Scrolling up and down, including up past the very top, leaves nothing showing above or through the navbar, and the sidebar's top edge meets it with no seam
- [ ] "Skip to content", the heading focus lands on after a delete, and anything else scrolled into view all clear the navbar rather than landing under it
- [ ] Navbar logo, sidebar icons and page heading all start on the same left edge, and the wordmark starts where the sidebar labels do
- [ ] The navbar row is vertically centred in the bar, and its action is a 40px target ending on the right gutter
- [ ] Stat tiles sit on the same column edges as the form and the task panel, and switching List/Calendar moves nothing sideways
- [ ] Calendar: drag a chip to a day, drag to the tray to clear, and reschedule from the dialog
- [ ] Month grid from md, agenda below it, with matching hint text
- [ ] Pending tasks appear above completed ones in the All view
- [ ] Display opens, closes on Escape and on a click outside, and returns focus to its button
- [ ] Each layout shows only itself, and switching moves nothing sideways
- [ ] Grouping by status, priority and project each rebuilds the board's columns, and None is disabled on the board
- [ ] Each Sorting option reorders the list, and unfinished work stays above finished work in all three
- [ ] The Date and Priority filters narrow the list, and the chips name every setting that is on
- [ ] Turning Completed tasks off hides finished work in both the list and the board
- [ ] Dragging a card to another column moves the task, the toast offers Undo, and the stats follow
- [ ] A card can be moved with Space and the arrows alone, and keeps focus after the move
- [ ] Grouped by priority or project, the cards carry Start and Complete again and are not draggable
- [ ] A task's name opens the dialog from a click and from the keyboard, in both the list and the board
- [ ] Each dialog field saves on its own, and the list behind it follows
- [ ] The dialog's up and down arrows step through the list that is on the page
- [ ] A sub-task adds, ticks off, deletes, and never appears as a task row or in a stat tile
- [ ] Loading, empty (per filter), error, and success states all appear correctly
- [ ] Full keyboard navigation with visible focus rings
- [ ] No `innerHTML` with task data
- [ ] No console errors
