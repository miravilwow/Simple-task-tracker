import { createElement, createIcon } from './dom.js';

/**
 * The popover behind a sidebar row's "…" button.
 *
 * It is appended to <body> and positioned `fixed`, not absolutely inside the row: the sidebar
 * scrolls on its own, so a panel positioned within it would be clipped by that overflow.
 * The flip side is that the panel does not follow the page, so any scroll closes it.
 */
let open = null;

function closeMenu({ restoreFocus = true } = {}) {
    if (!open) {
        return;
    }

    const { panel, trigger, controller } = open;

    open = null;
    controller.abort();
    panel.remove();
    trigger.setAttribute('aria-expanded', 'false');

    if (restoreFocus) {
        trigger.focus();
    }
}

/**
 * Anchored under the trigger, pulled back inside the viewport when it would hang off an edge.
 */
function position(panel, trigger) {
    const anchor = trigger.getBoundingClientRect();
    const gap = 4;
    const { width, height } = panel.getBoundingClientRect();

    const left = Math.min(Math.max(gap, anchor.left), window.innerWidth - width - gap);
    // Flip above the trigger when there is no room below it.
    const below = anchor.bottom + gap;
    const top = below + height > window.innerHeight ? Math.max(gap, anchor.top - height - gap) : below;

    panel.style.left = `${left}px`;
    panel.style.top = `${top}px`;
}

function moveFocus(items, current, step) {
    const index = items.indexOf(current);
    // Wraps, so Up from the first item lands on the last.
    items[(index + step + items.length) % items.length].focus();
}

/**
 * @param {HTMLElement} trigger the "…" button the menu belongs to
 * @param {Array<{label: string, icon: string, destructive?: boolean, onSelect: () => void}>} entries
 */
export function openMenu(trigger, entries) {
    // A second click on the same trigger closes the menu rather than reopening it.
    const reopening = open?.trigger === trigger;
    closeMenu({ restoreFocus: false });

    if (reopening) {
        trigger.focus();

        return;
    }

    const controller = new AbortController();
    const on = (target, event, handler, options) =>
        target.addEventListener(event, handler, { ...options, signal: controller.signal });

    const panel = createElement(
        'div',
        'fixed z-50 min-w-44 rounded-lg border border-gray-200 bg-white p-1 shadow-lg',
    );
    panel.setAttribute('role', 'menu');
    panel.setAttribute('aria-label', trigger.dataset.menuLabel ?? 'Actions');

    const items = entries.map((entry) => {
        const item = createElement(
            'button',
            [
                'flex min-h-10 w-full items-center gap-2 rounded-md px-3 text-sm',
                'focus-visible:ring-2 focus-visible:outline-none',
                entry.destructive
                    ? 'text-red-600 hover:bg-red-50 focus-visible:ring-red-500'
                    : 'text-gray-700 hover:bg-gray-100 focus-visible:ring-indigo-500',
            ].join(' '),
        );
        item.type = 'button';
        item.setAttribute('role', 'menuitem');
        item.append(createIcon(entry.icon, 'size-4 shrink-0'), createElement('span', '', entry.label));
        on(item, 'click', () => {
            // Close first: the action may replace the row the trigger lives in, and focus has to
            // go back to it while it is still there.
            closeMenu();
            entry.onSelect();
        });

        return item;
    });

    panel.append(...items);
    document.body.append(panel);
    position(panel, trigger);

    trigger.setAttribute('aria-expanded', 'true');
    open = { panel, trigger, controller };
    items[0].focus();

    on(panel, 'keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            moveFocus(items, document.activeElement, event.key === 'ArrowDown' ? 1 : -1);
        } else if (event.key === 'Home' || event.key === 'End') {
            event.preventDefault();
            items[event.key === 'Home' ? 0 : items.length - 1].focus();
        }
    });

    on(document, 'keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeMenu();
        }
    });

    // Tabbing out, or clicking anywhere else, dismisses it without stealing focus back.
    on(
        document,
        'focusin',
        (event) => {
            if (!panel.contains(event.target)) {
                closeMenu({ restoreFocus: false });
            }
        },
        { capture: true },
    );
    on(document, 'pointerdown', (event) => {
        if (!panel.contains(event.target) && event.target !== trigger) {
            closeMenu({ restoreFocus: false });
        }
    });
    // Fixed positioning does not follow a scroll, so the panel would drift away from its row.
    on(window, 'scroll', () => closeMenu({ restoreFocus: false }), { capture: true });
    on(window, 'resize', () => closeMenu({ restoreFocus: false }));
}
