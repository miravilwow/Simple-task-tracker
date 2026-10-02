/**
 * The task table's sortable column headers.
 *
 * None of its code is carried over: it is React over TanStack Table, which is a state machine for
 * a table that renders itself, and this app renders its own rows. What is ported is the shape, the
 * same way the sidebar and the toast were.
 */

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
