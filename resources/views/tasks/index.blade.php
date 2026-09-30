<x-layout title="Your tasks · Simple Task Tracker">
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

    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:flex lg:gap-8 lg:px-8">
        <button type="button" id="sidebar-backdrop"
            class="fixed inset-0 z-30 hidden cursor-default bg-gray-900/40 lg:hidden" tabindex="-1" aria-hidden="true"></button>

        <aside id="sidebar" aria-label="Views and categories"
            {{-- `invisible` while closed keeps the off-screen drawer out of the tab order;
                 translate alone would leave it keyboard-reachable. --}}
            class="invisible fixed inset-y-0 left-0 z-40 w-72 -translate-x-full overflow-y-auto border-r border-gray-200 bg-white p-4 transition-transform duration-200 motion-reduce:transition-none data-[open=true]:visible data-[open=true]:translate-x-0 lg:visible lg:sticky lg:top-6 lg:z-auto lg:inset-auto lg:w-60 lg:shrink-0 lg:translate-x-0 lg:self-start lg:overflow-visible lg:border-0 lg:bg-transparent lg:p-0">
            <div class="mb-4 flex items-center justify-between lg:hidden">
                <p class="font-medium">Views</p>
                <button type="button" id="sidebar-close" aria-label="Close menu"
                    class="flex size-10 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none">
                    <x-icon name="close" />
                </button>
            </div>

            <nav aria-label="Task views">
                <ul class="space-y-1">
                    @foreach ($views as $view)
                        <li>
                            <button type="button" data-view="{{ $view['key'] }}"
                                aria-pressed="{{ $view['key'] === 'all' ? 'true' : 'false' }}"
                                class="flex min-h-10 w-full items-center gap-3 rounded-lg px-3 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none aria-pressed:bg-indigo-50 aria-pressed:text-indigo-700">
                                <x-icon :name="$view['icon']" class="size-5 shrink-0" />
                                <span class="flex-1 text-left">{{ $view['label'] }}</span>
                                @if ($view['count'])
                                    <span data-view-count="{{ $view['count'] }}"
                                        class="text-xs text-gray-400 tabular-nums"></span>
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="mt-6">
                <div class="flex items-center justify-between px-3">
                    <h2 id="categories-heading" class="text-xs font-semibold tracking-wide text-gray-500 uppercase">
                        Categories
                    </h2>
                    <button type="button" id="category-toggle" aria-expanded="false" aria-controls="category-form"
                        class="flex size-8 items-center justify-center rounded-md text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none">
                        <x-icon name="plus" class="size-4" />
                        <span class="sr-only">New category</span>
                    </button>
                </div>

                <form id="category-form" class="mt-2 hidden space-y-2 rounded-lg border border-gray-200 bg-white p-3" novalidate>
                    <div>
                        <label for="category-name" class="sr-only">Category name</label>
                        <input id="category-name" name="name" type="text" maxlength="40" required placeholder="Category name"
                            aria-describedby="category-name-error"
                            class="block min-h-10 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm placeholder:text-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none aria-invalid:border-red-500">
                        <p id="category-name-error" class="mt-1 hidden text-sm text-red-600"></p>
                    </div>

                    <fieldset>
                        <legend class="sr-only">Colour</legend>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach (['slate' => 'bg-slate-400', 'red' => 'bg-red-400', 'amber' => 'bg-amber-400', 'green' => 'bg-green-400', 'blue' => 'bg-blue-400', 'violet' => 'bg-violet-400', 'pink' => 'bg-pink-400'] as $value => $dot)
                                <label
                                    class="flex size-8 cursor-pointer items-center justify-center rounded-md border border-transparent transition-colors hover:bg-gray-100 has-checked:border-gray-400 has-checked:bg-gray-100 has-focus-visible:ring-2 has-focus-visible:ring-indigo-500">
                                    <input type="radio" name="color" value="{{ $value }}" class="sr-only" @checked($value === 'slate')>
                                    <span class="size-4 rounded-full {{ $dot }}"></span>
                                    <span class="sr-only">{{ ucfirst($value) }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p id="category-color-error" class="mt-1 hidden text-sm text-red-600"></p>
                    </fieldset>

                    <button type="submit" id="category-submit"
                        class="inline-flex min-h-10 w-full items-center justify-center rounded-lg bg-indigo-600 px-3 text-sm font-medium text-white transition-colors hover:bg-indigo-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-60">
                        <span data-label>Add category</span>
                    </button>
                </form>

                <ul id="category-list" class="mt-2 space-y-1"></ul>
                <p id="category-empty" class="hidden px-3 py-2 text-sm text-gray-500">No categories yet.</p>
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <button type="button" id="sidebar-open" aria-label="Open menu" aria-expanded="false" aria-controls="sidebar"
                        class="flex size-10 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none lg:hidden">
                        <x-icon name="menu" />
                    </button>
                    <div>
                        <h1 id="view-title" tabindex="-1" class="text-2xl font-semibold tracking-tight focus:outline-none">All tasks</h1>
                        <p id="view-subtitle" class="text-sm text-gray-500">Create tasks, set priorities, and track what's done.</p>
                    </div>
                </div>

                <div role="group" aria-label="Switch layout" class="flex gap-1 rounded-lg bg-gray-100 p-1">
                    @foreach ([['list', 'List', 'list'], ['calendar', 'Calendar', 'calendar']] as [$mode, $label, $icon])
                        <button type="button" data-mode="{{ $mode }}" aria-pressed="{{ $mode === 'list' ? 'true' : 'false' }}"
                            class="inline-flex min-h-9 items-center gap-2 rounded-md px-3 text-sm font-medium text-gray-600 transition-colors hover:text-gray-900 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none aria-pressed:bg-white aria-pressed:text-indigo-700 aria-pressed:shadow-sm">
                            <x-icon :name="$icon" class="size-4" />
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            <section aria-labelledby="stats-heading" class="mt-6">
                <h2 id="stats-heading" class="sr-only">Task statistics</h2>

                <dl class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    <x-stat-card id="stat-total" label="Total tasks" icon="list" tone="slate" />
                    <x-stat-card id="stat-pending" label="Pending" icon="clock" tone="blue" />
                    <x-stat-card id="stat-completed" label="Completed" icon="check-circle" tone="green" />
                    <x-stat-card id="stat-overdue" label="Overdue" icon="warning" tone="red" />
                </dl>

                {{-- A single ratio against a total reads better as a meter than as another number. --}}
                <div class="mt-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex items-baseline justify-between gap-4">
                        <p class="text-sm font-medium text-gray-700">Progress</p>
                        <p id="progress-label" class="text-sm text-gray-500 tabular-nums">No tasks yet</p>
                    </div>
                    <div id="progress-track" class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100" role="progressbar"
                        aria-labelledby="progress-label" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <div id="progress-bar" class="h-full w-0 rounded-full bg-indigo-600 transition-[width] duration-500 motion-reduce:transition-none"></div>
                    </div>
                </div>
            </section>

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
            <div id="list-view" class="mt-6 grid gap-6 xl:grid-cols-3 xl:items-start">
                <section aria-labelledby="new-task-heading"
                    class="rounded-xl border border-gray-200 bg-white shadow-sm xl:sticky xl:top-6">
                    <div class="flex items-center gap-2 border-b border-gray-200 px-4 py-3 sm:px-6">
                        <x-icon name="plus" class="size-5 text-indigo-600" />
                        <h2 id="new-task-heading" class="font-medium">New task</h2>
                    </div>

                    <form id="task-form" class="space-y-4 p-4 sm:p-6" novalidate>
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700">
                                Title <span class="text-red-600" aria-hidden="true">*</span>
                            </label>
                            <input id="title" name="title" type="text" maxlength="255" required
                                placeholder="e.g. Fix the checkout timeout" aria-describedby="title-error"
                                class="mt-1.5 block min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm transition-colors placeholder:text-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none aria-invalid:border-red-500 aria-invalid:ring-2 aria-invalid:ring-red-500/20">
                            <p id="title-error" class="mt-1.5 hidden text-sm text-red-600"></p>
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">
                                Description <span class="font-normal text-gray-500">(optional)</span>
                            </label>
                            <textarea id="description" name="description" rows="2" placeholder="Add any details worth remembering"
                                aria-describedby="description-error"
                                class="mt-1.5 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm transition-colors placeholder:text-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none aria-invalid:border-red-500"></textarea>
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
                                        class="flex min-h-11 cursor-pointer items-center justify-center rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-600 transition-colors hover:bg-gray-50 has-focus-visible:ring-2 has-focus-visible:ring-indigo-500 has-focus-visible:ring-offset-2 {{ $option['active'] }}">
                                        <input type="radio" name="priority" value="{{ $value }}" class="sr-only"
                                            @checked($value === 'medium')>
                                        {{ $option['label'] }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                            <div>
                                <label for="category_id" class="block text-sm font-medium text-gray-700">
                                    Category <span class="font-normal text-gray-500">(optional)</span>
                                </label>
                                <select id="category_id" name="category_id" aria-describedby="category_id-error"
                                    class="mt-1.5 block min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none aria-invalid:border-red-500">
                                    <option value="">No category</option>
                                </select>
                                <p id="category_id-error" class="mt-1.5 hidden text-sm text-red-600"></p>
                            </div>

                            <div>
                                <label for="due_date" class="block text-sm font-medium text-gray-700">
                                    Due date <span class="font-normal text-gray-500">(optional)</span>
                                </label>
                                <input id="due_date" name="due_date" type="date" aria-describedby="due_date-error"
                                    class="mt-1.5 block min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none aria-invalid:border-red-500">
                                <p id="due_date-error" class="mt-1.5 hidden text-sm text-red-600"></p>
                            </div>
                        </div>

                        <button type="submit" id="submit-button"
                            class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60">
                            <x-icon name="plus" class="size-4" />
                            <span data-label>Add task</span>
                        </button>
                    </form>
                </section>

                <section aria-labelledby="tasks-heading"
                    class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm xl:col-span-2">
                    <div class="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        {{-- tabindex allows focus to return here after a task row is removed. --}}
                        <h2 id="tasks-heading" tabindex="-1" class="font-medium focus:outline-none">Tasks</h2>

                        <div role="group" aria-label="Filter tasks by status"
                            class="grid grid-cols-3 gap-1 rounded-lg bg-gray-100 p-1 sm:flex">
                            @foreach (['' => 'All', 'pending' => 'Pending', 'completed' => 'Completed'] as $value => $label)
                                <button type="button" data-filter="{{ $value }}"
                                    aria-pressed="{{ $value === '' ? 'true' : 'false' }}"
                                    class="min-h-9 rounded-md px-3 text-sm font-medium text-gray-600 transition-colors hover:text-gray-900 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none aria-pressed:bg-white aria-pressed:text-indigo-700 aria-pressed:shadow-sm">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
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
                                class="flex size-10 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 transition-colors hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none">
                                <x-icon name="chevron-left" class="size-4" />
                            </button>
                            <button type="button" id="calendar-today"
                                class="inline-flex min-h-10 items-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none">
                                Today
                            </button>
                            <button type="button" id="calendar-next" aria-label="Next month"
                                class="flex size-10 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 transition-colors hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none">
                                <x-icon name="chevron-right" class="size-4" />
                            </button>
                        </div>
                    </div>

                    {{-- Dragging only exists on the month grid, which itself only appears from md. --}}
                    <p class="border-b border-gray-200 bg-indigo-50/60 px-4 py-2 text-xs text-indigo-800 sm:px-6">
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

                <section aria-labelledby="unscheduled-heading"
                    class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
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
            <button type="button" id="confirm-cancel"
                class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:outline-none">
                Cancel
            </button>
            <button type="button" id="confirm-accept"
                class="inline-flex min-h-10 items-center justify-center rounded-lg bg-red-600 px-4 text-sm font-medium text-white transition-colors hover:bg-red-700 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 focus-visible:outline-none">
                Delete
            </button>
        </div>
    </dialog>

    {{-- Dragging is a pointer-only gesture, so rescheduling also has to work from a dialog. --}}
    <dialog id="schedule-dialog"
        class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-xl border border-gray-200 p-0 shadow-xl backdrop:bg-gray-900/40">
        <form id="schedule-form" method="dialog" class="p-6">
            <h2 class="font-medium">Reschedule task</h2>
            <p id="schedule-task-title" class="mt-1 text-sm wrap-break-word text-gray-600"></p>

            <label for="schedule-date" class="mt-4 block text-sm font-medium text-gray-700">Due date</label>
            <input id="schedule-date" type="date"
                class="mt-1.5 block min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none">

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" id="schedule-clear"
                    class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none">
                    Clear date
                </button>
                <button type="button" id="schedule-cancel"
                    class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none">
                    Cancel
                </button>
                <button type="button" id="schedule-save"
                    class="inline-flex min-h-10 items-center justify-center rounded-lg bg-indigo-600 px-4 text-sm font-medium text-white transition-colors hover:bg-indigo-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:outline-none">
                    Save
                </button>
            </div>
        </form>
    </dialog>

    <div id="toast-region" aria-live="polite"
        class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4"></div>
</x-layout>
