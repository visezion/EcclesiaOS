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
    $change = filled($metric['change'] ?? null) ? trim((string) $metric['change']) : null;
    $changeIsNegative = $change !== null && str_starts_with($change, '-');
    $changeIsNeutral = $change !== null && preg_match('/^0(?:\.0+)?%$/', $change) === 1;
    $changeIcon = $changeIsNegative ? 'arrow-down' : ($changeIsNeutral ? 'minus' : 'arrow-up');
    $changeTone = $changeIsNegative ? 'text-rose-600' : ($changeIsNeutral ? 'text-slate-500' : 'text-emerald-600');
@endphp

<a href="{{ route($metric['route']) }}" class="dashboard-card flex min-h-[104px] items-center gap-3 hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-2 focus-visible:ring-violet-500 sm:gap-4" aria-label="{{ $term($metric['label']).': '.$metric['value'] }}">
    <div class="grid size-10 shrink-0 place-items-center rounded-full sm:size-12 {{ $tone }}">
        <i data-lucide="{{ $metric['icon'] }}" class="size-5 sm:size-6"></i>
    </div>
    <div class="min-w-0 flex-1">
        <div class="text-xs font-semibold leading-tight text-slate-500">{{ $term($metric['label']) }}</div>
        <div class="mt-1 break-words text-xl font-bold leading-tight tracking-normal text-slate-950 sm:text-2xl">{{ $metric['value'] }}</div>
        <div class="mt-1 flex min-w-0 flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs leading-tight">
            @if ($change)
                <span class="shrink-0 font-semibold {{ $changeTone }}"><i data-lucide="{{ $changeIcon }}" class="inline size-3"></i> {{ $change }}</span>
            @endif
            <span class="min-w-0 {{ $change ? 'text-slate-500' : 'font-semibold text-emerald-600' }}">{{ $term($metric['period']) }}</span>
        </div>
    </div>
</a>
