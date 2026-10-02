// Heroicons v2 (MIT) outline paths, matching resources/views/components/icon.blade.php.
const ICONS = {
    check: 'm4.5 12.75 6 6 9-13.5',
    undo: 'M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3',
    trash: 'm14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0',
    calendar:
        'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5',
    close: 'M6 18 18 6M6 6l12 12',
    inbox: 'M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H6.911a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661Z',
    'chevron-left': 'M15.75 19.5 8.25 12l7.5-7.5',
    clock: 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    ellipsis:
        'M6.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM18.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z',
    pencil:
        'm16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10',
    'chevron-right': 'm8.25 4.5 7.5 7.5-7.5 7.5',
    star: 'M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z',
    duplicate:
        'M16.5 8.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v8.25A2.25 2.25 0 0 0 6 16.5h2.25m0-8.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-7.5A2.25 2.25 0 0 1 8.25 18v-9.75Z',
    'arrow-right': 'M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3',
    'face-smile':
        'M15.182 15.182a4.5 4.5 0 0 1-6.364 0M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z',
    chat: 'M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155',
};

// "Sep 30". Shared, because a due date on a task row and a day in the activity feed are
// the same format, and two copies would be two things to keep in step.
export const shortDate = new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric' });

/**
 * Show or hide an element that lays its own contents out with flex.
 *
 * `hidden` and `flex` both set `display`, so hiding one of these means adding one class
 * and removing the other. Written out at each call site that was two lines that had to
 * agree, and they only had to agree because they were two lines.
 */
export function setVisible(element, shown) {
    element.classList.toggle('hidden', !shown);
    element.classList.toggle('flex', shown);
}

export function createElement(tag, className, text) {
    const element = document.createElement(tag);

    if (className) {
        element.className = className;
    }

    if (text !== undefined) {
        element.textContent = text;
    }

    return element;
}

export function createIcon(name, className = 'size-4') {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');

    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '1.5');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('class', className);

    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', ICONS[name]);
    svg.append(path);

    return svg;
}

export function createBadge(label, classes) {
    return createElement('span', `inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ${classes}`, label);
}

// Buttons wrap their text in a span so an icon beside it survives a label swap.
const labelOf = (button) => button.querySelector('[data-label]') ?? button;

// The icon a button started with, so the spinner and the tick can be swapped in and out without
// the button having to remember what it was. A WeakMap rather than markup stashed in an attribute:
// the node goes back exactly as it was, and nothing is rebuilt from a string.
const restingIcons = new WeakMap();

/** The mark a button is wearing: its own icon, or the spinner standing in for it while it works. */
const markOf = (button) => button.querySelector('svg, [data-spinner]');

/**
 * Puts a new mark on a button, remembering the one it was wearing at rest.
 */
function setMark(button, node) {
    const current = markOf(button);

    if (! current) {
        return;
    }

    if (! restingIcons.has(button)) {
        restingIcons.set(button, current);
    }

    current.replaceWith(node);
}

/** The size and colour classes the button's own icon wears, so a swapped-in one matches it. */
const restingClass = (button) => restingIcons.get(button)?.getAttribute('class') ?? 'size-4';

/**
 * A ring with a gap in it, rather than a drawn icon: a circle turning is what a spinner looks
 * like everywhere, and a path would have to be redrawn to say the same thing.
 */
function spinner() {
    const ring = createElement('span', 'btn-spinner');

    ring.dataset.spinner = '';
    ring.setAttribute('aria-hidden', 'true');

    return ring;
}

/**
 * A button goes through three states: what it does, that it is doing it, and that it is done.
 *
 * `setBusy` is the middle one. The label changes to the -ing form and the icon becomes a spinner,
 * so the button says the same thing twice over, for anyone reading it and anyone glancing at it.
 */
export function setBusy(button, label) {
    const target = labelOf(button);

    // Pin the width before the label grows. "Complete" becomes "Completing…" and then
    // "Completed", three different widths, and a row of buttons would shuffle under the cursor
    // at each step. min-width rather than width, so a button that stretches to its row still can.
    button.style.minWidth = `${button.offsetWidth}px`;

    target.dataset.previous = target.textContent;
    target.textContent = label;
    button.disabled = true;
    setMark(button, spinner());
}

/**
 * The last state: a tick and the past tense, in green, while the list behind it catches up.
 *
 * The button stays disabled through it. It is reporting what happened, not offering to do it
 * again, and several of these buttons are about to be removed by the re-render anyway.
 */
export function setDone(button, label) {
    labelOf(button).textContent = label;
    button.classList.add('btn-done');
    setMark(button, createIcon('check', restingClass(button)));
}

export function clearBusy(button) {
    const target = labelOf(button);
    const resting = restingIcons.get(button);

    target.textContent = target.dataset.previous;
    button.disabled = false;
    button.classList.remove('btn-done');
    button.style.minWidth = '';

    if (resting) {
        markOf(button)?.replaceWith(resting);
        restingIcons.delete(button);
    }
}



/**
 * `new Date('2026-10-05')` is parsed as UTC midnight, which lands on the previous day
 * for anyone west of Greenwich. These two keep every date in local time.
 */
export function parseDate(iso) {
    const [year, month, day] = iso.split('-').map(Number);

    return new Date(year, month - 1, day);
}

export function toIsoDate(date) {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

export const startOfToday = () => {
    const now = new Date();

    return new Date(now.getFullYear(), now.getMonth(), now.getDate());
};
