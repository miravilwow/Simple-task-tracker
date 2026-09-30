<x-layout title="Your tasks · Simple Task Tracker">
    @push('scripts')
        @vite('resources/js/app.js')
    @endpush

    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Your tasks</h1>
            <p class="text-sm text-gray-500">Create tasks, set priorities, and track what's done.</p>
        </div>

        <div class="mt-6 space-y-6">
            <section aria-labelledby="stats-heading">
                <h2 id="stats-heading" class="sr-only">Task statistics</h2>

                <dl class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    <x-stat-card id="stat-total" label="Total tasks" icon="list" tone="slate" />
                    <x-stat-card id="stat-pending" label="Pending" icon="clock" tone="blue" />
                    <x-stat-card id="stat-completed" label="Completed" icon="check-circle" tone="green" />
                    <x-stat-card id="stat-high" label="High priority pending" icon="fire" tone="red" />
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

            <div class="grid gap-6 lg:grid-cols-3 lg:items-start">
                <section aria-labelledby="new-task-heading"
                    class="rounded-xl border border-gray-200 bg-white shadow-sm lg:sticky lg:top-6">
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
                            <textarea id="description" name="description" rows="3" placeholder="Add any details worth remembering"
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

                        <button type="submit" id="submit-button"
                            class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60">
                            <x-icon name="plus" class="size-4" />
                            <span data-label>Add task</span>
                        </button>
                    </form>
                </section>

                <section aria-labelledby="tasks-heading"
                    class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm lg:col-span-2">
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

                    <div id="load-error" class="hidden p-4 sm:px-6" role="alert">
                        <div class="flex flex-col gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 sm:flex-row sm:items-center sm:justify-between">
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

    <div id="toast-region" aria-live="polite"
        class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4"></div>
</x-layout>
