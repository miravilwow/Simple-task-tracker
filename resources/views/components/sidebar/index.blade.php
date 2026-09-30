@props(['header' => null, 'footer' => null])

@php
    // Read the cookie server-side so a collapsed sidebar does not flash open on load.
    $collapsed = request()->cookie('sidebar_state') === 'collapsed';
@endphp

<button type="button" data-sidebar-backdrop tabindex="-1" aria-hidden="true"
    class="fixed inset-0 z-30 hidden cursor-default bg-gray-900/40 lg:hidden"></button>

<aside data-sidebar data-state="{{ $collapsed ? 'collapsed' : 'expanded' }}" aria-label="Views and categories"
    {{ $attributes->class(['sidebar flex flex-col gap-4 border-r border-gray-200 bg-white p-4 lg:rounded-xl lg:border lg:p-3']) }}>
    <div class="flex items-center justify-between gap-2">
        <div class="sidebar-collapsible min-w-0">
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
