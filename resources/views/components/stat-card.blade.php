@props(['id', 'label', 'icon', 'tone' => 'slate'])

@php
    $tones = [
        'slate' => 'bg-slate-100 text-slate-600',
        'blue' => 'bg-blue-100 text-blue-700',
        'green' => 'bg-green-100 text-green-700',
        'red' => 'bg-red-100 text-red-700',
    ];
@endphp

<div class="flex flex-col items-start gap-2 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition-shadow hover:shadow-md sm:flex-row sm:items-center sm:gap-4">
    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg sm:size-11 {{ $tones[$tone] }}">
        <x-icon :name="$icon" class="size-5 sm:size-6" />
    </span>
    <div class="min-w-0">
        <dt class="text-sm leading-tight text-gray-500">{{ $label }}</dt>
        <dd id="{{ $id }}" class="text-2xl font-semibold tabular-nums">–</dd>
    </div>
</div>
