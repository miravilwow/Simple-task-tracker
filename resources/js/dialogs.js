const confirmDialog = document.getElementById('confirm-dialog');
const confirmTitle = document.getElementById('confirm-title');
const confirmMessage = document.getElementById('confirm-message');
const confirmAccept = document.getElementById('confirm-accept');
const confirmCancel = document.getElementById('confirm-cancel');

const scheduleDialog = document.getElementById('schedule-dialog');
const scheduleTitle = document.getElementById('schedule-task-title');
const scheduleDate = document.getElementById('schedule-date');
const scheduleSave = document.getElementById('schedule-save');
const scheduleClear = document.getElementById('schedule-clear');
const scheduleCancel = document.getElementById('schedule-cancel');

/**
 * A real dialog can be styled, traps focus, and closes on Escape, which window.confirm cannot.
 * One AbortController removes every listener the moment any of them settles the promise.
 */
function openDialog(dialog, wire) {
    return new Promise((resolve) => {
        const controller = new AbortController();
        const finish = (result) => {
            controller.abort();

            if (dialog.open) {
                dialog.close();
            }

            resolve(result);
        };

        const on = (element, event, handler) =>
            element.addEventListener(event, handler, { signal: controller.signal });

        wire({ on, finish });
        on(dialog, 'close', () => finish(null));
        dialog.showModal();
    });
}

export function confirmAction({ title, message, confirmLabel = 'Delete' }) {
    confirmTitle.textContent = title;
    confirmMessage.textContent = message;
    confirmAccept.textContent = confirmLabel;

    return openDialog(confirmDialog, ({ on, finish }) => {
        on(confirmAccept, 'click', () => finish(true));
        on(confirmCancel, 'click', () => finish(false));
    });
}

/**
 * Resolves to { date: 'YYYY-MM-DD' | null } when saved, or null when dismissed.
 * Dragging is pointer-only, so this is the path keyboard and touch users take.
 */
export function openScheduleDialog(task) {
    scheduleTitle.textContent = task.title;
    scheduleDate.value = task.due_date ?? '';
    // Setting .value fires nothing, and the inline month grid follows the field's change event.
    scheduleDate.dispatchEvent(new Event('change', { bubbles: true }));

    return openDialog(scheduleDialog, ({ on, finish }) => {
        on(scheduleSave, 'click', () => finish({ date: scheduleDate.value || null }));
        on(scheduleClear, 'click', () => finish({ date: null }));
        on(scheduleCancel, 'click', () => finish(null));
    });
}
