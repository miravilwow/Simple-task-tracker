@props(['label' => null, 'action' => null])

<div {{ $attributes->class(['flex flex-col gap-1']) }}>
    @if ($label)
        <div class="sidebar-collapsible flex min-h-8 items-center justify-between">
            <h2 class="sidebar-group-label">{{ $label }}</h2>
            {{ $action }}
        </div>
    @endif

    {{ $slot }}
</div>
