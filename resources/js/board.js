/**
 * The board layout. Its columns are whatever the Display panel groups by, so grouping by status
 * gives To do / In progress / Done and grouping by priority gives High / Medium / Low.
 *
 * Dragging only moves a card between the three stages, because that is the only move the API
 * has an endpoint for. Under any other grouping the cards carry their buttons instead, rather
 * than offering a gesture that would silently do nothing.
 */
import { groupTasks } from './display.js';
import { createElement } from './dom.js';

const board = document.getElementById('board-view');

function column(group, renderCard, draggable) {
    const section = createElement('section', 'board-column');
    const head = createElement('div', 'flex items-center justify-between gap-2 px-4 py-3');
    const heading = createElement('h2', 'text-xs font-semibold tracking-wide text-gray-600 uppercase', group.label);

    section.dataset.group = group.key;
    head.append(heading, createElement('span', 'text-xs font-medium text-gray-500 tabular-nums', String(group.tasks.length)));

    const list = createElement('ul', 'flex flex-col gap-3 px-3 pb-3');
    list.append(...group.tasks.map(renderCard));
    section.append(head, list);

    if (group.tasks.length === 0) {
        section.append(
            draggable
                ? createElement('p', 'mx-3 mb-3 rounded-lg border border-dashed border-gray-300 px-3 py-5 text-center text-sm text-gray-400', 'Drop a task here')
                : createElement('p', 'px-4 pb-4 text-sm text-gray-400', 'Nothing here yet.'),
        );
    }

    return section;
}

/**
 * @param {{tasks: array, grouping: string, categories: array, renderCard: Function}} options
 */
export function renderBoard({ tasks, grouping, categories, renderCard }) {
    const groups = groupTasks({ tasks, grouping, categories });
    const draggable = grouping === 'status';

    board.replaceChildren(...groups.map((group) => column(group, renderCard, draggable)));
}

export function clearBoard() {
    board.replaceChildren();
}

/**
 * Wires dragging and its keyboard equivalent. `onMove(taskId, status)` runs the request.
 */
export function wireBoardDragging(onMove, onOpen) {
    let held = null;

    const columnOf = (target) => target.closest?.('[data-group]');
    const cardOf = (target) => target.closest?.('.board-card[draggable="true"]');

    board.addEventListener('dragstart', (event) => {
        const card = cardOf(event.target);

        if (card) {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', card.dataset.taskId);
            card.classList.add('opacity-40');
        }
    });

    board.addEventListener('dragend', (event) => {
        cardOf(event.target)?.classList.remove('opacity-40');
        board.querySelectorAll('[data-group]').forEach((item) => item.classList.remove('border-indigo-600', 'bg-indigo-50'));
    });

    board.addEventListener('dragover', (event) => {
        const target = columnOf(event.target);

        if (!target) {
            return;
        }

        // Without preventDefault the browser refuses the drop and the card springs back.
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        target.classList.add('border-indigo-600', 'bg-indigo-50');
    });

    board.addEventListener('dragleave', (event) => {
        const target = columnOf(event.target);

        if (target && !target.contains(event.relatedTarget)) {
            target.classList.remove('border-indigo-600', 'bg-indigo-50');
        }
    });

    board.addEventListener('drop', (event) => {
        const target = columnOf(event.target);

        if (target) {
            event.preventDefault();
            onMove(Number(event.dataTransfer.getData('text/plain')), target.dataset.group);
        }
    });

    board.addEventListener('keydown', async (event) => {
        const card = cardOf(event.target);

        // The card itself only: Enter on the title already opens the task, and both firing would
        // open it twice.
        if (!card || event.target !== card) {
            return;
        }

        const id = Number(card.dataset.taskId);
        const columns = [...board.querySelectorAll('[data-group]')].map((item) => item.dataset.group);

        if (event.key === 'Enter') {
            event.preventDefault();
            onOpen(id);

            return;
        }

        if (event.key === ' ') {
            event.preventDefault();
            held = held === id ? null : id;
            card.classList.toggle('ring-2', held === id);
            card.classList.toggle('ring-indigo-600', held === id);

            return;
        }

        if (held !== id || (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight')) {
            return;
        }

        event.preventDefault();
        const next = columns[columns.indexOf(card.dataset.status) + (event.key === 'ArrowRight' ? 1 : -1)];

        if (!next) {
            return;
        }

        held = null;
        await onMove(id, next);

        // The move rebuilds the board, so the card that had focus has to be found again.
        board.querySelector(`[data-task-id="${id}"]`)?.focus();
    });
}
