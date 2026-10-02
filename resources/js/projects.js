/**
 * Grouping by project draws one card per project rather than one column each, so the four stages
 * survive: a card opens that project's own board. The counts come from the tasks already loaded,
 * so a card says what the board behind it will show.
 */
import { createElement } from './dom.js';

const STAGES = [
    ['pending', 'To do'],
    ['in_progress', 'In progress'],
    ['in_review', 'In review'],
    ['completed', 'Done'],
];

function projectsOf(tasks) {
    const projects = new Map();

    for (const task of tasks) {
        const key = task.category ? String(task.category.id) : 'none';

        if (!projects.has(key)) {
            projects.set(key, { key, name: task.category?.name ?? 'No project', tasks: [] });
        }

        projects.get(key).tasks.push(task);
    }

    // By name, with "No project" last because it is not a project anyone named.
    return [...projects.values()].sort(
        (a, b) => (a.key === 'none') - (b.key === 'none') || a.name.localeCompare(b.name),
    );
}

function projectCard(project, onOpen) {
    const card = createElement('button', 'project-card');
    const total = project.tasks.length;
    const count = (status) => project.tasks.filter((task) => task.status === status).length;
    const percent = Math.round((count('completed') / total) * 100);

    card.type = 'button';
    card.dataset.project = project.key;

    const head = createElement('span', 'flex items-center justify-between gap-2');

    head.append(
        createElement('span', 'truncate font-medium text-gray-900', project.name),
        createElement('span', 'shrink-0 text-xs text-gray-500 tabular-nums', `${total} ${total === 1 ? 'task' : 'tasks'}`),
    );

    const stages = createElement('span', 'grid grid-cols-4 gap-1 text-center');

    for (const [status, label] of STAGES) {
        const cell = createElement('span', 'flex flex-col rounded-md bg-gray-50 px-1 py-1.5');

        cell.append(
            createElement('span', 'text-sm font-semibold text-gray-900 tabular-nums', String(count(status))),
            createElement('span', 'text-xs leading-tight text-gray-500', label),
        );
        stages.append(cell);
    }

    const bar = createElement('span', 'block h-1.5 overflow-hidden rounded-full bg-gray-100');
    const fill = createElement('span', 'block h-full rounded-full bg-green-600');

    fill.style.width = `${percent}%`;
    bar.append(fill);

    const progress = createElement('span', 'flex items-center gap-2');

    progress.append(bar, createElement('span', 'shrink-0 text-xs text-gray-500 tabular-nums', `${percent}% done`));
    bar.classList.add('flex-1');

    card.append(head, stages, progress);
    card.addEventListener('click', () => onOpen({ key: project.key, name: project.name }));

    return card;
}

/**
 * @param {HTMLElement} container
 * @param {{tasks: array, onOpen: Function}} options
 */
export function renderProjectGrid(container, { tasks, onOpen }) {
    const cards = projectsOf(tasks).map((project) => projectCard(project, onOpen));

    container.replaceChildren(
        ...(cards.length > 0 ? cards : [createElement('p', 'text-sm text-gray-500', 'No tasks to group yet.')]),
    );
}
