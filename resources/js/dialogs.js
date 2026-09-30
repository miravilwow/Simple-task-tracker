import { clearBusy, createElement, createIcon, setBusy } from './dom.js';

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

const commentsDialog = document.getElementById('comments-dialog');
const commentsProject = document.getElementById('comments-project');
const commentsList = document.getElementById('comments-list');
const commentsMessage = document.getElementById('comments-message');
const commentsClose = document.getElementById('comments-close');
const commentForm = document.getElementById('comment-form');
const commentBody = document.getElementById('comment-body');
const commentSubmit = document.getElementById('comment-submit');

const moveDialog = document.getElementById('move-dialog');
const moveProjectName = document.getElementById('move-project');
const moveParent = document.getElementById('move-parent');
const moveSave = document.getElementById('move-save');
const moveCancel = document.getElementById('move-cancel');

const commentFormatter = new Intl.DateTimeFormat('en-US', { dateStyle: 'medium', timeStyle: 'short' });

function setCommentsMessage(text) {
    commentsMessage.textContent = text ?? '';
    commentsMessage.classList.toggle('hidden', !text);
}

function commentEntry(comment, onRemove) {
    const item = createElement('li', 'flex gap-3 border-b border-gray-100 py-3 last:border-0');
    const body = createElement('div', 'min-w-0 flex-1');

    // Comment text is user input, so it only ever arrives through textContent.
    body.append(
        createElement('p', 'text-sm wrap-break-word whitespace-pre-line text-gray-700', comment.body),
        createElement('p', 'mt-0.5 text-xs text-gray-500', commentFormatter.format(new Date(comment.created_at))),
    );

    const remove = createElement(
        'button',
        'inline-flex size-8 shrink-0 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none',
    );
    remove.type = 'button';
    remove.append(createIcon('trash', 'size-4'), createElement('span', 'sr-only', 'Delete comment'));
    remove.addEventListener('click', () => onRemove(comment));

    item.append(body, remove);

    return item;
}

/**
 * A project's comment thread: read, add and delete, all without leaving the dialog.
 *
 * The three requests are passed in rather than made here, because the API wrapper lives in
 * app.js and this file only knows about dialogs.
 *
 * @param {string} projectName
 * @param {{load: () => Promise<Array<object>>, add: (body: string) => Promise<object>, remove: (comment: object) => Promise<object>}} actions
 */
export function openCommentsDialog(projectName, actions) {
    commentsProject.textContent = projectName;
    commentsList.replaceChildren();
    commentForm.reset();
    commentBody.removeAttribute('aria-invalid');
    document.getElementById('comment-body-error').classList.add('hidden');
    setCommentsMessage('Loading…');

    return openDialog(commentsDialog, ({ on, finish }) => {
        const refresh = async () => {
            try {
                const comments = await actions.load();

                // The dialog may have been dismissed while the request was still out.
                if (!commentsDialog.open) {
                    return;
                }

                commentsList.replaceChildren(
                    ...comments.map((comment) =>
                        commentEntry(comment, async (one) => {
                            await actions.remove(one);
                            await refresh();
                        }),
                    ),
                );
                setCommentsMessage(comments.length === 0 ? 'No comments yet.' : null);
            } catch (error) {
                setCommentsMessage(error.message || 'Comments could not be loaded.');
            }
        };

        on(commentsClose, 'click', () => finish(null));
        on(commentForm, 'submit', async (event) => {
            event.preventDefault();

            if (commentSubmit.disabled) {
                return;
            }

            const error = document.getElementById('comment-body-error');

            error.classList.add('hidden');
            commentBody.removeAttribute('aria-invalid');
            setBusy(commentSubmit, 'Posting…');

            try {
                await actions.add(commentBody.value);
                commentForm.reset();
                await refresh();
                commentBody.focus();
            } catch (failure) {
                error.textContent = failure.errors?.body?.[0] ?? failure.message ?? 'Something went wrong.';
                error.classList.remove('hidden');
                commentBody.setAttribute('aria-invalid', 'true');
                commentBody.focus();
            } finally {
                clearBusy(commentSubmit);
            }
        });

        refresh();
    });
}

/**
 * Resolves to { value: id | null } when moved, or null when dismissed. The wrapper object is
 * what lets "move to the top level" be a real answer rather than a dismissal.
 *
 * @param {object} project
 * @param {(select: HTMLSelectElement) => void} fillOptions
 */
export function openMoveDialog(project, fillOptions) {
    moveProjectName.textContent = project.name;
    fillOptions(moveParent);
    moveParent.value = project.parent_id ? String(project.parent_id) : '';

    return openDialog(moveDialog, ({ on, finish }) => {
        on(moveSave, 'click', () => finish({ value: moveParent.value ? Number(moveParent.value) : null }));
        on(moveCancel, 'click', () => finish(null));
    });
}
