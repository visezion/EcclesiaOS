@props([
    'name',
    'label',
    'options' => [],
    'selected' => null,
    'placeholder' => 'Search...',
    'emptyLabel' => 'None',
    'required' => false,
    'hint' => null,
])

@php
    $id = 'searchable-select-'.\Illuminate\Support\Str::uuid();
    $normalizedOptions = collect($options)->map(function ($option) {
        $label = (string) ($option['label'] ?? '');
        $meta = (string) ($option['meta'] ?? '');
        $initials = (string) ($option['initials'] ?? collect(explode(' ', $label))->filter()->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))->take(2)->join(''));

        return [
            'value' => (string) ($option['value'] ?? ''),
            'label' => $label,
            'meta' => $meta,
            'avatar' => $option['avatar'] ?? null,
            'initials' => $initials,
            'search' => strtolower(trim($label.' '.$meta.' '.($option['search'] ?? ''))),
        ];
    })->values();
@endphp

<div {{ $attributes->merge(['class' => 'space-y-1 text-sm font-medium text-slate-700']) }}
    x-data="searchableSelect({
        selected: @js((string) old($name, $selected)),
        options: @js($normalizedOptions),
        placeholder: @js($placeholder),
        emptyLabel: @js($emptyLabel),
        required: @js((bool) $required),
    })">
    <label for="{{ $id }}" class="block">{{ $label }}</label>
    <input type="hidden" name="{{ $name }}" :value="selected">
    <div class="relative" @click.outside="open = false">
        <div class="relative">
            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
            <input id="{{ $id }}" type="text" x-model="query" @focus="open = true" @input="open = true; selected = ''" placeholder="{{ $placeholder }}" class="w-full rounded-lg border border-slate-200 py-2.5 pl-9 pr-10 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            <button x-cloak x-show="selected || query" type="button" @click="clear()" class="absolute right-2 top-1/2 grid size-7 -translate-y-1/2 place-items-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Clear {{ $label }}">
                <i data-lucide="x" class="size-4"></i>
            </button>
        </div>
        <div x-cloak x-show="open" class="absolute z-30 mt-2 max-h-72 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white p-2 text-sm shadow-xl">
            @unless($required)
                <button type="button" @click="clear()" class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-slate-600 hover:bg-slate-50">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-500"><i data-lucide="x" class="size-4"></i></span>
                    <span>{{ $emptyLabel }}</span>
                </button>
            @endunless
            <template x-for="option in filteredOptions()" :key="option.value">
                <button type="button" @click="choose(option)" class="mt-1 flex w-full items-center gap-3 rounded-md px-3 py-2 text-left hover:bg-violet-50">
                    <span class="grid size-9 shrink-0 place-items-center overflow-hidden rounded-full bg-violet-50 text-xs font-bold uppercase text-violet-700 ring-1 ring-violet-100">
                        <template x-if="option.avatar">
                            <img :src="option.avatar" :alt="`${option.label} profile picture`" class="size-full object-cover">
                        </template>
                        <template x-if="! option.avatar">
                            <span x-text="option.initials || 'U'"></span>
                        </template>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-semibold text-slate-950" x-text="option.label"></span>
                        <span x-show="option.meta" class="block truncate text-xs text-slate-500" x-text="option.meta"></span>
                    </span>
                    <span x-show="selected === String(option.value)" class="size-2 rounded-full bg-emerald-500"></span>
                </button>
            </template>
            <div x-show="filteredOptions().length === 0" class="rounded-md px-3 py-4 text-center text-sm text-slate-500">No results found.</div>
        </div>
    </div>
    @if($hint)
        <span class="block text-xs font-normal leading-5 text-slate-400">{{ $hint }}</span>
    @endif
</div>
