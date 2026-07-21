@props(['status'])

@php
    $classes = match (strtolower((string) $status)) {
        'good', 'active', 'resolved', 'service' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'fair', 'fellowship', 'youth' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'maintenance', 'event' => 'bg-rose-50 text-rose-700 ring-rose-200',
        'critical' => 'bg-red-50 text-red-700 ring-red-200',
        default => 'bg-violet-50 text-violet-700 ring-violet-200',
    };
@endphp

<span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $classes }}">{{ $status }}</span>
