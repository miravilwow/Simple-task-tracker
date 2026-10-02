# Palette plan: moving the app onto the logo's colours

The logo is in (`<x-logo>`, `public/favicon.svg`). This is the plan for the interface around it.

## 1. What the logo's palette is

| Role in the mark | Hex | Nearest Tailwind |
|---|---|---|
| The lit faces | `#D81E26` | `red-600` (`#DC2626`) |
| The face turned away | `#AF1319` | `red-800` (`#991B1B`) |
| The stripes and the ground | `#FFFFFF` | `white` |

The logo's red is **already a Tailwind colour**. That matters: there is no custom scale to define, no
`@theme` block to write, and every tint and shade the interface needs (`red-50` through `red-800`)
comes with it. The swap is a rename, not a redesign.

## 2. The one real problem

The app has one accent, indigo, and it means **"this is the main action, or the thing you picked"**.
It appears 72 times, 53 of which are focus rings.

Red is not a free colour here. It already has three jobs:

| Job | Where | Classes |
|---|---|---|
| Destructive | Delete on a task row, Delete in the confirm dialog, the sidebar's purge | `text-red-600`, `bg-red-600` |
| High priority | The badge, and the 4px accent bar on a row and a calendar chip | `bg-red-100 text-red-700`, `bg-red-400` |
| Something is wrong | Field errors, the error banner, an error toast's title, `aria-invalid` borders | `text-red-600`, `border-red-500` |

Make red the primary colour and **"New task" and "Delete" become the same colour on the same screen**.
That breaks the rule this project is built on: *colour is meaningful, not decorative.*

So the palette cannot simply be swapped. The three jobs above have to be settled first.

## 3. Two routes

### Route A — red is the primary colour (recommended)

Indigo is retired. Red becomes the accent, and the three jobs are resolved like this:

| Job | What changes | Why it works |
|---|---|---|
| Destructive | Delete on a task row stops being red text. It becomes grey, turning red on hover and focus — the pattern the sidebar's purge, the sub-task remove and the comment delete already use. | A destructive action that is quiet until you reach for it is **safer**, not weaker. It also stops Delete competing with Complete on every row. |
| | Delete inside the confirm dialog stays solid red. | There it *is* the primary action of that dialog, so sharing the primary colour is correct. |
| High priority | No change. | A pale badge (`red-100`/`red-700`) and a solid button (`red-600`) are different objects at different weights, and the badge always carries its word. |
| Something is wrong | No change. | Error text sits under a field or in a banner, never on a button. |

This is how red-branded products handle it: the brand red is loud and rare, destructive is quiet
until hovered, and the confirm dialog is where red means danger.

**Cost:** one behavioural change (row Delete) plus a mechanical rename.

### Route B — red is the mark, graphite is the primary colour

The logo is red; the interface's primary action becomes near-black (`gray-900`), with red kept for
the mark, the selected sidebar row and the focus ring only.

**Cost:** rename only, no behavioural change. **Trade-off:** less red on screen than you probably want,
and the toast's action button is already near-black, so two near-black buttons could meet.

---

**Recommendation: Route A.** It is what "use the logo's palette" actually means, and the one thing it
costs — a quieter Delete — is an improvement on its own.

## 4. The mechanical work (Route A)

Every one of these is a find-and-replace across `resources/`:

| From | To | Count |
|---|---|---|
| `ring-indigo-500` | `ring-red-500` | 53 |
| `bg-indigo-600` / `hover:bg-indigo-700` | `bg-red-600` / `hover:bg-red-700` | 17 |
| `border-indigo-500` / `-600` / `-400` | `border-red-500` / `-600` / `-400` | 14 |
| `bg-indigo-50`, `hover:bg-indigo-50` | `bg-red-50`, `hover:bg-red-50` | 7 |
| `text-indigo-600` / `-700` / `-800` | `text-red-600` / `-700` / `-800` | 7 |
| `fill-indigo-200` / `-300` (empty-state art) | `fill-red-200` / `-300` | 3 |
| `aria-checked:bg-indigo-600`, `aria-pressed:*` | the red equivalents | 7 |

Files touched: `app.css`, `tasks/index.blade.php`, `layout.blade.php`, `date-field.blade.php`,
`sidebar/trigger.blade.php`, and `app.js`, `board.js`, `calendar.js`, `datepicker.js`, `dialogs.js`,
`menu.js`.

Plus the one hand edit: `deleteButton()` in `app.js` loses `text-red-600` and gains the grey-until-hover
classes.

## 5. What does not change

- **Green** stays Done and Complete. It is the only colour that means success, and it is the one that
  has to stay distinct from red for anyone with red-green colour blindness — which is exactly why
  every badge also carries its word.
- **Amber** stays Medium and In progress. **Blue** stays To do.
- The seven project colours in `App\Enums\CategoryColor` stay. They are the user's choice for their own
  projects, not the app's palette.
- The logo's own hex values stay literal. A logo keeps its colours when the interface changes.

## 6. Order, and how it is checked

1. Rename the classes. Run `npm run build` — Tailwind only emits classes it can see, so a typo shows
   up as a missing style, not a wrong one.
2. Change `deleteButton()`.
3. Run `php artisan test`. `PriorityBadgeTest` and the project-colour test pin the literal class
   strings, so anything renamed by accident fails there.
4. Walk the UI/UX checklist's focus-ring and state rows at 360px and 1280px.
5. Update the two rules in `UI_UX_RULES.md` that name indigo by name: "Keep one accent color (indigo)"
   and "The Comment button is indigo, not the red of the app this is modelled on" — the second becomes
   the opposite argument and has to be rewritten, not deleted.

Step 5 is not optional. A doc that still says indigo after the app is red is a contradiction a reviewer
will find.
