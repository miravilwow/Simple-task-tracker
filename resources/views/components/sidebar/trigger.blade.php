{{-- Opens the drawer below lg, where the rail is not shown. --}}
<button type="button" data-sidebar-trigger aria-label="Open menu" aria-expanded="false" aria-controls="sidebar"
    {{ $attributes->class(['flex size-10 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 transition-colors hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none lg:hidden']) }}>
    <x-icon name="menu" />
</button>
