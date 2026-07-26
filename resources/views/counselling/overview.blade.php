<x-app-layout title="Counselling Overview" :breadcrumbs="$breadcrumbs">
    @php($bookingStatusClasses = ['scheduled' => 'bg-blue-50 text-blue-700 ring-blue-100', 'confirmed' => 'bg-emerald-50 text-emerald-700 ring-emerald-100', 'completed' => 'bg-slate-100 text-slate-700 ring-slate-200', 'cancelled' => 'bg-rose-50 text-rose-700 ring-rose-100', 'no_show' => 'bg-orange-50 text-orange-700 ring-orange-100'])
    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-xl bg-rose-100 text-rose-600"><i data-lucide="heart-handshake" class="size-7"></i></div>
                <div><h1 class="text-2xl font-semibold text-slate-950">Counselling Overview</h1><p class="text-sm text-slate-500">Case health, bookings, assignments, next actions, and outcomes.</p></div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('counselling.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700"><i data-lucide="list" class="size-4"></i>Cases</a>
                <a href="{{ route('counselling.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white"><i data-lucide="calendar-plus" class="size-4"></i>Book Session</a>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card :metric="['label' => 'Open Cases', 'value' => number_format($stats['open']), 'change' => null, 'period' => 'active care', 'icon' => 'heart-handshake', 'color' => 'purple', 'route' => 'counselling.index']" />
            <x-stat-card :metric="['label' => 'Urgent', 'value' => number_format($stats['urgent']), 'change' => null, 'period' => 'priority cases', 'icon' => 'siren', 'color' => 'rose', 'route' => 'counselling.index']" />
            <x-stat-card :metric="['label' => 'Booked Sessions', 'value' => number_format($stats['scheduled']), 'change' => null, 'period' => 'next 14 days', 'icon' => 'calendar-clock', 'color' => 'orange', 'route' => 'counselling.index']" />
            <x-stat-card :metric="['label' => 'Resolved', 'value' => number_format($stats['resolved']), 'change' => null, 'period' => 'this month', 'icon' => 'badge-check', 'color' => 'emerald', 'route' => 'counselling.index']" />
        </div>

        <div class="grid gap-3 md:grid-cols-4">
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Today</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($bookingStats['today']) }}</p><p class="mt-1 text-sm text-slate-500">sessions</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">This Week</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($bookingStats['week']) }}</p><p class="mt-1 text-sm text-slate-500">upcoming</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Confirmed</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($bookingStats['confirmed']) }}</p><p class="mt-1 text-sm text-slate-500">ready</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Completed</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($bookingStats['completed_month']) }}</p><p class="mt-1 text-sm text-slate-500">this month</p></div>
        </div>

        <div class="grid gap-4 xl:grid-cols-[1fr_360px]">
            <main class="space-y-4">
                <section class="dashboard-card p-0">
                    <div class="border-b border-slate-100 p-4"><h2 class="text-base font-semibold text-slate-950">Upcoming Bookings</h2></div>
                    <div class="overflow-x-auto">
                        <table class="table-compact min-w-[760px]">
                            <thead><tr><th>Session</th><th>Member</th><th>Counselor</th><th>Location</th><th>Status</th></tr></thead>
                            <tbody>
                                @forelse($upcomingBookings as $booking)
                                    <tr>
                                        <td><div class="font-semibold text-slate-950">{{ $booking->starts_at?->format('M d, Y') }}</div><div class="text-xs text-slate-500">{{ $booking->starts_at?->format('h:i A') }} - {{ $booking->ends_at?->format('h:i A') }}</div></td>
                                        <td>{{ $booking->member?->first_name }} {{ $booking->member?->last_name }}</td>
                                        <td>{{ $booking->counselor?->name ?? 'Unassigned' }}</td>
                                        <td>{{ Str::headline($booking->location_type) }}</td>
                                        <td><span class="rounded-full px-2 py-1 text-[11px] font-semibold ring-1 {{ $bookingStatusClasses[$booking->status] ?? $bookingStatusClasses['scheduled'] }}">{{ Str::headline($booking->status) }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-12"><x-empty-state icon="calendar-plus" title="No bookings scheduled" message="Book sessions from the counselling register." /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="dashboard-card p-0">
                    <div class="border-b border-slate-100 p-4"><h2 class="text-base font-semibold text-slate-950">Recent Cases</h2></div>
                    <div class="overflow-x-auto">
                        <table class="table-compact min-w-[760px]">
                            <thead><tr><th>Member</th><th>Type</th><th>Priority</th><th>Status</th><th>Due</th><th class="text-right">Action</th></tr></thead>
                            <tbody>
                                @forelse($recentCases as $case)
                                    <tr><td><div class="font-semibold text-slate-950">{{ $case->member ? $case->member->first_name.' '.$case->member->last_name : 'Unknown member' }}</div><div class="text-xs text-slate-500">{{ $case->assignedUser?->name ?? 'Unassigned' }}</div></td><td>{{ $case->type }}</td><td><x-status-badge :status="Str::headline($case->priority)" /></td><td><x-status-badge :status="Str::headline($case->status)" /></td><td>{{ $case->due_at?->format('M d, Y h:i A') ?? 'No date' }}</td><td class="text-right"><a href="{{ route('counselling.edit', $case) }}" class="text-sm font-semibold text-violet-600">Edit</a></td></tr>
                                @empty
                                    <tr><td colspan="6" class="px-4 py-12"><x-empty-state icon="heart-handshake" title="No cases yet" message="Create the first counselling or family care case." /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>

            <aside class="space-y-4">
                <section class="dashboard-card"><h2 class="mb-4 text-base font-semibold text-slate-950">Status</h2><div class="space-y-3">@foreach($statusRows as $row)<div class="grid grid-cols-[1fr_auto] gap-2 text-sm"><span class="text-slate-600">{{ $row['label'] }}</span><span class="font-semibold">{{ $row['value'] }}</span><span class="col-span-2 h-1.5 rounded-full bg-slate-100"><span class="block h-full rounded-full bg-violet-500" style="width: {{ $row['percent'] }}%"></span></span></div>@endforeach</div></section>
                <section class="dashboard-card"><h2 class="mb-4 text-base font-semibold text-slate-950">Priority</h2><div class="space-y-3">@foreach($priorityRows as $row)<div class="grid grid-cols-[1fr_auto] gap-2 text-sm"><span class="text-slate-600">{{ $row['label'] }}</span><span class="font-semibold">{{ $row['value'] }}</span><span class="col-span-2 h-1.5 rounded-full bg-slate-100"><span class="block h-full rounded-full bg-rose-500" style="width: {{ $row['percent'] }}%"></span></span></div>@endforeach</div></section>
            </aside>
        </div>
    </div>
</x-app-layout>
