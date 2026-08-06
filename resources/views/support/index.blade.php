<x-app-layout title="Support Center" :breadcrumbs="$breadcrumbs">
    @php
        $statusTone = fn (string $status): string => match ($status) {
            'new' => 'bg-blue-50 text-blue-700',
            'triaged' => 'bg-violet-50 text-violet-700',
            'in_progress' => 'bg-amber-50 text-amber-700',
            'waiting_on_church' => 'bg-orange-50 text-orange-700',
            'resolved' => 'bg-emerald-50 text-emerald-700',
            default => 'bg-slate-100 text-slate-600',
        };
    @endphp

    <div class="space-y-4">
        @if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>@endif

        <header class="relative overflow-hidden rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="absolute -right-10 -top-20 size-56 rounded-full bg-violet-50"></div>
            <div class="relative flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 items-center gap-4">
                    <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-violet-50 text-violet-600"><i data-lucide="life-buoy" class="size-7"></i></span>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-violet-600">EcclesiaOS Support Network</p>
                        <h1 class="text-2xl font-black text-slate-950">Support Center</h1>
                        <p class="mt-1 max-w-3xl text-sm text-slate-500"><span class="font-semibold text-slate-700">How can we help?</span> One support workspace for private tickets, shared solutions, guides, and central assistance.</p>
                    </div>
                </div>
                <a href="{{ route('support.tickets.create') }}" class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 text-sm font-bold text-white hover:bg-violet-700"><i data-lucide="list-plus" class="size-4"></i>Submit a new ticket</a>
            </div>
        </header>

        <x-support-nav />

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach([
                ['Open tickets', $stats['open'], 'inbox', 'bg-violet-50 text-violet-600', route('support.tickets.index', ['status' => 'new'])],
                ['In progress', $stats['in_progress'], 'clock-3', 'bg-amber-50 text-amber-600', route('support.tickets.index', ['status' => 'in_progress'])],
                ['Waiting on church', $stats['waiting'], 'hourglass', 'bg-orange-50 text-orange-600', route('support.tickets.index', ['status' => 'waiting_on_church'])],
                ['Resolved', $stats['resolved'], 'badge-check', 'bg-emerald-50 text-emerald-600', route('support.tickets.index', ['status' => 'resolved'])],
                ['Delivery failed', $stats['failed'], 'circle-alert', 'bg-rose-50 text-rose-600', route('support.tickets.index')],
            ] as [$label, $value, $icon, $tone, $url])
                <a href="{{ $url }}" class="dashboard-card group block">
                    <div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl {{ $tone }}"><i data-lucide="{{ $icon }}" class="size-5"></i></span><div><div class="text-2xl font-black text-slate-950">{{ number_format($value) }}</div><div class="text-xs font-semibold text-slate-500">{{ $label }}</div></div></div>
                    <span class="mt-3 inline-flex items-center gap-1 text-[10px] font-bold text-violet-600">View all <i data-lucide="arrow-right" class="size-3"></i></span>
                </a>
            @endforeach
        </section>

        <section class="grid gap-4 2xl:grid-cols-[1fr_1fr_1.15fr]">
            <div class="dashboard-card">
                <div class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="list-plus" class="size-4"></i></span><div><h2 class="font-black text-slate-950">Submit a new ticket</h2><p class="text-xs text-slate-500">Create a private, trackable support request.</p></div></div>
                <form method="POST" action="{{ route('support.tickets.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                    @csrf
                    <label class="block text-xs font-bold text-slate-700">Subject<input name="subject" required maxlength="180" class="mt-1.5 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm" placeholder="Briefly describe your issue"></label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-xs font-bold text-slate-700">Category<select name="category" class="mt-1.5 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm">@foreach($categories as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                        <label class="block text-xs font-bold text-slate-700">Priority<select name="priority" class="mt-1.5 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm">@foreach($priorities as $value => $label)<option value="{{ $value }}" @selected($value === 'normal')>{{ $label }}</option>@endforeach</select></label>
                    </div>
                    <label class="block text-xs font-bold text-slate-700">Description<textarea name="description" required rows="5" class="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm leading-6" placeholder="What happened, what did you expect, and what have you tried?"></textarea></label>
                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-2.5 text-xs text-slate-500"><span class="inline-flex items-center gap-2 font-semibold"><i data-lucide="paperclip" class="size-4 text-violet-600"></i>Add attachments</span><input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.txt,.csv,.log" class="max-w-48 text-[10px]"></label>
                    <div class="flex items-center justify-between gap-3"><a href="{{ route('support.tickets.create') }}" class="text-xs font-bold text-violet-600">Open detailed form</a><button class="inline-flex h-10 items-center gap-2 rounded-lg bg-violet-600 px-5 text-xs font-bold text-white"><i data-lucide="send" class="size-4"></i>Submit a ticket</button></div>
                </form>
            </div>

            <div class="dashboard-card">
                <div class="flex items-center justify-between gap-3"><div class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="sparkles" class="size-4"></i></span><div><h2 class="font-black text-slate-950">Suggested solutions</h2><p class="text-xs text-slate-500">Resolved requests that may help before you submit.</p></div></div><a href="{{ route('support.community') }}" class="text-[10px] font-bold text-violet-600">View all</a></div>
                <div class="mt-4 divide-y divide-slate-100">
                    @forelse($suggestedTickets as $ticket)
                        <a href="{{ route('support.tickets.show', $ticket) }}" class="flex items-start gap-3 py-3 first:pt-0 last:pb-0"><span class="grid size-8 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-600"><i data-lucide="badge-check" class="size-4"></i></span><span class="min-w-0 flex-1"><span class="block truncate text-xs font-bold text-slate-800">{{ $ticket->subject }}</span><span class="mt-1 block text-[10px] text-slate-500">Resolved {{ $ticket->resolved_at?->diffForHumans() ?? $ticket->updated_at->diffForHumans() }}</span></span><span class="rounded-full bg-emerald-50 px-2 py-1 text-[9px] font-bold text-emerald-700">Solved</span></a>
                    @empty
                        <div class="py-10 text-center"><i data-lucide="lightbulb" class="mx-auto size-7 text-slate-300"></i><p class="mt-2 text-xs text-slate-500">Resolved solutions will appear here.</p></div>
                    @endforelse
                </div>
            </div>

            <div class="dashboard-card">
                <div class="flex items-center justify-between"><div><h2 class="font-black text-slate-950">Recent activity</h2><p class="mt-1 text-xs text-slate-500">Latest requests and delivery progress.</p></div><a href="{{ route('support.tickets.index') }}" class="text-[10px] font-bold text-violet-600">View all tickets</a></div>
                <div class="mt-4 divide-y divide-slate-100">
                    @forelse($recentTickets as $ticket)
                        <a href="{{ route('support.tickets.show', $ticket) }}" class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 py-3 first:pt-0 last:pb-0"><span class="size-2 rounded-full {{ $ticket->sync_status === 'failed' ? 'bg-rose-500' : ($ticket->status === 'resolved' ? 'bg-emerald-500' : 'bg-violet-500') }}"></span><span class="min-w-0"><span class="block truncate text-xs font-bold text-slate-800">{{ $ticket->subject }}</span><span class="mt-1 block font-mono text-[9px] text-slate-400">{{ $ticket->reference }}</span></span><span class="rounded-full px-2 py-1 text-[9px] font-bold {{ $statusTone($ticket->status) }}">{{ $statuses[$ticket->status] ?? str($ticket->status)->headline() }}</span></a>
                    @empty
                        <div class="py-10 text-center text-xs text-slate-500">No support activity yet.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="dashboard-card">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="refresh-cw" class="size-4"></i></span><div><h2 class="font-black text-slate-950">Synchronization status</h2><p class="text-xs text-slate-500">{{ $connection && $connection['enabled'] ? 'Connected to '.$connection['endpoint'] : 'Central support connection is currently disabled.' }}</p></div></div>
                <div class="grid flex-1 gap-3 sm:grid-cols-3 lg:max-w-2xl">
                    @foreach([['Synchronized', $syncStats['synced'], 'text-emerald-600'], ['Waiting to send', $syncStats['pending'], 'text-amber-600'], ['Delivery failed', $syncStats['failed'], 'text-rose-600']] as [$label, $value, $tone])
                        <div class="rounded-lg bg-slate-50 px-3 py-2"><div class="text-lg font-black text-slate-950">{{ number_format($value) }}</div><div class="text-[10px] font-bold {{ $tone }}">{{ $label }}</div></div>
                    @endforeach
                </div>
                @if(auth()->user()->isSuperAdministrator() || auth()->user()->hasPermission('manage settings'))<a href="{{ route('central-support.index') }}" class="inline-flex items-center gap-2 text-xs font-bold text-violet-600">Connection details <i data-lucide="arrow-right" class="size-3.5"></i></a>@endif
            </div>
        </section>
    </div>
</x-app-layout>
