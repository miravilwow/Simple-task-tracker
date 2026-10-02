{{--
    The app's mark: a striped isometric cube, redrawn as SVG from the supplied artwork.

    It is drawn rather than linked as the original JPG for three reasons. The JPG carries a white
    rectangle behind the cube, which would show as a box against the navbar; it is 390KB for a mark
    that renders at 24px; and a raster mark cannot take a size from its container. The geometry is a
    regular hexagon split into three faces, so one set of coordinates draws all of it.

    The colours are the logo's own and are deliberately literal, not Tailwind classes. A logo keeps
    its colours when the interface around it changes; it is the one thing on the page that is not
    part of the palette.
--}}
@props(['title' => null])

<svg {{ $attributes->merge(['class' => 'size-6 shrink-0']) }} viewBox="0 0 32 32"
    @if ($title) role="img" @else aria-hidden="true" @endif>
    @if ($title)
        <title>{{ $title }}</title>
    @endif

    {{-- Each face is clipped to its own quarter of the hexagon, so the stripes can run straight
         across it and stop exactly on the edge without any being drawn by hand. --}}
    <defs>
        <clipPath id="logo-top"><path d="M16 1.5 29 9 16 16.5 3 9Z" /></clipPath>
        <clipPath id="logo-left"><path d="M3 9 16 16.5 16 31.5 3 24Z" /></clipPath>
        <clipPath id="logo-right"><path d="M29 9 29 24 16 31.5 16 16.5Z" /></clipPath>
    </defs>

    <g clip-path="url(#logo-top)">
        <path d="M16 1.5 29 9 16 16.5 3 9Z" fill="#D81E26" />
        <g stroke="#fff" stroke-width="2.2">
            <path d="M20.55 1.875 3.65 11.625" />
            <path d="M23.15 3.375 6.25 13.125" />
            <path d="M25.75 4.875 8.85 14.625" />
            <path d="M28.35 6.375 11.45 16.125" />
        </g>
    </g>

    <g clip-path="url(#logo-left)">
        <path d="M3 9 16 16.5 16 31.5 3 24Z" fill="#D81E26" />
        <g stroke="#fff" stroke-width="2.2">
            <path d="M5.6 8.5V27.5" />
            <path d="M8.2 10V29" />
            <path d="M10.8 11.5V30.5" />
            <path d="M13.4 13V32" />
        </g>
    </g>

    {{-- The face turned away from the light. One darker red is what makes the drawing read as a
         cube rather than as a flat hexagon. --}}
    <g clip-path="url(#logo-right)">
        <path d="M29 9 29 24 16 31.5 16 16.5Z" fill="#AF1319" />
        <g stroke="#fff" stroke-width="2.2">
            <path d="M30.95 11.625 14.05 21.375" />
            <path d="M30.95 15.375 14.05 25.125" />
            <path d="M30.95 19.125 14.05 28.875" />
        </g>
    </g>
</svg>
