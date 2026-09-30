<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Simple Task Tracker</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased">
    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-12">
        <header class="mb-6">
            <h1 class="text-2xl font-semibold">Simple Task Tracker</h1>
            <p id="task-summary" class="mt-1 text-sm text-gray-500">&nbsp;</p>
        </header>

        <main>
            <section aria-labelledby="new-task-heading" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
                <h2 id="new-task-heading" class="text-lg font-medium">New task</h2>

                <form id="task-form" class="mt-4 space-y-4" novalidate>
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700">
                            Title <span class="text-red-600" aria-hidden="true">*</span>
                        </label>
                        <input id="title" name="title" type="text" maxlength="255" required
                            aria-describedby="title-error"
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none aria-[invalid=true]:border-red-500">
                        <p id="title-error" class="mt-1 hidden text-sm text-red-600"></p>
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">
                            Description <span class="font-normal text-gray-500">(optional)</span>
                        </label>
                        <textarea id="description" name="description" rows="3"
                            aria-describedby="description-error"
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none aria-[invalid=true]:border-red-500"></textarea>
                        <p id="description-error" class="mt-1 hidden text-sm text-red-600"></p>
                    </div>

                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div class="sm:w-48">
                            <label for="priority" class="block text-sm font-medium text-gray-700">Priority</label>
                            <select id="priority" name="priority"
                                aria-describedby="priority-error"
                                class="mt-1 block min-h-10 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30 focus:outline-none aria-[invalid=true]:border-red-500">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                            <p id="priority-error" class="mt-1 hidden text-sm text-red-600"></p>
                        </div>

                        <button type="submit" id="submit-button"
                            class="inline-flex min-h-10 min-w-28 items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60">
                            Add task
                        </button>
                    </div>
                </form>
            </section>

            <section aria-labelledby="tasks-heading" class="mt-8">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 id="tasks-heading" class="text-lg font-medium">Tasks</h2>

                    <div role="group" aria-label="Filter tasks by status" class="inline-flex self-start rounded-lg border border-gray-200 bg-white p-1 sm:self-auto">
                        @foreach (['' => 'All', 'pending' => 'Pending', 'completed' => 'Completed'] as $value => $label)
                            <button type="button" data-filter="{{ $value }}" aria-pressed="{{ $value === '' ? 'true' : 'false' }}"
                                class="min-h-10 rounded-md px-3 text-sm font-medium text-gray-600 hover:bg-gray-100 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none aria-pressed:bg-indigo-600 aria-pressed:text-white aria-pressed:hover:bg-indigo-700">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div id="load-error" class="mt-4 hidden" role="alert">
                    <div class="flex items-center justify-between gap-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <span>Couldn't load tasks. Try again.</span>
                        <button type="button" id="retry-button"
                            class="min-h-10 rounded-md border border-red-300 bg-white px-3 font-medium hover:bg-red-100 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none">
                            Retry
                        </button>
                    </div>
                </div>

                <p id="list-message" class="mt-4 rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
                    Loading tasks…
                </p>

                <ul id="task-list" class="mt-4 space-y-3"></ul>
            </section>
        </main>
    </div>

    <div id="toast-region" aria-live="polite"
        class="pointer-events-none fixed inset-x-0 bottom-4 flex flex-col items-center gap-2 px-4"></div>
</body>
</html>
