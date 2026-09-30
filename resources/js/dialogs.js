import { createElement, createIcon } from './dom.js';

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

const activityDialog = document.getElementById('activity-dialog');
const activityProject = document.getElementById('activity-project');
const activityList = document.getElementById('activity-list');
const activityMessage = document.getElementById('activity-message');
const activityClose = document.getElementById('activity-close');

// A full timestamp carries its own offset, so Date parses it in local time. The parseDate rule
// is about date-only strings, which do not.
const activityFormatter = new Intl.DateTimeFormat('en-US', { dateStyle: 'medium', timeStyle: 'short' });

function activityEntry(entry) {
    const item = createElement('li', 'flex gap-3 border-b border-gray-100 py-3 last:border-0');
    const body = createElement('div', 'min-w-0 flex-1');

    const line = createElement('p', 'text-sm wrap-break-word text-gray-700');
    line.append(
        createElement('span', 'font-medium text-gray-900', entry.label),
        // Task text is user input, so it only ever arrives through textContent.
        document.createTextNode(' '),
        createElement('span', '', entry.task_title),
    );

    body.append(line, createElement('p', 'mt-0.5 text-xs text-gray-500', activityFormatter.format(new Date(entry.created_at))));
    item.append(createIcon('clock', 'mt-0.5 size-4 shrink-0 text-gray-400'), body);

    return item;
}

function setActivityMessage(text) {
    activityMessage.textContent = text ?? '';
    activityMessage.classList.toggle('hidden', !text);
}

/**
 * A read-only panel rather than a question, so it resolves to nothing: it is opened, read and
 * dismissed. The entries are fetched by the caller, which is where the API wrapper lives.
 *
 * @param {string} projectName
 * @param {() => Promise<Array<object>>} loadEntries
 */
export function openActivityDialog(projectName, loadEntries) {
    activityProject.textContent = projectName;
    activityList.replaceChildren();
    activityList.setAttribute('aria-busy', 'true');
    setActivityMessage('Loading…');

    return openDialog(activityDialog, async ({ on, finish }) => {
        on(activityClose, 'click', () => finish(null));

        try {
            const entries = await loadEntries();

            // The dialog may have been dismissed while the request was still out.
            if (!activityDialog.open) {
                return;
            }

            activityList.replaceChildren(...entries.map(activityEntry));
            setActivityMessage(entries.length === 0 ? 'Nothing has happened in this project yet.' : null);
        } catch (error) {
            setActivityMessage(error.message || 'Activity could not be loaded.');
        } finally {
            activityList.setAttribute('aria-busy', 'false');
        }
    });
}
