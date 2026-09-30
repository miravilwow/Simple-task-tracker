// Heroicons v2 (MIT) outline paths, matching resources/views/components/icon.blade.php.
const ICONS = {
    check: 'm4.5 12.75 6 6 9-13.5',
    undo: 'M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3',
    trash: 'm14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0',
    'check-circle': 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    warning:
        'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
    calendar:
        'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5',
    close: 'M6 18 18 6M6 6l12 12',
    'chevron-left': 'M15.75 19.5 8.25 12l7.5-7.5',
    'chevron-right': 'm8.25 4.5 7.5 7.5-7.5 7.5',
};

export function createElement(tag, className, text) {
    const element = document.createElement(tag);

    if (className) {
        element.className = className;
    }

    if (text !== undefined) {
        element.textContent = text;
    }

    return element;
}

export function createIcon(name, className = 'size-4') {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');

    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '1.5');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('class', className);

    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', ICONS[name]);
    svg.append(path);

    return svg;
}

export function createBadge(label, classes) {
    return createElement('span', `inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ${classes}`, label);
}

// Buttons wrap their text in a span so an icon beside it survives a label swap.
const labelOf = (button) => button.querySelector('[data-label]') ?? button;

export function setBusy(button, label) {
    const target = labelOf(button);

    target.dataset.previous = target.textContent;
    target.textContent = label;
    button.disabled = true;
}

export function clearBusy(button) {
    const target = labelOf(button);

    target.textContent = target.dataset.previous;
    button.disabled = false;
}

const toastRegion = document.getElementById('toast-region');

export function showToast(message, type = 'success') {
    const styles = type === 'error' ? 'bg-red-600 text-white' : 'bg-gray-900 text-white';
    const toast = createElement(
        'div',
        `toast-enter flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm shadow-lg ${styles}`,
    );

    toast.append(createIcon(type === 'error' ? 'warning' : 'check-circle', 'size-4 shrink-0'));
    toast.append(createElement('span', '', message));
    toastRegion.append(toast);
    setTimeout(() => toast.remove(), 3000);
}

/**
 * `new Date('2026-10-05')` is parsed as UTC midnight, which lands on the previous day
 * for anyone west of Greenwich. These two keep every date in local time.
 */
export function parseDate(iso) {
    const [year, month, day] = iso.split('-').map(Number);

    return new Date(year, month - 1, day);
}

export function toIsoDate(date) {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

export const startOfToday = () => {
    const now = new Date();

    return new Date(now.getFullYear(), now.getMonth(), now.getDate());
};
