// Full class strings are listed here (not built dynamically) so Tailwind can find them when scanning this file.
const PRIORITY_BADGES = {
    high: { label: 'High', classes: 'bg-red-100 text-red-700', accent: 'bg-red-400' },
    medium: { label: 'Medium', classes: 'bg-amber-100 text-amber-800', accent: 'bg-amber-400' },
    low: { label: 'Low', classes: 'bg-slate-100 text-slate-700', accent: 'bg-slate-300' },
};

const STATUS_BADGES = {
    pending: { label: 'Pending', classes: 'bg-blue-100 text-blue-700' },
    completed: { label: 'Completed', classes: 'bg-green-100 text-green-700' },
};

const EMPTY_MESSAGES = {
    '': 'No tasks yet. Use the form to add your first one.',
    pending: 'No pending tasks. Everything is done.',
    completed: 'No completed tasks yet.',
};

const RATE_LIMIT_MESSAGE = 'Too many requests. Please wait a moment and try again.';

// Heroicons v2 (MIT) outline paths, matching resources/views/components/icon.blade.php.
const ICONS = {
    check: 'm4.5 12.75 6 6 9-13.5',
    undo: 'M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3',
    trash: 'm14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0',
    'check-circle': 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    warning:
        'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
};

const BUTTON_BASE =
    'inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60';

const dateFormatter = new Intl.DateTimeFormat('en-US', { dateStyle: 'medium', timeStyle: 'short' });

const form = document.getElementById('task-form');
const titleInput = document.getElementById('title');
const descriptionInput = document.getElementById('description');
const submitButton = document.getElementById('submit-button');
const statElements = {
    total: document.getElementById('stat-total'),
    pending: document.getElementById('stat-pending'),
    completed: document.getElementById('stat-completed'),
    high: document.getElementById('stat-high'),
};
const progressTrack = document.getElementById('progress-track');
const progressBar = document.getElementById('progress-bar');
const progressLabel = document.getElementById('progress-label');
const columnHeaders = document.getElementById('column-headers');
const tasksHeading = document.getElementById('tasks-heading');
const taskList = document.getElementById('task-list');
const skeleton = document.getElementById('skeleton');
const listMessage = document.getElementById('list-message');
const listMessageText = document.getElementById('list-message-text');
const loadError = document.getElementById('load-error');
const loadErrorMessage = document.getElementById('load-error-message');
const retryButton = document.getElementById('retry-button');
const toastRegion = document.getElementById('toast-region');
const filterButtons = document.querySelectorAll('[data-filter]');
const confirmDialog = document.getElementById('confirm-dialog');
const confirmMessage = document.getElementById('confirm-message');
const confirmAccept = document.getElementById('confirm-accept');
const confirmCancel = document.getElementById('confirm-cancel');

let currentFilter = '';
let latestRequestId = 0;

class ApiError extends Error {
    constructor(message, { errors = {}, status = 0 } = {}) {
        super(message);
        this.errors = errors;
        this.status = status;
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
        throw new ApiError(data.message ?? 'Something went wrong.', {
            errors: data.errors,
            status: response.status,
        });
    }

    return data;
}

// Laravel's own 429 body says "Too Many Attempts.", which means little to a user.
function errorMessage(error) {
    if (!(error instanceof ApiError)) {
        return 'Something went wrong.';
    }

    return error.status === 429 ? RATE_LIMIT_MESSAGE : error.message;
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

function createIcon(name, className = 'size-4') {
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

function createBadge({ label, classes }) {
    return createElement('span', `inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ${classes}`, label);
}

// The submit button wraps its text in a span so the icon beside it survives a label swap.
const labelOf = (button) => button.querySelector('[data-label]') ?? button;

function setBusy(button, label) {
    const target = labelOf(button);

    target.dataset.previous = target.textContent;
    target.textContent = label;
    button.disabled = true;
}

function clearBusy(button) {
    const target = labelOf(button);

    target.textContent = target.dataset.previous;
    button.disabled = false;
}

function showToast(message, type = 'success') {
    const styles =
        type === 'error' ? 'bg-red-600 text-white' : 'bg-gray-900 text-white';
    const toast = createElement(
        'div',
        `toast-enter flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm shadow-lg ${styles}`,
    );

    toast.append(createIcon(type === 'error' ? 'warning' : 'check-circle', 'size-4 shrink-0'));
    toast.append(createElement('span', '', message));
    toastRegion.append(toast);
    setTimeout(() => toast.remove(), 3000);
}

// A real dialog can be styled, traps focus, and closes on Escape, which window.confirm cannot.
function confirmDelete(title) {
    return new Promise((resolve) => {
        confirmMessage.textContent = `Delete "${title}"? This can't be undone.`;

        const controller = new AbortController();
        const finish = (result) => {
            controller.abort();

            if (confirmDialog.open) {
                confirmDialog.close();
            }

            resolve(result);
        };

        confirmAccept.addEventListener('click', () => finish(true), { signal: controller.signal });
        confirmCancel.addEventListener('click', () => finish(false), { signal: controller.signal });
        confirmDialog.addEventListener('close', () => finish(false), { signal: controller.signal });

        confirmDialog.showModal();
    });
}

// Mobile: a stacked card (content, badges, full-width actions). From md: one row of the task-columns grid.
function renderTask(task) {
    const isCompleted = task.status === 'completed';
    const priority = PRIORITY_BADGES[task.priority];

    const item = createElement(
        'li',
        'task-enter relative flex flex-wrap items-center gap-x-2 gap-y-3 py-4 pr-4 pl-5 transition-colors hover:bg-gray-50 sm:pr-6 sm:pl-7 md:grid md:task-columns md:gap-4',
    );
    item.append(createElement('span', `absolute inset-y-0 left-0 w-1 ${priority.accent}`));

    const content = createElement('div', 'w-full min-w-0 md:w-auto');
    content.append(
        createElement(
            'h3',
            `font-medium wrap-break-word ${isCompleted ? 'text-gray-400 line-through' : ''}`,
            task.title,
        ),
    );

    if (task.description) {
        content.append(
            createElement('p', 'mt-1 text-sm whitespace-pre-line wrap-break-word text-gray-600', task.description),
        );
    }

    const createdAt = createElement('time', '', dateFormatter.format(new Date(task.created_at)));
    createdAt.dateTime = task.created_at;
    const meta = createElement('p', 'mt-1 text-xs whitespace-nowrap text-gray-500', 'Created ');
    meta.append(createdAt);
    content.append(meta);

    const priorityCell = createElement('div');
    priorityCell.append(createBadge(priority));

    const statusCell = createElement('div');
    statusCell.append(createBadge(STATUS_BADGES[task.status]));

    const actions = createElement('div', 'flex w-full gap-2 md:w-auto md:justify-end');

    const primary = isCompleted
        ? {
              label: 'Reopen',
              icon: 'undo',
              classes: 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 focus-visible:ring-indigo-500',
              run: reopenTask,
          }
        : {
              label: 'Complete',
              icon: 'check',
              classes:
                  'border border-green-200 bg-green-50 text-green-700 hover:bg-green-100 focus-visible:ring-green-500',
              run: completeTask,
          };

    const primaryButton = createElement('button', `${BUTTON_BASE} flex-1 md:flex-none ${primary.classes}`);
    primaryButton.type = 'button';
    primaryButton.append(createIcon(primary.icon), createElement('span', '', primary.label));
    primaryButton.addEventListener('click', () => primary.run(task, primaryButton));

    const deleteButton = createElement(
        'button',
        `${BUTTON_BASE} text-red-600 hover:bg-red-50 focus-visible:ring-red-500`,
    );
    deleteButton.type = 'button';
    deleteButton.append(createIcon('trash'), createElement('span', '', 'Delete'));
    deleteButton.addEventListener('click', () => deleteTask(task, deleteButton));

    actions.append(primaryButton, deleteButton);
    item.append(content, priorityCell, statusCell, actions);

    return item;
}

function renderStats(stats) {
    statElements.total.textContent = stats.total;
    statElements.pending.textContent = stats.pending;
    statElements.completed.textContent = stats.completed;
    statElements.high.textContent = stats.high_priority_pending;

    const percent = stats.total === 0 ? 0 : Math.round((stats.completed / stats.total) * 100);

    progressBar.style.width = `${percent}%`;
    progressTrack.setAttribute('aria-valuenow', String(percent));
    progressLabel.textContent =
        stats.total === 0 ? 'No tasks yet' : `${stats.completed} of ${stats.total} done (${percent}%)`;
}

function clearStats() {
    for (const element of Object.values(statElements)) {
        element.textContent = '–';
    }

    progressBar.style.width = '0%';
    progressTrack.setAttribute('aria-valuenow', '0');
    progressLabel.textContent = 'Unavailable';
}

function renderList(tasks) {
    const hasTasks = tasks.length > 0;

    taskList.replaceChildren(...tasks.map(renderTask));
    listMessageText.textContent = EMPTY_MESSAGES[currentFilter];
    listMessage.classList.toggle('hidden', hasTasks);
    listMessage.classList.toggle('flex', !hasTasks);
    columnHeaders.classList.toggle('md:grid', hasTasks);
}

async function loadTasks() {
    // Ignore responses from older requests if the filter changed while they were in flight.
    const requestId = ++latestRequestId;

    try {
        const [tasks, stats] = await Promise.all([
            api(currentFilter ? `/tasks?status=${currentFilter}` : '/tasks'),
            api('/tasks/stats'),
        ]);

        if (requestId !== latestRequestId) {
            return;
        }

        skeleton.classList.add('hidden');
        loadError.classList.add('hidden');
        renderStats(stats.data);
        renderList(tasks.data);
    } catch (error) {
        if (requestId !== latestRequestId) {
            return;
        }

        // Stale counts next to an error banner would be misleading.
        skeleton.classList.add('hidden');
        clearStats();
        taskList.replaceChildren();
        loadErrorMessage.textContent =
            error instanceof ApiError && error.status === 429
                ? RATE_LIMIT_MESSAGE
                : "Couldn't load tasks. Try again.";
        loadError.classList.remove('hidden');
        listMessage.classList.add('hidden');
        listMessage.classList.remove('flex');
        columnHeaders.classList.remove('md:grid');
    }
}

const errorElementFor = (field) => document.getElementById(`${field}-error`);

function clearFieldErrors() {
    for (const field of ['title', 'description', 'priority']) {
        document.getElementById(field)?.removeAttribute('aria-invalid');

        const errorElement = errorElementFor(field);
        errorElement.textContent = '';
        errorElement.classList.add('hidden');
    }
}

function showFieldErrors(errors) {
    for (const [field, messages] of Object.entries(errors)) {
        const errorElement = errorElementFor(field);

        if (!errorElement) {
            continue;
        }

        // Priority is a radio group, so it has an error slot but no single input to mark.
        document.getElementById(field)?.setAttribute('aria-invalid', 'true');
        errorElement.textContent = messages[0];
        errorElement.classList.remove('hidden');
    }

    form.querySelector('[aria-invalid="true"]')?.focus();
}

async function createTask(event) {
    event.preventDefault();

    // Pressing Enter can re-submit the form even while the disabled button is mid-request.
    if (submitButton.disabled) {
        return;
    }

    clearFieldErrors();

    const payload = {
        title: titleInput.value.trim(),
        description: descriptionInput.value.trim() || null,
        priority: form.querySelector('input[name="priority"]:checked')?.value,
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

// Re-rendering the list destroys the button that was clicked, so focus has to be parked somewhere.
async function runRowAction(button, { busyLabel, request, successMessage, restoreFocus }) {
    const hadFocus = restoreFocus ?? document.activeElement === button;

    setBusy(button, busyLabel);

    try {
        await request();
        showToast(successMessage);
        await loadTasks();

        if (hadFocus) {
            tasksHeading.focus();
        }
    } catch (error) {
        showToast(errorMessage(error), 'error');
    } finally {
        clearBusy(button);
    }
}

function completeTask(task, button) {
    return runRowAction(button, {
        busyLabel: 'Completing…',
        request: () => api(`/tasks/${task.id}/complete`, { method: 'PATCH' }),
        successMessage: 'Task completed',
    });
}

function reopenTask(task, button) {
    return runRowAction(button, {
        busyLabel: 'Reopening…',
        request: () => api(`/tasks/${task.id}/reopen`, { method: 'PATCH' }),
        successMessage: 'Task reopened',
    });
}

async function deleteTask(task, button) {
    // The dialog steals focus, so record where it came from before opening.
    const hadFocus = document.activeElement === button;

    if (!(await confirmDelete(task.title))) {
        button.focus();
        return;
    }

    await runRowAction(button, {
        busyLabel: 'Deleting…',
        request: () => api(`/tasks/${task.id}`, { method: 'DELETE' }),
        successMessage: 'Task deleted',
        restoreFocus: hadFocus,
    });
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
