@props(['align' => 'right', 'width' => 'w-48'])

<div x-data="{ open: false }" class="relative">
    <div x-on:click="open = ! open">{{ $trigger }}</div>
    <div
        x-show="open"
        x-transition
        x-on:click.outside="open = false"
        class="{{ $align === 'right' ? 'right-0' : 'left-0' }} absolute z-50 mt-2 {{ $width }} rounded-lg border border-slate-200 bg-white p-2 text-slate-700 shadow-xl"
        style="display: none;"
    >
        {{ $slot }}
    </div>
</div>
