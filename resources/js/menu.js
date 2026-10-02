import { createElement, createIcon } from './dom.js';

/**
 * The popover behind a sidebar row's "…" button.
 *
 * Panels are appended to <body> and positioned `fixed`, not absolutely inside the row: the
 * sidebar scrolls on its own, so a panel placed within it would be clipped by that overflow.
 * The flip side is that a panel does not follow the page, so any scroll closes the whole menu.
 *
 * An entry is one of three things:
 *   { separator: true }                        a rule between groups
 *   { icon, label, onSelect, destructive? }    an action
 *   { icon, label, submenu: [...] }            a group that opens its own panel to the side
 */
let session = null;

function closeAll({ restoreFocus = true } = {}) {
    if (!session) {
        return;
    }

    const { panels, trigger, controller } = session;

    session = null;
    controller.abort();
    panels.forEach((panel) => panel.remove());
    trigger.setAttribute('aria-expanded', 'false');

    if (restoreFocus) {
        trigger.focus();
    }
}

/**
 * Drops every panel deeper than the given one, which is what closing a submenu means.
 */
function closeBelow(depth) {
    while (session.panels.length > depth + 1) {
        session.panels.pop().remove();
    }
}

/**
 * Anchored to the trigger, pulled back inside the viewport when it would hang off an edge.
 * A submenu grows sideways from its parent item; the root panel grows downwards from the button.
 */
function position(panel, anchorRect, { sideways }) {
    const gap = 4;
    const { width, height } = panel.getBoundingClientRect();

    let left = sideways ? anchorRect.right + gap : anchorRect.left;

    // Flip to the other side rather than hang off the edge.
    if (left + width > window.innerWidth - gap) {
        left = sideways ? anchorRect.left - width - gap : window.innerWidth - width - gap;
    }

    const preferred = sideways ? anchorRect.top : anchorRect.bottom + gap;
    const top = preferred + height > window.innerHeight - gap
        ? Math.max(gap, window.innerHeight - height - gap)
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

function buildPanel(entries, depth, label) {
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
            // A reaction entry is marked by its emoji rather than by a drawn icon.
            entry.emoji
                ? createElement('span', 'w-4 shrink-0 text-center text-base', entry.emoji)
                : createIcon(entry.icon, 'size-4 shrink-0 text-gray-500'),
            createElement('span', 'flex-1 text-left', entry.label),
        );

        // A zero renders as nothing rather than "0", the same as the sidebar's own badges.
        if (entry.count) {
            item.append(createElement('span', 'shrink-0 text-xs text-gray-400', String(entry.count)));
        }

        if (entry.submenu) {
            item.setAttribute('aria-haspopup', 'menu');
            item.setAttribute('aria-expanded', 'false');
            item.append(createIcon('chevron-right', 'size-4 shrink-0 text-gray-400'));
            item.addEventListener('click', () => openSubmenu(item, entry, depth, { focus: true }));
            // Hovering opens it for a pointer, but must not move focus: a mouse crossing the
            // menu would otherwise yank the keyboard user out of the list they are in.
            item.addEventListener('pointerenter', () => openSubmenu(item, entry, depth));
        } else {
            item.addEventListener('click', () => {
                // Close first: the action may replace the row the trigger lives in, and focus
                // has to go back to it while it is still there.
                closeAll();
                entry.onSelect();
            });
            // Moving onto a plain item drops any submenu that was open beside it.
            item.addEventListener('pointerenter', () => closeBelow(depth));
        }

        items.push(item);
        panel.append(item);
    }

    panel.addEventListener('keydown', (event) => onPanelKey(event, items, depth));
    document.body.append(panel);

    return { panel, items };
}

function onPanelKey(event, items, depth) {
    const active = document.activeElement;

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const index = items.indexOf(active);
        const step = event.key === 'ArrowDown' ? 1 : -1;
        // Wraps, so Up from the first item lands on the last.
        items[(index + step + items.length) % items.length].focus();
    } else if (event.key === 'Home' || event.key === 'End') {
        event.preventDefault();
        items[event.key === 'Home' ? 0 : items.length - 1].focus();
    } else if (event.key === 'ArrowRight' && active?.getAttribute('aria-haspopup') === 'menu') {
        event.preventDefault();
        active.click();
    } else if (event.key === 'ArrowLeft' && depth > 0) {
        event.preventDefault();
        closeBelow(depth - 1);
        session.openers[depth - 1]?.focus();
    }
}

function openSubmenu(item, entry, depth, { focus = false } = {}) {
    // Already open beside this item, so hovering back onto it should not rebuild it.
    if (session.openers[depth] === item && session.panels.length > depth + 1) {
        return;
    }

    closeBelow(depth);
    session.openers[depth] = item;

    const { panel, items } = buildPanel(entry.submenu, depth + 1, entry.label);
    session.panels.push(panel);
    position(panel, item.getBoundingClientRect(), { sideways: true });
    item.setAttribute('aria-expanded', 'true');

    if (focus) {
        items[0].focus();
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

    session = { panels: [], openers: [], trigger, controller };

    const { panel, items } = buildPanel(entries, 0, trigger.dataset.menuLabel ?? 'Actions');
    session.panels.push(panel);
    position(panel, trigger.getBoundingClientRect(), { sideways: false });

    trigger.setAttribute('aria-expanded', 'true');
    items[0].focus();

    const inside = (target) => session?.panels.some((one) => one.contains(target));

    on(document, 'keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();

            // Escape closes one level at a time, so a submenu opened by mistake is cheap to undo.
            if (session.panels.length > 1) {
                const depth = session.panels.length - 2;
                closeBelow(depth);
                session.openers[depth]?.setAttribute('aria-expanded', 'false');
                session.openers[depth]?.focus();
            } else {
                closeAll();
            }
        }
    });

    // Tabbing out, or clicking anywhere else, dismisses it without stealing focus back.
    on(document, 'focusin', (event) => {
        if (!inside(event.target)) {
            closeAll({ restoreFocus: false });
        }
    }, { capture: true });

    on(document, 'pointerdown', (event) => {
        if (!inside(event.target) && event.target !== trigger) {
            closeAll({ restoreFocus: false });
        }
    });

    // Fixed positioning does not follow a scroll, so the panels would drift off their rows.
    on(window, 'scroll', () => closeAll({ restoreFocus: false }), { capture: true });
    on(window, 'resize', () => closeAll({ restoreFocus: false }));
}
