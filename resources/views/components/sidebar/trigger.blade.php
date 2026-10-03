{{-- Opens the drawer on phones (below 30rem). From 30rem the sidebar is on screen as a rail. --}}
<button type="button" data-sidebar-trigger aria-label="Open menu" aria-expanded="false" aria-controls="sidebar"
    {{ $attributes->class(['flex size-10 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 transition-colors hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none min-[30rem]:hidden']) }}>
    <x-icon name="menu" />
</button>
