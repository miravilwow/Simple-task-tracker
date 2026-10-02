# Palette: the logo's colours, and what they cost

The app's mark is a striped red cube (`<x-logo>`, `public/favicon.svg`). This records how the
interface was moved onto its colours and why it was not a straight swap.

> This file deliberately avoids writing Tailwind class names in full. Tailwind scans the repository
> for anything that looks like a class, so a table of retired class names in a doc keeps emitting the
> CSS for colours the app no longer uses.

## What the logo's palette is

| Role in the mark | Hex | Nearest Tailwind |
|---|---|---|
| The lit faces | `#D81E26` | red 600 (`#DC2626`) |
| The face turned away | `#AF1319` | red 800 |
| The stripes and the ground | `#FFFFFF` | white |

The logo's red is already a Tailwind colour, so there was no custom scale to define and no theme
block to write.

## The problem a straight swap would have caused

The app had one accent, indigo, and it meant *"this is the main action, or the thing you picked."*
It appeared 72 times, 53 of them focus rings.

Red was not free. It already carried three jobs:

| Job | Where |
|---|---|
| Destructive | Delete on a task row, Delete in a card's menu, Delete in the confirm dialog |
| High priority | The badge, and the 4px accent bar on a row and a calendar chip |
| Something is wrong | Field errors, the error banner, an error toast's title, invalid borders |

Painting the primary button red would have put **New task and Delete in the same colour on the same
screen**, which breaks the rule the whole interface is built on: colour is meaningful, not decorative.

## The decision

Two routes were put up. **Route B was chosen.**

- **Route A — red becomes the primary colour.** Delete on a row would have had to go quiet, grey until
  hovered, to stop it competing with the primary. Rejected.
- **Route B — red is the mark and the accent; the primary action is near-black.** Chosen. No behaviour
  changes, and red stays rare enough to keep meaning something.

## What the palette is now

| Role | Colour | Where |
|---|---|---|
| Primary action | gray 900, white text | New task, Add task, every dialog's save |
| Accent | red 600 and its tints | The mark, the selected sidebar row, today in the calendar, a drop target, a held card, every focus ring, the Undo on a toast |
| Destructive | red 600 | Delete, always behind a confirmation |
| Success | green 700 | Done, and the Complete button |

The accent and the destructive colour are the same red. That is safe **only because red is never a
primary button here**: a red button in this app is either Undo on a toast or Delete inside a confirm
dialog, and both are things you meant to press.

## Three things that were not a rename

1. **The toast's action button.** It was near-black, with a comment explaining that a toast floats
   above the page and must not repeat the page's primary colour. Making the page's primary near-black
   inverted that argument, so the button is now red. It is also the right colour for the job: Undo has
   eight seconds to be noticed.
2. **The calendar's hint strip.** A plain find-and-replace turned it into pale red with dark red text,
   which is exactly what the load-error banner is. A hint that looks like an error is worse than no
   hint, so it is grey.
3. **The Comment button's comment in the markup** (the button went with comments later). It explained why the button was indigo rather than
   the reference app's red. The mark is red now, so the sentence had to be rewritten rather than
   recoloured.

## What did not change

- **Green** stays Done and Complete. It is the one colour that must stay distinct from red for anyone
  with red-green colour blindness, which is also why every badge carries its word.
- **Amber** stays Medium and In progress. **Blue** stays To do.
- The logo's own hex values stay literal in the SVG. A logo keeps its colours when the interface
  around it changes.

## A note for whoever builds on this

The primary button's class string is written out in full in seven places in `tasks/index.blade.php`.
The project's own rule says a repeated class string becomes a utility in `app.css`; this one has not
been extracted yet, and it is the next thing to do here.
