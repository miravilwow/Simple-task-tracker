{{--
    `fluid` switches the page from the boxed marketing shell to the full-bleed app shell: the
    navbar sticks, the `aside` slot holds a fixed sidebar, and the content and footer are pushed
    clear of it. `aside` renders outside `<main>` because a sidebar is navigation, not content.
--}}
@props(['title' => 'Simple Task Tracker', 'fluid' => false, 'aside' => null])

@php
    // Mirrors the sidebar's own cookie read, so the content offset is already right on first paint.
    $sidebarState = request()->cookie('sidebar_state') === 'collapsed' ? 'collapsed' : 'expanded';
@endphp

<!DOCTYPE html>
{{-- overscroll-y-none stops the rubber band at the top of the page; see app.css. --}}
<html lang="en" @class(['motion-safe:scroll-smooth', 'overscroll-y-none' => $fluid])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>

    {{-- The SVG is offered first and the .ico is the fallback, so a browser that understands both
         takes the one that stays sharp on a high-density screen. --}}
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet">

    @vite('resources/css/app.css')
    @stack('scripts')
</head>
<body class="flex min-h-screen flex-col bg-gray-50 font-sans text-gray-900 antialiased"
    @if ($fluid) data-app-shell data-sidebar-state="{{ $sidebarState }}" @endif>
    <a href="#main"
        class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:shadow-sm">
        Skip to content
    </a>

    {{--
        Fixed, not sticky, and for the same reason as the sidebar: the two are one chrome layer,
        and only a shared positioning scheme keeps them locked together. A sticky navbar rides the
        document, so an overscroll bounce slides it down over the fixed sidebar's heading.

        `app-header` sets the height on the <header>, not the <nav>, so the bottom border counts
        inside --header-height. Anything positioned against that variable then meets the header
        exactly instead of landing a pixel short of it.
    --}}
    {{-- The header is the flex row, so its `min-height` is what centres the nav. `h-full` on the
         nav would resolve against a height the header does not have, and silently do nothing. --}}
    <header @if ($fluid) data-app-header @endif @class([
        'border-b border-gray-200 bg-white',
        'app-header flex items-center fixed inset-x-0 top-0 z-30 shadow-sm' => $fluid,
    ])>
        <nav aria-label="Main" @class([
            'flex items-center justify-between gap-4',
            'app-container' => $fluid,
            'mx-auto w-full max-w-6xl px-4 py-3 sm:px-6 lg:px-8' => ! $fluid,
        ])>
            {{-- The negative margin cancels the padding, so the focus ring has room to breathe
                 without moving the logo off the gutter the sidebar's icons sit on. --}}
            <a href="{{ route('tasks.index') }}"
                class="-mx-2 flex items-center gap-2 rounded-md px-2 py-1 font-semibold focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none">
                {{-- 24px mark plus an 8px gap is the 32px the sidebar's 20px icon and 12px gap
                     reach, so the wordmark starts exactly where the sidebar's labels do. --}}
                <x-logo />
                Simple Task Tracker
            </a>

            {{-- There is no second page to link to any more, so the bar carries the date instead
                 of an action that would only lead back here. --}}
            <p class="-mr-3 hidden min-h-10 items-center px-3 text-sm text-gray-500 sm:inline-flex">
                {{ now()->format('D, M j') }}
            </p>
        </nav>
    </header>

    {{ $aside }}

    <main id="main" class="flex-1">
        {{ $slot }}
    </main>

</body>
</html>
