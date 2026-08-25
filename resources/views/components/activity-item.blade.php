@props(['activity'])

<div class="flex gap-3 border-b border-slate-100 py-3 last:border-0">
    <div class="grid size-9 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-600">
        <i data-lucide="{{ $activity['icon'] }}" class="size-4"></i>
    </div>
    <div class="min-w-0 flex-1">
        <p class="break-words text-sm font-semibold leading-5 text-slate-800 [overflow-wrap:anywhere]">{{ $activity['description'] }}</p>
        <p class="text-xs text-slate-500">{{ $activity['time'] }}</p>
    </div>
</div>
