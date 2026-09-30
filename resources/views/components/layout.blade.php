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
<html lang="en" class="motion-safe:scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>

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
        `app-header` sets the height on the <header>, not the <nav>, so the bottom border counts
        inside --header-height. Anything positioned against that variable then meets the header
        exactly instead of landing a pixel short of it.
        The shadow separates the bar from whatever scrolls beneath it.
    --}}
    <header @if ($fluid) data-app-header @endif @class([
        'border-b border-gray-200 bg-white',
        'app-header sticky top-0 z-30 shadow-sm' => $fluid,
    ])>
        <nav aria-label="Main" @class([
            'flex items-center justify-between gap-4',
            'h-full app-container' => $fluid,
            'mx-auto w-full max-w-6xl px-4 py-3 sm:px-6 lg:px-8' => ! $fluid,
        ])>
            <a href="{{ route('home') }}"
                class="flex items-center gap-2 rounded-md font-semibold focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none">
                <svg class="size-7 shrink-0" viewBox="0 0 32 32" aria-hidden="true">
                    <rect width="32" height="32" rx="8" class="fill-indigo-600" />
                    <path d="M10 16.5l4 4 8-9" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Simple Task Tracker
            </a>

            @if (request()->routeIs('tasks.index'))
                <a href="{{ route('home') }}"
                    class="rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none">
                    Home
                </a>
            @else
                <a href="{{ route('tasks.index') }}"
                    class="inline-flex min-h-10 shrink-0 items-center rounded-md bg-indigo-600 px-4 text-sm font-medium text-white hover:bg-indigo-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:outline-none">
                    Open app
                </a>
            @endif
        </nav>
    </header>

    {{ $aside }}

    <main id="main" class="flex-1">
        {{ $slot }}
    </main>

    <footer class="border-t border-gray-200 bg-white">
        <div @class([
            'py-6 text-sm text-gray-500',
            'app-container' => $fluid,
            'mx-auto max-w-6xl px-4 sm:px-6 lg:px-8' => ! $fluid,
        ])>
            &copy; {{ date('Y') }} Simple Task Tracker &middot; Built with Laravel, Tailwind CSS, and vanilla JavaScript.
        </div>
    </footer>
</body>
</html>
