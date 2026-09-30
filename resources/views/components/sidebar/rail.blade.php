{{-- Collapses the sidebar to an icon rail. Only meaningful where the rail exists, so large screens only. --}}
<button type="button" data-sidebar-rail aria-controls="sidebar" aria-expanded="true"
    class="sidebar-menu-action hidden lg:flex" title="Toggle sidebar (Ctrl+B)">
    <x-icon name="chevron-left" class="size-4 transition-transform" data-sidebar-rail-icon />
    <span class="sr-only">Toggle sidebar</span>
</button>
