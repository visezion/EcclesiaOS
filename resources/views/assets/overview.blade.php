<x-app-layout title="Asset Overview" :breadcrumbs="$breadcrumbs">
    @php($bookingStatusClasses = ['reserved' => 'bg-blue-50 text-blue-700 ring-blue-100', 'checked_out' => 'bg-violet-50 text-violet-700 ring-violet-100', 'returned' => 'bg-emerald-50 text-emerald-700 ring-emerald-100', 'cancelled' => 'bg-rose-50 text-rose-700 ring-rose-100', 'overdue' => 'bg-orange-50 text-orange-700 ring-orange-100'])
    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-xl bg-violet-100 text-violet-600"><i data-lucide="package-check" class="size-7"></i></div>
                <div><h1 class="text-2xl font-semibold text-slate-950">Inventory Overview</h1><p class="text-sm text-slate-500">Asset status, condition, value, and recent inventory movement.</p></div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('assets.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700"><i data-lucide="list" class="size-4"></i>Register</a>
                <a href="{{ route('assets.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white"><i data-lucide="plus" class="size-4"></i>Add Asset</a>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card :metric="['label' => 'Total Assets', 'value' => number_format($stats['total']), 'change' => null, 'period' => 'tracked records', 'icon' => 'package-check', 'color' => 'purple', 'route' => 'assets.index']" />
            <x-stat-card :metric="['label' => 'Available', 'value' => number_format($stats['available']), 'change' => null, 'period' => 'ready for use', 'icon' => 'check-circle-2', 'color' => 'emerald', 'route' => 'assets.index']" />
            <x-stat-card :metric="['label' => 'Maintenance', 'value' => number_format($stats['maintenance']), 'change' => null, 'period' => 'needs action', 'icon' => 'wrench', 'color' => 'orange', 'route' => 'assets.index']" />
            <x-stat-card :metric="['label' => 'Inventory Value', 'value' => '$'.number_format($stats['value'], 2), 'change' => null, 'period' => 'purchase value', 'icon' => 'badge-dollar-sign', 'color' => 'teal', 'route' => 'assets.index']" />
        </div>

        <div class="grid gap-3 md:grid-cols-4">
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Today</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($bookingStats['today']) }}</p><p class="mt-1 text-sm text-slate-500">reservations</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">This Week</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($bookingStats['week']) }}</p><p class="mt-1 text-sm text-slate-500">scheduled</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Checked Out</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($bookingStats['checked_out']) }}</p><p class="mt-1 text-sm text-slate-500">active handovers</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Overdue</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($bookingStats['overdue']) }}</p><p class="mt-1 text-sm text-slate-500">needs action</p></div>
        </div>

        <div class="grid gap-4 xl:grid-cols-[1fr_360px]">
            <main class="space-y-4">
            <section class="dashboard-card p-0">
                <div class="border-b border-slate-100 p-4"><h2 class="text-base font-semibold text-slate-950">Upcoming Reservations</h2></div>
                <div class="overflow-x-auto">
                    <table class="table-compact min-w-[760px]">
                        <thead><tr><th>Asset</th><th>Window</th><th>Assigned</th><th>Location</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($upcomingBookings as $booking)
                                <tr><td><div class="font-semibold text-slate-950">{{ $booking->asset?->name }}</div><div class="text-xs text-slate-500">{{ $booking->asset?->category?->name ?? 'Uncategorized' }}</div></td><td><div>{{ $booking->starts_at?->format('M d, Y') }}</div><div class="text-xs text-slate-500">{{ $booking->starts_at?->format('h:i A') }} - {{ $booking->ends_at?->format('h:i A') }}</div></td><td>{{ $booking->member ? $booking->member->first_name.' '.$booking->member->last_name : ($booking->assignedUser?->name ?? 'Unassigned') }}</td><td>{{ $booking->location ?: ($booking->campus?->name ?? 'No location') }}</td><td><span class="rounded-full px-2 py-1 text-[11px] font-semibold ring-1 {{ $bookingStatusClasses[$booking->status] ?? $bookingStatusClasses['reserved'] }}">{{ Str::headline($booking->status) }}</span></td></tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-12"><x-empty-state icon="calendar-plus" title="No reservations scheduled" message="Reserve assets from the inventory register." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="dashboard-card p-0">
                <div class="border-b border-slate-100 p-4"><h2 class="text-base font-semibold text-slate-950">Recent Assets</h2></div>
                <div class="overflow-x-auto">
                    <table class="table-compact min-w-[760px]">
                        <thead><tr><th>Asset</th><th>Category</th><th>{{ $terminology['campus_singular'] }}</th><th>Status</th><th>Condition</th><th class="text-right">Action</th></tr></thead>
                        <tbody>
                            @forelse($recentAssets as $asset)
                                <tr><td><div class="font-semibold text-slate-950">{{ $asset->name }}</div><div class="text-xs text-slate-500">{{ $asset->serial_number ?: 'No serial number' }}</div></td><td>{{ $asset->category?->name ?? 'Uncategorized' }}</td><td>{{ $asset->campus?->name ?? 'Unassigned' }}</td><td><x-status-badge :status="Str::headline($asset->status)" /></td><td><x-status-badge :status="Str::headline($asset->condition)" /></td><td class="text-right"><a href="{{ route('assets.edit', $asset) }}" class="text-sm font-semibold text-violet-600">Edit</a></td></tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-12"><x-empty-state icon="package-check" title="No assets yet" message="Create the first asset to start the inventory." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            </main>
            <aside class="space-y-4">
                <section class="dashboard-card"><h2 class="mb-4 text-base font-semibold text-slate-950">Status Mix</h2><div class="space-y-3">@foreach($statusRows as $row)<div class="grid grid-cols-[1fr_auto] gap-2 text-sm"><span class="text-slate-600">{{ $row['label'] }}</span><span class="font-semibold">{{ $row['value'] }}</span><span class="col-span-2 h-1.5 rounded-full bg-slate-100"><span class="block h-full rounded-full bg-violet-500" style="width: {{ $row['percent'] }}%"></span></span></div>@endforeach</div></section>
                <section class="dashboard-card"><h2 class="mb-4 text-base font-semibold text-slate-950">Condition</h2><div class="space-y-3">@forelse($conditionRows as $row)<div class="grid grid-cols-[1fr_auto] gap-2 text-sm"><span class="text-slate-600">{{ $row['label'] }}</span><span class="font-semibold">{{ $row['value'] }}</span><span class="col-span-2 h-1.5 rounded-full bg-slate-100"><span class="block h-full rounded-full bg-emerald-500" style="width: {{ $row['percent'] }}%"></span></span></div>@empty<div class="text-sm text-slate-500">No condition data yet.</div>@endforelse</div></section>
                <section class="dashboard-card"><h2 class="mb-4 text-base font-semibold text-slate-950">Top Categories</h2><div class="space-y-3">@forelse($categoryRows as $category)<div class="flex justify-between gap-3 text-sm"><span class="truncate text-slate-600">{{ $category->name }}</span><span class="font-semibold">{{ number_format($category->assets_count) }}</span></div>@empty<div class="text-sm text-slate-500">No categories yet.</div>@endforelse</div></section>
            </aside>
        </div>
    </div>
</x-app-layout>
