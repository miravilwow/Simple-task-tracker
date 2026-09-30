import { clearBusy, createElement, createIcon, setBusy, setVisible, shortDate } from './dom.js';
import { openMenu } from './menu.js';

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

const panelDialog = document.getElementById('project-panel');
const panelProject = document.getElementById('panel-project');
const panelClose = document.getElementById('panel-close');
const panelTabs = [...document.querySelectorAll('[data-panel-tab]')];
const panels = {
    comments: document.getElementById('panel-comments'),
    activity: document.getElementById('panel-activity'),
};

const commentsList = document.getElementById('comments-list');
const commentsEmpty = document.getElementById('comments-empty');
const commentsMessage = document.getElementById('comments-message');
const commentForm = document.getElementById('comment-form');
const commentBody = document.getElementById('comment-body');
const commentError = document.getElementById('comment-body-error');
const commentSubmit = document.getElementById('comment-submit');
const commentEmoji = document.getElementById('comment-emoji');
const commentEmojiRow = document.getElementById('comment-emoji-row');

const activityList = document.getElementById('activity-list');
const activityMessage = document.getElementById('activity-message');

const moveDialog = document.getElementById('move-dialog');
const moveProjectName = document.getElementById('move-project');
const moveParent = document.getElementById('move-parent');
const moveSave = document.getElementById('move-save');
const moveCancel = document.getElementById('move-cancel');

// The set is read back out of the markup the enum rendered, rather than listed again here, so
// the picker and App\Enums\Reaction cannot drift apart.
const REACTIONS = [...commentEmojiRow.querySelectorAll('[data-insert-emoji]')].map((button) => ({
    emoji: button.dataset.insertEmoji,
    label: button.querySelector('.sr-only').textContent,
}));

const weekdayFormatter = new Intl.DateTimeFormat('en-US', { weekday: 'long' });
const relativeFormatter = new Intl.RelativeTimeFormat('en-US', { numeric: 'auto' });

const MINUTE = 60_000;
const RELATIVE_STEPS = [
    ['day', 24 * 60 * MINUTE],
    ['hour', 60 * MINUTE],
    ['minute', MINUTE],
];

/**
 * "2 hours ago", "yesterday". Intl.RelativeTimeFormat is built into the browser, so the wording
 * needs no dictionary of our own. Anything a week old falls back to the date, because
 * "23 days ago" is harder to place than the day it happened.
 */
function relativeTime(date) {
    const elapsed = date.getTime() - Date.now();

    if (Math.abs(elapsed) > 7 * 24 * 60 * MINUTE) {
        return shortDate.format(date);
    }

    for (const [unit, size] of RELATIVE_STEPS) {
        if (Math.abs(elapsed) >= size) {
            return relativeFormatter.format(Math.round(elapsed / size), unit);
        }
    }

    return 'just now';
}

/**
 * "30 Sep · Today · Wednesday", or the date and weekday for any other day.
 */
function dayHeading(date) {
    const today = new Date();
    const sameDay = date.toDateString() === today.toDateString();
    const parts = [shortDate.format(date)];

    if (sameDay) {
        parts.push('Today');
    }

    parts.push(weekdayFormatter.format(date));

    return parts.join(' · ');
}

function setMessage(element, text) {
    element.textContent = text ?? '';
    element.classList.toggle('hidden', !text);
}

// ---------------------------------------------------------------- reactions

function reactionChip(reaction, onToggle) {
    const chip = createElement('button', 'reaction-chip');
    chip.type = 'button';
    chip.setAttribute('aria-pressed', String(reaction.mine));
    chip.setAttribute('aria-label', `${reaction.emoji}, ${reaction.count}`);
    chip.append(
        createElement('span', 'text-sm', reaction.emoji),
        createElement('span', '', String(reaction.count)),
    );
    chip.addEventListener('click', () => onToggle(reaction.emoji, !reaction.mine));

    return chip;
}

/**
 * The row of reactions a comment already has, followed by the button that offers the rest.
 * Only emoji nobody has used yet are in that list, so the same one is never offered twice.
 */
function reactionRow(comment, onToggle) {
    const row = createElement('div', 'mt-2 flex flex-wrap items-center gap-1');
    const used = new Set(comment.reactions.map((reaction) => reaction.emoji));

    row.append(...comment.reactions.map((reaction) => reactionChip(reaction, onToggle)));

    const add = createElement(
        'button',
        'inline-flex size-8 items-center justify-center rounded-full border border-dashed border-gray-300 text-gray-400 transition-colors hover:border-gray-400 hover:text-gray-600 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none',
    );
    add.type = 'button';
    add.setAttribute('aria-haspopup', 'menu');
    add.setAttribute('aria-expanded', 'false');
    add.dataset.menuLabel = 'Add a reaction';
    add.append(createIcon('face-smile', 'size-4'), createElement('span', 'sr-only', 'Add a reaction'));
    add.addEventListener('click', () =>
        openMenu(
            add,
            REACTIONS.filter((one) => !used.has(one.emoji)).map((one) => ({
                emoji: one.emoji,
                label: one.label,
                onSelect: () => onToggle(one.emoji, true),
            })),
        ),
    );

    row.append(add);

    return row;
}

// ---------------------------------------------------------------- comments

function commentEntry(comment, actions) {
    const item = createElement('li', 'border-b border-gray-100 py-3 last:border-0');

    const header = createElement('div', 'flex items-start justify-between gap-2');
    header.append(
        createElement('p', 'text-xs text-gray-500', relativeTime(new Date(comment.created_at))),
    );

    const remove = createElement(
        'button',
        'inline-flex size-8 shrink-0 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none',
    );
    remove.type = 'button';
    remove.append(createIcon('trash', 'size-4'), createElement('span', 'sr-only', 'Delete comment'));
    remove.addEventListener('click', () => actions.remove(comment));
    header.append(remove);

    item.append(
        header,
        // Comment text is user input, so it only ever arrives through textContent.
        createElement('p', 'mt-1 text-sm wrap-break-word whitespace-pre-line text-gray-700', comment.body),
        reactionRow(comment, (emoji, reacted) => actions.react(comment, emoji, reacted)),
    );

    return item;
}

// ---------------------------------------------------------------- activity

function activityEntry(entry) {
    const item = createElement('li', 'flex items-center gap-3 border-b border-gray-100 py-3 last:border-0');
    const line = createElement('p', 'min-w-0 flex-1 text-sm wrap-break-word text-gray-700');

    line.append(
        createElement('span', 'font-medium text-gray-900', entry.label),
        document.createTextNode(' '),
        createElement('span', '', entry.task_title),
    );

    item.append(line, createElement('span', 'shrink-0 text-xs text-gray-500', relativeTime(new Date(entry.created_at))));

    return item;
}

/**
 * The feed broken into days, newest day first, each headed by its date and how many things
 * happened on it. A flat list of timestamps is harder to read than one with the days marked.
 */
function activityDays(entries) {
    const days = new Map();

    for (const entry of entries) {
        const date = new Date(entry.created_at);
        const key = date.toDateString();

        if (!days.has(key)) {
            days.set(key, { date, entries: [] });
        }

        days.get(key).entries.push(entry);
    }

    return [...days.values()].map(({ date, entries: onThatDay }) => {
        const group = createElement('li', 'pt-2 first:pt-0');
        const heading = createElement('div', 'flex items-baseline justify-between gap-2 border-b border-gray-200 pb-1');

        heading.append(
            createElement('h3', 'text-sm font-medium text-gray-900', dayHeading(date)),
            createElement('span', 'text-xs text-gray-500', String(onThatDay.length)),
        );

        const list = createElement('ul', '');
        list.append(...onThatDay.map(activityEntry));
        group.append(heading, list);

        return group;
    });
}

// ---------------------------------------------------------------- the panel

function showTab(name) {
    for (const tab of panelTabs) {
        tab.setAttribute('aria-pressed', String(tab.dataset.panelTab === name));
    }

    for (const [key, element] of Object.entries(panels)) {
        // Only the comments panel lays itself out with flex; the activity panel scrolls.
        if (key === 'comments') {
            setVisible(element, key === name);
        } else {
            element.classList.toggle('hidden', key !== name);
        }
    }
}

/**
 * A project's comments and its activity, in one dialog with a tab between them: both answer
 * "what has happened here", so they are a click apart rather than a menu apart.
 *
 * The requests are passed in rather than made here, because the API wrapper lives in app.js and
 * this file only knows about dialogs.
 *
 * @param {object} project
 * @param {'comments'|'activity'} tab which one to open on
 * @param {{loadComments: Function, addComment: Function, removeComment: Function, react: Function, loadActivity: Function}} actions
 */
export function openProjectPanel(project, tab, actions) {
    panelProject.textContent = project.name;
    commentsList.replaceChildren();
    activityList.replaceChildren();
    commentForm.reset();
    commentError.classList.add('hidden');
    commentBody.removeAttribute('aria-invalid');
    commentEmojiRow.classList.add('hidden');
    commentEmojiRow.classList.remove('flex');
    commentEmoji.setAttribute('aria-expanded', 'false');
    commentsEmpty.classList.add('hidden');
    setMessage(commentsMessage, 'Loading…');
    setMessage(activityMessage, 'Loading…');
    showTab(tab);

    return openDialog(panelDialog, ({ on, finish }) => {
        const refreshComments = async () => {
            try {
                const comments = await actions.loadComments();

                // The dialog may have been dismissed while the request was still out.
                if (!panelDialog.open) {
                    return;
                }

                const react = async (comment, emoji, reacted) => {
                    await actions.react(comment, emoji, reacted);
                    await refreshComments();
                };

                commentsList.replaceChildren(
                    ...comments.map((comment) =>
                        commentEntry(comment, {
                            react,
                            remove: async (one) => {
                                await actions.removeComment(one);
                                await refreshComments();
                            },
                        }),
                    ),
                );
                setMessage(commentsMessage, null);
                setVisible(commentsEmpty, comments.length === 0);
            } catch (error) {
                commentsEmpty.classList.add('hidden');
                setMessage(commentsMessage, error.message || 'Comments could not be loaded.');
            }
        };

        const refreshActivity = async () => {
            try {
                const entries = await actions.loadActivity();

                if (!panelDialog.open) {
                    return;
                }

                activityList.replaceChildren(...activityDays(entries));
                setMessage(activityMessage, entries.length === 0 ? 'Nothing has happened in this project yet.' : null);
            } catch (error) {
                setMessage(activityMessage, error.message || 'Activity could not be loaded.');
            }
        };

        on(panelClose, 'click', () => finish(null));
        panelTabs.forEach((button) => on(button, 'click', () => showTab(button.dataset.panelTab)));

        on(commentEmoji, 'click', () => {
            const showing = commentEmojiRow.classList.contains('hidden');

            setVisible(commentEmojiRow, showing);
            commentEmoji.setAttribute('aria-expanded', String(showing));
        });

        for (const button of commentEmojiRow.querySelectorAll('[data-insert-emoji]')) {
            on(button, 'click', () => {
                // Inserted at the caret rather than appended, so it lands where the user is.
                const { selectionStart: start, selectionEnd: end, value } = commentBody;
                const emoji = button.dataset.insertEmoji;

                commentBody.value = value.slice(0, start) + emoji + value.slice(end);
                commentBody.focus();
                commentBody.setSelectionRange(start + emoji.length, start + emoji.length);
            });
        }

        on(commentForm, 'submit', async (event) => {
            event.preventDefault();

            if (commentSubmit.disabled) {
                return;
            }

            commentError.classList.add('hidden');
            commentBody.removeAttribute('aria-invalid');
            setBusy(commentSubmit, 'Posting…');

            try {
                await actions.addComment(commentBody.value);
                commentForm.reset();
                await refreshComments();
                commentBody.focus();
            } catch (failure) {
                commentError.textContent =
                    failure.errors?.body?.[0] ?? failure.message ?? 'Something went wrong.';
                commentError.classList.remove('hidden');
                commentBody.setAttribute('aria-invalid', 'true');
                commentBody.focus();
            } finally {
                clearBusy(commentSubmit);
            }
        });

        refreshComments();
        refreshActivity();
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
