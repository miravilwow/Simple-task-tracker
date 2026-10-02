/**
 * The filter bar on Upcoming, Overdue and Completed, the views without a Display panel. It owns
 * its three fields and nothing else; every change calls back and the page reloads.
 */
const EMPTY = { search: '', priority: '', project: '' };

// Long enough that typing a word is one request rather than one per letter.
const SEARCH_DELAY = 300;

const $ = (id) => document.getElementById(id);

export function createFilterBar(onChange) {
    const state = { ...EMPTY };
    const bar = $('filter-bar');
    const clear = $('filter-clear');
    const fields = { search: $('filter-search'), priority: $('filter-bar-priority'), project: $('filter-bar-project') };
    const fixedProjects = [...fields.project.options];
    let timer = null;

    function changed() {
        clear.hidden = Object.values(state).every((value) => value === '');
        onChange(state);
    }

    fields.search.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = setTimeout(() => {
            state.search = fields.search.value.trim();
            changed();
        }, SEARCH_DELAY);
    });

    ['priority', 'project'].forEach((key) =>
        fields[key].addEventListener('change', () => {
            state[key] = fields[key].value;
            changed();
        }),
    );

    function reset() {
        window.clearTimeout(timer);
        Object.assign(state, EMPTY);
        Object.entries(fields).forEach(([key, field]) => {
            field.value = EMPTY[key];
        });
        clear.hidden = true;
    }

    clear.addEventListener('click', () => {
        reset();
        changed();
        fields.search.focus();
    });

    function setShown(shown) {
        bar.hidden = !shown;
    }

    /**
     * Refilled after every load from the projects that still have tasks. When the chosen one is
     * no longer among them the select would read "All projects" over a list still narrowed to it,
     * so the filter is dropped and the list reloads to match what the select says.
     */
    function setProjects(categories) {
        const chosen = fields.project.value;

        fields.project.replaceChildren(
            ...fixedProjects,
            ...categories.map((category) =>
                Object.assign(document.createElement('option'), { value: String(category.id), textContent: category.name }),
            ),
        );
        fields.project.value = chosen;

        if (fields.project.value !== chosen) {
            state.project = '';
            fields.project.value = '';
            changed();
        }
    }

    return { state, reset, setShown, setProjects };
}
