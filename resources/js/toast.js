/**
 * Toasts.
 *
 * The look and the call shape follow Sonner (MIT) as shadcn/ui wraps it — a card with an icon, a
 * title, a quieter description under it, and an action on the right — rebuilt here for vanilla JS,
 * the same way the sidebar follows shadcn's Sidebar. Sonner itself needs React, and its classes
 * (`bg-background`, `text-muted-foreground`) need a token layer this project's Tailwind does not
 * have, so they would render as nothing.
 *
 * Two things in the original are deliberately left out. Its theme hook is for dark mode, which
 * this project does not have, so it would be a control that never fires. `toast.promise` has no
 * caller here: a request in flight is already shown on the button that started it, which is where
 * the person who pressed it is looking.
 */
import { createElement, createIcon } from './dom.js';

const region = document.getElementById('toast-region');

// Beyond this the newest toast is pushing older ones off the screen, which is a tower of things
// nobody read. The oldest goes rather than the newest, because the newest is what just happened.
const MAX_VISIBLE = 3;

// An action has to be noticed and then reached, which 3s does not allow for; a message with
// nothing to click still goes at the usual pace.
const LIFETIME = { plain: 3000, withAction: 8000 };

const VARIANTS = {
    default: { icon: null, tone: '' },
    success: { icon: 'check-circle', tone: 'text-green-600' },
    error: { icon: 'warning', tone: 'text-red-600' },
};

function actionButton(action, toast) {
    const button = createElement(
        'button',
        'btn-toast-action',
        action.label,
    );

    button.type = 'button';
    button.addEventListener('click', () => {
        toast.remove();
        action.onClick();
    });

    return button;
}

/**
 * @param {string} message the one line that always shows
 * @param {{description?: string, variant?: string, action?: {label: string, onClick: Function}}} options
 */
function show(message, { description = '', variant = 'default', action = null } = {}) {
    const { icon, tone } = VARIANTS[variant] ?? VARIANTS.default;
    const toast = createElement('div', 'toast-card toast-enter');

    if (icon) {
        toast.append(createIcon(icon, `size-5 shrink-0 ${tone}`));
    }

    const text = createElement('div', 'min-w-0 flex-1');

    text.append(createElement('p', 'text-sm font-medium text-gray-900', message));

    // Task and project names are user input, so every one of these is set as text and never as
    // markup. createElement does that for us.
    if (description) {
        text.append(createElement('p', 'mt-0.5 text-sm wrap-break-word text-gray-500', description));
    }

    toast.append(text);

    if (action) {
        toast.append(actionButton(action, toast));

        // #toast-region is pointer-events-none so a toast never swallows a click meant for the
        // page behind it. A toast with something to press has to take its clicks back.
        toast.classList.add('pointer-events-auto');
    }

    region.append(toast);

    while (region.children.length > MAX_VISIBLE) {
        region.firstElementChild.remove();
    }

    setTimeout(() => toast.remove(), action ? LIFETIME.withAction : LIFETIME.plain);

    return toast;
}

/**
 * `toast(message, options)` for something that merely happened, with `.success` and `.error` for
 * the two that carry a mark. The shape is Sonner's, so a call reads the same here as it does in
 * the component this was ported from.
 */
export const toast = Object.assign(
    (message, options = {}) => show(message, options),
    {
        success: (message, options = {}) => show(message, { ...options, variant: 'success' }),
        error: (message, options = {}) => show(message, { ...options, variant: 'error' }),
    },
);
