@props(['icon', 'label', 'badge' => null, 'active' => false])

{{-- The label collapses away with the rail, so the title keeps the button identifiable there. --}}
<button type="button" aria-pressed="{{ $active ? 'true' : 'false' }}" title="{{ $label }}"
    {{ $attributes->class(['sidebar-menu-button']) }}>
    <x-icon :name="$icon" class="size-5 shrink-0" />
    <span class="sidebar-collapsible flex-1 truncate text-left">{{ $label }}</span>
    @if ($badge)
        <span class="sidebar-collapsible sidebar-menu-badge" data-view-count="{{ $badge }}"></span>
    @endif
</button>
