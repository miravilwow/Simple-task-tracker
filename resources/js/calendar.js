import { createElement, parseDate, startOfToday, toIsoDate } from './dom.js';

const PRIORITY_DOTS = {
    high: 'bg-red-400',
    medium: 'bg-amber-400',
    low: 'bg-slate-300',
};

const monthFormatter = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' });
const agendaFormatter = new Intl.DateTimeFormat('en-US', { weekday: 'short', month: 'short', day: 'numeric' });

const DROP_ACTIVE = ['bg-red-50', 'ring-2', 'ring-red-400', 'ring-inset'];

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

function createChip(task, { onOpen, draggable }) {
    const chip = createElement(
        'button',
        `flex w-full items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2 py-1 text-left text-xs transition-colors hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none ${
            task.is_overdue ? 'border-red-200 bg-red-50' : ''
        }`,
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

export function renderMonthGrid(container, { tasks, month, onOpen, onReschedule }) {
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

        const cell = createElement(
            'div',
            `min-h-28 space-y-1 border-r border-b border-gray-200 p-1.5 transition-colors last:border-r-0 ${
                inMonth && !isPast ? '' : 'bg-gray-50'
            }`,
        );

        const number = createElement(
            'span',
            isToday
                ? 'flex size-6 items-center justify-center rounded-full bg-red-600 text-xs font-semibold text-white'
                : `flex size-6 items-center justify-center text-xs font-medium ${inMonth ? 'text-gray-600' : 'text-gray-400'}`,
            String(date.getDate()),
        );

        const header = createElement('div', 'flex items-center justify-between');
        header.append(number);

        if (isToday) {
            header.append(createElement('span', 'sr-only', 'Today'));
        }

        cell.append(header);

        for (const task of byDate.get(iso) ?? []) {
            cell.append(createChip(task, { onOpen, draggable: true }));
        }

        if (!isPast) {
            makeDropTarget(cell, iso, onReschedule);
        }
        cells.push(cell);
    }

    container.replaceChildren(...cells);
}

export function renderAgenda(container, { tasks, onOpen, emptyText = 'Nothing scheduled this month.' }) {
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
            const section = createElement('div', 'p-4');
            const heading = createElement(
                'p',
                `text-sm font-medium ${iso === today ? 'text-red-700' : 'text-gray-700'}`,
                agendaFormatter.format(parseDate(iso)) + (iso === today ? ' · Today' : ''),
            );

            section.append(heading);

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
