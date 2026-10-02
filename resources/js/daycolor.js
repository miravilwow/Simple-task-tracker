/**
 * The colour someone gives a calendar day. It is decoration for their own sorting of the calendar,
 * so it lives in this browser's storage rather than in the API. Storage can be blocked, so the
 * colours are held in memory too: a blocked write only means they are not kept for next time.
 */
const STORAGE_KEY = 'day_colors';

// Full class strings, so Tailwind finds them. One step stronger than a card's tint, so a card
// sitting on the day still reads against it.
export const DAY_TINTS = {
    red: 'bg-red-100',
    orange: 'bg-orange-100',
    yellow: 'bg-yellow-100',
    green: 'bg-green-100',
    teal: 'bg-teal-100',
    blue: 'bg-blue-100',
    purple: 'bg-purple-100',
    pink: 'bg-pink-100',
};

function load() {
    try {
        const stored = JSON.parse(localStorage.getItem(STORAGE_KEY));

        return stored && typeof stored === 'object' ? stored : {};
    } catch {
        return {};
    }
}

const colors = load();

export const dayColorOf = (iso) => (Object.hasOwn(DAY_TINTS, colors[iso]) ? colors[iso] : null);

export function setDayColor(iso, color) {
    if (color) {
        colors[iso] = color;
    } else {
        delete colors[iso];
    }

    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(colors));
    } catch {
        // Held in memory above; only the next visit loses it.
    }
}
