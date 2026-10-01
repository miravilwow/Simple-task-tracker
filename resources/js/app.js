import { ApiError, api, errorMessage, RATE_LIMIT_MESSAGE } from './api.js';
import { clearBoard, renderBoard, wireBoardDragging } from './board.js';
import { createDisplay, groupTasks } from './display.js';
import { openTaskDetail, wireTaskDetail } from './taskdialog.js';
import { monthLabel, monthRange, renderAgenda, renderMonthGrid, renderUnscheduled } from './calendar.js';
import { enhanceDateFields } from './datepicker.js';
import { confirmAction, openMoveDialog, openProjectPanel, openScheduleDialog } from './dialogs.js';
import { openMenu } from './menu.js';
import './shell.js';
import { closeDrawer } from './sidebar.js';
import {
    clearBusy,
    createBadge,
    createElement,
    createIcon,
    parseDate,
    setBusy,
    setVisible,
    shortDate,
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
    pending: { label: 'To do', classes: 'bg-blue-100 text-blue-700' },
    in_progress: { label: 'In progress', classes: 'bg-amber-100 text-amber-800' },
    completed: { label: 'Done', classes: 'bg-green-100 text-green-700' },
};

// Full class strings, never built from the colour name, so Tailwind can find them. The set
// mirrors App\Enums\CategoryColor, and CategoryApiTest fails if the two drift apart.
const PROJECT_COLORS = {
    slate: 'text-slate-500',
    red: 'text-red-500',
    amber: 'text-amber-500',
    green: 'text-green-500',
    blue: 'text-blue-500',
    violet: 'text-violet-500',
    pink: 'text-pink-500',
};

/**
 * One category icon, tinted with its colour. The Iconify class is built from the name the API
 * sent, which Tailwind cannot see, so every one of them is safelisted in app.css through
 * `@source inline(...)`. Keep that list and App\Enums\CategoryIcon in step.
 */
function categoryIcon(category, size) {
    const icon = createElement(
        'span',
        `icon-[fluent--${category.icon}-24-regular] ${size} shrink-0 ${PROJECT_COLORS[category.color] ?? PROJECT_COLORS.slate}`,
    );
    icon.setAttribute('aria-hidden', 'true');

    return icon;
}

const VIEWS = {
    all: { title: 'All tasks', subtitle: "Create tasks, set priorities, and track what's done.", params: {} },
    today: { title: 'Today', subtitle: 'Still to do today.', params: { due: 'today' } },
    // No `due` here: this view is the calendar, and its month window is already the date
    // filter. Sending both would blank every day before today in the current month.
    upcoming: { title: 'Upcoming', subtitle: 'Everything with a date. Drag a task onto a day to schedule it.', params: {} },
    overdue: { title: 'Overdue', subtitle: 'Past their due date and still pending.', params: { due: 'overdue' } },
    completed: { title: 'Completed', subtitle: "Everything you've finished.", params: {} },
};

const BUTTON_BASE =
    'inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60';

const createdFormatter = new Intl.DateTimeFormat('en-US', { dateStyle: 'medium' });

// Where this browser's reaction token lives. It identifies a browser, not a person.
const REACTOR_KEY = 'task-tracker-reactor';

// Used only when localStorage is unavailable, so reactions still work for the life of the page.
const session = { reactor: crypto.randomUUID() };

const $ = (id) => document.getElementById(id);

const elements = {
    viewTitle: $('view-title'),
    viewSubtitle: $('view-subtitle'),
    categoryList: $('category-list'),
    taskDialog: $('task-dialog'),
    newTaskTrigger: $('new-task-trigger'),
    taskCancel: $('task-cancel'),
    categoryEmpty: $('category-empty'),
    favoritesGroup: $('favorites-group'),
    favoriteList: $('favorite-list'),
    deletedToggle: $('deleted-toggle'),
    deletedList: $('deleted-list'),
    deletedCount: $('deleted-count'),
    projectNew: $('project-new'),
    projectDialog: $('project-dialog'),
    projectDialogTitle: $('project-dialog-title'),
    projectForm: $('project-form'),
    projectName: $('project-name'),
    projectNameCount: $('project-name-count'),
    projectDescription: $('project-description'),
    projectColor: $('project-color'),
    projectParent: $('project-parent'),
    projectSubmit: $('project-submit'),
    projectCancel: $('project-cancel'),
    projectClose: $('project-close'),
    iconGrid: $('icon-grid'),
    iconEmpty: $('icon-empty'),
    categorySelect: $('category_id'),
    listView: $('list-view'),
    boardView: $('board-view'),
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
    calendarGrid: $('calendar-grid'),
    calendarAgenda: $('calendar-agenda'),
    calendarMonth: $('calendar-month'),
    unscheduledList: $('unscheduled-list'),
    unscheduledEmpty: $('unscheduled-empty'),
    unscheduledCount: $('unscheduled-count'),
};

const viewButtons = document.querySelectorAll('[data-view]');

const today = startOfToday();

const state = {
    view: 'all',
    categoryId: null,
    month: new Date(today.getFullYear(), today.getMonth(), 1),
    categories: [],
    // The project whose row has been swapped for the edit form, and the icon it had when the
    // edit began, which the picker keeps offering however the name is retyped.
    deletedCategories: [],
    deletedOpen: false,
    editing: null,
    editingIcon: null,
    projectDialogOpener: null,
    // The control the task dialog was opened from, so closing it puts focus back there.
    taskDialogOpener: null,
};

let latestRequestId = 0;

// The list as the page last drew it, so the board and the dialog can read what is on screen.
let latestTasks = [];

// ---------------------------------------------------------------- formatting

function dueLabel(task) {
    if (!task.due_date) {
        return 'No due date';
    }

    const formatted = shortDate.format(parseDate(task.due_date));

    if (task.is_overdue) {
        return `Overdue · ${formatted}`;
    }

    return task.due_date === toIsoDate(today) ? 'Due today' : `Due ${formatted}`;
}

function emptyMessage() {
    if (state.categoryId) {
        return 'No tasks in this project.';
    }

    // The Display panel can narrow the list to nothing, and "No tasks yet" would then be a lie.
    if (display.state.date || display.state.priority || !display.state.showCompleted) {
        return 'Nothing matches these display settings.';
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

function actionLabel(text, className = '') {
    const span = createElement('span', className, text);
    span.dataset.label = '';

    return span;
}

function deleteButton(task, compact) {
    const button = createElement('button', `${BUTTON_BASE} text-red-600 hover:bg-red-50 focus-visible:ring-red-500`);

    button.type = 'button';
    button.append(createIcon('trash'), actionLabel('Delete', compact ? 'sr-only' : ''));
    button.addEventListener('click', () => deleteTask(task, button));

    return button;
}

/**
 * The actions a task offers, built once for both layouts so a row and a card can never disagree
 * about what can be done to a task.
 *
 * `compact` is the card, where Delete keeps its word as screen-reader text only. `stageButtons`
 * is false on a draggable board, where the drag is the move and Start or Complete would be a
 * second way to do the one thing the columns already do.
 */
function taskActions(task, { compact = false } = {}) {
    const buttons = [];

    // Complete is the only stage button anywhere. Starting a task is the board's drag or the
    // dialog's Status field; reopening one is the Undo on its toast, the same two, or dragging
    // it back. A finished task has nothing left to press but Delete.
    if (task.status !== 'completed') {
        const completeButton = createElement(
            'button',
            [
                BUTTON_BASE,
                compact ? '' : 'flex-1 md:flex-none',
                'border border-green-200 bg-green-50 text-green-700 hover:bg-green-100 focus-visible:ring-green-500',
            ]
                .filter(Boolean)
                .join(' '),
        );
        completeButton.type = 'button';
        completeButton.append(createIcon('check'), actionLabel('Complete'));
        completeButton.addEventListener('click', () => completeTask(task, completeButton));
        buttons.push(completeButton);
    }

    buttons.push(deleteButton(task, compact));

    return buttons;
}

function titleButton(task) {

    const button = createElement(
        'button',
        'block w-full text-left wrap-break-word hover:underline hover:underline-offset-2 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none',
        task.title,
    );

    button.type = 'button';
    button.addEventListener('click', () => showTaskDetail(task.id, button));

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
    const heading = createElement('h3', `font-medium ${isCompleted ? 'text-gray-400 line-through' : ''}`);
    heading.append(titleButton(task));
    content.append(heading);

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
    actions.append(...taskActions(task));
    item.append(content, priorityCell, statusCell, actions);

    return item;
}

/**
 * A board card stacks the facts a row lays out in columns. Both call taskActions, so what a task
 * can do never depends on which layout is open.
 */
function renderCard(task) {
    const priority = PRIORITY_BADGES[task.priority];
    const isCompleted = task.status === 'completed';
    const draggable = display.state.grouping === 'status';

    const item = createElement('li', 'board-card task-enter');
    item.dataset.taskId = task.id;
    item.dataset.status = task.status;

    if (draggable) {
        item.draggable = true;
        item.tabIndex = 0;
        item.classList.add('cursor-grab', 'focus-visible:ring-2', 'focus-visible:ring-indigo-500', 'focus-visible:outline-none');
        item.setAttribute('aria-roledescription', 'Draggable task');
        item.setAttribute('aria-describedby', 'board-help');
    }

    const heading = createElement('h3', `text-sm font-medium ${isCompleted ? 'text-gray-400 line-through' : ''}`);
    heading.append(titleButton(task));
    item.append(createElement('span', `absolute inset-y-0 left-0 w-1 ${priority.accent}`), heading);

    if (task.description) {
        item.append(createElement('p', 'mt-1 text-xs whitespace-pre-line wrap-break-word text-gray-500', task.description));
    }

    const meta = createElement('div', 'mt-2 flex flex-wrap items-center gap-x-2 gap-y-1');

    if (task.category) {
        const chip = createElement('span', 'inline-flex items-center gap-1.5 text-xs text-gray-600');
        chip.append(categoryIcon(task.category, 'size-3.5'), createElement('span', '', task.category.name));
        meta.append(chip);
    }

    meta.append(createDueButton(task), createBadge(priority.label, priority.classes));

    // Grouped by status the column already says it, so the badge would print it twice.
    if (!draggable) {
        meta.append(createBadge(STATUS_BADGES[task.status].label, STATUS_BADGES[task.status].classes));
    }

    const actions = createElement('div', 'mt-3 flex items-center gap-2');
    actions.append(...taskActions(task, { compact: true }));
    item.append(meta, actions);

    return item;
}

/**
 * A heading row inside the list. The list is one `<ul>` whatever the grouping, so a group is a
 * row that happens to be a heading rather than a second structure to keep in step.
 */
function groupHeading(group) {
    const row = createElement('li', 'flex items-center justify-between gap-2 bg-gray-50 px-4 py-2 sm:px-6');

    row.append(
        createElement('h3', 'text-xs font-semibold tracking-wide text-gray-600 uppercase', group.label),
        createElement('span', 'text-xs font-medium text-gray-500 tabular-nums', String(group.tasks.length)),
    );

    return row;
}

function renderList(tasks, groups) {
    const hasTasks = tasks.length > 0;
    const rows = [];

    // Ungrouped, the list is what it has always been. Grouped, each heading carries its own rows,
    // and an empty group is left out rather than printed as a heading over nothing.
    if (groups.length === 1 && groups[0].key === 'all') {
        rows.push(...tasks.map(renderTask));
    } else {
        for (const group of groups.filter((item) => item.tasks.length > 0)) {
            rows.push(groupHeading(group), ...group.tasks.map(renderTask));
        }
    }

    elements.taskList.replaceChildren(...rows);
    elements.listMessageText.textContent = emptyMessage();
    setVisible(elements.listMessage, !hasTasks);
    elements.columnHeaders.classList.toggle('md:grid', hasTasks);
}

// ---------------------------------------------------------------- stats & sidebar

function renderStats(data) {
    for (const element of document.querySelectorAll('[data-view-count]')) {
        const value = data[element.dataset.viewCount];
        element.textContent = value > 0 ? value : '';
    }

}

function clearStats() {
    for (const element of document.querySelectorAll('[data-view-count]')) {
        element.textContent = '';
    }
}

/**
 * Every action a project offers sits behind one "…", so the row carries a single control
 * however many it has. Delete is inside it too: throwing work away is not a button to be
 * brushed past on the way to selecting a project.
 */
function projectMenu(category, trigger) {
    openMenu(trigger, [
        { icon: 'pencil', label: 'Edit', onSelect: () => openProjectDialog({ category }) },
        {
            icon: 'star',
            label: category.is_favorite ? 'Remove from favorites' : 'Add to favorites',
            onSelect: () => setFavorite(category, !category.is_favorite),
        },
        { separator: true },
        {
            icon: 'arrow-right',
            label: 'Project actions',
            submenu: [
                { icon: 'arrow-right', label: 'Move', onSelect: () => moveProject(category) },
                { icon: 'duplicate', label: 'Duplicate', onSelect: () => duplicateProject(category) },
            ],
        },
        { separator: true },
        {
            icon: 'chat',
            label: 'Comments',
            count: category.comment_count,
            onSelect: () => showProjectPanel(category, 'comments'),
        },
        { icon: 'clock', label: 'View activity', onSelect: () => showProjectPanel(category, 'activity') },
        { separator: true },
        { icon: 'trash', label: 'Delete', destructive: true, onSelect: () => deleteCategory(category) },
    ]);
}

/**
 * A favourite is drawn twice, in Favorites and again in the tree. The scope is what lets focus
 * return to the row it actually left, rather than to whichever copy comes first in the document.
 */
function categoryRow(category, scope) {
    const row = createElement('div', 'flex items-center gap-1');

    // Same sidebar utilities the Blade menu buttons use, so both stay in step.
    const select = createElement('button', 'sidebar-menu-button flex-1');
    select.type = 'button';
    select.title = category.description ? `${category.name} — ${category.description}` : category.name;
    select.setAttribute('aria-pressed', String(state.categoryId === category.id));
    select.append(
        categoryIcon(category, 'size-5'),
        createElement('span', 'sidebar-collapsible flex-1 truncate text-left', category.name),
    );

    // A zero renders as nothing rather than "0".
    if (category.task_count > 0) {
        select.append(
            createElement('span', 'sidebar-collapsible sidebar-menu-badge', String(category.task_count)),
        );
    }

    select.addEventListener('click', () => selectCategory(category));

    const more = createElement('button', 'sidebar-collapsible sidebar-menu-action');
    more.type = 'button';
    more.dataset.categoryId = String(category.id);
    more.dataset.categoryScope = scope;
    more.dataset.menuLabel = `Actions for ${category.name}`;
    more.setAttribute('aria-haspopup', 'menu');
    more.setAttribute('aria-expanded', 'false');
    more.append(createIcon('ellipsis', 'size-4'), createElement('span', 'sr-only', `Actions for ${category.name}`));
    more.addEventListener('click', () => projectMenu(category, more));

    row.append(select, more);

    return row;
}

/**
 * The flat list the API returns, nested by parent_id.
 *
 * A project whose parent is missing from the list — deleted, say — is treated as a root, so a
 * branch can never disappear from the sidebar because of where its parent happens to be.
 */
function buildTree(categories) {
    const byId = new Map(categories.map((category) => [category.id, { ...category, children: [] }]));

    return [...byId.values()].filter((node) => {
        const parent = node.parent_id === null ? null : byId.get(node.parent_id);

        parent?.children.push(node);

        return !parent;
    });
}

function renderTree(nodes, depth = 0) {
    return nodes.map((node) => {
        const item = createElement('li');
        item.append(categoryRow(node, 'tree'));

        if (node.children.length > 0) {
            // A rule down the left edge, so a child reads as belonging to the row above it.
            const children = createElement('ul', 'mt-1 ml-3 flex flex-col gap-1 border-l border-gray-200 pl-1');
            children.append(...renderTree(node.children, depth + 1));
            item.append(children);
        }

        return item;
    });
}

function renderFavorites(categories) {
    const favorites = categories.filter((category) => category.is_favorite);

    // Favourites are flat: the point of the group is to skip the tree, not to repeat it.
    elements.favoriteList.replaceChildren(
        ...favorites.map((category) => {
            const item = createElement('li');
            item.append(categoryRow(category, 'favorites'));

            return item;
        }),
    );

    elements.favoritesGroup.classList.toggle('hidden', favorites.length === 0);
}

/**
 * The undo toast lasts seconds; this is where a deleted project stays reachable afterwards.
 * Without it a soft-deleted row would sit in the database with no way back to it, which would
 * make the soft delete pointless.
 */
function renderDeleted(categories) {
    elements.deletedCount.textContent = categories.length > 0 ? String(categories.length) : '';
    elements.deletedToggle.classList.toggle('hidden', categories.length === 0);

    if (categories.length === 0) {
        setDeletedOpen(false);
    }

    elements.deletedList.replaceChildren(
        ...categories.map((category) => {
            const item = createElement('li');
            const row = createElement('div', 'flex items-center gap-1');

            // Not a button: a deleted project cannot be selected, only put back or finished off.
            const label = createElement('div', 'sidebar-menu-button flex-1 text-gray-400 line-through');
            label.append(
                categoryIcon(category, 'size-5'),
                createElement('span', 'sidebar-collapsible flex-1 truncate text-left', category.name),
            );

            const restore = createElement('button', 'sidebar-collapsible sidebar-menu-action');
            restore.type = 'button';
            restore.title = `Restore ${category.name}`;
            restore.append(createIcon('undo', 'size-4'), createElement('span', 'sr-only', `Restore ${category.name}`));
            restore.addEventListener('click', () =>
                runAndReload(() => api(`/categories/${category.id}/restore`, { method: 'PATCH' }), 'Project restored'),
            );

            const purge = createElement(
                'button',
                'sidebar-collapsible sidebar-menu-action hover:bg-red-50 hover:text-red-600 focus-visible:ring-red-500',
            );
            purge.type = 'button';
            purge.title = `Delete ${category.name} permanently`;
            purge.append(
                createIcon('trash', 'size-4'),
                createElement('span', 'sr-only', `Delete ${category.name} permanently`),
            );
            purge.addEventListener('click', () => purgeCategory(category));

            row.append(label, restore, purge);
            item.append(row);

            return item;
        }),
    );
}

function renderCategories(categories, deleted) {
    state.categories = categories;
    state.deletedCategories = deleted;

    // Rebuilding the list throws away the node that had focus, so remember which row it was on.
    const previous = document.activeElement?.closest?.('[data-category-id]')?.dataset;
    const focused = previous ? { id: previous.categoryId, scope: previous.categoryScope } : null;

    renderFavorites(categories);
    elements.categoryList.replaceChildren(...renderTree(buildTree(categories)));
    elements.categoryEmpty.classList.toggle('hidden', categories.length > 0);
    renderDeleted(deleted);

    // Keep the task form's picker in step with the sidebar, preserving any choice already made.
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

    if (focused) {
        document
            .querySelector(`[data-category-scope="${focused.scope}"][data-category-id="${focused.id}"]`)
            ?.focus();
    }
}

// ---------------------------------------------------------------- loading

function currentParams() {
    const params = { ...VIEWS[state.view].params, sort: display.state.sorting, priority: display.state.priority };

    // The Completed toggle and the Date filter both narrow the same list, and a sidebar view
    // that already names one of them wins: Completed means completed.
    if (state.view === 'completed') {
        params.status = 'completed';
    } else if (!display.state.showCompleted) {
        params.completed = 0;
    }

    // The calendar is already a date view: a `due` beside its month window would fight it and
    // leave a grid that is empty for no visible reason.
    if (display.state.date && currentLayout() !== 'calendar') {
        params.due = display.state.date;
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
    clearBoard();
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
        // The deleted list is its own request: the sidebar shows both, and asking for them
        // together would mean the tree could not tell one from the other.
        const requests = [
            api('/tasks/stats'),
            api('/categories'),
            api('/categories', { params: { deleted: 1 } }),
        ];

        requests.push(
            currentLayout() === 'calendar'
                ? api('/tasks', { params: { ...currentParams(), ...range } })
                : api('/tasks', { params: currentParams() }),
        );

        if (currentLayout() === 'calendar') {
            requests.push(api('/tasks', { params: { ...currentParams(), due: 'none' } }));
        }

        const [statsResponse, categoriesResponse, deletedResponse, tasksResponse, unscheduledResponse] =
            await Promise.all(requests);

        if (requestId !== latestRequestId) {
            return;
        }

        elements.skeleton.classList.add('hidden');
        elements.loadError.classList.add('hidden');
        renderStats(statsResponse.data);
        renderCategories(categoriesResponse.data, deletedResponse.data);

        if (currentLayout() === 'calendar') {
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
        } else if (currentLayout() === 'board') {
            renderBoard({
                tasks: tasksResponse.data,
                grouping: display.state.grouping,
                categories: categoriesResponse.data,
                renderCard,
            });
        } else {
            renderList(
                tasksResponse.data,
                groupTasks({
                    tasks: tasksResponse.data,
                    grouping: display.state.grouping,
                    categories: categoriesResponse.data,
                }),
            );
        }

        display.renderSummary(tasksResponse.data.length);
        latestTasks = tasksResponse.data;
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
        showToast(successMessage, 'success', undo ? { label: 'Undo', onClick: () => runAndReload(undo, undoMessage) } : null);
        await load();

        if (hadFocus) {
            // The Tasks heading lives inside the list panel, which is hidden in the other two
            // layouts. Focusing it there would drop focus onto the body.
            (currentLayout() === 'list' ? elements.tasksHeading : elements.viewTitle).focus();
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
/**
 * Run one request, say what happened, and reload. Every action that is a single call and a
 * toast goes through here: undoing a task, favouriting a project, moving it, restoring it.
 */
async function runAndReload(request, message, undo) {
    try {
        await request();
        showToast(message, 'success', undo ? { label: 'Undo', onClick: () => runAndReload(undo, 'Move undone') } : null);
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

async function deleteTask(task, button) {
    // The dialog steals focus, so record where it came from before opening.
    const hadFocus = document.activeElement === button;
    const confirmed = await confirmAction({
        title: 'Delete task',
        message: `Delete "${task.title}"? You can undo this from the toast straight after.`,
    });

    if (!confirmed) {
        // The task dialog deletes without a button of its own, so there may be nothing to go back to.
        button?.focus();

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
function openTaskDialog({ category = null, opener = elements.newTaskTrigger } = {}) {
    clearFieldErrors(TASK_FIELDS);
    // Opened from a project's menu, the task starts in that project. The select still shows it,
    // so the choice is visible and can be changed rather than being decided behind the scenes.
    elements.categorySelect.value = category ? String(category.id) : '';
    state.taskDialogOpener = opener;
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
function suggestIcons(name, keep = null) {
    const terms = name.toLowerCase().split(/[^a-z0-9]+/).filter(Boolean);
    let shown = 0;
    let checked = false;

    for (const option of elements.iconGrid.querySelectorAll('[data-icon]')) {
        // keep is the icon a project already has: it stays on offer however its name is retyped,
        // so editing one can never quietly swap the icon the user chose.
        const pinned = option.dataset.icon === keep;
        const match = pinned || (terms.length > 0 && iconMatches(option.dataset.icon, terms));
        const radio = option.querySelector('input');

        setVisible(option, match);

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

    setVisible(elements.iconGrid, shown > 0);
    elements.iconEmpty.classList.toggle('hidden', terms.length === 0 || shown > 0);
}

/**
 * One form does both jobs. Editing moves that same node into the row it belongs to, which is
 * what keeps a single 224-option icon grid in the page instead of one per project.
 */
const PROJECT_FIELDS = ['project-name', 'project-description', 'project-color', 'project-parent', 'project-icon'];

/**
 * Add and Edit are the same dialog. Two would mean two copies of the 224-option icon grid in
 * the page, and two places for the picker's behaviour to drift apart.
 */
function openProjectDialog({ category = null, opener = elements.projectNew } = {}) {
    state.editing = category;
    state.editingIcon = category?.icon ?? null;
    state.projectDialogOpener = opener;

    clearFieldErrors(PROJECT_FIELDS);
    elements.projectDialogTitle.textContent = category ? 'Edit project' : 'New project';
    elements.projectSubmit.querySelector('[data-label]').textContent = category ? 'Save' : 'Add project';

    elements.projectName.value = category?.name ?? '';
    elements.projectDescription.value = category?.description ?? '';
    elements.projectColor.value = category?.color ?? 'slate';
    fillParentOptions(elements.projectParent, category);
    elements.projectParent.value = category?.parent_id ? String(category.parent_id) : '';
    updateNameCount();

    // Checked before the grid is filtered, so the pass below sees a choice already made and
    // leaves it alone rather than offering the closest name instead.
    const current = elements.iconGrid.querySelector(`[data-icon="${category?.icon ?? 'folder'}"] input`);

    if (current) {
        current.checked = true;
    }

    suggestIcons(elements.projectName.value, state.editingIcon);
    elements.projectDialog.showModal();
    elements.projectName.focus();
    elements.projectName.select();
}

/**
 * A project may not become its own parent, nor the child of one of its own children: that would
 * cut the whole branch off the tree. The server refuses it too; leaving the options out is what
 * stops the user reaching for something that cannot work.
 */
function fillParentOptions(select, category) {
    const excluded = category ? new Set(descendantIds(category.id)) : new Set();
    const none = createElement('option', '', 'No parent');
    none.value = '';

    select.replaceChildren(
        none,
        ...state.categories
            .filter((one) => !excluded.has(one.id))
            .map((one) => {
                const option = createElement('option', '', one.name);
                option.value = String(one.id);

                return option;
            }),
    );
}

function descendantIds(id) {
    const children = state.categories.filter((one) => one.parent_id === id);

    return [id, ...children.flatMap((child) => descendantIds(child.id))];
}

function closeProjectDialog() {
    // The close handler does the clearing, because Escape and the backdrop get there too.
    elements.projectDialog.close();
}

function updateNameCount() {
    elements.projectNameCount.textContent = `${elements.projectName.value.length}/40`;
}

function projectPayload() {
    return {
        name: elements.projectName.value.trim(),
        description: elements.projectDescription.value.trim() || null,
        color: elements.projectColor.value,
        // Nothing matched the name, so no icon is on offer and none is checked.
        icon: elements.projectForm.querySelector('input[name="icon"]:checked')?.value ?? 'folder',
        parent_id: elements.projectParent.value ? Number(elements.projectParent.value) : null,
    };
}

async function submitProjectForm(event) {
    event.preventDefault();

    // Pressing Enter can re-submit the form even while the disabled button is mid-request.
    if (elements.projectSubmit.disabled) {
        return;
    }

    const editing = state.editing;

    clearFieldErrors(PROJECT_FIELDS);
    setBusy(elements.projectSubmit, 'Saving…');

    try {
        await api(editing ? `/categories/${editing.id}` : '/categories', {
            method: editing ? 'PATCH' : 'POST',
            body: projectPayload(),
        });

        closeProjectDialog();
        showToast(editing ? 'Project updated' : 'Project added');
        await load();
    } catch (error) {
        // The API names these fields "name", "icon" and so on; the inputs are prefixed to stay
        // unique on a page that also has a task form.
        if (error instanceof ApiError && Object.keys(error.errors).length > 0) {
            const prefixed = Object.fromEntries(
                Object.entries(error.errors).map(([field, messages]) => [`project-${field.replace('_id', '')}`, messages]),
            );
            showFieldErrors(prefixed, elements.projectForm);
        } else {
            showToast(errorMessage(error), 'error');
        }
    } finally {
        clearBusy(elements.projectSubmit);
    }
}

function setFavorite(category, isFavorite) {
    runAndReload(
        () => api(`/categories/${category.id}/favorite`, {
            method: 'PATCH',
            body: { is_favorite: isFavorite },
        }),
        isFavorite ? 'Added to favorites' : 'Removed from favorites',
    );
}

function duplicateProject(category) {
    runAndReload(
        () => api(`/categories/${category.id}/duplicate`, { method: 'POST' }),
        'Project duplicated',
    );
}

/**
 * "Move into folder" and "set parent" are the same thing here, because a folder is simply a
 * project with children. One tree, so one operation.
 */
async function moveProject(category) {
    const parentId = await openMoveDialog(category, (select) => fillParentOptions(select, category));

    if (parentId === null) {
        return;
    }

    await runAndReload(
        () => api(`/categories/${category.id}/move`, {
            method: 'PATCH',
            body: { parent_id: parentId.value },
        }),
        'Project moved',
    );
}

/**
 * The app has no accounts, so a reaction cannot belong to a person. It belongs to this browser:
 * a random token kept in localStorage, which is enough to make a reaction toggle correctly and
 * to stop one browser counting itself twice. Losing it only means losing which reactions were
 * mine, never the counts themselves.
 */
function reactorToken() {
    try {
        const stored = localStorage.getItem(REACTOR_KEY);

        if (stored) {
            return stored;
        }

        const fresh = crypto.randomUUID();
        localStorage.setItem(REACTOR_KEY, fresh);

        return fresh;
    } catch {
        // Private browsing can refuse storage. Reactions still post and still count; they just
        // stop being remembered as mine between loads.
        return session.reactor;
    }
}

/**
 * Comments and Activity are two views of the same question, so they share one dialog and the
 * menu's two entries differ only in which tab opens.
 */
function showProjectPanel(category, tab) {
    const reactor = reactorToken();

    openProjectPanel(category, tab, {
        loadComments: async () =>
            (await api(`/categories/${category.id}/comments`, { params: { reactor } })).data,
        addComment: (body) => api(`/categories/${category.id}/comments`, { method: 'POST', body: { body } }),
        removeComment: (comment) =>
            api(`/categories/${category.id}/comments/${comment.id}`, { method: 'DELETE' }),
        react: (comment, emoji, reacted) =>
            api(`/categories/${category.id}/comments/${comment.id}/reactions`, {
                method: 'PATCH',
                body: { emoji, reacted, reactor },
            }),
        loadActivity: async () => (await api(`/categories/${category.id}/activity`)).data,
    });
}

async function deleteCategory(category) {
    const confirmed = await confirmAction({
        title: 'Delete project',
        message:
            category.task_count > 0
                ? `Delete "${category.name}"? Its ${category.task_count} task(s) will stay, without a project.`
                : `Delete "${category.name}"?`,
        confirmLabel: 'Delete project',
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

        // The same Undo a deleted task gets. A project holds more than a task does, so it would
        // be the odd one out without it.
        showToast('Project deleted', 'success', {
            label: 'Undo',
            onClick: () =>
                runAndReload(() => api(`/categories/${category.id}/restore`, { method: 'PATCH' }), 'Project restored'),
        });
        await load();
    } catch (error) {
        showToast(errorMessage(error), 'error');
    }
}

function setDeletedOpen(open) {
    state.deletedOpen = open;
    elements.deletedToggle.setAttribute('aria-expanded', String(open));
    setVisible(elements.deletedList, open);
}

/**
 * The one action in the app that cannot be undone, so it asks first and says so plainly.
 */
async function purgeCategory(category) {
    const confirmed = await confirmAction({
        title: 'Delete permanently',
        message: `Delete "${category.name}" for good? This cannot be undone, and its comments and activity go with it.`,
        confirmLabel: 'Delete permanently',
    });

    if (!confirmed) {
        return;
    }

    runAndReload(
        () => api(`/categories/${category.id}/force`, { method: 'DELETE' }),
        'Project deleted permanently',
    );
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
}

function setView(key) {
    state.view = key;
    state.categoryId = null;
    applyView();
    applyLayout();
    closeDrawer();
    load();
}

function selectCategory(category) {
    state.categoryId = state.categoryId === category.id ? null : category.id;
    state.view = 'all';
    applyView();
    applyLayout();
    closeDrawer();
    load();
}

/**
 * Upcoming is the calendar: a month grid is what "what is coming up" looks like, and there is
 * nothing there to lay out two ways. Every other view takes the Display panel's choice.
 */
const currentLayout = () => (state.view === 'upcoming' ? 'calendar' : display.state.mode);

// `md:grid` and `xl:grid` sit in media queries and would win over `hidden`, so each layout's
// grid class is added only while that layout is open.
function applyLayout() {
    const layout = currentLayout();

    display.setLayoutLocked(layout === 'calendar');
    elements.listView.classList.toggle('hidden', layout !== 'list');
    elements.boardView.classList.toggle('hidden', layout !== 'board');
    elements.boardView.classList.toggle('md:grid', layout === 'board');
    elements.calendarView.classList.toggle('hidden', layout !== 'calendar');
    elements.calendarView.classList.toggle('xl:grid', layout === 'calendar');
}

function applyDisplay({ date }) {
    // Today, Upcoming and Overdue are date filters already. Choosing a Date in the panel takes
    // over, the same way picking a project does, rather than leaving two filters to fight while
    // the heading still names the view that lost.
    if (date && VIEWS[state.view].params.due) {
        state.view = 'all';
        applyView();
    }

    applyLayout();
    load();
}

function shiftMonth(offset) {
    state.month = new Date(state.month.getFullYear(), state.month.getMonth() + offset, 1);
    load();
}

const STAGE_ENDPOINTS = { pending: 'reopen', in_progress: 'start', completed: 'complete' };

/** A drop on the board, and the keyboard move that does the same thing. */
function moveTask(id, status, from) {
    // The dialog knows the task's stage first-hand. The board reads it from the list, which is
    // the only place a dropped card can have come from.
    const current = from ?? latestTasks.find((item) => item.id === id)?.status;

    if (!current || current === status || !STAGE_ENDPOINTS[status]) {
        return;
    }

    const undo = STAGE_ENDPOINTS[current];

    return runAndReload(
        () => api(`/tasks/${id}/${STAGE_ENDPOINTS[status]}`, { method: 'PATCH' }),
        `Moved to ${STATUS_BADGES[status].label}`,
        () => api(`/tasks/${id}/${undo}`, { method: 'PATCH' }),
    );
}

function showTaskDetail(id, trigger) {
    openTaskDetail(id, trigger, {
        onStage: moveTask,
        categories: state.categories,
        order: latestTasks.map((task) => task.id),
        onChange: load,
        onDelete: (task) => deleteTask(task, null),
    });
}

const display = createDisplay(applyDisplay);

// ---------------------------------------------------------------- wiring


elements.form.addEventListener('submit', createTask);
elements.projectForm.addEventListener('submit', submitProjectForm);
elements.projectName.addEventListener('input', (event) => {
    updateNameCount();
    suggestIcons(event.target.value, state.editingIcon);
});
elements.projectNew.addEventListener('click', () => openProjectDialog());
elements.projectCancel.addEventListener('click', closeProjectDialog);
elements.projectClose.addEventListener('click', closeProjectDialog);
// Escape and the backdrop close the dialog without going through Cancel, so the form is reset
// here rather than in each handler.
elements.projectDialog.addEventListener('close', () => {
    state.editing = null;
    state.editingIcon = null;
    elements.projectForm.reset();
    clearFieldErrors(PROJECT_FIELDS);
    suggestIcons('');

    const opener = state.projectDialogOpener ?? elements.projectNew;
    state.projectDialogOpener = null;
    (opener.isConnected ? opener : elements.projectNew).focus();
});
elements.projectDialog.addEventListener('click', (event) => {
    if (event.target === elements.projectDialog) {
        closeProjectDialog();
    }
});
elements.deletedToggle.addEventListener('click', () => setDeletedOpen(!state.deletedOpen));
elements.newTaskTrigger.addEventListener('click', () => openTaskDialog());
elements.taskCancel.addEventListener('click', closeTaskDialog);
// Escape and the backdrop close the dialog without going through the Cancel button, so the
// form is cleared here too rather than in each handler.
elements.taskDialog.addEventListener('close', () => {
    elements.form.reset();
    clearFieldErrors(TASK_FIELDS);
    // A task started from a project's menu hands focus back to that row, not to the header
    // button, which is not where the user was.
    const opener = state.taskDialogOpener ?? elements.newTaskTrigger;
    state.taskDialogOpener = null;
    (opener.isConnected ? opener : elements.newTaskTrigger).focus();
});
elements.taskDialog.addEventListener('click', (event) => {
    if (event.target === elements.taskDialog) {
        elements.taskDialog.close();
    }
});
elements.retry.addEventListener('click', load);

viewButtons.forEach((button) => button.addEventListener('click', () => setView(button.dataset.view)));

$('calendar-prev').addEventListener('click', () => shiftMonth(-1));
$('calendar-next').addEventListener('click', () => shiftMonth(1));
$('calendar-today').addEventListener('click', () => {
    state.month = new Date(today.getFullYear(), today.getMonth(), 1);
    load();
});

wireTaskDetail();
wireBoardDragging(moveTask, (id) => showTaskDetail(id));
enhanceDateFields();

// The Display panel owns the layout, so the first render is its first change rather than a
// separate load() that would have to repeat what applyDisplay already does.
display.apply();

