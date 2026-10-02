import { ApiError, api, errorMessage, RATE_LIMIT_MESSAGE } from './api.js';
import { clearBoard, renderBoard, wireBoardDragging } from './board.js';
import { createDisplay, groupTasks } from './display.js';
import { renderProjectGrid } from './projects.js';
import { createDayDialog } from './daydialog.js';
import { createFilterBar } from './filterbar.js';
import { openTaskDetail, wireTaskDetail } from './taskdialog.js';
import { monthLabel, monthRange, renderAgenda, renderMonthGrid, renderUnscheduled } from './calendar.js';
import { enhanceDateFields } from './datepicker.js';
import { confirmAction, openScheduleDialog } from './dialogs.js';
import { openMenu } from './menu.js';
import { createSelection, rowCheckbox, syncHeaderCheckbox, syncSortHeaders } from './table.js';
import './shell.js';
import { closeDrawer } from './sidebar.js';
import {
    CARD_TINTS,
    clearBusy,
    createBadge,
    createElement,
    createIcon,
    formatTime,
    parseDate,
    setBusy,
    setDone,
    setVisible,
    shortDate,
    STATUS_BADGES,
    startOfToday,
    toIsoDate,
} from './dom.js';
import { toast } from './toast.js';

// Full class strings are listed here (not built dynamically) so Tailwind can find them.
const PRIORITY_BADGES = {
    high: { label: 'High', classes: 'bg-red-100 text-red-700', accent: 'bg-red-400' },
    medium: { label: 'Medium', classes: 'bg-amber-100 text-amber-800', accent: 'bg-amber-400' },
    low: { label: 'Low', classes: 'bg-slate-100 text-slate-700', accent: 'bg-slate-300' },
};

const VIEWS = {
    all: { title: 'All tasks', subtitle: "Create tasks, set priorities, and track what's done.", params: {} },
    today: { title: 'Today', subtitle: 'Still to do today.', params: { due: 'today' } },
    // No `due` here: this view is the calendar, and its month window is already the date
    // filter. Sending both would blank every day before today in the current month.
    upcoming: { title: 'Upcoming', subtitle: 'Everything with a date. Drag a task onto a day to schedule it.', params: {} },
    overdue: { title: 'Overdue', subtitle: 'Past their due date and still pending.', params: { due: 'overdue' } },
    completed: { title: 'Completed', subtitle: "Everything you've finished.", params: {} },
};

// The string lives in app.css as @utility btn-action, because the selection toolbar in Blade needs
// the same shape and a copy in each is how the two drift apart.
const UNSCHEDULED_EMPTY = 'Every task has a date.';
const FILTERED_EMPTY = 'Nothing matches these filters.';

const BUTTON_BASE = 'btn-action';

const createdFormatter = new Intl.DateTimeFormat('en-US', { dateStyle: 'medium' });

const $ = (id) => document.getElementById(id);

const elements = {
    boardControls: $('board-controls'),
    displaySummary: $('display-summary'),
    viewTitle: $('view-title'),
    viewSubtitle: $('view-subtitle'),
    taskDialog: $('task-dialog'),
    newTaskTrigger: $('new-task-trigger'),
    taskCancel: $('task-cancel'),
    categoryName: $('category_name'),
    projectOptions: $('project-options'),
    listView: $('list-view'),
    boardView: $('board-view'),
    projectCrumb: $('project-crumb'),
    projectBack: $('project-back'),
    projectCrumbName: $('project-crumb-name'),
    calendarView: $('calendar-view'),
    form: $('task-form'),
    title: $('title'),
    description: $('description'),
    dueDate: $('due_date'),
    dueTime: $('due_time'),
    submit: $('submit-button'),
    taskList: $('task-list'),
    tasksHeading: $('tasks-heading'),
    columnHeaders: $('column-headers'),
    selectAll: $('select-all'),
    selectionBar: $('selection-bar'),
    selectionCount: $('selection-count'),
    selectionComplete: $('selection-complete'),
    selectionDelete: $('selection-delete'),
    selectionClear: $('selection-clear'),
    skeleton: $('skeleton'),
    boardSkeleton: $('board-skeleton'),
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

// The two column headers that sort. Status has none: every order already runs through byStage().
const sortHeaders = document.querySelectorAll('#column-headers [data-sort]');

const today = startOfToday();

const state = {
    view: 'all',
    // The project whose board is open, as a category id string or 'none'; null shows the project cards.
    project: null,
    projectName: '',
    // The table's sort, set by its column headers. The board always asks for its manual order.
    listSort: 'default',
    month: new Date(today.getFullYear(), today.getMonth(), 1),
};

let latestRequestId = 0;

// The list as the page last drew it, so the board and the dialog can read what is on screen.
let latestTasks = [];

// Every reload redraws the whole layout, so only a task missing from the last draw plays the
// entrance animation. Replaying it on every card made the board blink after each drop.
let drawnIds = new Set();
const enterClass = (task) => (drawnIds.has(task.id) ? '' : 'task-enter');

// The ids the list last drew, which is what "select every task shown" means and what a selection
// is pruned against. Not the same as latestTasks: a grouping can leave an empty group out.
let listedIds = [];

// The rows the user has ticked. Only the list layout draws checkboxes, so the board and the
// calendar leave it empty and the toolbar never appears over them.
const selection = createSelection(() => syncSelection());

// ---------------------------------------------------------------- formatting

function dueLabel(task) {
    if (!task.due_date) {
        return 'No due date';
    }

    // The time is detail on the end of the day, never in front of it: the day is what decides
    // which view the task is in and whether it is late.
    const at = task.due_time ? `, ${formatTime(task.due_time)}` : '';
    const formatted = shortDate.format(parseDate(task.due_date)) + at;

    if (task.is_overdue) {
        return `Overdue · ${formatted}`;
    }

    return task.due_date === toIsoDate(today) ? `Due today${at}` : `Due ${formatted}`;
}

const filtersAreOn = () => Object.values(filterBar.state).some((value) => value !== '');

function emptyMessage() {
    // The filter bar can narrow the list to nothing, and "No tasks yet" would then be a lie.
    if (filtersAreOn()) {
        return FILTERED_EMPTY;
    }

    return {
        all: 'No tasks yet. Use New task to add your first one.',
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
        `-mx-1 inline-flex min-h-8 items-center gap-1 rounded-md px-2 text-xs transition-colors hover:bg-gray-100 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none ${
            task.is_overdue ? 'text-red-600' : 'text-gray-500'
        }`,
    );
    button.type = 'button';
    button.append(createIcon('calendar', 'size-3.5 shrink-0'), createElement('span', '', dueLabel(task)));
    button.addEventListener('click', () => rescheduleFromDialog(task));

    return button;
}

function projectChip(category) {
    const chip = createElement('span', 'inline-flex items-center gap-1.5 text-xs text-gray-600');
    chip.append(createIcon('folder', 'size-3.5 shrink-0 text-gray-400'), createElement('span', '', category.name));

    return chip;
}

function actionLabel(text, className = '') {
    const span = createElement('span', className, text);
    span.dataset.label = '';

    return span;
}

// Complete is the only stage button anywhere. Starting a task is the board's drag or the dialog's
// Status field; reopening one is the Undo on its toast, the same two, or dragging it back.
function completeButton(task, compact) {
    const button = createElement(
        'button',
        [BUTTON_BASE, 'btn-complete', compact ? '' : 'flex-1 md:flex-none'].filter(Boolean).join(' '),
    );

    button.type = 'button';
    button.append(createIcon('check'), actionLabel('Complete'));
    button.addEventListener('click', () => completeTask(task, button));

    return button;
}

/**
 * A table row's actions: Complete, then everything else behind the "…".
 *
 * Complete stays a button because it is the commonest thing anyone does on this page, and burying
 * it would make one click into two. Delete goes inside, which is where the rules already wanted it
 * — "never the most prominent button on the row" — and a row is the one place on the page where
 * horizontal space is genuinely contested.
 *
 * A board card offers fewer: Complete, then Edit and Delete behind its own "…".
 */
function rowActions(task) {
    const buttons = [];

    if (task.status !== 'completed') {
        buttons.push(completeButton(task, false));
    }

    buttons.push(rowMenu(task));

    return buttons;
}

function menuTrigger(task, sizeClass, buildEntries) {
    const trigger = createElement('button', `btn-row-menu ${sizeClass}`);

    trigger.type = 'button';
    trigger.dataset.menuLabel = 'Task actions';
    trigger.setAttribute('aria-haspopup', 'menu');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-label', `Actions for ${task.title}`);
    trigger.append(createIcon('ellipsis'));

    // Built on open rather than up front, so the labels read from the task as it is now.
    trigger.addEventListener('click', () => openMenu(trigger, buildEntries(trigger)));

    return trigger;
}

function rowMenu(task) {
    return menuTrigger(task, 'size-10', (trigger) => {
        const entries = [
            { icon: 'pencil', label: 'Open', onSelect: () => showTaskDetail(task.id, trigger) },
            {
                icon: 'calendar',
                label: task.due_date ? 'Reschedule' : 'Set a date',
                onSelect: () => rescheduleFromDialog(task),
            },
        ];

        if (task.status === 'completed') {
            entries.push({ icon: 'undo', label: 'Reopen', onSelect: () => reopenTask(task) });
        }

        entries.push(
            { separator: true },
            { icon: 'trash', label: 'Delete', destructive: true, onSelect: () => deleteTask(task) },
        );

        return entries;
    });
}

function cardMenu(task) {
    return menuTrigger(task, 'absolute top-1 right-1 size-8', (trigger) => [
        { icon: 'pencil', label: 'Edit', onSelect: () => showTaskDetail(task.id, trigger) },
        { separator: true },
        { icon: 'trash', label: 'Delete', destructive: true, onSelect: () => deleteTask(task) },
    ]);
}

function titleButton(task) {

    const button = createElement(
        'button',
        'block w-full text-left wrap-break-word hover:underline hover:underline-offset-2 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none',
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
        `${enterClass(task)} relative flex flex-wrap items-center gap-x-2 gap-y-3 py-4 pr-4 pl-5 transition-colors hover:bg-gray-50 sm:pr-6 sm:pl-7 md:grid md:task-columns md:gap-4`,
    );
    item.append(createElement('span', `absolute inset-y-0 left-0 w-1 ${priority.accent}`));

    // flex-1 rather than w-full, so below md the checkbox and the title share the first line
    // instead of the checkbox sitting alone above it.
    const content = createElement('div', 'min-w-0 flex-1 md:w-auto');
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
        meta.append(projectChip(task.category));
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

    const actions = createElement('div', 'flex w-full items-center gap-2 md:w-auto md:justify-end');
    actions.append(...rowActions(task));

    const select = createElement('div', 'flex items-center');
    select.append(rowCheckbox(task, selection));

    item.append(select, content, priorityCell, statusCell, actions);

    return item;
}

/**
 * A board card stacks the facts a row lays out in columns.
 */
function renderCard(task) {
    const priority = PRIORITY_BADGES[task.priority];
    const isCompleted = task.status === 'completed';
    const draggable = boardGrouping() === 'status';

    const item = createElement('li', `board-card ${CARD_TINTS[task.color] ?? 'bg-white border-gray-200'} ${enterClass(task)}`);
    item.dataset.taskId = task.id;
    item.dataset.status = task.status;

    if (draggable) {
        item.draggable = true;
        item.tabIndex = 0;
        item.classList.add('cursor-grab', 'focus-visible:ring-2', 'focus-visible:ring-red-500', 'focus-visible:outline-none');
        item.setAttribute('aria-roledescription', 'Draggable task');
        item.setAttribute('aria-describedby', 'board-help');
    }

    const heading = createElement('h3', `pr-8 text-sm font-medium ${isCompleted ? 'text-gray-400 line-through' : ''}`);
    heading.append(titleButton(task));
    item.append(createElement('span', `absolute inset-y-0 left-0 w-1 ${priority.accent}`), heading, cardMenu(task));

    if (task.description) {
        item.append(createElement('p', 'mt-1 text-xs whitespace-pre-line wrap-break-word text-gray-500', task.description));
    }

    const meta = createElement('div', 'mt-2 flex flex-wrap items-center gap-x-2 gap-y-1');

    if (task.category) {
        meta.append(projectChip(task.category));
    }

    meta.append(createDueButton(task), createBadge(priority.label, priority.classes));

    // Grouped by status the column already says it, so the badge would print it twice.
    if (!draggable) {
        meta.append(createBadge(STATUS_BADGES[task.status].label, STATUS_BADGES[task.status].classes));
    }

    item.append(meta);

    if (!isCompleted) {
        const actions = createElement('div', 'mt-3 flex items-center gap-2');
        actions.append(completeButton(task, true));
        item.append(actions);
    }

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

    // The rows have just been rebuilt, so anything ticked that is no longer drawn goes with them.
    listedIds = tasks.map((task) => task.id);
    selection.keepOnly(listedIds);
    syncSelection();
}

/**
 * Points the toolbar and the header checkbox at the rows on screen.
 *
 * Complete is disabled when every ticked row is already finished. The endpoint would answer that
 * nothing changed, and a toast reporting nothing is worse than a button that says so first.
 */
function syncSelection() {
    const ticked = selection.size;
    const tasks = latestTasks.filter((task) => selection.has(task.id));

    // The toolbar takes over the header row rather than sharing it, so checking a box never
    // stacks a second line under "Tasks" and grows the panel on a narrow width.
    elements.tasksHeading.classList.toggle('hidden', ticked > 0);
    setVisible(elements.selectionBar, ticked > 0);
    elements.selectionCount.textContent = ticked > 0 ? `${ticked} selected` : '';
    elements.selectionComplete.disabled = ! tasks.some((task) => task.status !== 'completed');
    syncHeaderCheckbox(elements.selectAll, listedIds, selection);
}

/**
 * One action across the ticked rows.
 *
 * The response, not the selection, is what Undo sends back: a task already in the state asked for
 * is skipped by the server, so the two lists are not always the same and reversing the selection
 * would reopen work nobody touched.
 */
function runBulk(button, { action, busyLabel, doneLabel, successMessage, undoMessage }) {
    const ids = selection.ids();
    let result = null;

    return runAction(button, {
        busyLabel,
        doneLabel,
        request: async () => {
            result = await api('/tasks/bulk', { method: 'POST', body: { action, ids } });
        },
        successMessage,
        description: () => countLabel(result.count),
        undo: () => api('/tasks/bulk', { method: 'POST', body: { action: result.undo, ids: result.ids } }),
        undoMessage,
    });
}

const countLabel = (count) => `${count} ${count === 1 ? 'task' : 'tasks'}`;

async function deleteSelection(button) {
    const count = selection.size;
    const confirmed = await confirmAction({
        title: 'Delete tasks',
        message: `Delete ${countLabel(count)}? You can undo this from the toast straight after.`,
    });

    if (! confirmed) {
        button.focus();

        return;
    }

    await runBulk(button, {
        action: 'delete',
        busyLabel: 'Deleting…',
        doneLabel: 'Deleted',
        successMessage: 'Tasks deleted',
        undoMessage: 'Tasks restored',
    });
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

// ---------------------------------------------------------------- projects

// Both dialogs type into the one list, so a project created a moment ago is offered next time.
function renderProjectOptions(categories) {
    elements.projectOptions.replaceChildren(
        ...categories.map((category) => {
            const option = createElement('option');
            option.value = category.name;

            return option;
        }),
    );
}

function currentParams() {
    const params = {
        ...VIEWS[state.view].params,
        sort: currentLayout() === 'board' ? 'manual' : state.listSort,
    };

    if (state.view === 'completed') {
        params.status = 'completed';
    }

    // The Display panel's settings belong to the board views, the only ones that show it.
    if (currentLayout() === 'board') {
        params.priority = display.state.priority;
        params.due = display.state.date || params.due;

        if (state.project !== null) {
            params.project = state.project;
        }

        if (!display.state.showCompleted) {
            params.completed = 0;
        }
    }

    if (currentLayout() !== 'board') {
        Object.assign(params, filterBar.state);
    }

    return params;
}

function hideSkeletons() {
    elements.skeleton.classList.add('hidden');
    elements.boardSkeleton.remove();
}

function showLoadError(error) {
    clearStats();
    hideSkeletons();
    elements.projectCrumb.hidden = true;
    elements.taskList.replaceChildren();
    clearBoard();
    elements.listMessage.classList.add('hidden');
    elements.listMessage.classList.remove('flex');
    elements.columnHeaders.classList.remove('md:grid');
    elements.loadErrorMessage.textContent =
        error instanceof ApiError && error.status === 429 ? RATE_LIMIT_MESSAGE : "Couldn't load tasks. Try again.";
    elements.loadError.classList.remove('hidden');
}

// A project's own board is grouped by stage; everywhere else the board follows the panel.
const boardGrouping = () => (state.project === null ? display.state.grouping : 'status');

function openProject({ key, name }) {
    state.project = key;
    state.projectName = name;
    load().then(() => {
        if (state.project === key && !elements.projectCrumb.hidden) {
            elements.projectBack.focus();
        }
    });
}

function closeProject() {
    const key = state.project;

    state.project = null;
    load().then(() => {
        if (state.project === null) {
            elements.boardView.querySelector(`[data-project="${key}"]`)?.focus();
        }
    });
}

async function load() {
    // Ignore responses from older requests if the view changed while they were in flight.
    const requestId = ++latestRequestId;
    const range = monthRange(state.month);

    try {
        const requests = [
            api('/tasks/stats'),
            api('/categories'),
        ];

        requests.push(
            currentLayout() === 'calendar'
                ? api('/tasks', { params: { ...currentParams(), ...range } })
                : api('/tasks', { params: currentParams() }),
        );

        if (currentLayout() === 'calendar') {
            requests.push(api('/tasks', { params: { ...currentParams(), due: 'none' } }));
        }

        const [statsResponse, categoriesResponse, tasksResponse, unscheduledResponse] =
            await Promise.all(requests);

        if (requestId !== latestRequestId) {
            return;
        }

        hideSkeletons();
        elements.projectCrumb.hidden = true;
        elements.loadError.classList.add('hidden');
        renderStats(statsResponse.data);
        renderProjectOptions(categoriesResponse.data);
        filterBar.setProjects(categoriesResponse.data);

        // Set before anything draws: renderList syncs the selection toolbar, which has to read the
        // list it is about to show rather than the one it is replacing.
        latestTasks = tasksResponse.data;

        if (currentLayout() === 'calendar') {
            elements.calendarMonth.textContent = monthLabel(state.month);
            renderMonthGrid(elements.calendarGrid, {
                tasks: tasksResponse.data,
                month: state.month,
                onOpen: rescheduleFromDialog,
                onReschedule: scheduleTask,
                onOpenDay: openDay,
            });
            renderAgenda(elements.calendarAgenda, {
                tasks: tasksResponse.data,
                onOpen: rescheduleFromDialog,
                onOpenDay: openDay,
                emptyText: filtersAreOn() ? FILTERED_EMPTY : undefined,
            });
            renderUnscheduled(elements.unscheduledList, {
                tasks: unscheduledResponse.data,
                onOpen: rescheduleFromDialog,
                onReschedule: scheduleTask,
            });
            dayDialog.refresh(tasksOn, dayEmptyText());
            elements.unscheduledCount.textContent = String(unscheduledResponse.data.length);
            elements.unscheduledEmpty.textContent = filtersAreOn() ? FILTERED_EMPTY : UNSCHEDULED_EMPTY;
            elements.unscheduledEmpty.classList.toggle('hidden', unscheduledResponse.data.length > 0);
        } else if (currentLayout() === 'board') {
            const showingProjects = display.state.grouping === 'project' && state.project === null;

            elements.projectCrumb.hidden = state.project === null;
            elements.projectCrumbName.textContent = state.projectName;

            if (showingProjects) {
                renderProjectGrid(elements.boardView, { tasks: tasksResponse.data, onOpen: openProject });
            } else {
                renderBoard({ tasks: tasksResponse.data, grouping: boardGrouping(), renderCard });
            }
        } else {
            renderList(
                tasksResponse.data,
                groupTasks({ tasks: tasksResponse.data, grouping: 'status' }),
            );
        }

        drawnIds = new Set(tasksResponse.data.map((task) => task.id));
        display.renderSummary(
            tasksResponse.data.length,
            state.project === null ? [] : [['Project: ', state.projectName]],
        );
    } catch (error) {
        if (requestId === latestRequestId) {
            showLoadError(error);
        }
    }
}

// ---------------------------------------------------------------- task actions

// How long a finished button stays on screen before the list replaces it. Short enough not to
// be in the way, long enough to read.
const DONE_HOLD = 700;

const hold = () => new Promise((resolve) => setTimeout(resolve, DONE_HOLD));

// Re-rendering destroys the button that was clicked, so focus has to be parked somewhere.
// The Tasks heading lives inside the list panel, which is hidden in the other two layouts.
// Focusing it there would drop focus onto the body.
const focusHeading = () => (currentLayout() === 'list' ? elements.tasksHeading : elements.viewTitle).focus();

async function runAction(button, { busyLabel, doneLabel, request, successMessage, description, restoreFocus, undo, undoMessage }) {
    const hadFocus = restoreFocus ?? document.activeElement === button;

    if (button) {
        setBusy(button, busyLabel);
    }

    try {
        await request();

        if (button && doneLabel) {
            setDone(button, doneLabel);
        }

        // A bulk action only knows what it changed once the server has answered, so a description
        // may be a function rather than a string. It is read here, after the request.
        const detail = typeof description === 'function' ? description() : description;

        toast.success(successMessage, {
            description: detail,
            action: undo
                ? { label: 'Undo', onClick: () => runAndReload(undo, { message: undoMessage, description: detail }) }
                : null,
        });

        // The reload is real work — several requests — so the finished state covers time that
        // was being spent anyway. The floor is there for when the server answers too quickly to
        // read it, not to make the app feel slower than it is.
        await Promise.all([load(), button && doneLabel ? hold() : null]);

        if (hadFocus) {
            focusHeading();
        }
    } catch (error) {
        toast.error(errorMessage(error));
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
 * toast goes through here, which is what the toasts' Undo buttons run.
 */
async function runAndReload(request, { message, description = '', undo = null } = {}) {
    try {
        await request();
        toast.success(message, {
            description,
            action: undo
                ? { label: 'Undo', onClick: () => runAndReload(undo, { message: 'Move undone', description }) }
                : null,
        });
        await load();
    } catch (error) {
        toast.error(errorMessage(error));
    }
}

const completeTask = (task, button) =>
    runAction(button, {
        busyLabel: 'Completing…',
        doneLabel: 'Completed',
        request: () => api(`/tasks/${task.id}/complete`, { method: 'PATCH' }),
        successMessage: 'Task completed',
        description: task.title,
        // Back to the stage it left, not always To do: an In review task would otherwise lose its place.
        undo: () => api(`/tasks/${task.id}/${STAGE_ENDPOINTS[task.status]}`, { method: 'PATCH' }),
        undoMessage: `Moved back to ${STATUS_BADGES[task.status].label}`,
    });

// Reopening from the row's menu. The same endpoint the dialog's Status field and a board drag use.
const reopenTask = (task) =>
    runAction(null, {
        request: () => api(`/tasks/${task.id}/reopen`, { method: 'PATCH' }),
        successMessage: 'Task reopened',
        description: task.title,
        undo: () => api(`/tasks/${task.id}/complete`, { method: 'PATCH' }),
        undoMessage: 'Task completed',
    });

async function deleteTask(task, button = null) {
    // The dialog steals focus, so record where it came from before opening. A menu item has no
    // button of its own to come back to: the menu is gone by now, so the heading takes it.
    const hadFocus = !button || document.activeElement === button;
    const confirmed = await confirmAction({
        title: 'Delete task',
        message: `Delete "${task.title}"? You can undo this from the toast straight after.`,
    });

    if (!confirmed) {
        if (button) {
            button.focus();
        } else {
            focusHeading();
        }

        return;
    }

    await runAction(button, {
        busyLabel: 'Deleting…',
        doneLabel: 'Deleted',
        request: () => api(`/tasks/${task.id}`, { method: 'DELETE' }),
        successMessage: 'Task deleted',
        description: task.title,
        restoreFocus: hadFocus,
        undo: () => api(`/tasks/${task.id}/restore`, { method: 'PATCH' }),
        undoMessage: 'Task restored',
    });
}

/**
 * Shared by dragging onto a day, dropping into the tray, and the reschedule dialog. `time` is
 * only ever passed by the dialog — a drag has no gesture for it — and only sent once a date is
 * on the task, the same rule the task dialog's Time field keeps.
 */
async function scheduleTask(taskId, date, time) {
    try {
        await api(`/tasks/${taskId}/schedule`, { method: 'PATCH', body: { due_date: date } });

        if (date && time !== undefined) {
            await api(`/tasks/${taskId}`, { method: 'PATCH', body: { due_time: time } });
        }

        // The date the task now carries, not the moment it was dragged: the new due date is the
        // thing worth reading back, and it is what a reschedule was for.
        toast.success(date ? 'Task rescheduled' : 'Due date cleared', {
            at: date ? parseDate(date) : new Date(),
        });
        await load();
    } catch (error) {
        toast.error(errorMessage(error));
    }
}

async function rescheduleFromDialog(task) {
    const result = await openScheduleDialog(task);

    if (result) {
        await scheduleTask(task.id, result.date, result.time);
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

    // A collapsed date field hides its own input behind a trigger, and no browser moves focus to
    // something that is not on screen. The trigger is what the user would reach for anyway.
    const invalid = scope.querySelector('[aria-invalid="true"]');
    const target = invalid?.hidden
        ? invalid.closest('[data-date-field]')?.querySelector('[data-date-trigger]')
        : invalid;

    target?.focus();
}

const TASK_FIELDS = ['title', 'description', 'priority', 'category_name', 'due_date', 'due_time'];

// Where focus goes on close when something other than the New task button opened the dialog.
let taskDialogReturnTarget = null;

// The form sits in a dialog so the list keeps the full width. <dialog> traps focus and closes
// on Escape by itself; what it does not do is clear a half-filled form, so closing does.
function openTaskDialog(returnTarget = null) {
    taskDialogReturnTarget = returnTarget;
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
        category_name: elements.categoryName.value.trim() || null,
        due_date: elements.dueDate.value || null,
        due_time: elements.dueTime.value || null,
    };

    // Both at once rather than one per submit: the server would report them together, and a form
    // that reveals its second missing field only after the first is filled is a form that nags.
    const missing = {};

    if (!payload.title) {
        missing.title = ['The title field is required.'];
    }

    if (!payload.due_date) {
        missing.due_date = ['The due date field is required.'];
    }

    if (Object.keys(missing).length > 0) {
        showFieldErrors(missing, elements.form);

        return;
    }

    setBusy(elements.submit, 'Saving…');

    try {
        await api('/tasks', { method: 'POST', body: payload });

        // The dialog waits for the button to finish saying so, then closes.
        setDone(elements.submit, 'Added');
        await hold();
        closeTaskDialog();
        toast.success('Task added', { description: payload.title });
        await load();
    } catch (error) {
        if (error instanceof ApiError && Object.keys(error.errors).length > 0) {
            showFieldErrors(error.errors, elements.form);
        } else {
            toast.error(errorMessage(error));
        }
    } finally {
        clearBusy(elements.submit);
    }
}

// ---------------------------------------------------------------- navigation

function applyView() {
    const view = VIEWS[state.view];

    elements.viewTitle.textContent = view.title;
    elements.viewSubtitle.textContent = view.subtitle;

    viewButtons.forEach((button) => {
        button.setAttribute('aria-pressed', String(button.dataset.view === state.view));
    });

    // Display and New task belong to the board. The other views have nothing to add a task to
    // and carry their own filter bar.
    const onBoard = currentLayout() === 'board';

    elements.boardControls.hidden = !onBoard;
    elements.displaySummary.hidden = !onBoard;
    filterBar.setShown(!onBoard);
}

function setView(key) {
    state.project = null;
    elements.projectCrumb.hidden = true;
    state.view = key;
    filterBar.reset();
    applyView();
    applyLayout();
    syncSortHeaders(sortHeaders, state.listSort);
    closeDrawer();
    load();
}

/**
 * Every view decides its own layout: All tasks and Today are the board, Upcoming is the calendar,
 * and Overdue and Completed are the table, the one layout with checkboxes.
 */
const LAYOUTS = { all: 'board', today: 'board', upcoming: 'calendar', overdue: 'list', completed: 'list' };

const currentLayout = () => LAYOUTS[state.view];

// `md:grid` and `xl:grid` sit in media queries and would win over `hidden`, so each layout's
// grid class is added only while that layout is open.
function applyLayout() {
    const layout = currentLayout();

    // Only the list has checkboxes. Leaving it with a selection still held would bring the toolbar
    // back on return, counting rows the user ticked before they went somewhere else.
    if (layout !== 'list') {
        selection.clear();
    }

    elements.listView.classList.toggle('hidden', layout !== 'list');
    elements.boardView.classList.toggle('hidden', layout !== 'board');
    elements.boardView.classList.toggle('md:grid', layout === 'board');
    elements.calendarView.classList.toggle('hidden', layout !== 'calendar');
    elements.calendarView.classList.toggle('xl:grid', layout === 'calendar');
}

function applyDisplay({ date }) {
    if (display.state.grouping !== 'project') {
        state.project = null;
    }

    // Today, Upcoming and Overdue are date filters already. Choosing a Date in the panel takes
    // over, rather than leaving two filters to fight while the heading still names the view that
    // lost.
    if (date && VIEWS[state.view].params.due) {
        state.view = 'all';
        state.project = null;
        elements.projectCrumb.hidden = true;
        applyView();
    }

    applyLayout();
    syncSortHeaders(sortHeaders, state.listSort);
    load();
}

function shiftMonth(offset) {
    state.month = new Date(state.month.getFullYear(), state.month.getMonth() + offset, 1);
    load();
}

const STAGE_ENDPOINTS = { pending: 'reopen', in_progress: 'start', in_review: 'review', completed: 'complete' };

/**
 * The task dialog's Status field. It knows the task's stage first-hand, and it has no column to
 * place the task in, so it keeps the four stage endpoints.
 */
function moveTask(id, status, from) {
    if (!from || from === status || !STAGE_ENDPOINTS[status]) {
        return;
    }

    const undo = STAGE_ENDPOINTS[from];

    return runAndReload(
        () => api(`/tasks/${id}/${STAGE_ENDPOINTS[status]}`, { method: 'PATCH' }),
        {
            message: `Moved to ${STATUS_BADGES[status].label}`,
            undo: () => api(`/tasks/${id}/${undo}`, { method: 'PATCH' }),
        },
    );
}

/**
 * A drop on the board, and the arrow keys that do the same thing. One request carries both halves
 * of the move, because a card dropped between two others says which column and where in it.
 *
 * Undo names the task this one used to follow, so taking a move back puts the card exactly where
 * it was rather than at the end of the column it came from.
 */
function reorderTask(id, status, after) {
    const task = latestTasks.find((item) => item.id === id);

    if (!task || (task.status === status && after === previousInColumn(task))) {
        return;
    }

    const wasAfter = previousInColumn(task);
    const message = task.status === status ? 'Task moved' : `Moved to ${STATUS_BADGES[status].label}`;
    const reorder = (body) => api(`/tasks/${id}/reorder`, { method: 'PATCH', body });

    return runAndReload(
        () => reorder({ status, after }),
        {
            message,
            description: task.title,
            undo: () => reorder({ status: task.status, after: wasAfter }),
        },
    );
}

/** The task a card currently follows in its own column, or null when it is already at the top. */
function previousInColumn(task) {
    const column = latestTasks.filter((item) => item.status === task.status);
    const index = column.findIndex((item) => item.id === task.id);

    return index > 0 ? column[index - 1].id : null;
}

function showTaskDetail(id, trigger, ids) {
    openTaskDetail(id, trigger, {
        onStage: moveTask,
        order: () => ids ?? latestTasks.map((task) => task.id),
        onChange: load,
        onDelete: (task) => deleteTask(task, null),
    });
}

const tasksOn = (iso) => latestTasks.filter((task) => task.due_date === iso);
const dayDialog = createDayDialog({ onOpenTask: showTaskDetail, onColorChange: () => load() });
const dayEmptyText = () => (filtersAreOn() ? FILTERED_EMPTY : undefined);
const openDay = (iso) => dayDialog.open(iso, tasksOn(iso), dayEmptyText());

const display = createDisplay(applyDisplay);
const filterBar = createFilterBar(() => load());

// ---------------------------------------------------------------- wiring

elements.form.addEventListener('submit', createTask);
elements.newTaskTrigger.addEventListener('click', () => openTaskDialog());
elements.taskCancel.addEventListener('click', closeTaskDialog);
// Escape and the backdrop close the dialog without going through the Cancel button, so the
// form is cleared here too rather than in each handler.
elements.taskDialog.addEventListener('close', () => {
    elements.form.reset();
    // A reset fires no change event, and the date's trigger would go on naming the day that was
    // just cleared. The datepicker keeps itself in step with this field, so tell it.
    elements.dueDate.dispatchEvent(new Event('change', { bubbles: true }));
    clearFieldErrors(TASK_FIELDS);
    const target = taskDialogReturnTarget?.isConnected && taskDialogReturnTarget.offsetParent !== null
        ? taskDialogReturnTarget
        : elements.newTaskTrigger;

    taskDialogReturnTarget = null;
    target.focus();
});
elements.taskDialog.addEventListener('click', (event) => {
    if (event.target === elements.taskDialog) {
        elements.taskDialog.close();
    }
});
elements.retry.addEventListener('click', load);
elements.projectBack.addEventListener('click', closeProject);

elements.selectAll.addEventListener('change', () =>
    selection.setMany(listedIds, elements.selectAll.checked),
);
elements.selectionClear.addEventListener('click', () => {
    selection.clear();
    elements.selectAll.focus();
});
elements.selectionComplete.addEventListener('click', () =>
    runBulk(elements.selectionComplete, {
        action: 'complete',
        busyLabel: 'Completing…',
        doneLabel: 'Completed',
        successMessage: 'Tasks completed',
        undoMessage: 'Tasks reopened',
    }),
);
elements.selectionDelete.addEventListener('click', () => deleteSelection(elements.selectionDelete));

// The table's own headers are its only sort control. A header selects an order rather than
// flipping one, so pressing the active one again does nothing.
sortHeaders.forEach((button) =>
    button.addEventListener('click', () => {
        if (state.listSort === button.dataset.sort) {
            return;
        }

        state.listSort = button.dataset.sort;
        syncSortHeaders(sortHeaders, state.listSort);
        load();
    }),
);

viewButtons.forEach((button) => button.addEventListener('click', () => setView(button.dataset.view)));

$('calendar-prev').addEventListener('click', () => shiftMonth(-1));
$('calendar-next').addEventListener('click', () => shiftMonth(1));
$('calendar-today').addEventListener('click', () => {
    state.month = new Date(today.getFullYear(), today.getMonth(), 1);
    load();
});

wireTaskDetail();
wireBoardDragging(reorderTask, (id) => showTaskDetail(id));
enhanceDateFields();

// The view's layout table owns the layout, and the panel's first change is what applies it, so the
// first render is that change rather than a separate load() repeating what applyDisplay does.
display.apply();

