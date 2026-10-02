<x-layout title="Your tasks · Simple Task Tracker" :fluid="true">
    @push('scripts')
        @vite('resources/js/app.js')
    @endpush

    @php
        $views = [
            ['key' => 'all', 'label' => 'All tasks', 'icon' => 'list', 'count' => 'total'],
            ['key' => 'today', 'label' => 'Today', 'icon' => 'clock', 'count' => 'due_today'],
            ['key' => 'upcoming', 'label' => 'Upcoming', 'icon' => 'calendar', 'count' => null],
            ['key' => 'overdue', 'label' => 'Overdue', 'icon' => 'warning', 'count' => 'overdue'],
            ['key' => 'completed', 'label' => 'Completed', 'icon' => 'check-circle', 'count' => 'completed'],
        ];
    @endphp

    <x-slot:aside>
        <x-sidebar id="sidebar">
            <x-slot:header>
                <p class="truncate font-medium">Views</p>
            </x-slot:header>

            <x-sidebar.group>
                <nav aria-label="Task views">
                    <ul class="flex flex-col gap-1">
                        @foreach ($views as $view)
                            <li>
                                <x-sidebar.menu-button :icon="$view['icon']" :label="$view['label']"
                                    :badge="$view['count']" :active="$view['key'] === 'all'"
                                    data-view="{{ $view['key'] }}" />
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </x-sidebar.group>

            <x-sidebar.group label="Favorites" id="favorites-group" class="hidden">
                <ul id="favorite-list" class="flex flex-col gap-1"></ul>
            </x-sidebar.group>

            <x-sidebar.group label="My projects">
                <x-slot:action>
                    <button type="button" id="project-new" class="sidebar-menu-action">
                        <x-icon name="plus" class="size-4" />
                        <span class="sr-only">New project</span>
                    </button>
                </x-slot:action>

                <ul id="category-list" class="flex flex-col gap-1"></ul>
                <p id="category-empty" class="sidebar-collapsible hidden px-3 py-2 text-sm text-gray-500">
                    No projects yet.
                </p>

                {{-- A deleted project is recoverable, and the undo toast lasts seconds. This is
                     where it stays reachable afterwards, so nothing is ever quietly stranded. --}}
                <button type="button" id="deleted-toggle" aria-expanded="false" aria-controls="deleted-list"
                    class="sidebar-collapsible mt-1 hidden sidebar-menu-button text-gray-500">
                    <x-icon name="trash" class="size-5 shrink-0" />
                    <span class="sidebar-collapsible flex-1 truncate text-left">Deleted</span>
                    <span id="deleted-count" class="sidebar-collapsible sidebar-menu-badge"></span>
                </button>
                <ul id="deleted-list" class="mt-1 hidden flex-col gap-1"></ul>
            </x-sidebar.group>

            <x-slot:footer>
                <p class="px-3 text-xs text-gray-400">
                    Press <kbd class="rounded border border-gray-300 bg-gray-50 px-1 font-sans">Ctrl</kbd> +
                    <kbd class="rounded border border-gray-300 bg-gray-50 px-1 font-sans">B</kbd> to toggle
                </p>
            </x-slot:footer>
        </x-sidebar>
    </x-slot:aside>

    <div class="app-container py-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <x-sidebar.trigger />
                <div>
                    <h1 id="view-title" tabindex="-1" class="text-2xl font-semibold tracking-tight focus:outline-none">All tasks</h1>
                    <p id="view-subtitle" class="text-sm text-gray-500">Create tasks, set priorities, and track what's done.</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                {{-- One control for the layout, the grouping, the sorting and the filters. They
                     all answer "what am I looking at", so they belong behind one button rather
                     than spread across the header and the task panel. --}}
                <div class="relative">
                    <button type="button" id="display-trigger" class="btn-secondary" aria-expanded="false"
                        aria-controls="display-panel" aria-haspopup="true">
                        <x-icon name="display" class="size-4" />
                        Display
                    </button>

                    <div id="display-panel" role="group" aria-label="Display options" hidden
                        class="absolute right-0 top-[calc(100%+0.5rem)] z-30 w-80 overflow-hidden rounded-xl border border-gray-200 bg-white text-left shadow-lg">
                        <div class="p-4">
                            <h2 id="layout-label" class="mb-2.5 font-medium">Layout</h2>
                            <div role="group" aria-labelledby="layout-label" class="grid grid-cols-2 gap-1 rounded-lg bg-gray-100 p-1">
                                @foreach ([['list', 'List', 'list'], ['board', 'Board', 'board']] as [$mode, $label, $icon])
                                    <button type="button" data-mode="{{ $mode }}"
                                        aria-pressed="{{ $mode === 'list' ? 'true' : 'false' }}"
                                        class="layout-choice disabled:cursor-not-allowed disabled:opacity-40">
                                        <x-icon :name="$icon" class="size-5" />
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>

                            <div class="mt-1 flex min-h-11 items-center justify-between gap-3">
                                <span id="completed-label">Completed tasks</span>
                                <button type="button" id="show-completed" role="switch" aria-checked="true"
                                    aria-labelledby="completed-label" class="switch"></button>
                            </div>
                        </div>

                        @foreach ([
                            ['sort', 'Sort', [
                                ['grouping', 'Grouping', ['status' => 'Status', 'priority' => 'Priority', 'project' => 'Project', 'none' => 'None']],
                                ['sorting', 'Sorting', ['default' => 'Default', 'due' => 'Due date', 'name' => 'Name', 'manual' => 'Manual']],
                            ]],
                            ['filter', 'Filter', [
                                ['filter-date', 'Date', ['' => 'All', 'overdue' => 'Overdue', 'today' => 'Today', 'upcoming' => 'Upcoming', 'none' => 'No date']],
                                ['filter-priority', 'Priority', ['' => 'All', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low']],
                            ]],
                        ] as [$key, $heading, $fields])
                            <div class="border-t border-gray-200 p-4">
                                <button type="button" data-section="{{ $key }}-body" aria-expanded="true"
                                    aria-controls="{{ $key }}-body"
                                    class="flex w-full items-center justify-between gap-2 font-medium focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none">
                                    {{ $heading }}
                                    <x-icon name="chevron-down" class="size-4 text-gray-500 transition-transform aria-[expanded=false]:-rotate-90" />
                                </button>

                                <div id="{{ $key }}-body">
                                    @foreach ($fields as [$id, $label, $options])
                                        <div class="flex min-h-11 items-center justify-between gap-3">
                                            <label for="{{ $id }}">{{ $label }}</label>
                                            <select id="{{ $id }}" class="display-select">
                                                @foreach ($options as $value => $text)
                                                    <option value="{{ $value }}">{{ $text }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                        <div class="border-t border-gray-200 p-4">
                            <button type="button" id="display-reset"
                                class="text-sm font-medium text-red-600 underline underline-offset-2 hover:text-red-700 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none">
                                Reset to default
                            </button>
                        </div>
                    </div>
                </div>

                <button type="button" id="new-task-trigger"
                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-gray-900 px-4 text-sm font-medium text-white transition-colors hover:bg-gray-800 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 focus-visible:outline-none">
                    <x-icon name="plus" class="size-4" />
                    New task
                </button>
            </div>
        </div>


        {{-- The chips name every active Display setting. They sit above all three layouts, not
             inside the list panel: the board is where nothing else says what is filtered. --}}
        <div id="display-summary" class="mt-4 flex flex-wrap items-center gap-1.5"></div>

        <div id="load-error" class="mt-6 hidden" role="alert">
            <div class="flex flex-col gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 sm:flex-row sm:items-center sm:justify-between">
                <span class="flex items-center gap-2">
                    <x-icon name="warning" class="size-5 shrink-0" />
                    <span id="load-error-message">Couldn't load tasks. Try again.</span>
                </span>
                <button type="button" id="retry-button"
                    class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg border border-red-300 bg-white px-3 font-medium transition-colors hover:bg-red-100 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none">
                    Retry
                </button>
            </div>
        </div>

        {{-- List layout --}}
        {{-- With the form behind a dialog, the panel has the whole width to itself. --}}
        <div id="list-view" class="mt-6">
            <section aria-labelledby="tasks-heading"
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    {{-- tabindex allows focus to return here after a task row is removed. --}}
                    <h2 id="tasks-heading" tabindex="-1" class="font-medium focus:outline-none">Tasks</h2>

                </div>

                <div id="column-headers" aria-hidden="true"
                    class="hidden gap-4 border-b border-gray-200 bg-gray-50 px-6 py-2 text-xs font-medium text-gray-500 md:task-columns">
                    <span>Task</span>
                    <span>Priority</span>
                    <span>Status</span>
                    <span class="text-right">Actions</span>
                </div>

                {{-- Skeleton rows hold the layout steady on first load. --}}
                <div id="skeleton" class="divide-y divide-gray-200">
                    @for ($i = 0; $i < 3; $i++)
                        <div class="flex animate-pulse items-center gap-4 px-4 py-4 sm:px-6">
                            <div class="flex-1 space-y-2">
                                <div class="h-4 w-1/2 rounded bg-gray-200"></div>
                                <div class="h-3 w-1/3 rounded bg-gray-100"></div>
                            </div>
                            <div class="h-6 w-16 rounded-full bg-gray-100"></div>
                            <div class="hidden h-9 w-24 rounded-lg bg-gray-100 md:block"></div>
                        </div>
                    @endfor
                </div>

                <div id="list-message" class="hidden flex-col items-center gap-3 px-4 py-14 text-center">
                    <span class="flex size-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                        <x-icon name="inbox" class="size-6" />
                    </span>
                    <p id="list-message-text" class="text-sm text-gray-500"></p>
                </div>

                <ul id="task-list" class="divide-y divide-gray-200"></ul>
            </section>
        </div>

        {{-- Board layout --}}
        {{-- The columns are whatever the Display panel groups by, so they are built in JS rather
             than fixed here. `md:grid` sits in a media query and would win over `hidden`, so the
             grid classes are added when the view opens, exactly as the calendar's are. --}}
        <div id="board-view" class="mt-6 hidden gap-4 md:grid-cols-[repeat(auto-fit,minmax(15rem,1fr))] xl:gap-6"></div>
        <p id="board-help" class="sr-only">
            Press Enter to open a task. Press Space to pick it up, the left and right arrows to move
            it between columns, Space again to drop it, or Escape to cancel.
        </p>

        {{-- Calendar layout --}}

        {{-- The grid classes are added by JS when this view opens: `xl:grid` sits in a media
             query and would otherwise win over `hidden` on wide screens. --}}
        <div id="calendar-view" class="mt-6 hidden gap-6 xl:grid-cols-4 xl:items-start">
            <section aria-labelledby="calendar-heading"
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm xl:col-span-3">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-4 sm:px-6">
                    <h2 id="calendar-heading" tabindex="-1" class="font-medium focus:outline-none">
                        <span id="calendar-month">Calendar</span>
                    </h2>
                    <div class="flex items-center gap-1">
                        <button type="button" id="calendar-prev" aria-label="Previous month"
                            class="flex size-10 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 transition-colors hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none">
                            <x-icon name="chevron-left" class="size-4" />
                        </button>
                        <button type="button" id="calendar-today"
                            class="inline-flex min-h-10 items-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none">
                            Today
                        </button>
                        <button type="button" id="calendar-next" aria-label="Next month"
                            class="flex size-10 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 transition-colors hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none">
                            <x-icon name="chevron-right" class="size-4" />
                        </button>
                    </div>
                </div>

                {{-- Dragging only exists on the month grid, which itself only appears from md. --}}
                {{-- Grey, not the accent's tint. A pale red panel with dark red text is exactly what
                     the load error above is, and a hint that looks like an error is worse than no
                     hint. This one has nothing to announce; it is only telling you what you can do. --}}
                <p class="border-b border-gray-200 bg-gray-50 px-4 py-2 text-xs text-gray-600 sm:px-6">
                    <span class="hidden md:inline">Drag a task onto a day to reschedule it, or open a task to pick a date.</span>
                    <span class="md:hidden">Tap a task to change its date.</span>
                </p>

                {{-- Month grid: pointer-friendly, and only worth showing once the cells have room. --}}
                <div class="hidden md:block">
                    <div aria-hidden="true" class="grid grid-cols-7 border-b border-gray-200 bg-gray-50 text-center text-xs font-medium text-gray-500">
                        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day)
                            <span class="py-2">{{ $day }}</span>
                        @endforeach
                    </div>
                    <div id="calendar-grid" class="grid grid-cols-7"></div>
                </div>

                {{-- Below md a month grid gives each day about 50px, so an agenda reads better. --}}
                <div id="calendar-agenda" class="divide-y divide-gray-200 md:hidden"></div>
            </section>

            {{-- Ordered into the first column so the tray lands where the New task form sits in
                 the list layout, and switching views moves nothing sideways. --}}
            <section aria-labelledby="unscheduled-heading"
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm xl:order-first xl:sticky xl:app-sticky-top">
                <div class="flex items-center gap-2 border-b border-gray-200 px-4 py-3">
                    <x-icon name="inbox-stack" class="size-5 text-gray-500" />
                    <h2 id="unscheduled-heading" class="font-medium">No due date</h2>
                    <span id="unscheduled-count" class="ml-auto text-xs text-gray-400 tabular-nums"></span>
                </div>
                <ul id="unscheduled-list" class="max-h-96 space-y-2 overflow-y-auto p-3"></ul>
                <p id="unscheduled-empty" class="hidden px-4 py-6 text-center text-sm text-gray-500">
                    Every task has a date.
                </p>
            </section>
        </div>
    </div>

    {{-- A real dialog replaces window.confirm: it can be styled, and showModal traps focus and closes on Escape. --}}
    <dialog id="confirm-dialog"
        class="m-auto w-[calc(100%-2rem)] max-w-md rounded-xl border border-gray-200 p-0 shadow-xl backdrop:bg-gray-900/40">
        <div class="flex gap-4 p-6">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">
                <x-icon name="warning" class="size-6" />
            </span>
            <div class="min-w-0">
                <h2 id="confirm-title" class="font-medium">Delete task</h2>
                <p id="confirm-message" class="mt-1 text-sm wrap-break-word text-gray-600"></p>
            </div>
        </div>
        <div class="flex flex-col-reverse gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4 sm:flex-row sm:justify-end">
            <button type="button" id="confirm-cancel" class="btn-secondary">
                Cancel
            </button>
            <button type="button" id="confirm-accept"
                class="inline-flex min-h-10 items-center justify-center rounded-lg bg-red-600 px-4 text-sm font-medium text-white transition-colors hover:bg-red-700 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 focus-visible:outline-none">
                Delete
            </button>
        </div>
    </dialog>

    {{-- Dragging is a pointer-only gesture, so rescheduling also has to work from a dialog. --}}
    {{-- The New task form lives here rather than in the page, so the list keeps the full width.
         A <dialog> clips a floating panel, so its date field is the inline grid. --}}
    <dialog id="task-dialog"
        class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg rounded-xl border border-gray-200 p-0 shadow-xl backdrop:bg-gray-900/40">
        <div class="flex items-center gap-2 border-b border-gray-200 px-6 py-4">
            <x-icon name="plus" class="size-5 text-red-600" />
            <h2 id="new-task-heading" class="font-medium">New task</h2>
        </div>

        <div class="space-y-4 p-6">
<form id="task-form" novalidate>
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700">
                            Title <span class="text-red-600" aria-hidden="true">*</span>
                        </label>
                        <input id="title" name="title" type="text" maxlength="255" required
                            placeholder="e.g. Fix the checkout timeout" aria-describedby="title-error"
                            class="mt-1.5 block min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm transition-colors placeholder:text-gray-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/30 focus:outline-none aria-invalid:border-red-500 aria-invalid:ring-2 aria-invalid:ring-red-500/20">
                        <p id="title-error" class="mt-1.5 hidden text-sm text-red-600"></p>
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">
                            Description <span class="font-normal text-gray-500">(optional)</span>
                        </label>
                        <textarea id="description" name="description" rows="2" placeholder="Add any details worth remembering"
                            aria-describedby="description-error"
                            class="mt-1.5 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm transition-colors placeholder:text-gray-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/30 focus:outline-none aria-invalid:border-red-500"></textarea>
                        <p id="description-error" class="mt-1.5 hidden text-sm text-red-600"></p>
                    </div>

                    <fieldset>
                        <legend class="block text-sm font-medium text-gray-700">Priority</legend>
                        <p id="priority-error" class="mt-1.5 hidden text-sm text-red-600"></p>

                        {{-- Radio cards make the three options visible at once, unlike a closed select. --}}
                        <div class="mt-1.5 grid grid-cols-3 gap-2">
                            @foreach ([
                                'low' => ['label' => 'Low', 'active' => 'has-checked:border-slate-400 has-checked:bg-slate-50 has-checked:text-slate-800'],
                                'medium' => ['label' => 'Medium', 'active' => 'has-checked:border-amber-400 has-checked:bg-amber-50 has-checked:text-amber-900'],
                                'high' => ['label' => 'High', 'active' => 'has-checked:border-red-400 has-checked:bg-red-50 has-checked:text-red-800'],
                            ] as $value => $option)
                                <label
                                    class="flex min-h-11 cursor-pointer items-center justify-center rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50 has-focus-visible:ring-2 has-focus-visible:ring-red-500 has-focus-visible:ring-offset-2 {{ $option['active'] }}">
                                    <input type="radio" name="priority" value="{{ $value }}" class="sr-only"
                                        @checked($value === 'medium')>
                                    {{ $option['label'] }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="space-y-4">
                        <div>
                            <label for="category_id" class="block text-sm font-medium text-gray-700">
                                Category <span class="font-normal text-gray-500">(optional)</span>
                            </label>
                            <select id="category_id" name="category_id" aria-describedby="category_id-error"
                                class="mt-1.5 block min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-red-500 focus:ring-2 focus:ring-red-500/30 focus:outline-none aria-invalid:border-red-500">
                                <option value="">No category</option>
                            </select>
                            <p id="category_id-error" class="mt-1.5 hidden text-sm text-red-600"></p>
                        </div>

                        <x-date-field id="due_date" name="due_date" label="Due date" :optional="true" :inline="true"
                            describedby="due_date-error">
                            <p id="due_date-error" class="mt-1.5 hidden text-sm text-red-600"></p>
                        </x-date-field>
                    </div>

                    <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        {{-- Not btn-secondary: this dialog's buttons are 44px to match its Save,
                             where every other dialog uses 40px. --}}
                        <button type="button" id="task-cancel"
                            class="btn-secondary min-h-11">
                            Cancel
                        </button>
                        <button type="submit" id="submit-button"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-gray-900 px-4 text-sm font-medium text-white transition-colors hover:bg-gray-800 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60">
                            <x-icon name="plus" class="size-4" />
                            <span data-label>Add task</span>
                        </button>
                    </div>
                </form>
        </div>
    </dialog>

    <dialog id="schedule-dialog"
        class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-xl border border-gray-200 p-0 shadow-xl backdrop:bg-gray-900/40">
        <form id="schedule-form" method="dialog" class="p-6">
            <h2 class="font-medium">Reschedule task</h2>
            <p id="schedule-task-title" class="mt-1 text-sm wrap-break-word text-gray-600"></p>

            <div class="mt-4">
                <x-date-field id="schedule-date" label="Due date" :inline="true" />
            </div>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" id="schedule-clear"
                    class="btn-secondary">
                    Clear date
                </button>
                <button type="button" id="schedule-cancel"
                    class="btn-secondary">
                    Cancel
                </button>
                <button type="button" id="schedule-save"
                    class="inline-flex min-h-10 items-center justify-center rounded-lg bg-gray-900 px-4 text-sm font-medium text-white transition-colors hover:bg-gray-800 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 focus-visible:outline-none">
                    Save
                </button>
            </div>
        </form>
    </dialog>

    {{-- Add and Edit are one dialog. Two would mean two copies of the 224-option icon grid. --}}
    <dialog id="project-dialog"
        class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg rounded-xl border border-gray-200 p-0 shadow-xl backdrop:bg-gray-900/40">
        <form id="project-form" class="flex max-h-[calc(100dvh-2rem)] flex-col" novalidate>
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-6 pb-4">
                <h2 id="project-dialog-title" class="font-medium">New project</h2>
                <button type="button" id="project-close" aria-label="Close"
                    class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none">
                    <x-icon name="close" />
                </button>
            </div>

            <div class="flex-1 space-y-4 overflow-y-auto overscroll-contain p-6">
                <div>
                    <div class="flex items-baseline justify-between gap-2">
                        <label for="project-name" class="block text-sm font-medium text-gray-700">
                            Name <span class="text-red-600" aria-hidden="true">*</span>
                        </label>
                        {{-- A live count, because the server's 40-character limit is otherwise invisible. --}}
                        <span id="project-name-count" class="text-xs text-gray-400">0/40</span>
                    </div>
                    <input id="project-name" name="name" type="text" maxlength="40" required
                        aria-describedby="project-name-error"
                        class="mt-1.5 block min-h-10 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm placeholder:text-gray-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/30 focus:outline-none aria-invalid:border-red-500">
                    <p id="project-name-error" class="mt-1.5 hidden text-sm text-red-600"></p>
                </div>

                <div>
                    <label for="project-description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea id="project-description" name="description" rows="3" maxlength="500"
                        aria-describedby="project-description-error"
                        class="mt-1.5 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm placeholder:text-gray-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/30 focus:outline-none aria-invalid:border-red-500"></textarea>
                    <p id="project-description-error" class="mt-1.5 hidden text-sm text-red-600"></p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="project-color" class="block text-sm font-medium text-gray-700">Color</label>
                        <select id="project-color" name="color" aria-describedby="project-color-error"
                            class="mt-1.5 block min-h-10 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-red-500 focus:ring-2 focus:ring-red-500/30 focus:outline-none">
                            @foreach (\App\Enums\CategoryColor::cases() as $color)
                                <option value="{{ $color->value }}">{{ $color->label() }}</option>
                            @endforeach
                        </select>
                        <p id="project-color-error" class="mt-1.5 hidden text-sm text-red-600"></p>
                    </div>

                    <div>
                        <label for="project-parent" class="block text-sm font-medium text-gray-700">Parent project</label>
                        <select id="project-parent" name="parent_id" aria-describedby="project-parent-error"
                            class="mt-1.5 block min-h-10 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-red-500 focus:ring-2 focus:ring-red-500/30 focus:outline-none">
                            <option value="">No parent</option>
                        </select>
                        <p id="project-parent-error" class="mt-1.5 hidden text-sm text-red-600"></p>
                    </div>
                </div>

                <fieldset>
                    <legend class="block text-sm font-medium text-gray-700">Icon</legend>
                    <div id="icon-grid"
                        class="mt-1.5 hidden max-h-44 flex-wrap gap-1 overflow-y-auto overscroll-contain rounded-lg border border-gray-200 p-2">
                        @foreach (\App\Enums\CategoryIcon::cases() as $icon)
                            <label data-icon="{{ $icon->value }}" title="{{ $icon->label() }}"
                                class="hidden size-8 cursor-pointer items-center justify-center rounded-md border border-transparent text-gray-600 transition-colors hover:bg-gray-100 has-checked:border-gray-400 has-checked:bg-gray-100 has-checked:text-gray-900 has-focus-visible:ring-2 has-focus-visible:ring-red-500">
                                <input type="radio" name="icon" value="{{ $icon->value }}" class="sr-only"
                                    @checked($icon === \App\Enums\CategoryIcon::Folder)>
                                <span class="{{ $icon->cssClass() }} size-4" aria-hidden="true"></span>
                                <span class="sr-only">{{ $icon->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p id="icon-empty" class="mt-1.5 text-sm text-gray-500">
                        Type a name above to see matching icons. Folder is used until one is picked.
                    </p>
                    <p id="project-icon-error" class="mt-1.5 hidden text-sm text-red-600"></p>
                </fieldset>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-gray-200 p-6 pt-4 sm:flex-row sm:justify-end">
                <button type="button" id="project-cancel"
                    class="btn-secondary">
                    Cancel
                </button>
                <button type="submit" id="project-submit"
                    class="inline-flex min-h-10 items-center justify-center rounded-lg bg-gray-900 px-4 text-sm font-medium text-white transition-colors hover:bg-gray-800 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-60">
                    <span data-label>Add project</span>
                </button>
            </div>
        </form>
    </dialog>

    <dialog id="move-dialog"
        class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-xl border border-gray-200 p-0 shadow-xl backdrop:bg-gray-900/40">
        <div class="p-6">
            <h2 class="font-medium">Move project</h2>
            <p id="move-project" class="mt-1 text-sm wrap-break-word text-gray-600"></p>

            <div class="mt-4">
                <label for="move-parent" class="block text-sm font-medium text-gray-700">Parent project</label>
                <select id="move-parent" aria-describedby="move-error"
                    class="mt-1.5 block min-h-10 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-red-500 focus:ring-2 focus:ring-red-500/30 focus:outline-none">
                    <option value="">No parent</option>
                </select>
                <p id="move-error" class="mt-1.5 hidden text-sm text-red-600"></p>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" id="move-cancel"
                    class="btn-secondary">
                    Cancel
                </button>
                <button type="button" id="move-save"
                    class="inline-flex min-h-10 items-center justify-center rounded-lg bg-gray-900 px-4 text-sm font-medium text-white transition-colors hover:bg-gray-800 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 focus-visible:outline-none">
                    Move
                </button>
            </div>
        </div>
    </dialog>

    {{-- Comments and Activity are two views of the same thing: what has happened on a project.
         One dialog with a tab switch keeps them a click apart instead of a menu apart. --}}
    <dialog id="project-panel"
        class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-lg rounded-xl border border-gray-200 p-0 shadow-xl backdrop:bg-gray-900/40">
        <div class="flex max-h-[calc(100dvh-2rem)] flex-col">
            <div class="flex items-center justify-between gap-4 border-b border-gray-200 p-4">
                <p class="flex min-w-0 items-center gap-2 font-medium">
                    <span class="text-gray-400" aria-hidden="true">#</span>
                    <span id="panel-project" class="truncate"></span>
                </p>
                <button type="button" id="panel-close" aria-label="Close"
                    class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none">
                    <x-icon name="close" />
                </button>
            </div>

            <div class="flex justify-center border-b border-gray-200 p-3">
                <div class="inline-flex gap-1 rounded-full bg-gray-100 p-1" role="group" aria-label="Switch view">
                    <button type="button" data-panel-tab="comments" aria-pressed="true"
                        class="panel-tab">Comments</button>
                    <button type="button" data-panel-tab="activity" aria-pressed="false"
                        class="panel-tab">Activity</button>
                </div>
            </div>

            <div id="panel-comments" class="flex min-h-0 flex-1 flex-col">
                <div class="flex-1 overflow-y-auto overscroll-contain px-4">
                    <ul id="comments-list" class="pt-2"></ul>

                    {{-- The illustration is decorative; the sentence under it is what carries the
                         meaning, so the SVG is aria-hidden and never the only thing here. --}}
                    <div id="comments-empty" class="hidden flex-col items-center px-6 py-10 text-center">
                        <svg viewBox="0 0 120 96" class="h-24 w-auto" fill="none" aria-hidden="true">
                            <rect x="30" y="10" width="58" height="40" rx="8" class="fill-gray-200" />
                            <path d="M52 50h16l-8 10z" class="fill-gray-200" />
                            <rect x="12" y="26" width="34" height="26" rx="7" class="fill-amber-200" />
                            <path d="M24 52h10l-5 7z" class="fill-amber-200" />
                            <rect x="74" y="30" width="34" height="24" rx="7" class="fill-red-200" />
                            <path d="M86 54h10l-5 7z" class="fill-red-200" />
                            <path d="M60 60v22" class="stroke-gray-300" stroke-width="2" stroke-linecap="round" />
                            <circle cx="60" cy="86" r="4" class="fill-gray-300" />
                            <circle cx="20" cy="16" r="3" class="fill-amber-300" />
                            <circle cx="104" cy="18" r="2.5" class="fill-red-300" />
                            <circle cx="98" cy="70" r="3" class="fill-gray-200" />
                        </svg>
                        <p class="mt-4 max-w-xs text-sm text-gray-500">
                            Keep the discussion about this project in one place, next to the work it belongs to.
                        </p>
                    </div>

                    <p id="comments-message" class="hidden px-2 py-6 text-center text-sm text-gray-500"></p>
                </div>

                <form id="comment-form" class="border-t border-gray-200 p-4" novalidate>
                    <div class="rounded-lg border border-gray-300 focus-within:border-red-500 focus-within:ring-2 focus-within:ring-red-500/30">
                        <label for="comment-body" class="sr-only">Add a comment</label>
                        <textarea id="comment-body" name="body" rows="2" maxlength="1000" placeholder="Comment"
                            aria-describedby="comment-body-error"
                            class="block w-full resize-none rounded-t-lg border-0 px-3 py-2 text-sm placeholder:text-gray-400 focus:outline-none"></textarea>
                        <div class="flex items-center justify-between gap-2 px-2 pb-2">
                            <button type="button" id="comment-emoji" aria-expanded="false" aria-haspopup="true"
                                aria-controls="comment-emoji-row" aria-label="Add an emoji"
                                class="inline-flex size-8 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none">
                                <x-icon name="face-smile" class="size-5" />
                            </button>
                            {{-- Near-black, like every other primary action here, and still not the
                                 reference's red even though the mark is red now: red stays this app's
                                 destructive colour, and posting a comment is the least destructive
                                 thing on the screen. --}}
                            <button type="submit" id="comment-submit"
                                class="inline-flex min-h-10 items-center justify-center rounded-lg bg-gray-900 px-4 text-sm font-medium text-white transition-colors hover:bg-gray-800 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-60">
                                <span data-label>Comment</span>
                            </button>
                        </div>
                        <div id="comment-emoji-row" class="hidden flex-wrap gap-1 border-t border-gray-200 p-2">
                            @foreach (\App\Enums\Reaction::cases() as $reaction)
                                <button type="button" data-insert-emoji="{{ $reaction->value }}"
                                    class="inline-flex size-8 items-center justify-center rounded-md text-lg transition-colors hover:bg-gray-100 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none">
                                    <span aria-hidden="true">{{ $reaction->value }}</span>
                                    <span class="sr-only">{{ $reaction->label() }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <p id="comment-body-error" class="mt-1.5 hidden text-sm text-red-600"></p>
                </form>
            </div>

            <div id="panel-activity" class="hidden min-h-0 flex-1 overflow-y-auto overscroll-contain px-4">
                <ul id="activity-list" class="pt-2"></ul>
                <p id="activity-message" class="hidden px-2 py-6 text-center text-sm text-gray-500"></p>
            </div>
        </div>
    </dialog>

    <div id="toast-region" aria-live="polite"
        class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4"></div>
    {{-- Task detail. Every field saves on its own, so there is no Save button and nothing is lost
         by closing. Reminders, labels and a location are deliberately absent: the app has no
         accounts and no mail, so a reminder would be a control that never fires. --}}
    <dialog id="task-detail"
        class="m-auto w-[min(62rem,calc(100vw-2rem))] max-h-[calc(100dvh-2rem)] overflow-hidden rounded-xl p-0 backdrop:bg-gray-900/40">
        <div class="flex items-center justify-between gap-3 border-b border-gray-200 p-2.5">
            <span id="detail-crumb" class="flex items-center gap-1.5 px-1 text-sm font-medium text-gray-600"></span>

            <div class="flex items-center gap-0.5">
                @foreach ([
                    ['detail-prev', 'Previous task', 'chevron-up'],
                    ['detail-next', 'Next task', 'chevron-down'],
                    ['detail-delete', 'Delete task', 'trash'],
                    ['detail-close', 'Close', 'close'],
                ] as [$id, $label, $icon])
                    <button type="button" id="{{ $id }}" aria-label="{{ $label }}" class="icon-button">
                        <x-icon :name="$icon" class="size-4" />
                    </button>
                @endforeach
            </div>
        </div>

        <div class="grid max-h-[calc(100dvh-6rem)] overflow-auto lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <button type="button" id="detail-tick" role="checkbox" aria-checked="false"
                        aria-label="Mark as done" class="tick mt-1.5"></button>

                    <div class="min-w-0 flex-1">
                        <label for="detail-title" class="sr-only">Task name</label>
                        <input type="text" id="detail-title" maxlength="255" class="detail-title" />

                        <label for="detail-description" class="sr-only">Description</label>
                        <textarea id="detail-description" rows="2" placeholder="Description"
                            class="detail-description"></textarea>
                    </div>
                </div>

                <div class="mt-5">
                    <div class="flex items-baseline justify-between gap-2 border-b border-gray-200 pb-2">
                        <h2 class="text-sm font-medium text-gray-700">Sub-tasks</h2>
                        <span id="subtask-count" class="text-xs text-gray-500 tabular-nums"></span>
                    </div>

                    <ul id="subtask-list"></ul>

                    <button type="button" id="subtask-add"
                        class="mt-2 inline-flex min-h-9 items-center gap-2 text-gray-500 transition-colors hover:text-red-600 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none">
                        <x-icon name="plus" class="size-4" />
                        Add sub-task
                    </button>

                    <form id="subtask-form" class="mt-2 flex gap-2" hidden>
                        <label for="subtask-title" class="sr-only">Sub-task name</label>
                        <input type="text" id="subtask-title" maxlength="255" placeholder="What needs doing?"
                            class="min-h-10 flex-1 rounded-lg border border-gray-300 px-3 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none" />
                        <button type="submit"
                            class="min-h-10 rounded-lg bg-gray-900 px-3.5 text-sm font-medium text-white transition-colors hover:bg-gray-800 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none">
                            Add
                        </button>
                        <button type="button" id="subtask-cancel" class="btn-secondary">Cancel</button>
                    </form>
                </div>
            </div>

            <aside class="border-t border-gray-200 p-5 sm:p-6 lg:border-t-0 lg:border-l">
                <div class="divide-y divide-gray-200">
                    <div class="pb-3">
                        <label for="detail-project" class="detail-label">Project</label>
                        <select id="detail-project" class="detail-field"></select>
                    </div>

                    <div class="py-3">
                        {{-- Inline, because a <dialog> clips a floating panel. --}}
                        <x-date-field id="detail-date" label="Date" :inline="true" />
                    </div>

                    <div class="py-3">
                        {{-- The board's drag only exists on the board. This is how a task reaches
                             In progress from the list, the calendar, or a keyboard. --}}
                        <label for="detail-status" class="detail-label">Status</label>
                        <select id="detail-status" class="detail-field">
                            @foreach (['pending' => 'To do', 'in_progress' => 'In progress', 'completed' => 'Done'] as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="pt-3">
                        <label for="detail-priority" class="detail-label">Priority</label>
                        <select id="detail-priority" class="detail-field">
                            @foreach (['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'] as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </aside>
        </div>
    </dialog>

</x-layout>

