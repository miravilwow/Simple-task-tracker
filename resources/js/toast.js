/**
 * Toasts.
 *
 * The look and the call shape follow Sonner (MIT) as shadcn/ui wraps it — a card with a title, a
 * quieter line under it, and a dark action on the right — rebuilt here for vanilla JS, the same
 * way the sidebar follows shadcn's Sidebar. Sonner itself needs React, and its classes
 * (`bg-background`, `text-muted-foreground`) need a token layer this project's Tailwind does not
 * have, so they would render as nothing.
 *
 * Two things in the original are deliberately left out. Its theme hook is for dark mode, which
 * this project does not have, so it would be a control that never fires. `toast.promise` has no
 * caller here: a request in flight is already shown on the button that started it, which is where
 * the person who pressed it is looking.
 */
import { createElement } from './dom.js';

const region = document.getElementById('toast-region');

// Beyond this the newest toast is pushing older ones off the screen, which is a tower of things
// nobody read. The oldest goes rather than the newest, because the newest is what just happened.
const MAX_VISIBLE = 3;

// An action has to be noticed and then reached, which 3s does not allow for; a message with
// nothing to click still goes at the usual pace.
const LIFETIME = { plain: 3000, withAction: 8000 };

const dayFormat = new Intl.DateTimeFormat('en-US', {
    weekday: 'long',
    month: 'long',
    day: '2-digit',
    year: 'numeric',
});

const timeFormat = new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit' });

/**
 * "Sunday, December 03, 2023 at 9:00 AM" for any other day, and "Today at 9:00 AM" for this one.
 *
 * Nearly every toast reports something that just happened, and spelling out today's weekday and
 * year to say "a moment ago" is a line of noise that also pushes the detail beside it onto a
 * second row. The long form is kept for the dates that are worth reading in full — a task's own
 * due date, which is the case the design this follows was showing.
 */
export function stamp(date = new Date()) {
    const isToday = date.toDateString() === new Date().toDateString();

    return `${isToday ? 'Today' : dayFormat.format(date)} at ${timeFormat.format(date)}`;
}

function actionButton(action, toast) {
    const button = createElement('button', 'btn-toast-action', action.label);

    button.type = 'button';
    button.addEventListener('click', () => {
        toast.remove();
        action.onClick();
    });

    return button;
}

/**
 * @param {string} message the bold line that always shows
 * @param {{description?: string, at?: Date|null, variant?: string, action?: object}} options
 */
function show(message, { description = '', at = new Date(), variant = 'default', action = null } = {}) {
    const toast = createElement('div', 'toast-card toast-enter');
    const text = createElement('div', 'min-w-0 flex-1');

    // No icon, and the two lines carry the whole message. An error is told apart by its title
    // rather than by a mark, and the title is the server's own words, so the meaning is never in
    // the colour alone.
    text.append(
        createElement('p', `toast-title ${variant === 'error' ? 'text-red-600' : 'text-gray-900'}`, message),
    );

    // Task and project names are user input, so every one of these is set as text and never as
    // markup. createElement does that for us.
    const detail = [description, at ? stamp(at) : ''].filter(Boolean).join(' · ');

    if (detail) {
        text.append(createElement('p', 'toast-description', detail));
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
 * `toast(message, options)` for something that merely happened, with `.success` and `.error`
 * beside it. The shape is Sonner's, so a call reads the same here as in the component this was
 * ported from.
 */
export const toast = Object.assign(
    (message, options = {}) => show(message, options),
    {
        success: (message, options = {}) => show(message, { ...options, variant: 'success' }),
        error: (message, options = {}) => show(message, { ...options, variant: 'error' }),
    },
);
