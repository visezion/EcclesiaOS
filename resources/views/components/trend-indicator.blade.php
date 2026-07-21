@props(['value' => null])

@if ($value)
    <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600">
        <i data-lucide="arrow-up" class="size-3"></i>{{ $value }}
    </span>
@endif
