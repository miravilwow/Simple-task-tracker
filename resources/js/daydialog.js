/**
 * The day dialog: every task due on one day, because a calendar cell has room for three. It owns
 * the dialog and nothing else; opening a task and adding one are the page's to do.
 */
import { PRIORITY_DOTS } from './calendar.js';
import { CARD_TINTS, createElement, parseDate, startOfToday, toIsoDate } from './dom.js';

const titleFormatter = new Intl.DateTimeFormat('en-US', { weekday: 'long', month: 'long', day: 'numeric' });

const $ = (id) => document.getElementById(id);

export function createDayDialog({ onOpenTask, onAddTask }) {
    const dialog = $('day-dialog');
    const title = $('day-title');
    const count = $('day-count');
    const list = $('day-list');
    const add = $('day-add');
    let iso = null;
    let trigger = null;

    // The rows are rebuilt on every reload, so a task's opener is looked up when focus returns.
    function focusRow(id) {
        (list.querySelector(`[data-task-id="${id}"]`) ?? $('day-close')).focus();
    }

    function row(task) {
        const item = createElement('li', `flex items-center gap-2 rounded-lg border px-3 py-2 ${CARD_TINTS[task.color] ?? 'border-gray-200 bg-white'}`);
        const open = createElement('button', `min-h-8 min-w-0 flex-1 truncate text-left text-sm font-medium focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none ${task.status === 'completed' ? 'text-gray-400 line-through' : 'text-gray-900'}`, task.title);

        open.type = 'button';
        open.addEventListener('click', () => onOpenTask(task.id, { focus: () => focusRow(task.id) }));
        open.dataset.taskId = task.id;
        item.append(createElement('span', `size-2 shrink-0 rounded-full ${PRIORITY_DOTS[task.priority]}`), open);

        return item;
    }

    function draw(tasks) {
        title.textContent = titleFormatter.format(parseDate(iso));
        count.textContent = `${tasks.length} ${tasks.length === 1 ? 'task' : 'tasks'}`;
        list.replaceChildren(...(tasks.length > 0 ? tasks.map(row) : [createElement('li', 'py-6 text-center text-sm text-gray-500', 'Nothing on this day yet.')]));
        // The server refuses a past due date, so a past day offers nothing to add.
        add.hidden = iso < toIsoDate(startOfToday());
    }

    function open(day, tasks) {
        iso = day;
        trigger = document.activeElement;
        draw(tasks);
        dialog.showModal();
    }

    $('day-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });
    dialog.addEventListener('close', () => trigger?.focus?.());
    add.addEventListener('click', () => {
        const day = iso;

        trigger = null;
        dialog.close();
        onAddTask(day);
    });

    return {
        open,
        // Redrawn after every reload so the list never shows a task that moved or changed.
        refresh(tasksFor) {
            if (!dialog.open) {
                return;
            }

            draw(tasksFor(iso));
        },
    };
}
