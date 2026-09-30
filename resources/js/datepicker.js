/**
 * Date field: a month-grid popover over a real `<input type="date">`.
 *
 * Why not the browser's own picker? Chrome, Firefox and Safari each draw a different control, so
 * the one field on the form that the user has to think about is also the only one that does not
 * look like the rest of the app. This gives it the same surface, spacing and focus ring as
 * everything else, and the same month grid the calendar layout already uses.
 *
 * The native input stays underneath and stays the source of truth: it is what holds the ISO
 * value every caller reads, what the form validates, and what still works as a date control if
 * this script never runs. Enhancement only hides the browser's indicator and adds the popover,
 * so typing a date into the field keeps working exactly as before.
 */
import { cellCount, gridStart, monthLabel } from './calendar.js';
import { createElement, createIcon, parseDate, startOfToday, toIsoDate } from './dom.js';

const WEEKDAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
const dayFormatter = new Intl.DateTimeFormat('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric',
});

const DAY_BASE =
    'flex size-9 items-center justify-center rounded-md text-sm transition-colors focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none';
const NAV_BUTTON =
    'flex size-8 items-center justify-center rounded-md text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none';
const FOOTER_BUTTON =
    'min-h-8 rounded-md px-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none';

const sameDay = (a, b) => toIsoDate(a) === toIsoDate(b);
const addDays = (date, days) => new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);
const addMonths = (date, months) => new Date(date.getFullYear(), date.getMonth() + months, 1);

/**
 * Enhances one `[data-date-field]`. Everything it owns is scoped to that field.
 *
 * `inline` fields drop the popover and sit the grid in the flow. The reschedule dialog uses it,
 * for two reasons: choosing a date is the dialog's only job, so hiding the grid behind a button
 * costs a click for nothing; and a `<dialog>` is `overflow: auto` in the UA stylesheet, so an
 * absolutely positioned panel inside one is clipped rather than floating over it.
 */
function enhance(field) {
    const input = field.querySelector('input[type="date"]');
    const inline = field.dataset.dateField === 'inline';
    const trigger = field.querySelector('[data-date-trigger]');

    if (!input || (!inline && !trigger)) {
        return;
    }

    const popover = createElement(
        'div',
        inline
            ? 'mt-2 w-full rounded-xl border border-gray-200 bg-gray-50 p-3'
            : 'absolute z-20 hidden w-72 rounded-xl border border-gray-200 bg-white p-3 shadow-lg',
    );

    if (!inline) {
        popover.setAttribute('role', 'dialog');
        popover.setAttribute('aria-label', 'Choose a date');
    }

    (inline ? field : trigger.parentElement).append(popover);

    const heading = createElement('p', 'text-sm font-medium', '');
    const previous = createElement('button', NAV_BUTTON);
    const next = createElement('button', NAV_BUTTON);
    previous.type = 'button';
    next.type = 'button';
    previous.append(createIcon('chevron-left'));
    next.append(createIcon('chevron-right'));
    previous.append(createElement('span', 'sr-only', 'Previous month'));
    next.append(createElement('span', 'sr-only', 'Next month'));

    const header = createElement('div', 'flex items-center justify-between gap-2 px-1');
    header.append(previous, heading, next);

    const weekdays = createElement('div', 'mt-2 grid grid-cols-7 text-center text-xs text-gray-400');
    weekdays.setAttribute('aria-hidden', 'true');
    WEEKDAYS.forEach((day) => weekdays.append(createElement('span', 'py-1', day)));

    const grid = createElement('div', 'grid grid-cols-7 justify-items-center gap-y-0.5');

    const clear = createElement('button', FOOTER_BUTTON, 'Clear');
    const today = createElement('button', FOOTER_BUTTON, 'Today');
    clear.type = 'button';
    today.type = 'button';

    // Inline, the surrounding dialog already owns clearing the date, and two Clears read as a bug.
    const footer = createElement(
        'div',
        `mt-2 flex items-center border-t border-gray-200 pt-2 ${inline ? 'justify-end' : 'justify-between'}`,
    );
    footer.append(...(inline ? [today] : [clear, today]));

    popover.append(header, weekdays, grid, footer);

    // The month on show, and the day the arrow keys are sitting on. They are separate: moving the
    // cursor past the end of a month should turn the page without selecting anything.
    let visibleMonth = startOfToday();
    let cursor = startOfToday();

    const selected = () => (input.value ? parseDate(input.value) : null);

    function render() {
        heading.textContent = monthLabel(visibleMonth);
        grid.replaceChildren();

        const start = gridStart(visibleMonth);
        const chosen = selected();
        const now = startOfToday();

        for (let index = 0; index < cellCount(visibleMonth); index += 1) {
            const date = addDays(start, index);
            const outside = date.getMonth() !== visibleMonth.getMonth();
            const isChosen = chosen !== null && sameDay(date, chosen);
            const isToday = sameDay(date, now);

            let tone = 'text-gray-700 hover:bg-gray-100';

            if (outside) {
                tone = 'text-gray-300 hover:bg-gray-50';
            }

            if (isToday && !isChosen) {
                tone = 'font-semibold text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50';
            }

            if (isChosen) {
                tone = 'bg-indigo-600 font-semibold text-white hover:bg-indigo-700';
            }

            const day = createElement('button', `${DAY_BASE} ${tone}`, String(date.getDate()));
            day.type = 'button';
            day.dataset.date = toIsoDate(date);
            day.setAttribute('aria-label', dayFormatter.format(date));
            day.tabIndex = sameDay(date, cursor) ? 0 : -1;

            if (isChosen) {
                day.setAttribute('aria-pressed', 'true');
            }

            if (isToday) {
                day.setAttribute('aria-current', 'date');
            }

            grid.append(day);
        }
    }

    /** Moves the keyboard cursor, turning the page when it leaves the month on show. */
    function moveCursor(date) {
        cursor = date;

        if (date.getMonth() !== visibleMonth.getMonth() || date.getFullYear() !== visibleMonth.getFullYear()) {
            visibleMonth = new Date(date.getFullYear(), date.getMonth(), 1);
        }

        render();
        grid.querySelector('[tabindex="0"]')?.focus();
    }

    const isOpen = () => inline || !popover.classList.contains('hidden');

    /**
     * Hangs the panel off the field's left edge and below it, and flips only when that would run
     * off the viewport. Left by default, because the field can sit in a column narrower than the
     * panel, and growing rightwards keeps the panel on the gutter the page is aligned to.
     */
    function place() {
        popover.classList.remove('right-0', 'bottom-full', 'mb-2');
        popover.classList.add('left-0', 'mt-2');

        const anchor = trigger.parentElement.getBoundingClientRect();

        if (anchor.left + popover.offsetWidth > window.innerWidth - 8) {
            popover.classList.replace('left-0', 'right-0');
        }

        if (window.innerHeight - anchor.bottom < popover.offsetHeight + 16) {
            popover.classList.remove('mt-2');
            popover.classList.add('bottom-full', 'mb-2');
        }
    }

    function open() {
        cursor = selected() ?? startOfToday();
        visibleMonth = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
        render();

        popover.classList.remove('hidden');
        trigger.setAttribute('aria-expanded', 'true');
        place();

        grid.querySelector('[tabindex="0"]')?.focus();
    }

    function close({ restoreFocus = true } = {}) {
        if (inline || !isOpen()) {
            return;
        }

        popover.classList.add('hidden');
        trigger.setAttribute('aria-expanded', 'false');

        if (restoreFocus) {
            trigger.focus();
        }
    }

    function commit(iso) {
        input.value = iso;
        // A programmatic value change fires nothing, and callers listen for a real edit.
        input.dispatchEvent(new Event('change', { bubbles: true }));

        if (inline) {
            cursor = iso ? parseDate(iso) : startOfToday();
            render();
        }

        close();
    }

    trigger?.addEventListener('click', () => (isOpen() ? close() : open()));
    previous.addEventListener('click', () => {
        visibleMonth = addMonths(visibleMonth, -1);
        render();
    });
    next.addEventListener('click', () => {
        visibleMonth = addMonths(visibleMonth, 1);
        render();
    });
    clear.addEventListener('click', () => commit(''));
    today.addEventListener('click', () => commit(toIsoDate(startOfToday())));

    grid.addEventListener('click', (event) => {
        const day = event.target.closest('[data-date]');

        if (day) {
            commit(day.dataset.date);
        }
    });

    popover.addEventListener('keydown', (event) => {
        const steps = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };

        if (event.key in steps) {
            event.preventDefault();
            moveCursor(addDays(cursor, steps[event.key]));

            return;
        }

        if (event.key === 'PageUp' || event.key === 'PageDown') {
            event.preventDefault();
            const target = addMonths(cursor, event.key === 'PageUp' ? -1 : 1);
            // Clamp, so leaving the 31st for a shorter month does not skip into the next one.
            const lastDay = new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate();
            moveCursor(new Date(target.getFullYear(), target.getMonth(), Math.min(cursor.getDate(), lastDay)));

            return;
        }

        if (event.key === 'Home' || event.key === 'End') {
            event.preventDefault();
            const daysSinceMonday = (cursor.getDay() + 6) % 7;
            moveCursor(addDays(cursor, event.key === 'Home' ? -daysSinceMonday : 6 - daysSinceMonday));

            return;
        }

        // An inline grid has nothing to dismiss, and Escape there belongs to the dialog.
        if (event.key === 'Escape' && !inline) {
            // Escape is also the browser's own close request for a <dialog>. Cancelling the key
            // event keeps it from closing the popover and the dialog it sits in at once.
            event.preventDefault();
            event.stopPropagation();
            close();
        }
    });

    if (inline) {
        render();
    } else {
        // Clicking anywhere else dismisses it, but focus belongs to whatever was clicked.
        document.addEventListener('pointerdown', (event) => {
            if (isOpen() && !popover.contains(event.target) && !trigger.contains(event.target)) {
                close({ restoreFocus: false });
            }
        });
    }

    // Typing in the field is still a valid way to set the date, so keep the grid in step.
    input.addEventListener('change', () => {
        if (inline) {
            cursor = input.value ? parseDate(input.value) : startOfToday();
            visibleMonth = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
            render();
        } else if (isOpen()) {
            open();
        }
    });

    field.dataset.enhanced = 'true';
}

export function enhanceDateFields(root = document) {
    root.querySelectorAll('[data-date-field]').forEach(enhance);
}
