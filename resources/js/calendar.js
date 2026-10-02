import { CARD_TINTS, createElement, parseDate, startOfToday, toIsoDate } from './dom.js';

export const PRIORITY_DOTS = {
    high: 'bg-red-400',
    medium: 'bg-amber-400',
    low: 'bg-slate-300',
};

const MAX_CHIPS = 3;
const WARN_AT = 8;
const DANGER_AT = 10;

export const dayLoad = (count) => (count >= DANGER_AT ? 'danger' : count >= WARN_AT ? 'warn' : null);

// Full class strings. A busy day is told by its badge text as well, never the colour alone.
const LOAD_CELL = { warn: 'bg-amber-50', danger: 'bg-red-50' };
const LOAD_BADGE = { warn: 'bg-amber-100 text-amber-800', danger: 'bg-red-100 text-red-700' };

// A past day keeps its grey however busy it was; the badge still names the count.
const cellBackground = (isPast, inMonth, load) => {
    if (isPast) {
        return 'bg-gray-50';
    }

    return load ? LOAD_CELL[load] : inMonth ? '' : 'bg-gray-50';
};

const dayFormatter = new Intl.DateTimeFormat('en-US', { month: 'long', day: 'numeric' });

const createLoadBadge = (count, load) =>
    createElement('span', `rounded-full px-1.5 text-xs font-medium tabular-nums ${LOAD_BADGE[load]}`, `${count} tasks`);

const monthFormatter = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' });
const agendaFormatter = new Intl.DateTimeFormat('en-US', { weekday: 'short', month: 'short', day: 'numeric' });

// Ring only: a background here would fight the busy-day tint, and removing it would strip that tint.
const DROP_ACTIVE = ['ring-2', 'ring-red-400', 'ring-inset'];

/** The grid always starts on a Monday, so it usually reaches into the neighbouring months. */
export function gridStart(month) {
    const first = new Date(month.getFullYear(), month.getMonth(), 1);
    const daysSinceMonday = (first.getDay() + 6) % 7;

    return new Date(first.getFullYear(), first.getMonth(), 1 - daysSinceMonday);
}

export function cellCount(month) {
    const first = new Date(month.getFullYear(), month.getMonth(), 1);
    const daysSinceMonday = (first.getDay() + 6) % 7;
    const daysInMonth = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate();

    return Math.ceil((daysSinceMonday + daysInMonth) / 7) * 7;
}

/** The date window the grid covers, so the API only returns tasks that can actually be shown. */
export function monthRange(month) {
    const start = gridStart(month);
    const end = new Date(start);
    end.setDate(start.getDate() + cellCount(month) - 1);

    return { from: toIsoDate(start), to: toIsoDate(end) };
}

export const monthLabel = (month) => monthFormatter.format(month);

// Overdue keeps its red: a due date that has passed is meaning, a colour is decoration.
function chipSurface(task) {
    if (task.is_overdue) {
        return 'border-red-200 bg-red-50';
    }

    return CARD_TINTS[task.color] ?? 'border-gray-200 bg-white';
}

function createChip(task, { onOpen, draggable }) {
    const chip = createElement(
        'button',
        `flex w-full items-center gap-1.5 rounded-md border px-2 py-1 text-left text-xs transition-colors hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none ${chipSurface(task)}`,
    );
    chip.type = 'button';
    chip.dataset.taskId = String(task.id);
    // Day cells are narrow, so the visible label is usually truncated.
    chip.title = task.title;

    chip.append(createElement('span', `size-2 shrink-0 rounded-full ${PRIORITY_DOTS[task.priority]}`));
    chip.append(
        createElement(
            'span',
            `truncate ${task.status === 'completed' ? 'text-gray-400 line-through' : 'text-gray-700'}`,
            task.title,
        ),
    );

    chip.addEventListener('click', () => onOpen(task));

    if (draggable) {
        chip.draggable = true;
        chip.addEventListener('dragstart', (event) => {
            event.dataTransfer.setData('text/plain', String(task.id));
            event.dataTransfer.effectAllowed = 'move';
            chip.classList.add('opacity-50');
        });
        chip.addEventListener('dragend', () => chip.classList.remove('opacity-50'));
    }

    return chip;
}

/**
 * Turns an element into a drop target. `date` is an ISO string, or null for the
 * "no due date" tray, where dropping clears the task's date instead of setting one.
 */
function makeDropTarget(element, date, onReschedule) {
    element.addEventListener('dragover', (event) => {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        element.classList.add(...DROP_ACTIVE);
    });

    element.addEventListener('dragleave', () => element.classList.remove(...DROP_ACTIVE));

    element.addEventListener('drop', (event) => {
        event.preventDefault();
        element.classList.remove(...DROP_ACTIVE);

        const id = Number(event.dataTransfer.getData('text/plain'));

        if (id) {
            onReschedule(id, date);
        }
    });
}

function groupByDate(tasks) {
    const groups = new Map();

    for (const task of tasks) {
        const existing = groups.get(task.due_date);

        if (existing) {
            existing.push(task);
        } else {
            groups.set(task.due_date, [task]);
        }
    }

    return [...groups.entries()].sort(([a], [b]) => a.localeCompare(b));
}

export function renderMonthGrid(container, { tasks, month, onOpen, onReschedule, onOpenDay }) {
    const today = toIsoDate(startOfToday());
    const start = gridStart(month);
    const total = cellCount(month);
    const byDate = new Map(groupByDate(tasks));
    const cells = [];

    for (let index = 0; index < total; index++) {
        const date = new Date(start.getFullYear(), start.getMonth(), start.getDate() + index);
        const iso = toIsoDate(date);
        const inMonth = date.getMonth() === month.getMonth();
        const isToday = iso === today;
        // The server refuses a past due date, so a past day must not look like somewhere a chip
        // can land. The day still shows whatever is already on it.
        const isPast = iso < today;

        const dayTasks = byDate.get(iso) ?? [];
        const load = dayLoad(dayTasks.length);

        const cell = createElement(
            'div',
            `min-h-28 space-y-1 border-r border-b border-gray-200 p-1.5 transition-colors last:border-r-0 ${
                cellBackground(isPast, inMonth, load)
            }`,
        );

        const numberClasses = isToday
            ? 'flex size-6 items-center justify-center rounded-full bg-red-600 text-xs font-semibold text-white'
            : `flex size-6 items-center justify-center text-xs font-medium ${inMonth ? 'text-gray-600' : 'text-gray-400'}`;
        let number;

        if (onOpenDay) {
            number = createElement(
                'button',
                `${numberClasses} rounded-full hover:bg-gray-100 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none`,
                String(date.getDate()),
            );
            number.type = 'button';
            number.setAttribute(
                'aria-label',
                `${dayFormatter.format(date)}, ${dayTasks.length} ${dayTasks.length === 1 ? 'task' : 'tasks'}`,
            );
            number.addEventListener('click', () => onOpenDay(iso));
        } else {
            number = createElement('span', numberClasses, String(date.getDate()));
        }

        const header = createElement('div', 'flex items-center justify-between gap-1');
        header.append(number);

        if (isToday) {
            header.append(createElement('span', 'sr-only', 'Today'));
        }

        if (load) {
            header.append(createLoadBadge(dayTasks.length, load));
        }

        cell.append(header);

        for (const task of dayTasks.slice(0, MAX_CHIPS)) {
            cell.append(createChip(task, { onOpen, draggable: true }));
        }

        if (onOpenDay && dayTasks.length > MAX_CHIPS) {
            const more = createElement(
                'button',
                'w-full rounded-md px-2 py-1 text-left text-xs font-medium text-gray-600 hover:bg-gray-100 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none',
                `+${dayTasks.length - MAX_CHIPS} more`,
            );
            more.type = 'button';
            more.addEventListener('click', () => onOpenDay(iso));
            cell.append(more);
        }

        if (!isPast) {
            makeDropTarget(cell, iso, onReschedule);
        }
        cells.push(cell);
    }

    container.replaceChildren(...cells);
}

export function renderAgenda(container, { tasks, onOpen, onOpenDay, emptyText = 'Nothing scheduled this month.' }) {
    const groups = groupByDate(tasks);

    if (groups.length === 0) {
        container.replaceChildren(
            createElement('p', 'px-4 py-10 text-center text-sm text-gray-500', emptyText),
        );

        return;
    }

    const today = toIsoDate(startOfToday());

    container.replaceChildren(
        ...groups.map(([iso, dayTasks]) => {
            const load = dayLoad(dayTasks.length);
            const section = createElement('div', 'p-4');
            const headingClasses = `text-sm font-medium ${iso === today ? 'text-red-700' : 'text-gray-700'}`;
            const headingText = agendaFormatter.format(parseDate(iso)) + (iso === today ? ' · Today' : '');
            const headingRow = createElement(
                'div',
                `flex items-center justify-between gap-2 rounded-md ${load ? `${LOAD_CELL[load]} px-2 py-1` : ''}`,
            );
            let heading;

            if (onOpenDay) {
                heading = createElement(
                    'button',
                    `${headingClasses} rounded-md text-left hover:underline focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none`,
                    headingText,
                );
                heading.type = 'button';
                heading.addEventListener('click', () => onOpenDay(iso));
            } else {
                heading = createElement('p', headingClasses, headingText);
            }

            headingRow.append(heading);

            if (load) {
                headingRow.append(createLoadBadge(dayTasks.length, load));
            }

            section.append(headingRow);

            const list = createElement('div', 'mt-2 space-y-1.5');
            for (const task of dayTasks) {
                list.append(createChip(task, { onOpen, draggable: false }));
            }
            section.append(list);

            return section;
        }),
    );
}

export function renderUnscheduled(container, { tasks, onOpen, onReschedule }) {
    container.replaceChildren(
        ...tasks.map((task) => {
            const item = createElement('li');
            item.append(createChip(task, { onOpen, draggable: true }));

            return item;
        }),
    );

    if (!container.dataset.dropReady) {
        makeDropTarget(container, null, onReschedule);
        container.dataset.dropReady = 'true';
    }
}
