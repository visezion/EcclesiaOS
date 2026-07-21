@props(['event'])

<div class="flex gap-3 border-b border-slate-100 py-3 last:border-0">
    <div class="grid w-12 shrink-0 place-items-center rounded-lg bg-rose-50 px-2 py-1 text-center text-rose-600">
        <span class="block text-[10px] font-bold uppercase">{{ str($event['date'])->before(' ') }}</span>
        <span class="block text-lg font-black leading-none">{{ str($event['date'])->after(' ') }}</span>
    </div>
    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-bold text-slate-900">{{ $event['title'] }}</p>
        <p class="truncate text-xs text-slate-500">{{ $event['time'] }}</p>
        <p class="truncate text-xs text-slate-500">{{ $event['venue'] }}</p>
    </div>
    <x-status-badge :status="$event['type']" />
</div>
