/**
 * The task table's selection.
 *
 * The design follows shadcn/ui's Data Table (MIT) — a checkbox column, a toolbar that takes over
 * the panel header once something is selected, and headers that sort. None of its code carries
 * over: it is React over TanStack Table, which is a state machine for a table that renders itself,
 * and this app renders its own rows. What is ported is the shape, the same way the sidebar and the
 * toast were.
 *
 * Two of its parts are deliberately left out. Column visibility and faceted filters are already the
 * Display panel here, and a second control for either is how the two quietly come to disagree.
 */
import { createElement } from './dom.js';

/**
 * The ids the user has ticked. It is a Set rather than a list because every question asked of it is
 * "is this one in", which a list answers by walking itself once per row drawn.
 *
 * @param {() => void} onChange runs whenever the selection actually changed
 */
export function createSelection(onChange) {
    const chosen = new Set();

    const after = (before) => {
        if (chosen.size !== before) {
            onChange();
        }
    };

    return {
        get size() {
            return chosen.size;
        },
        ids: () => [...chosen],
        has: (id) => chosen.has(id),

        toggle(id, on) {
            const before = chosen.size;
            on ? chosen.add(id) : chosen.delete(id);
            after(before);
        },

        setMany(ids, on) {
            const before = chosen.size;

            for (const id of ids) {
                on ? chosen.add(id) : chosen.delete(id);
            }

            after(before);
        },

        clear() {
            const before = chosen.size;
            chosen.clear();
            after(before);
        },

        /**
         * Drops whatever is no longer on screen. The list is rebuilt after every action, and the
         * rows that were just completed may have left the view with it. A toolbar still counting
         * them would act on work the user can no longer check.
         */
        keepOnly(ids) {
            const allowed = new Set(ids);
            const before = chosen.size;

            for (const id of chosen) {
                if (! allowed.has(id)) {
                    chosen.delete(id);
                }
            }

            after(before);
        },
    };
}

/** The checkbox on one row. Its label names the task, so a screen reader says which one. */
export function rowCheckbox(task, selection) {
    const box = createElement('input', 'row-check');

    box.type = 'checkbox';
    box.checked = selection.has(task.id);
    box.setAttribute('aria-label', `Select ${task.title}`);
    box.addEventListener('change', () => selection.toggle(task.id, box.checked));

    return box;
}

/**
 * Points the header checkbox at the rows currently drawn.
 *
 * `indeterminate` is a property of the element rather than an attribute, and the browser announces
 * it as "mixed" on its own. A button with `role="checkbox"` would have left us describing the
 * half-checked state by hand, which is the reason this is a real input.
 */
export function syncHeaderCheckbox(box, ids, selection) {
    const picked = ids.filter((id) => selection.has(id)).length;

    box.checked = ids.length > 0 && picked === ids.length;
    box.indeterminate = picked > 0 && picked < ids.length;
    box.disabled = ids.length === 0;
    box.setAttribute('aria-label', box.checked ? 'Clear the selection' : 'Select every task shown');
}

/**
 * Marks which column header the current sort belongs to.
 *
 * These sorts have no direction — the exam's own sorter is priority then oldest first, Name is A to
 * Z and Due date is soonest first — so a header selects a sort rather than toggling one, and it
 * carries `aria-pressed` rather than `aria-sort`. Saying ascending where nothing can descend would
 * be a control promising something the API does not have.
 */
export function syncSortHeaders(buttons, sorting) {
    for (const button of buttons) {
        button.setAttribute('aria-pressed', String(button.dataset.sort === sorting));
    }
}
