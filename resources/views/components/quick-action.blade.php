@props(['action'])

@php
    $classes = [
        'purple' => 'border-violet-300 bg-violet-50 text-violet-700 hover:bg-violet-600 hover:text-white',
        'blue' => 'border-blue-300 bg-blue-50 text-blue-700 hover:bg-blue-600 hover:text-white',
        'emerald' => 'border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white',
        'orange' => 'border-orange-300 bg-orange-50 text-orange-700 hover:bg-orange-600 hover:text-white',
        'violet' => 'border-violet-300 bg-violet-50 text-violet-700 hover:bg-violet-600 hover:text-white',
        'sky' => 'border-sky-300 bg-sky-50 text-sky-700 hover:bg-sky-600 hover:text-white',
        'rose' => 'border-rose-300 bg-rose-50 text-rose-700 hover:bg-rose-600 hover:text-white',
        'teal' => 'border-teal-300 bg-teal-50 text-teal-700 hover:bg-teal-600 hover:text-white',
    ][$action['color']] ?? 'border-slate-300 bg-white text-slate-700';
@endphp

<a href="{{ route($action['route']) }}" class="flex min-h-12 items-center justify-center gap-2 rounded-lg border px-4 text-sm font-bold transition focus-visible:ring-2 focus-visible:ring-violet-500 {{ $classes }}">
    <i data-lucide="{{ $action['icon'] }}" class="size-5"></i>
    <span>{{ $action['label'] }}</span>
</a>
