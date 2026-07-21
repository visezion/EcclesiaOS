@props(['metric'])

@php
    $tone = [
        'purple' => 'bg-violet-100 text-violet-600',
        'emerald' => 'bg-emerald-100 text-emerald-600',
        'rose' => 'bg-rose-100 text-rose-600',
        'indigo' => 'bg-indigo-100 text-indigo-600',
        'orange' => 'bg-orange-100 text-orange-600',
        'amber' => 'bg-amber-100 text-amber-600',
        'teal' => 'bg-teal-100 text-teal-600',
    ][$metric['color']] ?? 'bg-slate-100 text-slate-600';
@endphp

<a href="{{ route($metric['route']) }}" class="dashboard-card flex min-h-[92px] items-center gap-4 hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-2 focus-visible:ring-violet-500">
    <div class="grid size-12 shrink-0 place-items-center rounded-full {{ $tone }}">
        <i data-lucide="{{ $metric['icon'] }}" class="size-6"></i>
    </div>
    <div class="min-w-0">
        <div class="truncate text-xs font-semibold text-slate-500">{{ $metric['label'] }}</div>
        <div class="mt-1 text-2xl font-bold tracking-normal text-slate-950">{{ $metric['value'] }}</div>
        <div class="mt-1 flex items-center gap-1 text-xs">
            @if ($metric['change'])
                <span class="font-semibold text-emerald-600"><i data-lucide="arrow-up" class="inline size-3"></i> {{ $metric['change'] }}</span>
            @endif
            <span class="{{ $metric['change'] ? 'text-slate-500' : 'font-semibold text-emerald-600' }}">{{ $metric['period'] }}</span>
        </div>
    </div>
</a>
