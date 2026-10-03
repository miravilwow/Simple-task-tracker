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
            ['key' => 'deleted', 'label' => 'Deleted', 'icon' => 'trash', 'count' => 'deleted'],
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

            <div id="board-controls" class="flex items-center gap-3">
                {{-- Grouping and the filters for the board. The other views decide their own layout and
                     carry their own filter bar. --}}
                <div class="relative">
                    <button type="button" id="display-trigger" class="btn-secondary" aria-expanded="false"
                        aria-controls="display-panel" aria-haspopup="true">
                        <x-icon name="display" class="size-4" />
                        Display
                    </button>

                    <div id="display-panel" role="group" aria-label="Display options" hidden
                        class="absolute right-0 top-[calc(100%+0.5rem)] z-30 w-80 overflow-hidden rounded-xl border border-gray-200 bg-white text-left shadow-lg">
                        <div class="p-4">
                            <div class="flex min-h-11 items-center justify-between gap-3">
                                <span id="completed-label">Completed tasks</span>
                                <button type="button" id="show-completed" role="switch" aria-checked="true"
                                    aria-labelledby="completed-label" class="switch"></button>
                            </div>
                        </div>

                        @foreach ([
                            ['sort', 'Group', [
                                ['grouping', 'Grouping', ['status' => 'Status', 'priority' => 'Priority', 'project' => 'Project']],
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


        {{-- The chips name every active Display setting. They sit above the board, which is the only
             layout the Display panel drives, and the one where nothing else says what is filtered. --}}
        <div id="display-summary" class="mt-4 flex flex-wrap items-center gap-1.5"></div>

        {{-- Upcoming, Overdue and Completed have no Display panel; these are their filters. --}}
        <div id="filter-bar" role="search" aria-label="Filter tasks" hidden class="mt-4">
            {{-- Below sm: Search alone on the first line, the two selects sharing the second. A phone
                 cannot fit all three on one line without crushing the search box to a sliver. --}}
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex w-full min-w-0 flex-col gap-1 sm:w-auto sm:flex-1 sm:max-w-xs">
                    <label for="filter-search" class="text-xs font-medium text-gray-600">Search</label>
                    <input type="search" id="filter-search" maxlength="100" autocomplete="off"
                        placeholder="e.g. report" class="filter-control">
                </div>
                <div class="flex min-w-0 flex-1 flex-col gap-1 sm:flex-none">
                    <label for="filter-bar-priority" class="text-xs font-medium text-gray-600">Priority</label>
                    <select id="filter-bar-priority" class="filter-control">
                        @foreach (['' => 'All priorities', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low'] as $value => $text)
                            <option value="{{ $value }}">{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex min-w-0 flex-1 flex-col gap-1 sm:flex-none">
                    <label for="filter-bar-project" class="text-xs font-medium text-gray-600">Project</label>
                    <select id="filter-bar-project" class="filter-control">
                        <option value="">All projects</option>
                        <option value="none">No project</option>
                    </select>
                </div>
                <button type="button" id="filter-clear" hidden
                    class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-medium text-gray-600 underline underline-offset-2 hover:text-gray-900 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none">
                    Clear filters
                </button>
            </div>
        </div>

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
        <div id="list-view" class="mt-6 hidden">
            <section aria-labelledby="tasks-heading"
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 p-4 sm:px-6">
                    {{-- tabindex allows focus to return here after a task row is removed. --}}
                    <h2 id="tasks-heading" tabindex="-1" class="font-medium focus:outline-none">Tasks</h2>
                </div>

                {{-- Not aria-hidden any more: it holds two real sort buttons, and hiding the row
                     would hide them from a screen reader entirely. --}}
                {{-- The same horizontal padding as a task row (pl-7 clears the row's priority bar),
                     so each heading starts exactly over the column it names. --}}
                <div id="column-headers"
                    class="hidden gap-4 border-b border-gray-200 bg-gray-50 py-2 pr-6 pl-7 text-xs font-medium text-gray-500 md:task-columns">
                    <span class="flex items-center">
                        <button type="button" class="table-sort" data-sort="name" aria-pressed="false"
                            title="Sort by name">
                            Task
                        </button>
                    </span>
                    {{-- No sort of its own: Date and Name replace TaskSorter outright, which this
                         header does not, and the calendar is still where a date moves a task. --}}
                    <span class="flex items-center">Date</span>
                    <span class="flex items-center">Time</span>
                    <span class="flex items-center">
                        {{-- "default" is Src\TaskSorter, which is priority first. The header names what
                             the user sees rather than what the enum calls it. --}}
                        <button type="button" class="table-sort" data-sort="default" aria-pressed="false"
                            title="Sort by priority">
                            Priority
                        </button>
                    </span>
                    {{-- Status does not sort. Every order runs through byStage() already, so To do
                         always precedes the later stages; a sort button here would be a control
                         that changes nothing. --}}
                    <span class="flex items-center">Status</span>
                    <span class="flex items-center justify-center">Actions</span>
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
             grid classes are added when the view opens, exactly as the calendar's are. All tasks
             is the landing view and is a board, so the first paint is this skeleton, and both
             skeletons are removed together once the first load settles. --}}
        <div id="board-skeleton" class="mt-6 grid gap-4 md:grid-cols-3 xl:gap-6">
            @for ($column = 0; $column < 3; $column++)
                <div class="animate-pulse space-y-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="h-4 w-1/3 rounded bg-gray-200"></div>
                    @for ($card = 0; $card < 2; $card++)
                        <div class="space-y-2 rounded-lg border border-gray-200 p-4">
                            <div class="h-4 w-2/3 rounded bg-gray-200"></div>
                            <div class="h-3 w-1/2 rounded bg-gray-100"></div>
                        </div>
                    @endfor
                </div>
            @endfor
        </div>
        {{-- Shown while one project's board is open, so the way back is where the eye starts. --}}
        <nav id="project-crumb" aria-label="Project" hidden class="mt-6">
            <ol class="flex items-center gap-2 text-sm">
                <li>
                    <button type="button" id="project-back"
                        class="-mx-2 inline-flex min-h-10 items-center gap-1 rounded-md px-2 font-medium text-gray-600 hover:text-gray-900 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none">
                        <x-icon name="chevron-left" class="size-4" />
                        Projects
                    </button>
                </li>
                <li aria-hidden="true" class="text-gray-400">/</li>
                <li id="project-crumb-name" aria-current="page" class="font-medium text-gray-900"></li>
            </ol>
        </nav>
        <div id="board-view" class="mt-6 gap-4 md:grid md:grid-cols-[repeat(auto-fit,minmax(15rem,1fr))] xl:gap-6"></div>
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
                        {{-- Names the shown month relative to now ("Next month", "2 months ago"), and
                             jumps back to this month. Disabled while this month is already shown. --}}
                        <button type="button" id="calendar-today" title="Back to this month"
                            class="inline-flex min-h-10 min-w-32 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none disabled:cursor-default disabled:text-gray-400 disabled:hover:bg-white">
                            This month
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

            {{-- The fourth column, to the right of the month grid. --}}
            <section aria-labelledby="unscheduled-heading"
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm xl:sticky xl:app-sticky-top">
                <div class="flex items-center gap-2 border-b border-gray-200 px-4 py-3">
                    <x-icon name="inbox-stack" class="size-5 text-gray-500" />
                    <h2 id="unscheduled-heading" class="font-medium">No due date</h2>
                    <span id="unscheduled-count" class="ml-auto text-xs text-gray-400 tabular-nums"></span>
                </div>
                <ul id="unscheduled-list" class="max-h-96 space-y-2 overflow-y-auto scrollbar-none p-3"></ul>
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
            <span id="confirm-icon-wrap"
                class="flex size-11 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">
                <x-icon id="confirm-icon" name="warning" class="size-6" />
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

    {{-- Every task of one day, because a cell only has room for three. --}}
    <dialog id="day-dialog" aria-labelledby="day-title"
        class="m-auto w-[calc(100%-2rem)] max-w-lg max-h-[calc(100dvh-2rem)] rounded-xl border border-gray-200 p-0 shadow-xl backdrop:bg-gray-900/40">
        <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-4 sm:px-6">
            <div>
                <h2 id="day-title" class="font-medium"></h2>
                <p id="day-count" class="text-sm text-gray-500"></p>
            </div>
        </div>
        <ul id="day-list" class="max-h-96 space-y-2 overflow-y-auto scrollbar-none p-4 sm:px-6"></ul>
        {{-- Colours the day's cell on the calendar. Kept in this browser, so there is no request. --}}
        <div id="day-color-field" class="border-t border-gray-200 px-4 py-3 sm:px-6">
            <span id="day-color-label" class="detail-label">Day colour</span>
            <div id="day-color" role="radiogroup" aria-labelledby="day-color-label" class="mt-2 flex flex-wrap gap-2">
                @foreach (['' => 'bg-white', 'red' => 'bg-red-400', 'orange' => 'bg-orange-400', 'yellow' => 'bg-yellow-400', 'green' => 'bg-green-500', 'teal' => 'bg-teal-500', 'blue' => 'bg-blue-500', 'purple' => 'bg-purple-500', 'pink' => 'bg-pink-400'] as $value => $swatch)
                    <button type="button" role="radio" aria-checked="false" data-color="{{ $value }}"
                        aria-label="{{ $value === '' ? 'Default' : ucfirst($value) }}"
                        class="color-swatch {{ $swatch }}"></button>
                @endforeach
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-gray-200 p-4 sm:px-6">
            <button type="button" id="day-close" class="btn-secondary">Close</button>
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

        <div class="p-6">
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

                <div class="mt-4">
                    <label for="description" class="block text-sm font-medium text-gray-700">
                        Description <span class="font-normal text-gray-500">(optional)</span>
                    </label>
                    <textarea id="description" name="description" rows="2" placeholder="Add any details worth remembering"
                        aria-describedby="description-error"
                        class="mt-1.5 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm transition-colors placeholder:text-gray-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/30 focus:outline-none aria-invalid:border-red-500"></textarea>
                    <p id="description-error" class="mt-1.5 hidden text-sm text-red-600"></p>
                </div>

                <fieldset class="mt-4">
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

                <div class="mt-4 space-y-4">
                    <div>
                        <label for="category_name" class="block text-sm font-medium text-gray-700">
                            Project <span class="font-normal text-gray-500">(optional)</span>
                        </label>
                        <input id="category_name" name="category_name" type="text" maxlength="40"
                            autocomplete="off" list="project-options" placeholder="e.g. School, Work"
                            aria-describedby="category_name-hint category_name-error"
                            class="mt-1.5 block min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm transition-colors placeholder:text-gray-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/30 focus:outline-none aria-invalid:border-red-500 aria-invalid:ring-2 aria-invalid:ring-red-500/20">
                        <datalist id="project-options"></datalist>
                        <p id="category_name-hint" class="mt-1.5 text-xs text-gray-500">Type a new name to create a project.</p>
                        <p id="category_name-error" class="mt-1.5 hidden text-sm text-red-600"></p>
                    </div>

                    {{-- Side by side from sm, stacked below it: a month grid needs about 250px
                         of its own, which two columns of a phone-width dialog do not have. --}}
                    <div class="grid gap-3 sm:grid-cols-3">
                        {{-- Required: a task nobody has given a day to is one the calendar, Today,
                             Upcoming and Overdue all have nothing to say about. Collapsed, because
                             the open grid is the tallest thing on this form by some way. --}}
                        <div class="sm:col-span-2">
                            <x-date-field id="due_date" name="due_date" label="Due date" :required="true"
                                :collapsed="true" describedby="due_date-error">
                                <p id="due_date-error" class="mt-1.5 hidden text-sm text-red-600"></p>
                            </x-date-field>
                        </div>

                        <div>
                            {{-- The browser's own time control: unlike type="date" the three
                                 browsers draw it much the same, so there is nothing to replace.
                                 Required, like the day: the server refuses a new task without it. --}}
                            <label for="due_time" class="block text-sm font-medium text-gray-700">
                                Time <span class="text-red-600" aria-hidden="true">*</span>
                            </label>
                            <input id="due_time" name="due_time" type="time" required aria-describedby="due_time-error"
                                class="mt-1.5 block min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm transition-colors focus:border-red-500 focus:ring-2 focus:ring-red-500/30 focus:outline-none aria-invalid:border-red-500">
                            <p id="due_time-error" class="mt-1.5 hidden text-sm text-red-600"></p>
                        </div>
                    </div>
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

            <div class="mt-4">
                {{-- Same control and styling as New task's Time field. Disabled while the dialog
                     holds no date, because a time with nothing to sit on is what the API refuses. --}}
                <label for="schedule-time" class="block text-sm font-medium text-gray-700">
                    Time <span class="font-normal text-gray-500">(optional)</span>
                </label>
                <input id="schedule-time" type="time" aria-describedby="schedule-time-error"
                    class="mt-1.5 block min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm transition-colors focus:border-red-500 focus:ring-2 focus:ring-red-500/30 focus:outline-none aria-invalid:border-red-500 disabled:bg-gray-50 disabled:text-gray-400">
                <p id="schedule-time-error" class="mt-1.5 hidden text-sm text-red-600"></p>
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

        {{-- A deleted task opens read-only: every save endpoint refuses one, so its fields would
             only offer edits that fail. Disabling the fieldset disables every control inside it. --}}
        <p id="detail-trashed" hidden class="border-b border-gray-200 bg-gray-50 px-5 py-2 text-sm text-gray-600 sm:px-6">
            This task is deleted. Restore it from Deleted to make changes.
        </p>

        <fieldset id="detail-body"
            class="m-0 grid min-w-0 max-h-[calc(100dvh-6rem)] overflow-auto border-0 p-0 disabled:opacity-60 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <legend class="sr-only">Task details</legend>
            <div class="p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <button type="button" id="detail-tick" class="btn-action btn-complete mt-1.5 shrink-0">
                        <x-icon name="check" class="size-4" />
                        <span data-label>Done</span>
                    </button>

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
                        <label for="detail-category-name" class="detail-label">Project</label>
                        <input id="detail-category-name" type="text" maxlength="40" autocomplete="off" list="project-options"
                            placeholder="No project" class="detail-field">
                    </div>

                    {{-- Above the date grid, which is tall enough to push this out of view. --}}
                    <div class="py-3">
                        <span id="detail-color-label" class="detail-label">Color</span>
                        <div id="detail-color" role="radiogroup" aria-labelledby="detail-color-label" class="mt-2 flex flex-wrap gap-2">
                            @foreach (['' => 'bg-white', 'red' => 'bg-red-400', 'orange' => 'bg-orange-400', 'yellow' => 'bg-yellow-400', 'green' => 'bg-green-500', 'teal' => 'bg-teal-500', 'blue' => 'bg-blue-500', 'purple' => 'bg-purple-500', 'pink' => 'bg-pink-400'] as $value => $swatch)
                                <button type="button" role="radio" aria-checked="false" data-color="{{ $value }}"
                                    aria-label="{{ $value === '' ? 'Default' : ucfirst($value) }}"
                                    class="color-swatch {{ $swatch }}"></button>
                            @endforeach
                        </div>
                    </div>

                    <div class="py-3">
                        {{-- Inline, because a <dialog> clips a floating panel. --}}
                        <x-date-field id="detail-date" label="Date" :inline="true" />
                    </div>

                    <div class="py-3">
                        {{-- Disabled while the task has no day: a time with nothing to sit on is
                             what the API refuses, so the field must not offer it either. --}}
                        <label for="detail-time" class="detail-label">Time</label>
                        <input id="detail-time" type="time" class="detail-field disabled:bg-gray-50 disabled:text-gray-400">
                    </div>

                    <div class="py-3">
                        {{-- The board's drag only exists on the board. This is how a task reaches
                             In progress from the list, the calendar, or a keyboard. --}}
                        <label for="detail-status" class="detail-label">Status</label>
                        <select id="detail-status" class="detail-field">
                            @foreach (['pending' => 'To do', 'in_progress' => 'In progress', 'in_review' => 'In review', 'completed' => 'Done'] as $value => $label)
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
        </fieldset>
    </dialog>

</x-layout>

