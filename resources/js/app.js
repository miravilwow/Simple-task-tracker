// Full class strings are listed here (not built dynamically) so Tailwind can find them when scanning this file.
const PRIORITY_BADGES = {
    high: { label: 'High', classes: 'bg-red-100 text-red-700' },
    medium: { label: 'Medium', classes: 'bg-amber-100 text-amber-800' },
    low: { label: 'Low', classes: 'bg-slate-100 text-slate-700' },
};

const STATUS_BADGES = {
    pending: { label: 'Pending', classes: 'bg-blue-100 text-blue-700' },
    completed: { label: 'Completed', classes: 'bg-green-100 text-green-700' },
};

const EMPTY_MESSAGES = {
    '': 'No tasks yet. Add one above.',
    pending: 'No pending tasks.',
    completed: 'No completed tasks yet.',
};

const BUTTON_BASE =
    'inline-flex min-h-10 min-w-24 flex-1 items-center justify-center rounded-md px-3 py-2 text-sm font-medium focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60 md:flex-none';

const dateFormatter = new Intl.DateTimeFormat('en-US', { dateStyle: 'medium', timeStyle: 'short' });

const form = document.getElementById('task-form');
const titleInput = document.getElementById('title');
const descriptionInput = document.getElementById('description');
const priorityInput = document.getElementById('priority');
const submitButton = document.getElementById('submit-button');
const statElements = {
    total: document.getElementById('stat-total'),
    pending: document.getElementById('stat-pending'),
    completed: document.getElementById('stat-completed'),
    high: document.getElementById('stat-high'),
};
const columnHeaders = document.getElementById('column-headers');
const taskList = document.getElementById('task-list');
const listMessage = document.getElementById('list-message');
const loadError = document.getElementById('load-error');
const retryButton = document.getElementById('retry-button');
const toastRegion = document.getElementById('toast-region');
const filterButtons = document.querySelectorAll('[data-filter]');

let currentFilter = '';
let latestRequestId = 0;

class ApiError extends Error {
    constructor(message, errors = {}) {
        super(message);
        this.errors = errors;
    }
}

async function api(path, { method = 'GET', body } = {}) {
    const headers = { Accept: 'application/json' };

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(`/api${path}`, {
        method,
        headers,
        body: body === undefined ? undefined : JSON.stringify(body),
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new ApiError(data.message ?? 'Something went wrong.', data.errors);
    }

    return data;
}

function errorMessage(error) {
    return error instanceof ApiError ? error.message : 'Something went wrong.';
}

function createElement(tag, className, text) {
    const element = document.createElement(tag);

    if (className) {
        element.className = className;
    }

    if (text !== undefined) {
        element.textContent = text;
    }

    return element;
}

function createBadge({ label, classes }) {
    return createElement('span', `rounded-full px-2.5 py-0.5 text-xs font-medium ${classes}`, label);
}

function setBusy(button, label) {
    button.dataset.label = button.textContent;
    button.textContent = label;
    button.disabled = true;
}

function clearBusy(button) {
    button.textContent = button.dataset.label;
    button.disabled = false;
}

function showToast(message, type = 'success') {
    const colors = type === 'error' ? 'bg-red-600 text-white' : 'bg-gray-900 text-white';
    const toast = createElement('div', `rounded-md px-4 py-2 text-sm shadow-lg ${colors}`, message);

    toastRegion.append(toast);
    setTimeout(() => toast.remove(), 3000);
}

// Mobile: a stacked card (content, badges, full-width actions). From md: one row of the task-columns grid.
function renderTask(task) {
    const isCompleted = task.status === 'completed';
    const item = createElement(
        'li',
        'flex flex-wrap items-center gap-x-2 gap-y-3 px-4 py-4 sm:px-6 md:grid md:task-columns md:gap-4',
    );
    const content = createElement('div', 'w-full min-w-0 md:w-auto');

    content.append(
        createElement('h3', `font-medium break-words ${isCompleted ? 'text-gray-400 line-through' : ''}`, task.title),
    );

    if (task.description) {
        content.append(
            createElement('p', 'mt-2 text-sm whitespace-pre-line break-words text-gray-600', task.description),
        );
    }

    const createdAt = createElement('time', '', dateFormatter.format(new Date(task.created_at)));
    createdAt.dateTime = task.created_at;
    const meta = createElement('p', 'mt-1 text-xs text-gray-500', 'Created ');
    meta.append(createdAt);
    content.append(meta);

    const priorityCell = createElement('div');
    priorityCell.append(createBadge(PRIORITY_BADGES[task.priority]));

    const statusCell = createElement('div');
    statusCell.append(createBadge(STATUS_BADGES[task.status]));

    const actions = createElement('div', 'flex w-full gap-2 md:w-auto md:justify-end');

    if (!isCompleted) {
        const completeButton = createElement(
            'button',
            `${BUTTON_BASE} border border-green-200 bg-green-50 text-green-700 hover:bg-green-100 focus-visible:ring-green-500`,
            'Complete',
        );
        completeButton.type = 'button';
        completeButton.addEventListener('click', () => completeTask(task, completeButton));
        actions.append(completeButton);
    }

    const deleteButton = createElement(
        'button',
        `${BUTTON_BASE} text-red-600 hover:bg-red-50 focus-visible:ring-red-500`,
        'Delete',
    );
    deleteButton.type = 'button';
    deleteButton.addEventListener('click', () => deleteTask(task, deleteButton));
    actions.append(deleteButton);

    item.append(content, priorityCell, statusCell, actions);

    return item;
}

function renderStats(tasks) {
    const pending = tasks.filter((task) => task.status === 'pending');

    statElements.total.textContent = tasks.length;
    statElements.pending.textContent = pending.length;
    statElements.completed.textContent = tasks.length - pending.length;
    statElements.high.textContent = pending.filter((task) => task.priority === 'high').length;
}

function renderList(tasks) {
    taskList.replaceChildren(...tasks.map(renderTask));
    listMessage.textContent = EMPTY_MESSAGES[currentFilter];
    listMessage.classList.toggle('hidden', tasks.length > 0);
    columnHeaders.classList.toggle('md:grid', tasks.length > 0);
}

async function loadTasks() {
    // Ignore responses from older requests if the filter changed while they were in flight.
    const requestId = ++latestRequestId;

    try {
        const [allTasks, filteredTasks] = await Promise.all([
            api('/tasks'),
            currentFilter ? api(`/tasks?status=${currentFilter}`) : null,
        ]);

        if (requestId !== latestRequestId) {
            return;
        }

        loadError.classList.add('hidden');
        renderStats(allTasks.data);
        renderList((filteredTasks ?? allTasks).data);
    } catch {
        if (requestId !== latestRequestId) {
            return;
        }

        loadError.classList.remove('hidden');
        listMessage.classList.add('hidden');
        columnHeaders.classList.remove('md:grid');
    }
}

function clearFieldErrors() {
    for (const input of [titleInput, descriptionInput, priorityInput]) {
        input.removeAttribute('aria-invalid');
        const errorElement = document.getElementById(`${input.id}-error`);
        errorElement.textContent = '';
        errorElement.classList.add('hidden');
    }
}

function showFieldErrors(errors) {
    for (const [field, messages] of Object.entries(errors)) {
        const input = document.getElementById(field);
        const errorElement = document.getElementById(`${field}-error`);

        if (!input || !errorElement) {
            continue;
        }

        input.setAttribute('aria-invalid', 'true');
        errorElement.textContent = messages[0];
        errorElement.classList.remove('hidden');
    }

    form.querySelector('[aria-invalid="true"]')?.focus();
}

async function createTask(event) {
    event.preventDefault();
    clearFieldErrors();

    const payload = {
        title: titleInput.value.trim(),
        description: descriptionInput.value.trim() || null,
        priority: priorityInput.value,
    };

    if (!payload.title) {
        showFieldErrors({ title: ['The title field is required.'] });
        return;
    }

    setBusy(submitButton, 'Saving…');

    try {
        await api('/tasks', { method: 'POST', body: payload });
        form.reset();
        titleInput.focus();
        showToast('Task added');
        await loadTasks();
    } catch (error) {
        if (error instanceof ApiError && Object.keys(error.errors).length > 0) {
            showFieldErrors(error.errors);
        } else {
            showToast(errorMessage(error), 'error');
        }
    } finally {
        clearBusy(submitButton);
    }
}

async function completeTask(task, button) {
    setBusy(button, 'Completing…');

    try {
        await api(`/tasks/${task.id}/complete`, { method: 'PATCH' });
        showToast('Task completed');
        await loadTasks();
    } catch (error) {
        showToast(errorMessage(error), 'error');
    } finally {
        clearBusy(button);
    }
}

async function deleteTask(task, button) {
    if (!confirm(`Delete "${task.title}"?`)) {
        return;
    }

    setBusy(button, 'Deleting…');

    try {
        await api(`/tasks/${task.id}`, { method: 'DELETE' });
        showToast('Task deleted');
        await loadTasks();
    } catch (error) {
        showToast(errorMessage(error), 'error');
    } finally {
        clearBusy(button);
    }
}

function setFilter(button) {
    currentFilter = button.dataset.filter;
    filterButtons.forEach((filterButton) => {
        filterButton.setAttribute('aria-pressed', String(filterButton === button));
    });
    loadTasks();
}

form.addEventListener('submit', createTask);
retryButton.addEventListener('click', loadTasks);
filterButtons.forEach((button) => button.addEventListener('click', () => setFilter(button)));

loadTasks();
