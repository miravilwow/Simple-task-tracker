const confirmDialog = document.getElementById('confirm-dialog');
const confirmIconWrap = document.getElementById('confirm-icon-wrap');
const confirmIcon = document.getElementById('confirm-icon');
const confirmTitle = document.getElementById('confirm-title');
const confirmMessage = document.getElementById('confirm-message');
const confirmAccept = document.getElementById('confirm-accept');
const confirmCancel = document.getElementById('confirm-cancel');

// Delete is destructive (red); completing a task is not, so it borrows the Done/Complete green
// instead of the warning red, the same distinction the rest of the app's palette already draws.
const CONFIRM_TONES = {
    danger: {
        iconWrap: 'bg-red-100 text-red-600',
        iconPath: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
        accept: 'bg-red-600 hover:bg-red-700 focus-visible:ring-red-500',
    },
    success: {
        iconWrap: 'bg-green-100 text-green-700',
        iconPath: 'm4.5 12.75 6 6 9-13.5',
        accept: 'bg-green-700 hover:bg-green-800 focus-visible:ring-green-600',
    },
};

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

export function confirmAction({ title, message, confirmLabel = 'Delete', tone = 'danger' }) {
    const { iconWrap, iconPath, accept } = CONFIRM_TONES[tone];

    confirmTitle.textContent = title;
    confirmMessage.textContent = message;
    confirmAccept.textContent = confirmLabel;
    confirmIconWrap.className = `flex size-11 shrink-0 items-center justify-center rounded-full ${iconWrap}`;
    confirmIcon.querySelector('path').setAttribute('d', iconPath);
    confirmAccept.className = `inline-flex min-h-10 items-center justify-center rounded-lg px-4 text-sm font-medium text-white transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none ${accept}`;

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
