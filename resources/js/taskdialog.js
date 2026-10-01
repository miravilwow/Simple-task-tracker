/**
 * The task dialog: one task's name, description, project, date, priority and sub-tasks.
 *
 * Every field saves on its own request, so there is no Save button and nothing is lost by
 * closing. A native <dialog> traps focus and closes on Escape for free.
 */
import { api } from './api.js';
import { createElement, createIcon, showToast } from './dom.js';

const $ = (id) => document.getElementById(id);

const elements = {
    dialog: $('task-detail'),
    crumb: $('detail-crumb'),
    tick: $('detail-tick'),
    title: $('detail-title'),
    description: $('detail-description'),
    project: $('detail-project'),
    date: $('detail-date'),
    priority: $('detail-priority'),
    prev: $('detail-prev'),
    next: $('detail-next'),
    subtaskList: $('subtask-list'),
    subtaskCount: $('subtask-count'),
    subtaskForm: $('subtask-form'),
    subtaskTitle: $('subtask-title'),
};

let task = null;
let opener = null;
let order = [];
let handlers = {};

const errorMessage = (error) => error?.message ?? 'Something went wrong.';

function tickButton(checked, label) {
    const button = createElement('button', 'tick');

    button.type = 'button';
    button.setAttribute('role', 'checkbox');
    button.setAttribute('aria-checked', String(checked));
    button.setAttribute('aria-label', label);
    button.append(createIcon('check', 'size-3'));

    return button;
}

function renderSubtasks() {
    const subtasks = task.subtasks ?? [];
    const done = subtasks.filter((item) => item.is_done).length;

    elements.subtaskCount.textContent = subtasks.length ? `${done}/${subtasks.length}` : '';
    elements.subtaskList.replaceChildren(
        ...subtasks.map((subtask) => {
            const row = createElement('li', 'flex items-center gap-2.5 border-b border-gray-100 py-2');
            const tick = tickButton(subtask.is_done, subtask.is_done ? 'Mark as not done' : 'Mark as done');
            const label = createElement(
                'span',
                `min-w-0 flex-1 wrap-break-word ${subtask.is_done ? 'text-gray-400 line-through' : ''}`,
                subtask.title,
            );
            const remove = createElement('button', 'icon-button size-8 hover:bg-red-50 hover:text-red-600');

            remove.type = 'button';
            remove.setAttribute('aria-label', `Remove ${subtask.title}`);
            remove.append(createIcon('trash', 'size-4'));

            tick.addEventListener('click', () => saveSubtask(subtask, { is_done: !subtask.is_done }));
            remove.addEventListener('click', () => removeSubtask(subtask));
            row.append(tick, label, remove);

            return row;
        }),
    );
}

function fill() {
    const isDone = task.status === 'completed';

    elements.crumb.replaceChildren(
        createIcon('inbox', 'size-4'),
        createElement('span', '', task.category?.name ?? 'No project'),
    );
    elements.title.value = task.title;
    elements.description.value = task.description ?? '';
    elements.project.value = task.category ? String(task.category.id) : '';
    elements.date.value = task.due_date ?? '';
    elements.priority.value = task.priority;
    elements.tick.setAttribute('aria-checked', String(isDone));
    elements.tick.setAttribute('aria-label', isDone ? 'Mark as not done' : 'Mark as done');

    const index = order.indexOf(task.id);
    elements.prev.disabled = index <= 0;
    elements.next.disabled = index === -1 || index === order.length - 1;

    renderSubtasks();
}

/** Runs a request, refreshes the dialog from the answer, and reloads the page behind it. */
async function save(request) {
    try {
        const response = await request();

        if (response?.data) {
            task = response.data;
            fill();
        }

        await handlers.onChange();
    } catch (error) {
        showToast(errorMessage(error), 'error');
    }
}

const patchTask = (payload) => save(() => api(`/tasks/${task.id}`, { method: 'PATCH', body: payload }));

function saveSubtask(subtask, payload) {
    return save(async () => {
        await api(`/tasks/${task.id}/subtasks/${subtask.id}`, { method: 'PATCH', body: payload });

        return api(`/tasks/${task.id}`);
    });
}

function removeSubtask(subtask) {
    return save(async () => {
        await api(`/tasks/${task.id}/subtasks/${subtask.id}`, { method: 'DELETE' });

        return api(`/tasks/${task.id}`);
    });
}

async function step(offset) {
    const next = order[order.indexOf(task.id) + offset];

    if (next) {
        await load(next);
    }
}

async function load(id) {
    const response = await api(`/tasks/${id}`);

    task = response.data;
    fill();
}

/**
 * @param {{categories: array, order: number[], onChange: Function, onDelete: Function}} context
 */
export async function openTaskDetail(id, trigger, context) {
    handlers = context;
    order = context.order;
    opener = trigger ?? null;

    elements.project.replaceChildren(
        Object.assign(document.createElement('option'), { value: '', textContent: 'No project' }),
        ...context.categories.map((category) =>
            Object.assign(document.createElement('option'), { value: String(category.id), textContent: category.name }),
        ),
    );

    try {
        await load(id);
        elements.dialog.showModal();
    } catch (error) {
        showToast(errorMessage(error), 'error');
    }
}

export function wireTaskDetail() {
    elements.dialog.addEventListener('close', () => {
        elements.subtaskForm.hidden = true;
        elements.subtaskTitle.value = '';
        opener?.focus();
        opener = null;
        task = null;
    });

    $('detail-close').addEventListener('click', () => elements.dialog.close());
    elements.prev.addEventListener('click', () => step(-1));
    elements.next.addEventListener('click', () => step(1));

    $('detail-delete').addEventListener('click', async () => {
        const removed = task;

        elements.dialog.close();
        await handlers.onDelete(removed);
    });

    elements.tick.addEventListener('click', () =>
        save(() => api(`/tasks/${task.id}/${task.status === 'completed' ? 'reopen' : 'complete'}`, { method: 'PATCH' })),
    );

    // change, not input: one request per edit rather than one per keystroke.
    elements.title.addEventListener('change', () => patchTask({ title: elements.title.value.trim() }));
    elements.description.addEventListener('change', () => patchTask({ description: elements.description.value.trim() || null }));
    elements.priority.addEventListener('change', () => patchTask({ priority: elements.priority.value }));
    elements.project.addEventListener('change', () =>
        patchTask({ category_id: elements.project.value ? Number(elements.project.value) : null }),
    );
    elements.date.addEventListener('change', () =>
        save(() => api(`/tasks/${task.id}/schedule`, { method: 'PATCH', body: { due_date: elements.date.value || null } })),
    );

    $('subtask-add').addEventListener('click', () => {
        elements.subtaskForm.hidden = false;
        elements.subtaskTitle.focus();
    });

    $('subtask-cancel').addEventListener('click', () => {
        elements.subtaskForm.hidden = true;
        elements.subtaskTitle.value = '';
    });

    elements.subtaskForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const title = elements.subtaskTitle.value.trim();

        if (!title) {
            return;
        }

        await save(async () => {
            await api(`/tasks/${task.id}/subtasks`, { method: 'POST', body: { title } });

            return api(`/tasks/${task.id}`);
        });

        elements.subtaskTitle.value = '';
        elements.subtaskTitle.focus();
    });
}
