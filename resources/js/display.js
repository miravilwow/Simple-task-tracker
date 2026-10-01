/**
 * The Display panel: layout, completed tasks, grouping, sorting and the two filters.
 *
 * It owns the settings and nothing else. Every change calls back with the new state, and the
 * page decides what to re-render.
 */
const DEFAULTS = {
    mode: 'list',
    showCompleted: true,
    grouping: 'status',
    sorting: 'default',
    date: '',
    priority: '',
};

export const GROUPINGS = {
    status: { label: 'Status', keys: ['in_progress', 'pending', 'completed'] },
    priority: { label: 'Priority', keys: ['high', 'medium', 'low'] },
    project: { label: 'Project', keys: null },
    none: { label: 'None', keys: null },
};

const SORTINGS = { default: 'Default', due: 'Due date', name: 'Name' };
const DATES = { overdue: 'Overdue', today: 'Today', upcoming: 'Upcoming', none: 'No date' };
const PRIORITIES = { high: 'High', medium: 'Medium', low: 'Low' };

const $ = (id) => document.getElementById(id);

export function createDisplay(onChange) {
    const state = { ...DEFAULTS };

    const trigger = $('display-trigger');
    const panel = $('display-panel');
    const summary = $('display-summary');
    const grouping = $('grouping');
    const completed = $('show-completed');
    const modeButtons = document.querySelectorAll('[data-mode]');
    const selects = { grouping, sorting: $('sorting'), date: $('filter-date'), priority: $('filter-priority') };

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

    // A panel that hides its own settings is how someone ends up staring at an empty list.
    function renderSummary(count) {
        const chips = [chip('Showing ', String(count))];

        if (state.grouping !== 'none') {
            chips.push(chip('Grouped by ', GROUPINGS[state.grouping].label));
        }

        if (state.sorting !== 'default') {
            chips.push(chip('Sorted by ', SORTINGS[state.sorting]));
        }

        if (state.date) {
            chips.push(chip('Date: ', DATES[state.date]));
        }

        if (state.priority) {
            chips.push(chip('Priority: ', PRIORITIES[state.priority]));
        }

        if (!state.showCompleted) {
            chips.push(chip('Completed ', 'hidden'));
        }

        summary.replaceChildren(...chips);
    }

    // A board with nothing to group by is a list, so None is withdrawn rather than ignored.
    function syncGrouping() {
        grouping.querySelector('option[value="none"]').disabled = state.mode === 'board';

        if (state.mode === 'board' && state.grouping === 'none') {
            state.grouping = 'status';
            grouping.value = 'status';
        }
    }

    function apply() {
        syncGrouping();
        modeButtons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.mode === state.mode)));
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

    modeButtons.forEach((button) =>
        button.addEventListener('click', () => {
            state.mode = button.dataset.mode;
            apply();
        }),
    );

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
