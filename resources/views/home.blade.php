@php
    $badges = [
        'high' => 'bg-red-100 text-red-700',
        'medium' => 'bg-amber-100 text-amber-800',
        'low' => 'bg-slate-100 text-slate-700',
        'pending' => 'bg-blue-100 text-blue-700',
        'completed' => 'bg-green-100 text-green-700',
    ];

    $accents = [
        'high' => 'bg-red-400',
        'medium' => 'bg-amber-400',
        'low' => 'bg-slate-300',
    ];

    // Decorative preview rows, already in the order TaskSorter would return them.
    $previewTasks = [
        ['title' => 'Fix checkout payment timeout', 'priority' => 'high', 'status' => 'pending'],
        ['title' => 'Review pull request #42', 'priority' => 'medium', 'status' => 'pending'],
        ['title' => 'Update project dependencies', 'priority' => 'low', 'status' => 'pending'],
        ['title' => 'Clean up unused CSS classes', 'priority' => 'low', 'status' => 'completed'],
    ];

    $features = [
        [
            'icon' => 'fire',
            'title' => 'Priorities that sort themselves',
            'text' => 'High-priority work rises to the top, and pending tasks always sit above finished ones.',
        ],
        [
            'icon' => 'check-circle',
            'title' => 'One-click completion',
            'text' => 'Mark a task done and the list updates instantly, with no page reload. Reopen it if you change your mind.',
        ],
        [
            'icon' => 'filter',
            'title' => 'Focus with filters',
            'text' => 'Switch between all, pending, and completed tasks to see exactly what you need.',
        ],
        [
            'icon' => 'chart',
            'title' => 'Progress at a glance',
            'text' => 'Live counts and a progress bar show how much is done and what still needs attention.',
        ],
    ];

    $steps = [
        ['title' => 'Add a task', 'text' => 'Give it a title and, if you like, a short description.'],
        ['title' => 'Set its priority', 'text' => 'Choose high, medium, or low. The list orders itself for you.'],
        ['title' => 'Check it off', 'text' => 'Hit Complete when it\'s done and watch your progress grow.'],
    ];
@endphp

<x-layout title="Simple Task Tracker">
    <section class="bg-white">
        <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 py-16 sm:px-6 sm:py-24 lg:grid-cols-2 lg:px-8">
            <div>
                <p class="text-sm font-semibold text-indigo-600">Task management, simplified</p>
                <h1 class="mt-3 text-4xl font-semibold tracking-tight sm:text-5xl">Stay on top of what matters most.</h1>
                <p class="mt-4 text-lg text-gray-600">
                    Capture tasks in seconds, rank them by priority, and check them off as you go.
                    Your most important work is always first in line.
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('tasks.index') }}"
                        class="inline-flex min-h-10 items-center justify-center rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:outline-none">
                        Open task tracker
                    </a>
                    <a href="#how-it-works"
                        class="inline-flex min-h-10 items-center justify-center rounded-md border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 focus-visible:outline-none">
                        See how it works
                    </a>
                </div>
            </div>

            <div aria-hidden="true" class="rounded-2xl border border-gray-200 bg-gray-50 p-4 shadow-sm sm:p-6">
                <div class="grid grid-cols-3 gap-3">
                    @foreach ([['Pending', 3, 'clock', 'bg-blue-100 text-blue-700'], ['Done', 1, 'check-circle', 'bg-green-100 text-green-700'], ['High', 1, 'fire', 'bg-red-100 text-red-700']] as [$label, $count, $icon, $tone])
                        <div class="rounded-xl border border-gray-200 bg-white p-3">
                            <span class="flex size-8 items-center justify-center rounded-lg {{ $tone }}">
                                <x-icon :name="$icon" class="size-4" />
                            </span>
                            <p class="mt-2 text-xs text-gray-500">{{ $label }}</p>
                            <p class="text-xl font-semibold tabular-nums">{{ $count }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-3 rounded-xl border border-gray-200 bg-white p-3">
                    <div class="flex items-baseline justify-between">
                        <p class="text-xs font-medium text-gray-700">Progress</p>
                        <p class="text-xs text-gray-500 tabular-nums">1 of 4 done (25%)</p>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full w-1/4 rounded-full bg-indigo-600"></div>
                    </div>
                </div>

                <ul class="mt-3 divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200 bg-white">
                    @foreach ($previewTasks as $task)
                        <li class="relative flex items-center justify-between gap-3 py-3 pr-4 pl-5">
                            <span class="absolute inset-y-0 left-0 w-1 {{ $accents[$task['priority']] }}"></span>
                            <span @class([
                                'min-w-0 truncate text-sm font-medium',
                                'text-gray-400 line-through' => $task['status'] === 'completed',
                            ])>{{ $task['title'] }}</span>
                            <span class="flex shrink-0 gap-2">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badges[$task['priority']] }}">{{ ucfirst($task['priority']) }}</span>
                                <span class="hidden rounded-full px-2.5 py-0.5 text-xs font-medium sm:inline {{ $badges[$task['status']] }}">{{ ucfirst($task['status']) }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section aria-labelledby="features-heading" class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">
        <h2 id="features-heading" class="text-2xl font-semibold sm:text-3xl">Everything you need, nothing you don't</h2>
        <p class="mt-2 max-w-2xl text-gray-600">A focused set of tools for getting through your list, without the clutter.</p>

        <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($features as $feature)
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <x-icon :name="$feature['icon']" class="size-6" />
                    </div>
                    <h3 class="mt-4 font-medium">{{ $feature['title'] }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ $feature['text'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section id="how-it-works" aria-labelledby="how-heading" class="scroll-mt-4 border-y border-gray-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">
            <h2 id="how-heading" class="text-2xl font-semibold sm:text-3xl">How it works</h2>

            <ol class="mt-10 grid gap-8 md:grid-cols-3">
                @foreach ($steps as $step)
                    <li class="flex gap-4">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-indigo-600 font-semibold text-white" aria-hidden="true">
                            {{ $loop->iteration }}
                        </span>
                        <div>
                            <h3 class="font-medium">{{ $step['title'] }}</h3>
                            <p class="mt-1 text-sm text-gray-600">{{ $step['text'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="flex flex-col items-start gap-6 rounded-xl bg-indigo-600 p-8 text-white sm:p-10 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-2xl font-semibold">Ready to get organized?</h2>
                <p class="mt-2 text-indigo-100">Your task list is one click away.</p>
            </div>
            <a href="{{ route('tasks.index') }}"
                class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-md bg-white px-5 py-2.5 text-sm font-medium text-indigo-700 hover:bg-indigo-50 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-indigo-600 focus-visible:outline-none">
                Open task tracker
            </a>
        </div>
    </section>
</x-layout>
