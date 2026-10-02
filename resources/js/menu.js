import { createElement, createIcon } from './dom.js';

/**
 * The popover behind a "…" button.
 *
 * The panel is appended to <body> and positioned `fixed`, not absolutely inside its row, so a
 * scrolling container around the trigger cannot clip it. The flip side is that it does not follow
 * the page, so any scroll closes it.
 *
 * An entry is one of two things:
 *   { separator: true }                        a rule between groups
 *   { icon, label, onSelect, destructive? }    an action
 */
let session = null;

function closeAll({ restoreFocus = true } = {}) {
    if (!session) {
        return;
    }

    const { panel, trigger, controller } = session;

    session = null;
    controller.abort();
    panel.remove();
    trigger.setAttribute('aria-expanded', 'false');

    if (restoreFocus) {
        trigger.focus();
    }
}

/** Anchored under the trigger, pulled back inside the viewport when it would hang off an edge. */
function position(panel, anchorRect) {
    const gap = 4;
    const { width, height } = panel.getBoundingClientRect();

    const left = Math.min(anchorRect.left, window.innerWidth - width - gap);
    const preferred = anchorRect.bottom + gap;
    const top = preferred + height > window.innerHeight - gap
        ? window.innerHeight - height - gap
        : preferred;

    panel.style.left = `${Math.max(gap, left)}px`;
    panel.style.top = `${Math.max(gap, top)}px`;
}

function itemClasses(destructive) {
    return [
        'flex min-h-10 w-full items-center gap-2.5 rounded-md px-3 text-sm',
        'focus-visible:ring-2 focus-visible:outline-none',
        destructive
            ? 'text-red-600 hover:bg-red-50 focus-visible:ring-red-500'
            : 'text-gray-700 hover:bg-gray-100 focus-visible:ring-red-500',
    ].join(' ');
}

function buildPanel(entries, label) {
    const panel = createElement(
        'div',
        'fixed z-50 min-w-52 rounded-lg border border-gray-200 bg-white p-1 shadow-lg',
    );
    panel.setAttribute('role', 'menu');
    panel.setAttribute('aria-label', label);

    const items = [];

    for (const entry of entries) {
        if (entry.separator) {
            panel.append(createElement('div', 'my-1 border-t border-gray-100'));
            continue;
        }

        const item = createElement('button', itemClasses(entry.destructive));
        item.type = 'button';
        item.setAttribute('role', 'menuitem');
        item.append(
            createIcon(entry.icon, 'size-4 shrink-0 text-gray-500'),
            createElement('span', 'flex-1 text-left', entry.label),
        );
        item.addEventListener('click', () => {
            // Close first: the action may replace the row the trigger lives in, and focus
            // has to go back to it while it is still there.
            closeAll();
            entry.onSelect();
        });

        items.push(item);
        panel.append(item);
    }

    panel.addEventListener('keydown', (event) => onPanelKey(event, items));
    document.body.append(panel);

    return { panel, items };
}

function onPanelKey(event, items) {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const index = items.indexOf(document.activeElement);
        const step = event.key === 'ArrowDown' ? 1 : -1;
        // Wraps, so Up from the first item lands on the last.
        items[(index + step + items.length) % items.length].focus();
    } else if (event.key === 'Home' || event.key === 'End') {
        event.preventDefault();
        items[event.key === 'Home' ? 0 : items.length - 1].focus();
    }
}

/**
 * @param {HTMLElement} trigger the "…" button the menu belongs to
 * @param {Array<object>} entries see the note at the top of this file
 */
export function openMenu(trigger, entries) {
    // A second click on the same trigger closes the menu rather than reopening it.
    const reopening = session?.trigger === trigger;
    closeAll({ restoreFocus: false });

    if (reopening) {
        trigger.focus();

        return;
    }

    const controller = new AbortController();
    const on = (target, event, handler, options) =>
        target.addEventListener(event, handler, { ...options, signal: controller.signal });

    const { panel, items } = buildPanel(entries, trigger.dataset.menuLabel ?? 'Actions');

    session = { panel, trigger, controller };
    position(panel, trigger.getBoundingClientRect());

    trigger.setAttribute('aria-expanded', 'true');
    items[0].focus();

    on(document, 'keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeAll();
        }
    });

    // Tabbing out, or clicking anywhere else, dismisses it without stealing focus back.
    on(document, 'focusin', (event) => {
        if (!panel.contains(event.target)) {
            closeAll({ restoreFocus: false });
        }
    }, { capture: true });

    on(document, 'pointerdown', (event) => {
        if (!panel.contains(event.target) && event.target !== trigger) {
            closeAll({ restoreFocus: false });
        }
    });

    // Fixed positioning does not follow a scroll, so the panel would drift off its row.
    on(window, 'scroll', () => closeAll({ restoreFocus: false }), { capture: true });
    on(window, 'resize', () => closeAll({ restoreFocus: false }));
}
