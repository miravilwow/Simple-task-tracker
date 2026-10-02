/**
 * The day dialog: every task due on one day, because a calendar cell has room for three. It owns
 * the dialog and nothing else; opening a task and adding one are the page's to do.
 */
import { PRIORITY_DOTS } from './calendar.js';
import { dayColorOf, setDayColor } from './daycolor.js';
import { CARD_TINTS, createBadge, createElement, parseDate, startOfToday, STATUS_BADGES, toIsoDate } from './dom.js';

const titleFormatter = new Intl.DateTimeFormat('en-US', { weekday: 'long', month: 'long', day: 'numeric' });

const EMPTY = 'Nothing on this day yet.';

const $ = (id) => document.getElementById(id);

export function createDayDialog({ onOpenTask, onAddTask, onColorChange }) {
    const dialog = $('day-dialog');
    const colorField = $('day-color-field');
    const colorGroup = $('day-color');
    const swatches = () => [...colorGroup.querySelectorAll('[data-color]')];
    const title = $('day-title');
    const count = $('day-count');
    const list = $('day-list');
    const add = $('day-add');
    let iso = null;
    let returnFocus = false;
    let shown = [];

    // The rows are rebuilt on every reload, so a task's opener is looked up when focus returns.
    function focusRow(id) {
        (list.querySelector(`[data-task-id="${id}"]`) ?? $('day-close')).focus();
    }

    // The grid is rebuilt by any edit, so the button that opened the dialog is found again by its day.
    function opener() {
        const found = [...document.querySelectorAll(`#calendar-grid [data-iso="${iso}"], #calendar-agenda [data-iso="${iso}"]`)].find((element) => element.offsetParent);

        return found ?? $('calendar-heading');
    }

    function row(task) {
        const item = createElement('li', `flex items-center gap-2 rounded-lg border px-3 py-2 ${CARD_TINTS[task.color] ?? 'border-gray-200 bg-white'}`);
        const open = createElement('button', `min-h-8 min-w-0 flex-1 truncate text-left text-sm font-medium focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none ${task.status === 'completed' ? 'text-gray-400 line-through' : 'text-gray-900'}`, task.title);

        open.type = 'button';
        open.addEventListener('click', () => onOpenTask(task.id, { focus: () => focusRow(task.id) }, shown.map((item) => item.id)));
        open.dataset.taskId = task.id;
        item.append(createElement('span', `size-2 shrink-0 rounded-full ${PRIORITY_DOTS[task.priority]}`), open, createBadge(STATUS_BADGES[task.status].label, STATUS_BADGES[task.status].classes));

        return item;
    }

    // Roving tabindex, as the task dialog's colour group has: Tab lands on the checked swatch.
    function markSwatch(color) {
        swatches().forEach((item) => {
            const checked = item.dataset.color === color;

            item.setAttribute('aria-checked', String(checked));
            item.tabIndex = checked ? 0 : -1;
        });
    }

    function pickColor(swatch) {
        markSwatch(swatch.dataset.color);
        setDayColor(iso, swatch.dataset.color);
        onColorChange();
    }

    function draw(tasks, emptyText) {
        shown = tasks;
        // A day with nothing on it has no cell worth colouring, and a past day stays grey.
        colorField.hidden = tasks.length === 0 || iso < toIsoDate(startOfToday());
        markSwatch(dayColorOf(iso) ?? '');
        title.textContent = titleFormatter.format(parseDate(iso));
        count.textContent = `${tasks.length} ${tasks.length === 1 ? 'task' : 'tasks'}`;
        list.replaceChildren(...(tasks.length > 0 ? tasks.map(row) : [createElement('li', 'py-6 text-center text-sm text-gray-500', emptyText)]));
        // The server refuses a past due date, so a past day offers nothing to add.
        add.hidden = iso < toIsoDate(startOfToday());
    }

    function open(day, tasks, emptyText = EMPTY) {
        iso = day;
        returnFocus = true;
        draw(tasks, emptyText);
        dialog.showModal();
    }

    colorGroup.addEventListener('click', (event) => {
        const swatch = event.target.closest('[data-color]');

        if (swatch) {
            pickColor(swatch);
        }
    });

    colorGroup.addEventListener('keydown', (event) => {
        const step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[event.key];
        const index = swatches().indexOf(document.activeElement);

        if (!step || index === -1) {
            return;
        }

        event.preventDefault();
        const next = swatches()[(index + step + swatches().length) % swatches().length];

        next.focus();
        pickColor(next);
    });

    $('day-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
    dialog.addEventListener('close', () => returnFocus && opener().focus());
    add.addEventListener('click', () => {
        const day = iso;

        returnFocus = false;
        dialog.close();
        onAddTask(day);
    });

    return {
        open,
        // Redrawn after every reload so the list never shows a task that moved or changed.
        refresh(tasksFor, emptyText = EMPTY) {
            if (!dialog.open) {
                return;
            }

            draw(tasksFor(iso), emptyText);
        },
    };
}
