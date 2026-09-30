@props(['header' => null, 'footer' => null])

@php
    // Read the cookie server-side so a collapsed sidebar does not flash open on load.
    $collapsed = request()->cookie('sidebar_state') === 'collapsed';
@endphp

{{-- Above the sticky navbar, below the drawer it dims. --}}
<button type="button" data-sidebar-backdrop tabindex="-1" aria-hidden="true"
    class="fixed inset-0 z-40 hidden cursor-default bg-gray-900/40 lg:hidden"></button>

{{-- p-3 plus each row's own px-3 puts every label on the app container's 1.5rem gutter. --}}
<aside data-sidebar data-state="{{ $collapsed ? 'collapsed' : 'expanded' }}" aria-label="Views and categories"
    {{ $attributes->class(['sidebar flex flex-col gap-4 border-r border-gray-200 bg-white p-3']) }}>
    <div class="sidebar-header flex items-center justify-between gap-2">
        {{-- px-3 matches the menu rows, so the heading sits on the same gutter as everything below. --}}
        <div class="sidebar-collapsible min-w-0 px-3">
            {{ $header }}
        </div>
        <button type="button" data-sidebar-close aria-label="Close menu"
            class="sidebar-menu-action lg:hidden">
            <x-icon name="close" />
        </button>
        <x-sidebar.rail />
    </div>

    <div class="flex flex-1 flex-col gap-4">
        {{ $slot }}
    </div>

    @if ($footer)
        <div class="sidebar-collapsible border-t border-gray-200 pt-3">
            {{ $footer }}
        </div>
    @endif
</aside>
