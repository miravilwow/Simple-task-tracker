/**
 * App shell. The fixed sidebar and every sticky card are positioned against `--header-height`,
 * so that variable has to hold the navbar's real height rather than a constant that happens to
 * be right today. It is not: a text-only zoom, a larger default font, or a longer brand name all
 * make the bar taller, and the sidebar would then start behind it.
 *
 * Measuring the element and writing the result back keeps the two in step. Without this script
 * the CSS default still applies, so the boxed landing page needs no JavaScript for its header.
 */
const header = document.querySelector('[data-app-header]');

if (header) {
    // getBoundingClientRect, not offsetHeight: the latter rounds, and a fractional device pixel
    // ratio would then leave a hairline of content showing between the navbar and the sidebar.
    const sync = () =>
        document.documentElement.style.setProperty(
            '--header-height',
            `${header.getBoundingClientRect().height}px`,
        );

    new ResizeObserver(sync).observe(header);
    sync();
}
