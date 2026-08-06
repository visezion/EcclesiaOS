<x-app-layout title="Financial Assistance" :breadcrumbs="$breadcrumbs">
    @php
        $statusTone = [
            'submitted' => 'bg-blue-50 text-blue-700 ring-blue-200',
            'under_review' => 'bg-violet-50 text-violet-700 ring-violet-200',
            'changes_requested' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'disbursed' => 'bg-teal-50 text-teal-700 ring-teal-200',
            'rejected' => 'bg-rose-50 text-rose-700 ring-rose-200',
            'cancelled' => 'bg-slate-100 text-slate-600 ring-slate-200',
        ];
        $urgencyTone = ['normal' => 'text-slate-500', 'important' => 'text-amber-600', 'urgent' => 'text-orange-600', 'critical' => 'text-rose-600'];
    @endphp

    <div class="space-y-5">
        @if(session('status'))<x-alert type="success">{{ session('status') }}</x-alert>@endif

        <header class="relative overflow-hidden rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="absolute -right-10 -top-20 size-56 rounded-full bg-violet-50"></div>
            <div class="relative flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4">
                    <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-violet-50 text-violet-600"><i data-lucide="hand-coins" class="size-7"></i></span>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-violet-600">Giving &amp; Finance</p>
                        <h1 class="text-2xl font-black text-slate-950">Financial Assistance</h1>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">Request support from your campus, another branch, or headquarters. Evidence, approvals, notifications, and disbursement records stay together.</p>
                    </div>
                </div>
                @if($canCreate)
                    <a href="{{ route('financial-assistance.create') }}" class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 text-sm font-bold text-white hover:bg-violet-700"><i data-lucide="plus" class="size-4"></i>New assistance request</a>
                @endif
            </div>
        </header>

        <section class="dashboard-stat-grid">
            @foreach([
                ['Open requests', $stats['open'], 'clock-3', 'bg-blue-50 text-blue-600'],
                ['Awaiting your action', $stats['awaiting_me'], 'badge-check', 'bg-amber-50 text-amber-600'],
                ['Approved', $stats['approved'], 'badge-check', 'bg-emerald-50 text-emerald-600'],
                ['Total disbursed', $currency.' '.number_format((float) $stats['disbursed'], 2), 'badge-dollar-sign', 'bg-violet-50 text-violet-600'],
            ] as [$label, $value, $icon, $tone])
                <div class="dashboard-card flex min-h-[104px] items-center gap-4">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl {{ $tone }}"><i data-lucide="{{ $icon }}" class="size-5"></i></span>
                    <div><div class="text-xl font-black text-slate-950">{{ $value }}</div><div class="mt-1 text-xs font-semibold text-slate-500">{{ $label }}</div></div>
                </div>
            @endforeach
        </section>

        <section class="dashboard-card">
            <form method="GET" class="grid gap-3 lg:grid-cols-[minmax(220px,1fr)_180px_170px_210px_auto]">
                <label class="relative"><span class="sr-only">Search requests</span><i data-lucide="search" class="absolute left-3 top-3 size-4 text-slate-400"></i><input name="q" value="{{ request('q') }}" placeholder="Reference, purpose, or beneficiary..." class="h-10 w-full rounded-lg border-slate-200 pl-10 text-sm focus:border-violet-400 focus:ring-violet-400"></label>
                <select name="status" class="h-10 rounded-lg border-slate-200 text-sm"><option value="">All statuses</option>@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select>
                <select name="urgency" class="h-10 rounded-lg border-slate-200 text-sm"><option value="">All urgency</option>@foreach($urgencies as $key => $label)<option value="{{ $key }}" @selected(request('urgency') === $key)>{{ $label }}</option>@endforeach</select>
                <select name="campus" class="h-10 rounded-lg border-slate-200 text-sm"><option value="">All receiving campuses</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected((string) request('campus') === (string) $campus->id)>{{ $campus->name }}</option>@endforeach</select>
                <div class="flex gap-2"><button class="inline-flex h-10 flex-1 items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 text-sm font-bold text-white hover:bg-violet-700"><i data-lucide="sliders-horizontal" class="size-4"></i>Filter</button>@if(request()->hasAny(['q','status','urgency','campus']))<a href="{{ route('financial-assistance.index') }}" class="grid size-10 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50" title="Clear filters"><i data-lucide="x" class="size-4"></i></a>@endif</div>
            </form>
        </section>

        <section class="dashboard-card overflow-hidden p-0">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div><h2 class="font-black text-slate-950">Requests</h2><p class="mt-0.5 text-xs text-slate-500">{{ number_format($requests->total()) }} visible requests</p></div>
                <div class="hidden items-center gap-2 text-xs font-semibold text-slate-400 sm:flex"><i data-lucide="shield-check" class="size-4 text-emerald-500"></i>Campus scoped and private</div>
            </div>

            <div class="divide-y divide-slate-100 lg:hidden">
                @forelse($requests as $item)
                    <a href="{{ route('financial-assistance.show', $item) }}" class="block p-4 transition hover:bg-slate-50">
                        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><div class="font-mono text-[10px] font-bold text-violet-600">{{ $item->reference }}</div><h3 class="mt-1 truncate text-sm font-black text-slate-950">{{ $item->title }}</h3></div><span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-bold ring-1 {{ $statusTone[$item->status] ?? $statusTone['cancelled'] }}">{{ $statuses[$item->status] ?? Str::headline($item->status) }}</span></div>
                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-500"><span class="font-black text-slate-900">{{ $item->currency }} {{ number_format((float) $item->amount, 2) }}</span><span><i data-lucide="map-pin" class="mr-1 inline size-3.5"></i>{{ $item->targetCampus?->name }}</span><span class="{{ $urgencyTone[$item->urgency] ?? 'text-slate-500' }}">{{ $urgencies[$item->urgency] ?? Str::headline($item->urgency) }}</span></div>
                    </a>
                @empty
                    <x-empty-state icon="hand-coins" title="No assistance requests" message="Create a request or adjust the filters to see matching records." />
                @endforelse
            </div>

            <div class="hidden overflow-x-auto lg:block">
                <table class="w-full min-w-[980px] text-left">
                    <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Request</th><th class="px-4 py-3">Requester</th><th class="px-4 py-3">Receiving campus</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Stage</th><th class="px-4 py-3">Status</th><th class="px-5 py-3"></th></tr></thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @forelse($requests as $item)
                            <tr class="group hover:bg-slate-50/80">
                                <td class="px-5 py-4"><div class="font-mono text-[10px] font-bold text-violet-600">{{ $item->reference }}</div><div class="mt-1 max-w-[260px] truncate font-bold text-slate-900">{{ $item->title }}</div><div class="mt-1 text-xs {{ $urgencyTone[$item->urgency] ?? 'text-slate-500' }}">{{ $urgencies[$item->urgency] ?? Str::headline($item->urgency) }}</div></td>
                                <td class="px-4 py-4"><div class="font-semibold text-slate-800">{{ $item->requester?->name ?? 'Former user' }}</div><div class="text-xs text-slate-400">{{ $item->sourceCampus?->name ?? 'No campus' }}</div></td>
                                <td class="px-4 py-4 text-slate-700">{{ $item->targetCampus?->name }}</td>
                                <td class="px-4 py-4 font-black text-slate-950">{{ $item->currency }} {{ number_format((float) $item->amount, 2) }}</td>
                                <td class="px-4 py-4 text-xs font-semibold text-slate-500">{{ Str::headline($item->current_stage) }}</td>
                                <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-[10px] font-bold ring-1 {{ $statusTone[$item->status] ?? $statusTone['cancelled'] }}">{{ $statuses[$item->status] ?? Str::headline($item->status) }}</span></td>
                                <td class="px-5 py-4 text-right"><a href="{{ route('financial-assistance.show', $item) }}" class="inline-flex items-center gap-1 text-xs font-black text-violet-600">Open <i data-lucide="arrow-right" class="size-3.5"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-empty-state icon="hand-coins" title="No assistance requests" message="Create a request or adjust the filters to see matching records." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($requests->hasPages())<div class="border-t border-slate-100 p-4">{{ $requests->links() }}</div>@endif
        </section>
    </div>
</x-app-layout>
