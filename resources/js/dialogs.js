const confirmDialog = document.getElementById('confirm-dialog');
const confirmTitle = document.getElementById('confirm-title');
const confirmMessage = document.getElementById('confirm-message');
const confirmAccept = document.getElementById('confirm-accept');
const confirmCancel = document.getElementById('confirm-cancel');

const scheduleDialog = document.getElementById('schedule-dialog');
const scheduleTitle = document.getElementById('schedule-task-title');
const scheduleDate = document.getElementById('schedule-date');
const scheduleTime = document.getElementById('schedule-time');
const scheduleSave = document.getElementById('schedule-save');
const scheduleClear = document.getElementById('schedule-clear');
const scheduleCancel = document.getElementById('schedule-cancel');

/** A time has nothing to sit on without a day, the same rule the task dialog's Time field keeps. */
function syncScheduleTime() {
    scheduleTime.disabled = ! scheduleDate.value;

    if (scheduleTime.disabled) {
        scheduleTime.value = '';
    }
}

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
 * Resolves to { date: 'YYYY-MM-DD' | null, time: 'HH:mm' | null } when saved, or null when
 * dismissed. Dragging is pointer-only, so this is the path keyboard and touch users take.
 */
export function openScheduleDialog(task) {
    scheduleTitle.textContent = task.title;
    scheduleDate.value = task.due_date ?? '';
    // Setting .value fires nothing, and the inline month grid follows the field's change event.
    scheduleDate.dispatchEvent(new Event('change', { bubbles: true }));
    scheduleTime.value = task.due_time ?? '';
    syncScheduleTime();

    return openDialog(scheduleDialog, ({ on, finish }) => {
        on(scheduleDate, 'change', syncScheduleTime);
        on(scheduleSave, 'click', () => finish({ date: scheduleDate.value || null, time: scheduleTime.value || null }));
        on(scheduleClear, 'click', () => finish({ date: null, time: null }));
        on(scheduleCancel, 'click', () => finish(null));
    });
}
