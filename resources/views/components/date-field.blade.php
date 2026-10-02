@props(['id', 'label', 'name' => null, 'optional' => false, 'required' => false, 'describedby' => null, 'inline' => false, 'collapsed' => false])

{{--
    A date field with our own month grid, because Chrome, Firefox and Safari each draw a different
    native control. The `<input type="date">` underneath is still the real one: it holds the ISO
    value, it validates, and without JavaScript it is still a working date control. `datepicker.js`
    only hides the browser's indicator and adds the grid.

    `inline` shows the grid in the flow instead of behind a button. Use it where picking a date is
    the whole point of the screen, and inside a `<dialog>`, which clips a floating panel.

    `collapsed` is that same in-flow grid behind a trigger, for a dialog where the date is one
    field among several: a month grid standing open is the tallest thing on the form, and it was
    pushing the buttons off a short viewport. The grid stays in the flow rather than floating,
    because a floating panel is exactly what a `<dialog>` clips.
--}}
<div data-date-field="{{ $inline ? 'inline' : ($collapsed ? 'collapsed' : 'popover') }}">
    <label for="{{ $id }}" class="block text-sm font-medium text-gray-700">
        {{ $label }}
        @if ($optional)
            <span class="font-normal text-gray-500">(optional)</span>
        @endif
        {{-- The same mark Title carries, so one required field looks like the other. --}}
        @if ($required)
            <span class="text-red-600" aria-hidden="true">*</span>
        @endif
    </label>

    @if ($collapsed)
        {{-- Hidden until the script takes over: with no JavaScript the native input below is the
             whole control, and the two must never both be on screen. --}}
        <button type="button" data-date-trigger aria-expanded="false" aria-controls="{{ $id }}-grid"
            class="mt-1.5 hidden min-h-11 w-full items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white px-3 text-left text-sm transition-colors hover:bg-gray-50 focus-visible:border-red-500 focus-visible:ring-2 focus-visible:ring-red-500/30 focus-visible:outline-none">
            <span data-date-label class="text-gray-400">Select date</span>
            <x-icon name="chevron-down" class="size-4 shrink-0 text-gray-400" />
        </button>
    @endif

    <div class="relative mt-1.5">
        {{-- `min` is what a browser enforces on its own control, and it matches the server's
             after_or_equal:today, so the field cannot offer what the API would refuse. --}}
        <input id="{{ $id }}" type="date" min="{{ now()->toDateString() }}" @required($required) @if ($name) name="{{ $name }}" @endif
            @if ($describedby) aria-describedby="{{ $describedby }}" @endif
            @class([
                'date-input block min-h-11 w-full rounded-lg border border-gray-300 bg-white py-2 pl-3 text-sm transition-colors focus:border-red-500 focus:ring-2 focus:ring-red-500/30 focus:outline-none aria-invalid:border-red-500',
                'pr-12' => ! $inline && ! $collapsed,
                'pr-3' => $inline || $collapsed,
            ])>

        @unless ($inline || $collapsed)
            {{-- Hidden until the script takes over, so it never sits beside the browser's own indicator.
                 Collapsed already has its own trigger above; rendering this one too gave a field two
                 `[data-date-trigger]` elements, and the enhanced-field CSS that reveals a field's trigger
                 does not know which one is live, so it revealed both. --}}
            <button type="button" data-date-trigger aria-haspopup="dialog" aria-expanded="false"
                class="absolute inset-y-1 right-1 hidden w-10 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:outline-none">
                <x-icon name="calendar" class="size-5" />
                <span class="sr-only">Choose a date</span>
            </button>
        @endunless
    </div>

    {{ $slot }}
</div>
