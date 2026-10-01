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

// One gap, moved between the columns rather than built and thrown away on every dragover. It is
// what the cards part around, so it has to be the same node each time or the parting restarts.
const gap = createElement('li', 'board-gap');

gap.setAttribute('aria-hidden', 'true');

function column(group, renderCard, draggable) {
    const section = createElement('section', 'board-column');
    const head = createElement('div', 'flex items-center justify-between gap-2 px-4 py-3');
    const heading = createElement('h2', 'text-xs font-semibold tracking-wide text-gray-600 uppercase', group.label);

    section.dataset.group = group.key;
    head.append(heading, createElement('span', 'text-xs font-medium text-gray-500 tabular-nums', String(group.tasks.length)));

    // flex-1 so the list reaches the bottom of the column. A drop is accepted anywhere in the
    // column, not only where the cards sit, so a card let go under the last one still lands.
    const list = createElement('ul', 'flex flex-1 flex-col gap-3 px-3 pb-3');

    list.append(...group.tasks.map(renderCard));
    section.append(head, list);

    if (group.tasks.length === 0) {
        const empty = draggable
            ? createElement('li', 'flex flex-1 items-center justify-center rounded-lg border border-dashed border-gray-300 px-3 py-5 text-center text-sm text-gray-400', 'Drop a task here')
            : createElement('li', 'px-1 text-sm text-gray-400', 'Nothing here yet.');

        empty.dataset.empty = 'true';
        list.append(empty);
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

/** The cards a drop counts against: the real ones, minus the card being dragged out of them. */
const cardsIn = (list, dragged) =>
    [...list.children].filter((item) => item.classList.contains('board-card') && item !== dragged);

/**
 * Where the gap belongs for a pointer at this height: before the first card whose middle the
 * pointer has not yet passed. Comparing against middles rather than edges is what makes the cards
 * part one at a time instead of flickering as the pointer crosses a border.
 */
function gapIndex(list, y, dragged) {
    const cards = cardsIn(list, dragged);
    const index = cards.findIndex((card) => {
        const box = card.getBoundingClientRect();

        return y < box.top + box.height / 2;
    });

    return index === -1 ? cards.length : index;
}

/** Moves the gap into this column at this index, parting the cards around it. */
function showGap(list, index, dragged) {
    const cards = cardsIn(list, dragged);

    list.insertBefore(gap, cards[index] ?? null);
    list.querySelector('[data-empty]')?.classList.add('hidden');
}

function hideGap() {
    board.querySelectorAll('[data-empty]').forEach((item) => item.classList.remove('hidden'));
    gap.remove();
}

/**
 * Wires dragging and its keyboard equivalent.
 *
 * `onMove(taskId, status, after)` runs the request. `after` is the id of the task the moved one
 * should follow, and null for the top of the column. Naming a card rather than counting them is
 * what keeps a drop honest while a filter is hiding some of them.
 */
export function wireBoardDragging(onMove, onOpen) {
    let held = null;
    let dragged = null;

    const columnOf = (target) => target.closest?.('[data-group]');
    const cardOf = (target) => target.closest?.('.board-card[draggable="true"]');
    const listOf = (section) => section.querySelector('ul');

    const clearHighlights = () =>
        board.querySelectorAll('[data-group]').forEach((item) => item.classList.remove('border-indigo-600', 'bg-indigo-50'));

    function endDrag() {
        dragged?.classList.remove('hidden');
        dragged = null;
        hideGap();
        clearHighlights();
    }

    board.addEventListener('dragstart', (event) => {
        const card = cardOf(event.target);

        if (!card) {
            return;
        }

        dragged = card;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', card.dataset.taskId);

        // Hidden, not faded: the gap is what says where the card is going, and a card left in
        // place as well would be two answers to one question. The browser has already taken its
        // drag image by the next tick, so hiding now would cancel the drag outright.
        setTimeout(() => card.classList.add('hidden'), 0);
    });

    board.addEventListener('dragend', endDrag);

    board.addEventListener('dragover', (event) => {
        const section = columnOf(event.target);

        if (!section || !dragged) {
            return;
        }

        // Without preventDefault the browser refuses the drop and the card springs back.
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        section.classList.add('border-indigo-600', 'bg-indigo-50');

        const list = listOf(section);

        showGap(list, gapIndex(list, event.clientY, dragged), dragged);
    });

    board.addEventListener('dragleave', (event) => {
        const section = columnOf(event.target);

        if (section && !section.contains(event.relatedTarget)) {
            section.classList.remove('border-indigo-600', 'bg-indigo-50');
        }
    });

    board.addEventListener('drop', (event) => {
        const section = columnOf(event.target);

        if (!section || !dragged) {
            return;
        }

        event.preventDefault();

        // The index the gap is actually sitting at, not one worked out again from the pointer: the
        // gap has moved the cards since, so a second measurement could name a different place than
        // the one the user was shown.
        // The nearest card above the gap, which is what the drop means: "put it after this one".
        // Nothing above the gap means the top of the column.
        let anchor = gap.previousElementSibling;

        while (anchor && (anchor === dragged || !anchor.classList.contains('board-card'))) {
            anchor = anchor.previousElementSibling;
        }

        const id = Number(event.dataTransfer.getData('text/plain'));
        const group = section.dataset.group;
        const after = anchor ? Number(anchor.dataset.taskId) : null;

        endDrag();
        onMove(id, group, after);
    });

    board.addEventListener('keydown', async (event) => {
        const card = cardOf(event.target);

        // The card itself only: Enter on the title already opens the task, and both firing would
        // open it twice.
        if (!card || event.target !== card) {
            return;
        }

        const id = Number(card.dataset.taskId);

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

        const moves = { ArrowLeft: 'column', ArrowRight: 'column', ArrowUp: 'row', ArrowDown: 'row' };

        if (held !== id || !moves[event.key]) {
            return;
        }

        event.preventDefault();
        const step = event.key === 'ArrowRight' || event.key === 'ArrowDown' ? 1 : -1;
        const move = moves[event.key] === 'column'
            ? intoColumn(card, step)
            : alongColumn(card, step);

        if (!move) {
            return;
        }

        held = null;
        card.classList.remove('ring-2', 'ring-indigo-600');
        await onMove(id, move.status, move.after);

        // The move rebuilds the board, so the card that had focus has to be found again.
        board.querySelector(`[data-task-id="${id}"]`)?.focus();
    });

    const idOf = (card) => (card ? Number(card.dataset.taskId) : null);

    /** The left and right arrows change the stage, landing the card at the end of its new column. */
    function intoColumn(card, step) {
        const sections = [...board.querySelectorAll('[data-group]')];
        const next = sections[sections.findIndex((item) => item.dataset.group === card.dataset.status) + step];

        if (!next) {
            return null;
        }

        const cards = cardsIn(listOf(next), card);

        return { status: next.dataset.group, after: idOf(cards[cards.length - 1]) };
    }

    /**
     * The up and down arrows rearrange one column, which is the keyboard's half of a drop. Moving
     * down means following the card below; moving up means following the one two above, which is
     * nothing at all when the card is already second.
     */
    function alongColumn(card, step) {
        const cards = cardsIn(card.closest('ul'), null);
        const index = cards.indexOf(card);

        if (step > 0 ? index === cards.length - 1 : index === 0) {
            return null;
        }

        return { status: card.dataset.status, after: idOf(step > 0 ? cards[index + 1] : cards[index - 2]) };
    }
}
