/**
 * The Display panel on All tasks and Today: completed tasks, grouping and the two filters.
 *
 * It owns the settings and nothing else. Every change calls back with the new state, and the
 * page decides what to re-render.
 */
const DEFAULTS = {
    showCompleted: true,
    grouping: 'status',
    date: '',
    priority: '',
};

// To do, In progress, In review, Done: the order the work moves in, and the order the board's
// columns and the list's groups both read.
export const GROUPINGS = {
    status: { label: 'Status', keys: ['pending', 'in_progress', 'in_review', 'completed'] },
    priority: { label: 'Priority', keys: ['high', 'medium', 'low'] },
    project: { label: 'Project', keys: null },
};

const LABELS = {
    status: { pending: 'To do', in_progress: 'In progress', in_review: 'In review', completed: 'Done' },
    priority: { high: 'High', medium: 'Medium', low: 'Low' },
};

/**
 * Splits a list into the groups asked for. One function for the board and the list, so a board
 * column and a list group always hold the same tasks under the same heading.
 *
 * @param {{tasks: array, grouping: string, categories: array}} options
 */
export function groupTasks({ tasks, grouping, categories }) {
    if (grouping === 'project') {
        return [
            ...categories.map((category) => ({
                key: `project-${category.id}`,
                label: category.name,
                tasks: tasks.filter((task) => task.category?.id === category.id),
            })),
            { key: 'project-none', label: 'No project', tasks: tasks.filter((task) => !task.category) },
        ];
    }

    return GROUPINGS[grouping].keys.map((key) => ({
        key,
        label: LABELS[grouping][key],
        tasks: tasks.filter((task) => task[grouping] === key),
    }));
}

const DATES = { overdue: 'Overdue', today: 'Today', upcoming: 'Upcoming', none: 'No date' };
const PRIORITIES = { high: 'High', medium: 'Medium', low: 'Low' };

const $ = (id) => document.getElementById(id);

export function createDisplay(onChange) {
    const state = { ...DEFAULTS };

    const trigger = $('display-trigger');
    const panel = $('display-panel');
    const summary = $('display-summary');
    const completed = $('show-completed');
    const selects = { grouping: $('grouping'), date: $('filter-date'), priority: $('filter-priority') };

    const setOpen = (open) => {
        panel.hidden = !open;
        trigger.setAttribute('aria-expanded', String(open));
    };

    function chip(text, value) {
        const element = document.createElement('span');

        element.className = 'display-chip';
        element.append(text, Object.assign(document.createElement('b'), { textContent: value }));

        return element;
    }

    // A panel that hides its own settings is how someone ends up staring at an empty board.
    function renderSummary(count, extra = []) {
        const chips = [chip('Showing ', String(count)), chip('Grouped by ', GROUPINGS[state.grouping].label)];

        if (state.date) {
            chips.push(chip('Date: ', DATES[state.date]));
        }

        if (state.priority) {
            chips.push(chip('Priority: ', PRIORITIES[state.priority]));
        }

        if (!state.showCompleted) {
            chips.push(chip('Completed ', 'hidden'));
        }

        extra.forEach(([text, value]) => chips.push(chip(text, value)));
        summary.replaceChildren(...chips);
    }

    function apply() {
        completed.setAttribute('aria-checked', String(state.showCompleted));
        onChange(state);
    }

    trigger.addEventListener('click', () => setOpen(panel.hidden));

    document.addEventListener('click', (event) => {
        if (!panel.hidden && !panel.contains(event.target) && !trigger.contains(event.target)) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            setOpen(false);
            trigger.focus();
        }
    });

    panel.querySelectorAll('[data-section]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const open = toggle.getAttribute('aria-expanded') === 'false';

            toggle.setAttribute('aria-expanded', String(open));
            $(toggle.dataset.section).classList.toggle('hidden', !open);
        });
    });

    completed.addEventListener('click', () => {
        state.showCompleted = !state.showCompleted;
        apply();
    });

    Object.entries(selects).forEach(([key, select]) =>
        select.addEventListener('change', () => {
            state[key] = select.value;
            apply();
        }),
    );

    $('display-reset').addEventListener('click', () => {
        Object.assign(state, DEFAULTS);
        Object.entries(selects).forEach(([key, select]) => {
            select.value = DEFAULTS[key];
        });
        apply();
    });

    return { state, renderSummary, apply };
}
