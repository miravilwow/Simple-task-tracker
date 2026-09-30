import { ApiError, api, errorMessage, RATE_LIMIT_MESSAGE } from './api.js';
import { monthLabel, monthRange, renderAgenda, renderMonthGrid, renderUnscheduled } from './calendar.js';
import { enhanceDateFields } from './datepicker.js';
import { confirmAction, openScheduleDialog } from './dialogs.js';
import './shell.js';
import { closeDrawer } from './sidebar.js';
import {
    clearBusy,
    createBadge,
    createElement,
    createIcon,
    parseDate,
    setBusy,
    showToast,
    startOfToday,
    toIsoDate,
} from './dom.js';

// Full class strings are listed here (not built dynamically) so Tailwind can find them.
const PRIORITY_BADGES = {
    high: { label: 'High', classes: 'bg-red-100 text-red-700', accent: 'bg-red-400' },
    medium: { label: 'Medium', classes: 'bg-amber-100 text-amber-800', accent: 'bg-amber-400' },
    low: { label: 'Low', classes: 'bg-slate-100 text-slate-700', accent: 'bg-slate-300' },
};

const STATUS_BADGES = {
    pending: { label: 'Pending', classes: 'bg-blue-100 text-blue-700' },
    completed: { label: 'Completed', classes: 'bg-green-100 text-green-700' },
};

/**
 * One category icon, tinted with its colour. The Iconify class is built from the name the API
 * sent, which Tailwind cannot see, so every one of them is safelisted in app.css through
 * `@source inline(...)`. Keep that list and App\Enums\CategoryIcon in step.
 */
function categoryIcon(category, size) {
    const icon = createElement(
        'span',
        `icon-[fluent--${category.icon}-24-regular] ${size} shrink-0 text-gray-500`,
    );
    icon.setAttribute('aria-hidden', 'true');

    return icon;
}

const VIEWS = {
    all: { title: 'All tasks', subtitle: "Create tasks, set priorities, and track what's done.", params: {} },
    today: { title: 'Today', subtitle: 'Still to do today.', params: { due: 'today' } },
    upcoming: { title: 'Upcoming', subtitle: 'Still to do today and beyond.', params: { due: 'upcoming' } },
    overdue: { title: 'Overdue', subtitle: 'Past their due date and still pending.', params: { due: 'overdue' } },
    completed: { title: 'Completed', subtitle: "Everything you've finished.", params: {} },
};

const BUTTON_BASE =
    'inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60';

const dueFormatter = new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric' });
const createdFormatter = new Intl.DateTimeFormat('en-US', { dateStyle: 'medium' });

const $ = (id) => document.getElementById(id);

const elements = {
    viewTitle: $('view-title'),
    viewSubtitle: $('view-subtitle'),
    categoryList: $('category-list'),
    taskDialog: $('task-dialog'),
    newTaskTrigger: $('new-task-trigger'),
    taskCancel: $('task-cancel'),
    categoryEmpty: $('category-empty'),
    categoryForm: $('category-form'),
    categoryToggle: $('category-toggle'),
    categoryName: $('category-name'),
    categorySubmit: $('category-submit'),
    iconGrid: $('icon-grid'),
    iconEmpty: $('icon-empty'),
    categorySelect: $('category_id'),
    listView: $('list-view'),
    calendarView: $('calendar-view'),
    form: $('task-form'),
    title: $('title'),
    description: $('description'),
    dueDate: $('due_date'),
    submit: $('submit-button'),
    taskList: $('task-list'),
    tasksHeading: $('tasks-heading'),
    columnHeaders: $('column-headers'),
    skeleton: $('skeleton'),
    listMessage: $('list-message'),
    listMessageText: $('list-message-text'),
    loadError: $('load-error'),
    loadErrorMessage: $('load-error-message'),
    retry: $('retry-button'),
    progressBar: $('progress-bar'),
    progressTrack: $('progress-track'),
    progressLabel: $('progress-label'),
    calendarGrid: $('calendar-grid'),
    calendarAgenda: $('calendar-agenda'),
    calendarMonth: $('calendar-month'),
    unscheduledList: $('unscheduled-list'),
    unscheduledEmpty: $('unscheduled-empty'),
    unscheduledCount: $('unscheduled-count'),
};

const stats = {
    total: $('stat-total'),
    pending: $('stat-pending'),
    completed: $('stat-completed'),
    overdue: $('stat-overdue'),
};

const viewButtons = document.querySelectorAll('[data-view]');
const modeButtons = document.querySelectorAll('[data-mode]');
const statusButtons = document.querySelectorAll('[data-filter]');

const today = startOfToday();

const state = {
    view: 'all',
    mode: 'list',
    status: '',
    categoryId: null,
    month: new Date(today.getFullYear(), today.getMonth(), 1),
    categories: [],
};

let latestRequestId = 0;

// ---------------------------------------------------------------- formatting

function dueLabel(task) {
    if (!task.due_date) {
        return 'No due date';
    }

    const formatted = dueFormatter.format(parseDate(task.due_date));

    if (task.is_overdue) {
        return `Overdue · ${formatted}`;
    }

    return task.due_date === toIsoDate(today) ? 'Due today' : `Due ${formatted}`;
}

function emptyMessage() {
    if (state.categoryId) {
        return 'No tasks in this project.';
    }

    if (state.status === 'completed') {
        return 'No completed tasks yet.';
    }

    if (state.status === 'pending') {
        return 'No pending tasks. Everything is done.';
    }

    return {
        all: 'No tasks yet. Use the form to add your first one.',
        today: 'Nothing left for today. Nice work.',
        upcoming: 'Nothing scheduled ahead.',
        overdue: 'Nothing overdue. Nice work.',
        completed: 'No completed tasks yet.',
    }[state.view];
}

// ---------------------------------------------------------------- list view

function createDueButton(task) {
    const button = createElement(
        'button',
        `-mx-1 inline-flex min-h-8 items-center gap-1 rounded-md px-2 text-xs transition-colors hover:bg-gray-100 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none ${
            task.is_overdue ? 'text-red-600' : 'text-gray-500'
        }`,
    );
    button.type = 'button';
    button.append(createIcon('calendar', 'size-3.5 shrink-0'), createElement('span', '', dueLabel(task)));
    button.addEventListener('click', () => rescheduleFromDialog(task));

    return button;
}

// Mobile: a stacked card. From md: one row of the task-columns grid.
function renderTask(task) {
    const priority = PRIORITY_BADGES[task.priority];
    const isCompleted = task.status === 'completed';

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

    const meta = createElement('div', 'mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1');

    if (task.category) {
        const chip = createElement('span', 'inline-flex items-center gap-1.5 text-xs text-gray-600');
        chip.append(
            categoryIcon(task.category, 'size-3.5'),
            createElement('span', '', task.category.name),
        );
        meta.append(chip);
    }

    meta.append(createDueButton(task));
    meta.append(
        createElement('span', 'text-xs text-gray-400', `Added ${createdFormatter.format(new Date(task.created_at))}`),
    );
    content.append(meta);

    const priorityCell = createElement('div');
    priorityCell.append(createBadge(priority.label, priority.classes));

    const statusCell = createElement('div');
    statusCell.append(createBadge(STATUS_BADGES[task.status].label, STATUS_BADGES[task.status].classes));

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

function renderList(tasks) {
    const hasTasks = tasks.length > 0;

    elements.taskList.replaceChildren(...tasks.map(renderTask));
    elements.listMessageText.textContent = emptyMessage();
    elements.listMessage.classList.toggle('hidden', hasTasks);
    elements.listMessage.classList.toggle('flex', !hasTasks);
    elements.columnHeaders.classList.toggle('md:grid', hasTasks);
}

// ---------------------------------------------------------------- stats & sidebar

function renderStats(data) {
    stats.total.textContent = data.total;
    stats.pending.textContent = data.pending;
    stats.completed.textContent = data.completed;
    stats.overdue.textContent = data.overdue;

    for (const element of document.querySelectorAll('[data-view-count]')) {
        const value = data[element.dataset.viewCount];
        element.textContent = value > 0 ? value : '';
    }

    const percent = data.total === 0 ? 0 : Math.round((data.completed / data.total) * 100);

    elements.progressBar.style.width = `${percent}%`;
    elements.progressTrack.setAttribute('aria-valuenow', String(percent));
    elements.progressLabel.textContent =
        data.total === 0 ? 'No tasks yet' : `${data.completed} of ${data.total} done (${percent}%)`;
}

function clearStats() {
    for (const element of Object.values(stats)) {
        element.textContent = '–';
    }

    elements.progressBar.style.width = '0%';
    elements.progressTrack.setAttribute('aria-valuenow', '0');
    elements.progressLabel.textContent = 'Unavailable';
}

function renderCategories(categories) {
    state.categories = categories;

    elements.categoryList.replaceChildren(
        ...categories.map((category) => {
            const item = createElement('li');
            const row = createElement('div', 'flex items-center gap-1');

            // Same sidebar utilities the Blade menu buttons use, so both stay in step.
            const select = createElement('button', 'sidebar-menu-button flex-1');
            select.type = 'button';
            select.title = category.name;
            select.setAttribute('aria-pressed', String(state.categoryId === category.id));
            select.append(
                categoryIcon(category, 'size-5'),
                createElement('span', 'sidebar-collapsible flex-1 truncate text-left', category.name),
                createElement('span', 'sidebar-collapsible sidebar-menu-badge', String(category.task_count)),
            );
            select.addEventListener('click', () => selectCategory(category));

            const remove = createElement(
                'button',
                'sidebar-collapsible sidebar-menu-action hover:bg-red-50 hover:text-red-600 focus-visible:ring-red-500',
            );
            remove.type = 'button';
            remove.append(createIcon('close', 'size-4'));
            remove.append(createElement('span', 'sr-only', `Delete ${category.name}`));
            remove.addEventListener('click', () => deleteCategory(category));

            row.append(select, remove);
            item.append(row);

            return item;
        }),
    );

    elements.categoryEmpty.classList.toggle('hidden', categories.length > 0);

    // Keep the form's picker in step with the sidebar, preserving any choice already made.
    const selected = elements.categorySelect.value;
    const placeholder = createElement('option', '', 'No category');
    // An <option> with no value attribute submits its own text, which would post "No category".
    placeholder.value = '';

    elements.categorySelect.replaceChildren(
        placeholder,
        ...categories.map((category) => {
            const option = createElement('option', '', category.name);
            option.value = String(category.id);

            return option;
        }),
    );
    elements.categorySelect.value = selected;
}

// ---------------------------------------------------------------- loading

function currentParams() {
    const params = { ...VIEWS[state.view].params };

    if (state.status) {
        params.status = state.status;
    }

    if (state.categoryId) {
        params.category_id = state.categoryId;
    }

    return params;
}

function showLoadError(error) {
    clearStats();
    elements.skeleton.classList.add('hidden');
    elements.taskList.replaceChildren();
    elements.listMessage.classList.add('hidden');
    elements.listMessage.classList.remove('flex');
    elements.columnHeaders.classList.remove('md:grid');
    elements.loadErrorMessage.textContent =
        error instanceof ApiError && error.status === 429 ? RATE_LIMIT_MESSAGE : "Couldn't load tasks. Try again.";
    elements.loadError.classList.remove('hidden');
}

async function load() {
    // Ignore responses from older requests if the view changed while they were in flight.
    const requestId = ++latestRequestId;
    const range = monthRange(state.month);

    try {
        const requests = [api('/tasks/stats'), api('/categories')];

        requests.push(
            state.mode === 'calendar'
                ? api('/tasks', { params: { ...currentParams(), ...range } })
                : api('/tasks', { params: currentParams() }),
        );

        if (state.mode === 'calendar') {
            requests.push(api('/tasks', { params: { ...currentParams(), due: 'none' } }));
        }

        const [statsResponse, categoriesResponse, tasksResponse, unscheduledResponse] = await Promise.all(requests);

        if (requestId !== latestRequestId) {
            return;
        }

        elements.skeleton.classList.add('hidden');
        elements.loadError.classList.add('hidden');
        renderStats(statsResponse.data);
        renderCategories(categoriesResponse.data);

        if (state.mode === 'calendar') {
            elements.calendarMonth.textContent = monthLabel(state.month);
            renderMonthGrid(elements.calendarGrid, {
                tasks: tasksResponse.data,
                month: state.month,
                onOpen: rescheduleFromDialog,
                onReschedule: scheduleTask,
            });
            renderAgenda(elements.calendarAgenda, { tasks: tasksResponse.data, onOpen: rescheduleFromDialog });
            renderUnscheduled(elements.unscheduledList, {
                tasks: unscheduledResponse.data,
                onOpen: rescheduleFromDialog,
                onReschedule: scheduleTask,
            });
            elements.unscheduledCount.textContent = String(unscheduledResponse.data.length);
            elements.unscheduledEmpty.classList.toggle('hidden', unscheduledResponse.data.length > 0);
        } else {
            renderList(tasksResponse.data);
        }
    } catch (error) {
        if (requestId === latestRequestId) {
            showLoadError(error);
        }
    }
}

// ---------------------------------------------------------------- task actions

// Re-rendering destroys the button that was clicked, so focus has to be parked somewhere.
async function runAction(button, { busyLabel, request, successMessage, restoreFocus, undo, undoMessage }) {
    const hadFocus = restoreFocus ?? document.activeElement === button;

    if (button) {
        setBusy(button, busyLabel);
    }

    try {
        await request();
        showToast(successMessage, 'success', undo ? { label: 'Undo', onClick: () => runUndo(undo, undoMessage) } : null);
        await load();

        if (hadFocus) {
            elements.tasksHeading.focus();
        }
    } catch (error) {
        showToast(errorMessage(error), 'error');
    } finally {
        if (button) {
            clearBusy(button);
        }
    }
}

// The button that started the action is gone by the time Undo is clicked, so there is nothing
// left to put in a busy state. The toast reports how the reversal went instead.
async function runUndo(request, message) {
    try {
        await request();
        showToast(message);
        await load();
    } catch (error) {
        showToast(errorMessage(error), 'error');
    }
}

const completeTask = (task, button) =>
    runAction(button, {
        busyLabel: 'Completing…',
        request: () => api(`/tasks/${task.id}/complete`, { method: 'PATCH' }),
        successMessage: 'Task completed',
        undo: () => api(`/tasks/${task.id}/reopen`, { method: 'PATCH' }),
        undoMessage: 'Task reopened',
    });

const reopenTask = (task, button) =>
    runAction(button, {
        busyLabel: 'Reopening…',
        request: () => api(`/tasks/${task.id}/reopen`, { method: 'PATCH' }),
        successMessage: 'Task reopened',
    });

async function deleteTask(task, button) {
    // The dialog steals focus, so record where it came from before opening.
    const hadFocus = document.activeElement === button;
    const confirmed = await confirmAction({
        title: 'Delete task',
        message: `Delete "${task.title}"? You can undo this from the toast straight after.`,
    });

    if (!confirmed) {
        button.focus();

        return;
    }

    await runAction(button, {
        busyLabel: 'Deleting…',
        request: () => api(`/tasks/${task.id}`, { method: 'DELETE' }),
        successMessage: 'Task deleted',
        restoreFocus: hadFocus,
        undo: () => api(`/tasks/${task.id}/restore`, { method: 'PATCH' }),
        undoMessage: 'Task restored',
    });
}

/** Shared by dragging onto a day, dropping into the tray, and the reschedule dialog. */
async function scheduleTask(taskId, date) {
    try {
        await api(`/tasks/${taskId}/schedule`, { method: 'PATCH', body: { due_date: date } });
        showToast(date ? 'Task rescheduled' : 'Due date cleared');
        await load();
    } catch (error) {
        showToast(errorMessage(error), 'error');
    }
}

async function rescheduleFromDialog(task) {
    const result = await openScheduleDialog(task);

    if (result) {
        await scheduleTask(task.id, result.date);
    }
}

// ---------------------------------------------------------------- forms

const errorElementFor = (field) => document.getElementById(`${field}-error`);

function clearFieldErrors(fields) {
    for (const field of fields) {
        document.getElementById(field)?.removeAttribute('aria-invalid');

        const errorElement = errorElementFor(field);
        errorElement.textContent = '';
        errorElement.classList.add('hidden');
    }
}

function showFieldErrors(errors, scope) {
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

    scope.querySelector('[aria-invalid="true"]')?.focus();
}

const TASK_FIELDS = ['title', 'description', 'priority', 'category_id', 'due_date'];

// The form sits in a dialog so the list keeps the full width. <dialog> traps focus and closes
// on Escape by itself; what it does not do is clear a half-filled form, so closing does.
function openTaskDialog() {
    clearFieldErrors(TASK_FIELDS);
    elements.taskDialog.showModal();
    elements.title.focus();
}

function closeTaskDialog() {
    // The close handler below does the clearing, because Escape and the backdrop get there too.
    elements.taskDialog.close();
}

async function createTask(event) {
    event.preventDefault();

    // Pressing Enter can re-submit the form even while the disabled button is mid-request.
    if (elements.submit.disabled) {
        return;
    }

    clearFieldErrors(TASK_FIELDS);

    const payload = {
        title: elements.title.value.trim(),
        description: elements.description.value.trim() || null,
        priority: elements.form.querySelector('input[name="priority"]:checked')?.value,
        category_id: elements.categorySelect.value || null,
        due_date: elements.dueDate.value || null,
    };

    if (!payload.title) {
        showFieldErrors({ title: ['The title field is required.'] }, elements.form);

        return;
    }

    setBusy(elements.submit, 'Saving…');

    try {
        await api('/tasks', { method: 'POST', body: payload });
        closeTaskDialog();
        showToast('Task added');
        await load();
    } catch (error) {
        if (error instanceof ApiError && Object.keys(error.errors).length > 0) {
            showFieldErrors(error.errors, elements.form);
        } else {
            showToast(errorMessage(error), 'error');
        }
    } finally {
        clearBusy(elements.submit);
    }
}

// ---------------------------------------------------------------- icon picker

// Fluent names its icons after the picture, not after the word someone would type: nobody
// calls a gym session "dumbbell" or work "briefcase". These carry the common words onto the
// set's own names. Every target is checked against App\Enums\CategoryIcon by a test.
const ICON_ALIASES = {
    work: ['briefcase', 'building'],
    job: ['briefcase'],
    office: ['building', 'briefcase'],
    business: ['briefcase', 'building'],
    gym: ['dumbbell', 'run'],
    fitness: ['dumbbell', 'run'],
    workout: ['dumbbell', 'run'],
    exercise: ['dumbbell', 'run'],
    running: ['run'],
    travel: ['airplane', 'luggage', 'beach'],
    trip: ['airplane', 'luggage'],
    vacation: ['beach', 'airplane'],
    holiday: ['beach', 'airplane'],
    flight: ['airplane'],
    school: ['hat-graduation', 'backpack'],
    study: ['book', 'hat-graduation'],
    college: ['hat-graduation'],
    thesis: ['document', 'book'],
    homework: ['book', 'backpack'],
    budget: ['money', 'wallet', 'savings'],
    finance: ['money', 'wallet', 'savings'],
    bills: ['receipt', 'payment'],
    expenses: ['receipt', 'money'],
    shopping: ['cart', 'shopping-bag'],
    groceries: ['cart', 'food'],
    errands: ['cart', 'vehicle-car'],
    health: ['heart', 'pill', 'stethoscope'],
    doctor: ['stethoscope', 'pill'],
    medical: ['stethoscope', 'syringe', 'pill'],
    medicine: ['pill'],
    pets: ['animal-dog', 'animal-cat'],
    dog: ['animal-dog'],
    cat: ['animal-cat'],
    coding: ['code', 'code-block'],
    dev: ['code', 'bug'],
    programming: ['code', 'code-block'],
    website: ['code', 'globe'],
    band: ['music-note-1', 'headphones'],
    cooking: ['food', 'bowl-chopsticks'],
    recipes: ['food', 'notepad'],
    chores: ['broom', 'home'],
    cleaning: ['broom', 'dust'],
    house: ['home', 'building-home'],
    driving: ['vehicle-car'],
    photo: ['camera', 'image'],
    photography: ['camera', 'image-multiple'],
    reading: ['book-open', 'reading-list'],
    design: ['paint-brush', 'design-ideas', 'color'],
    art: ['paint-brush', 'color'],
    drawing: ['draw-shape', 'pen'],
    game: ['games', 'xbox-console'],
    gaming: ['games', 'xbox-console'],
    meeting: ['device-meeting-room', 'calendar'],
    event: ['calendar', 'balloon'],
    birthday: ['gift', 'balloon'],
    wedding: ['heart', 'balloon'],
    garden: ['leaf', 'plant-grass', 'tree-deciduous'],
    plants: ['plant-grass', 'leaf'],
    sleep: ['bed', 'weather-moon'],
    idea: ['lightbulb', 'design-ideas'],
    ideas: ['lightbulb', 'design-ideas'],
    personal: ['person'],
    family: ['people', 'people-community'],
    team: ['people-team'],
    goals: ['target-arrow', 'trophy'],
    fix: ['wrench', 'toolbox'],
    repair: ['wrench', 'wrench-screwdriver'],
    move: ['box', 'vehicle-truck'],
    moving: ['box', 'vehicle-truck'],
};
// The project's own name is the search: there is no second box to fill in. A word matches the
// start of an icon's whole name or the start of any word in it, so "music" offers the music
// notes and "m" offers mail, money and music alike.
function iconMatches(name, terms) {
    const words = name.split('-');

    return terms.some(
        (term) =>
            name.startsWith(term) ||
            words.some((word) => word.startsWith(term)) ||
            (ICON_ALIASES[term] ?? []).includes(name),
    );
}

// Nothing is offered until the name suggests something, so the form stays small until it has
// a reason not to be. An icon that stops matching is also unchecked, because the picker must
// never save something it is no longer showing; the form falls back to Folder in that case.
function suggestIcons(name) {
    const terms = name.toLowerCase().split(/[^a-z0-9]+/).filter(Boolean);
    let shown = 0;
    let checked = false;

    for (const option of elements.iconGrid.querySelectorAll('[data-icon]')) {
        const match = terms.length > 0 && iconMatches(option.dataset.icon, terms);
        const radio = option.querySelector('input');

        option.classList.toggle('hidden', !match);
        option.classList.toggle('flex', match);

        if (!match) {
            radio.checked = false;
        } else {
            shown += 1;
            checked = checked || radio.checked;
        }
    }

    // Offer the closest name as the choice, so picking one is a glance rather than a step.
    if (shown > 0 && !checked) {
        elements.iconGrid.querySelector('[data-icon]:not(.hidden) input').checked = true;
    }

    elements.iconGrid.classList.toggle('hidden', shown === 0);
    elements.iconGrid.classList.toggle('flex', shown > 0);
    elements.iconEmpty.classList.toggle('hidden', terms.length === 0 || shown > 0);
}

async function createCategory(event) {
    event.preventDefault();

    if (elements.categorySubmit.disabled) {
        return;
    }

    clearFieldErrors(['category-name', 'category-icon']);
    setBusy(elements.categorySubmit, 'Adding…');

    try {
        await api('/categories', {
            method: 'POST',
            body: {
                name: elements.categoryName.value.trim(),
                // Nothing matched the name, so no icon is on offer and none is checked.
                icon: elements.categoryForm.querySelector('input[name="icon"]:checked')?.value ?? 'folder',
            },
        });
        elements.categoryForm.reset();
        suggestIcons('');
        showToast('Project added');
        await load();
        elements.categoryName.focus();
    } catch (error) {
        // The API names these fields "name" and "color"; the inputs are prefixed to stay unique.
        if (error instanceof ApiError && Object.keys(error.errors).length > 0) {
            const prefixed = Object.fromEntries(
                Object.entries(error.errors).map(([field, messages]) => [`category-${field}`, messages]),
            );
            showFieldErrors(prefixed, elements.categoryForm);
        } else {
            showToast(errorMessage(error), 'error');
        }
    } finally {
        clearBusy(elements.categorySubmit);
    }
}

async function deleteCategory(category) {
    const confirmed = await confirmAction({
        title: 'Delete project',
        message:
            category.task_count > 0
                ? `Delete "${category.name}"? Its ${category.task_count} task(s) will stay, without a project.`
                : `Delete "${category.name}"?`,
    });

    if (!confirmed) {
        return;
    }

    try {
        await api(`/categories/${category.id}`, { method: 'DELETE' });

        if (state.categoryId === category.id) {
            state.categoryId = null;
            applyView();
        }

        showToast('Project deleted');
        await load();
    } catch (error) {
        showToast(errorMessage(error), 'error');
    }
}

// ---------------------------------------------------------------- navigation

function applyView() {
    const view = VIEWS[state.view];
    const category = state.categories.find((item) => item.id === state.categoryId);

    elements.viewTitle.textContent = category ? category.name : view.title;
    elements.viewSubtitle.textContent = category ? 'Tasks in this project.' : view.subtitle;

    viewButtons.forEach((button) => {
        button.setAttribute('aria-pressed', String(!state.categoryId && button.dataset.view === state.view));
    });
    statusButtons.forEach((button) => {
        button.setAttribute('aria-pressed', String(button.dataset.filter === state.status));
    });
}

function setView(key) {
    state.view = key;
    state.categoryId = null;
    state.status = key === 'completed' ? 'completed' : '';
    applyView();
    closeDrawer();
    load();
}

function selectCategory(category) {
    state.categoryId = state.categoryId === category.id ? null : category.id;
    state.view = 'all';
    state.status = '';
    applyView();
    closeDrawer();
    load();
}

function setMode(mode) {
    state.mode = mode;
    modeButtons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.mode === mode)));
    elements.listView.classList.toggle('hidden', mode !== 'list');
    elements.calendarView.classList.toggle('hidden', mode !== 'calendar');
    elements.calendarView.classList.toggle('xl:grid', mode === 'calendar');
    load();
}

function shiftMonth(offset) {
    state.month = new Date(state.month.getFullYear(), state.month.getMonth() + offset, 1);
    load();
}

// ---------------------------------------------------------------- wiring

elements.form.addEventListener('submit', createTask);
elements.categoryForm.addEventListener('submit', createCategory);
elements.categoryName.addEventListener('input', (event) => suggestIcons(event.target.value));
elements.newTaskTrigger.addEventListener('click', openTaskDialog);
elements.taskCancel.addEventListener('click', closeTaskDialog);
// Escape and the backdrop close the dialog without going through the Cancel button, so the
// form is cleared here too rather than in each handler.
elements.taskDialog.addEventListener('close', () => {
    elements.form.reset();
    clearFieldErrors(TASK_FIELDS);
    elements.newTaskTrigger.focus();
});
elements.taskDialog.addEventListener('click', (event) => {
    if (event.target === elements.taskDialog) {
        elements.taskDialog.close();
    }
});
elements.retry.addEventListener('click', load);

elements.categoryToggle.addEventListener('click', () => {
    const open = elements.categoryForm.classList.toggle('hidden');
    elements.categoryToggle.setAttribute('aria-expanded', String(!open));

    if (!open) {
        elements.categoryName.focus();
    }
});

viewButtons.forEach((button) => button.addEventListener('click', () => setView(button.dataset.view)));
modeButtons.forEach((button) => button.addEventListener('click', () => setMode(button.dataset.mode)));
statusButtons.forEach((button) =>
    button.addEventListener('click', () => {
        state.status = button.dataset.filter;
        applyView();
        load();
    }),
);

$('calendar-prev').addEventListener('click', () => shiftMonth(-1));
$('calendar-next').addEventListener('click', () => shiftMonth(1));
$('calendar-today').addEventListener('click', () => {
    state.month = new Date(today.getFullYear(), today.getMonth(), 1);
    load();
});

enhanceDateFields();
load();
