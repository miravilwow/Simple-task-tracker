/**
 * Sidebar behaviour, following shadcn/ui's Sidebar: a drawer below lg, a sticky column that
 * collapses to an icon rail above it, toggled with Ctrl/Cmd+B and remembered in a cookie.
 */
const sidebar = document.querySelector('[data-sidebar]');
const backdrop = document.querySelector('[data-sidebar-backdrop]');
const trigger = document.querySelector('[data-sidebar-trigger]');
const rail = document.querySelector('[data-sidebar-rail]');
const railIcon = document.querySelector('[data-sidebar-rail-icon]');
const closeButton = document.querySelector('[data-sidebar-close]');

const DESKTOP = window.matchMedia('(min-width: 64rem)');
const COOKIE_MAX_AGE = 60 * 60 * 24 * 7;

const isCollapsed = () => sidebar.dataset.state === 'collapsed';

function syncRail() {
    const collapsed = isCollapsed();

    rail.setAttribute('aria-expanded', String(!collapsed));
    // The chevron points back toward the content when the rail is already closed.
    railIcon.classList.toggle('rotate-180', collapsed);
}

function setCollapsed(collapsed) {
    sidebar.dataset.state = collapsed ? 'collapsed' : 'expanded';
    // The shell reads this to size the gap it leaves for the fixed sidebar.
    document.body.dataset.sidebarState = sidebar.dataset.state;
    document.cookie = `sidebar_state=${sidebar.dataset.state}; path=/; max-age=${COOKIE_MAX_AGE}; samesite=lax`;
    syncRail();
}

export function openDrawer() {
    sidebar.dataset.open = 'true';
    trigger.setAttribute('aria-expanded', 'true');
    backdrop.classList.remove('hidden');
    closeButton.focus();
}

export function closeDrawer() {
    if (sidebar.dataset.open !== 'true') {
        return;
    }

    delete sidebar.dataset.open;
    trigger.setAttribute('aria-expanded', 'false');
    backdrop.classList.add('hidden');
    trigger.focus();
}

trigger.addEventListener('click', openDrawer);
closeButton.addEventListener('click', closeDrawer);
backdrop.addEventListener('click', closeDrawer);
rail.addEventListener('click', () => setCollapsed(!isCollapsed()));

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeDrawer();

        return;
    }

    if (event.key.toLowerCase() === 'b' && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();

        if (DESKTOP.matches) {
            setCollapsed(!isCollapsed());
        } else if (sidebar.dataset.open === 'true') {
            closeDrawer();
        } else {
            openDrawer();
        }
    }
});

// Leaving the drawer open while the viewport grows would strand the backdrop over the page.
DESKTOP.addEventListener('change', (event) => event.matches && closeDrawer());

syncRail();
