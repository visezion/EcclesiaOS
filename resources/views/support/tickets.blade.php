<x-app-layout title="My Support Tickets" :breadcrumbs="$breadcrumbs">
    @php
        $statusTone = fn (string $status): string => match ($status) {
            'new' => 'bg-blue-50 text-blue-700',
            'triaged' => 'bg-violet-50 text-violet-700',
            'in_progress' => 'bg-amber-50 text-amber-700',
            'waiting_on_church' => 'bg-orange-50 text-orange-700',
            'resolved' => 'bg-emerald-50 text-emerald-700',
            default => 'bg-slate-100 text-slate-600',
        };
        $priorityTone = fn (string $priority): string => match ($priority) {
            'urgent' => 'bg-rose-50 text-rose-700',
            'high' => 'bg-orange-50 text-orange-700',
            'normal' => 'bg-amber-50 text-amber-700',
            default => 'bg-emerald-50 text-emerald-700',
        };
    @endphp

    <div class="space-y-4">
        @if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>@endif

        <header class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4"><span class="grid size-14 place-items-center rounded-2xl bg-violet-50 text-violet-600"><i data-lucide="inbox" class="size-7"></i></span><div><p class="text-xs font-bold uppercase tracking-wide text-violet-600">Support Center</p><h1 class="text-2xl font-black text-slate-950">My Tickets</h1><p class="mt-0.5 text-sm text-slate-500">Track private requests, replies, progress, and delivery status.</p></div></div>
            <a href="{{ route('support.tickets.create') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 text-sm font-bold text-white"><i data-lucide="list-plus" class="size-4"></i>Submit new ticket</a>
        </header>

        <x-support-nav />

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach([
                ['Open', $stats['open'], 'inbox', 'bg-violet-50 text-violet-600'],
                ['In progress', $stats['in_progress'], 'clock-3', 'bg-amber-50 text-amber-600'],
                ['Waiting', $stats['waiting'], 'hourglass', 'bg-orange-50 text-orange-600'],
                ['Resolved', $stats['resolved'], 'badge-check', 'bg-emerald-50 text-emerald-600'],
                ['Delivery failed', $stats['failed'], 'circle-alert', 'bg-rose-50 text-rose-600'],
            ] as [$label, $value, $icon, $tone])
                <div class="dashboard-card"><div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl {{ $tone }}"><i data-lucide="{{ $icon }}" class="size-5"></i></span><div><div class="text-2xl font-black text-slate-950">{{ number_format($value) }}</div><div class="text-xs font-semibold text-slate-500">{{ $label }}</div></div></div></div>
            @endforeach
        </section>

        <section class="dashboard-card">
            <form method="GET" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_160px_190px_150px_auto_auto]">
                <label class="relative sm:col-span-2 xl:col-span-1"><i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i><input name="q" value="{{ request('q') }}" class="h-10 w-full rounded-lg border border-slate-200 pl-9 pr-3 text-sm" placeholder="Search subject or ticket ID"></label>
                <select name="status" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">All statuses</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select>
                <select name="category" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">All categories</option>@foreach($categories as $value => $label)<option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>@endforeach</select>
                <select name="priority" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">All priorities</option>@foreach($priorities as $value => $label)<option value="{{ $value }}" @selected(request('priority') === $value)>{{ $label }}</option>@endforeach</select>
                <button class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-slate-950 px-4 text-xs font-bold text-white"><i data-lucide="list-filter" class="size-4"></i>Filter</button>
                <a href="{{ route('support.tickets.index') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 px-4 text-xs font-bold text-slate-600"><i data-lucide="x" class="size-4"></i>Clear</a>
            </form>
        </section>

        <div class="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_340px]">
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="table-compact min-w-[1050px]">
                        <thead><tr><th>Ticket</th><th>Subject</th><th>Category</th><th>Priority</th><th>Status</th><th>Progress</th><th>Sync</th><th>Updated</th><th></th></tr></thead>
                        <tbody>
                            @forelse($tickets as $ticket)
                                <tr>
                                    <td class="font-mono text-[10px] font-black text-violet-700">{{ $ticket->reference }}</td>
                                    <td><div class="max-w-64 truncate font-bold text-slate-900">{{ $ticket->subject }}</div><div class="mt-1 text-[10px] text-slate-400">{{ $ticket->replies_count }} replies · {{ $ticket->attachments_count }} files</div></td>
                                    <td>{{ $categories[$ticket->category] ?? str($ticket->category)->headline() }}</td>
                                    <td><span class="rounded-full px-2 py-1 text-[9px] font-bold {{ $priorityTone($ticket->priority) }}">{{ $priorities[$ticket->priority] ?? str($ticket->priority)->headline() }}</span></td>
                                    <td><span class="rounded-full px-2 py-1 text-[9px] font-bold {{ $statusTone($ticket->status) }}">{{ $statuses[$ticket->status] ?? str($ticket->status)->headline() }}</span></td>
                                    <td><div class="flex items-center gap-2"><div class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-violet-600" style="width: {{ $ticket->progress }}%"></div></div><span class="text-[10px] font-bold">{{ $ticket->progress }}%</span></div></td>
                                    <td><span class="inline-flex items-center gap-1.5 text-[10px] font-bold {{ $ticket->sync_status === 'failed' ? 'text-rose-600' : ($ticket->sync_status === 'synced' ? 'text-emerald-600' : 'text-amber-600') }}"><span class="size-1.5 rounded-full bg-current"></span>{{ str($ticket->sync_status)->headline() }}</span></td>
                                    <td class="text-[10px]">{{ ($ticket->last_activity_at ?? $ticket->updated_at)->diffForHumans() }}</td>
                                    <td><a href="{{ route('support.tickets.show', $ticket) }}" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-500"><i data-lucide="arrow-right" class="size-4"></i></a></td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="py-12 text-center text-sm text-slate-500">No tickets match the current filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between"><span>Showing {{ number_format($tickets->firstItem() ?? 0) }}–{{ number_format($tickets->lastItem() ?? 0) }} of {{ number_format($tickets->total()) }} tickets</span>{{ $tickets->onEachSide(1)->links() }}</div>
            </section>

            <aside class="hidden space-y-4 2xl:block">
                @if($selectedTicket)
                    <section class="dashboard-card">
                        <div class="flex items-start justify-between gap-3"><div><span class="font-mono text-[10px] font-black text-violet-700">{{ $selectedTicket->reference }}</span><h2 class="mt-1 font-black text-slate-950">{{ $selectedTicket->subject }}</h2></div><span class="rounded-full px-2 py-1 text-[9px] font-bold {{ $statusTone($selectedTicket->status) }}">{{ $statuses[$selectedTicket->status] ?? str($selectedTicket->status)->headline() }}</span></div>
                        <p class="mt-4 line-clamp-4 text-xs leading-5 text-slate-500">{{ $selectedTicket->description }}</p>
                        <dl class="mt-4 grid grid-cols-2 gap-3 text-xs"><div><dt class="text-slate-400">Category</dt><dd class="mt-1 font-bold text-slate-700">{{ $categories[$selectedTicket->category] ?? str($selectedTicket->category)->headline() }}</dd></div><div><dt class="text-slate-400">Priority</dt><dd class="mt-1 font-bold text-slate-700">{{ $priorities[$selectedTicket->priority] ?? str($selectedTicket->priority)->headline() }}</dd></div><div><dt class="text-slate-400">Replies</dt><dd class="mt-1 font-bold text-slate-700">{{ $selectedTicket->replies_count }}</dd></div><div><dt class="text-slate-400">Assignee</dt><dd class="mt-1 font-bold text-slate-700">{{ $selectedTicket->assignee?->name ?? 'Support queue' }}</dd></div></dl>
                        <div class="mt-4 rounded-lg bg-slate-50 p-3"><div class="flex justify-between text-[10px] font-bold"><span>Delivery progress</span><span>{{ $selectedTicket->progress }}%</span></div><div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-violet-600" style="width: {{ $selectedTicket->progress }}%"></div></div></div>
                        <a href="{{ route('support.tickets.show', $selectedTicket) }}" class="mt-4 inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-violet-600 text-xs font-bold text-white">Open ticket <i data-lucide="arrow-right" class="size-4"></i></a>
                    </section>
                    <section class="dashboard-card"><h3 class="font-black text-slate-950">Church support contact</h3><p class="mt-2 text-xs leading-5 text-slate-500">Central EcclesiaOS Support<br>{{ config('services.central_support.url') }}</p></section>
                @endif
            </aside>
        </div>
    </div>
</x-app-layout>
