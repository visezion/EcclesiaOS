<x-app-layout title="Counselling" :breadcrumbs="$breadcrumbs">
    @php
        $priorityClasses = ['urgent' => 'bg-rose-100 text-rose-700 ring-rose-200', 'high' => 'bg-rose-50 text-rose-700 ring-rose-200', 'medium' => 'bg-orange-50 text-orange-700 ring-orange-200', 'low' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'];
        $statusClasses = ['pending' => 'bg-orange-50 text-orange-700 ring-orange-200', 'assigned' => 'bg-violet-50 text-violet-700 ring-violet-200', 'in-progress' => 'bg-blue-50 text-blue-700 ring-blue-200', 'on-hold' => 'bg-slate-100 text-slate-600 ring-slate-200', 'resolved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'];
        $bookingStatusClasses = ['scheduled' => 'bg-blue-50 text-blue-700 ring-blue-100', 'confirmed' => 'bg-emerald-50 text-emerald-700 ring-emerald-100', 'completed' => 'bg-slate-100 text-slate-700 ring-slate-200', 'cancelled' => 'bg-rose-50 text-rose-700 ring-rose-100', 'no_show' => 'bg-orange-50 text-orange-700 ring-orange-100'];
        $selectedAssignedUserId = \App\Support\OpaqueId::decode(request('assigned_user_id'), \App\Models\User::class);
    @endphp

    <div
        x-data="{ createOpen: {{ $errors->any() ? 'true' : 'false' }}, editing: null, bookingOpen: false, bookingEditing: null }"
        class="space-y-5"
    >
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-xl bg-rose-100 text-rose-600">
                    <i data-lucide="heart-handshake" class="size-7"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-semibold text-slate-950">Counselling</h1>
                    <p class="text-sm text-slate-500">Confidential care cases, assignments, priority, and next actions.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('counselling.overview') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <i data-lucide="layout-dashboard" class="size-4"></i>Overview
                </a>
                <button type="button" @click="createOpen = true" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700">
                    <i data-lucide="plus" class="size-4"></i>New Case
                </button>
                <button type="button" @click="bookingOpen = true" class="inline-flex items-center justify-center gap-2 rounded-lg border border-violet-200 bg-white px-4 py-2.5 text-sm font-semibold text-violet-700 hover:bg-violet-50">
                    <i data-lucide="calendar-plus" class="size-4"></i>Book Session
                </button>
                <a href="{{ route('counselling.export', request()->query()) }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <i data-lucide="download" class="size-4"></i>Export
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card :metric="['label' => 'Open Cases', 'value' => number_format($stats['open']), 'change' => null, 'period' => 'active care', 'icon' => 'heart-handshake', 'color' => 'rose', 'route' => 'counselling.index']" />
            <x-stat-card :metric="['label' => 'Urgent', 'value' => number_format($stats['urgent']), 'change' => null, 'period' => 'needs attention', 'icon' => 'triangle-alert', 'color' => 'orange', 'route' => 'counselling.index']" />
            <x-stat-card :metric="['label' => 'Booked Sessions', 'value' => number_format($stats['scheduled']), 'change' => null, 'period' => 'next 14 days', 'icon' => 'calendar-days', 'color' => 'purple', 'route' => 'counselling.index']" />
            <x-stat-card :metric="['label' => 'Resolved', 'value' => number_format($stats['resolved']), 'change' => null, 'period' => 'this month', 'icon' => 'check-circle-2', 'color' => 'emerald', 'route' => 'counselling.index']" />
        </div>

        <div class="grid gap-3 md:grid-cols-4">
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Today</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($bookingStats['today']) }}</p><p class="mt-1 text-sm text-slate-500">sessions booked</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">This Week</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($bookingStats['week']) }}</p><p class="mt-1 text-sm text-slate-500">scheduled or confirmed</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Confirmed</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($bookingStats['confirmed']) }}</p><p class="mt-1 text-sm text-slate-500">ready sessions</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Completed</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($bookingStats['completed_month']) }}</p><p class="mt-1 text-sm text-slate-500">this month</p></div>
        </div>

        <form method="GET" action="{{ route('counselling.index') }}" class="dashboard-card grid gap-3 xl:grid-cols-[1fr_150px_150px_150px_170px_auto_auto]">
            <input name="q" value="{{ request('q') }}" class="h-10 rounded-lg border border-slate-200 px-3 text-sm" placeholder="Search member name or email...">
            <select name="type" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Type</option>@foreach($types as $type)<option value="{{ $type }}" @selected(request('type') === $type)>{{ $type }}</option>@endforeach</select>
            <select name="priority" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Priority</option>@foreach($priorities as $priority)<option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ Str::headline($priority) }}</option>@endforeach</select>
            <select name="status" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Status</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ Str::headline($status) }}</option>@endforeach</select>
            <select name="assigned_user_id" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Assigned To</option>@foreach($users as $user)<option value="{{ $user->opaqueId() }}" @selected($selectedAssignedUserId === $user->id)>{{ $user->name }}</option>@endforeach</select>
            <button class="h-10 rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white">Apply</button>
            <a href="{{ route('counselling.index') }}" class="inline-flex h-10 items-center px-3 text-sm font-semibold text-slate-500">Clear</a>
        </form>

        <section class="dashboard-card p-0">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">Case Register</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ number_format($cases->total()) }} confidential cases</p>
                </div>
                <button type="button" @click="createOpen = true" class="inline-flex items-center justify-center gap-2 rounded-lg border border-violet-200 px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50">
                    <i data-lucide="plus" class="size-4"></i>Add Case
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="table-compact min-w-[980px]">
                    <thead>
                        <tr><th>Member</th><th>Care</th><th>Assigned</th><th>Next Action</th><th>Due</th><th class="text-right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($cases as $case)
                            <tr>
                                <td>
                                    <a href="{{ $case->member ? route('members.show', $case->member) : '#' }}" class="font-semibold text-slate-950 hover:text-violet-600">{{ $case->member?->first_name }} {{ $case->member?->last_name }}</a>
                                    <div class="text-xs text-slate-500">{{ $case->member?->email ?: 'No email' }} · {{ $case->campus?->name ?? $case->member?->campus?->name ?? 'Unassigned' }}</div>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1.5">
                                        <span class="rounded-full px-2 py-1 text-[11px] font-semibold ring-1 {{ $priorityClasses[$case->priority] ?? $priorityClasses['medium'] }}">{{ Str::headline($case->priority) }}</span>
                                        <span class="rounded-full px-2 py-1 text-[11px] font-semibold ring-1 {{ $statusClasses[$case->status] ?? $statusClasses['pending'] }}">{{ Str::headline($case->status) }}</span>
                                    </div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $case->type }}</div>
                                </td>
                                <td>{{ $case->assignedUser?->name ?? 'Unassigned' }}</td>
                                <td class="max-w-sm truncate">{{ $case->next_action ?: 'Not set' }}</td>
                                <td>{{ $case->due_at?->format('M d, Y') ?? 'No date' }}</td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="editing = '{{ $case->opaqueId() }}'" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50" title="Edit case">
                                            <i data-lucide="pencil" class="size-4"></i>
                                        </button>
                                        <button form="case-delete-{{ $case->id }}" class="grid size-8 place-items-center rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50" title="Archive">
                                            <i data-lucide="archive" class="size-4"></i>
                                        </button>
                                    </div>
                                    <form id="case-delete-{{ $case->id }}" method="POST" action="{{ route('counselling.destroy', $case) }}" class="hidden">@csrf @method('DELETE')</form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-12"><x-empty-state icon="heart-handshake" title="No counselling cases found" message="Create a case or adjust the filters." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-4">{{ $cases->links() }}</div>
        </section>

        <section class="dashboard-card p-0">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">Booking Schedule</h2>
                    <p class="mt-1 text-sm text-slate-500">Upcoming counselling appointments with counselor, location, and conflict-safe time windows.</p>
                </div>
                <button type="button" @click="bookingOpen = true" class="inline-flex items-center justify-center gap-2 rounded-lg border border-violet-200 px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50">
                    <i data-lucide="calendar-plus" class="size-4"></i>Book Session
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="table-compact min-w-[1040px]">
                    <thead>
                        <tr><th>Session</th><th>Member</th><th>Counselor</th><th>Location</th><th>Status</th><th class="text-right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($bookings as $booking)
                            <tr>
                                <td>
                                    <div class="font-semibold text-slate-950">{{ $booking->starts_at?->format('M d, Y') }}</div>
                                    <div class="text-xs text-slate-500">{{ $booking->starts_at?->format('h:i A') }} - {{ $booking->ends_at?->format('h:i A') }}</div>
                                </td>
                                <td>
                                    <div class="font-semibold text-slate-950">{{ $booking->member?->first_name }} {{ $booking->member?->last_name }}</div>
                                    <div class="text-xs text-slate-500">{{ $booking->case?->type }} · {{ $booking->campus?->name ?? $booking->member?->campus?->name ?? 'Unassigned' }}</div>
                                </td>
                                <td>{{ $booking->counselor?->name ?? 'Unassigned' }}</td>
                                <td>
                                    <div>{{ Str::headline($booking->location_type) }}</div>
                                    <div class="max-w-xs truncate text-xs text-slate-500">{{ $booking->meeting_url ?: ($booking->location ?: 'No location set') }}</div>
                                </td>
                                <td><span class="rounded-full px-2 py-1 text-[11px] font-semibold ring-1 {{ $bookingStatusClasses[$booking->status] ?? $bookingStatusClasses['scheduled'] }}">{{ Str::headline($booking->status) }}</span></td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="bookingEditing = '{{ $booking->opaqueId() }}'" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50" title="Edit booking">
                                            <i data-lucide="pencil" class="size-4"></i>
                                        </button>
                                        @if(! in_array($booking->status, ['cancelled', 'completed'], true))
                                            <button form="booking-cancel-{{ $booking->id }}" class="grid size-8 place-items-center rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50" title="Cancel booking">
                                                <i data-lucide="calendar-x" class="size-4"></i>
                                            </button>
                                        @endif
                                    </div>
                                    <form id="booking-cancel-{{ $booking->id }}" method="POST" action="{{ route('counselling.bookings.destroy', $booking) }}" class="hidden">@csrf @method('DELETE')</form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-12"><x-empty-state icon="calendar-plus" title="No bookings scheduled" message="Book counselling sessions from active cases and assign a counselor, location, and time." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div x-cloak x-show="createOpen || editing || bookingOpen || bookingEditing" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/40" @click="createOpen = false; editing = null; bookingOpen = false; bookingEditing = null"></div>

        <aside x-cloak x-show="createOpen" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-xl overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="createOpen = false">
            <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Create Counselling Case</h2>
                    <p class="mt-1 text-sm text-slate-500">Add the member, assigned leader, priority, and next action.</p>
                </div>
                <button type="button" @click="createOpen = false" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button>
            </div>
            <form method="POST" action="{{ route('counselling.store') }}" class="space-y-4 p-5">
                @csrf
                @include('counselling.partials.case-form', ['case' => null])
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" @click="createOpen = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="check" class="size-4"></i>Create Case</button>
                </div>
            </form>
        </aside>

        @foreach($cases as $case)
            <aside x-cloak x-show="editing === '{{ $case->opaqueId() }}'" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-xl overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="editing = null">
                <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Edit Counselling Case</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $case->member ? $case->member->first_name.' '.$case->member->last_name : 'Unknown member' }}</p>
                    </div>
                    <button type="button" @click="editing = null" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button>
                </div>
                <form method="POST" action="{{ route('counselling.update', $case) }}" class="space-y-4 p-5">
                    @csrf @method('PUT')
                    @include('counselling.partials.case-form', ['case' => $case])
                    <div class="flex justify-between gap-3 border-t border-slate-100 pt-4">
                        <button form="drawer-delete-case-{{ $case->id }}" class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Archive</button>
                        <div class="flex gap-3">
                            <button type="button" @click="editing = null" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button>
                            <button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="check" class="size-4"></i>Save Changes</button>
                        </div>
                    </div>
                </form>
                <form id="drawer-delete-case-{{ $case->id }}" method="POST" action="{{ route('counselling.destroy', $case) }}" class="hidden">@csrf @method('DELETE')</form>
            </aside>
        @endforeach

        <aside x-cloak x-show="bookingOpen" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-xl overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="bookingOpen = false">
            <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Book Counselling Session</h2>
                    <p class="mt-1 text-sm text-slate-500">Schedule a session and avoid member or counselor conflicts.</p>
                </div>
                <button type="button" @click="bookingOpen = false" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button>
            </div>
            <form method="POST" action="{{ route('counselling.bookings.store') }}" class="space-y-4 p-5">
                @csrf
                @include('counselling.partials.booking-form', ['booking' => null])
                <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" @click="bookingOpen = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="calendar-plus" class="size-4"></i>Book Session</button>
                </div>
            </form>
        </aside>

        @foreach($bookings as $booking)
            <aside x-cloak x-show="bookingEditing === '{{ $booking->opaqueId() }}'" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-xl overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="bookingEditing = null">
                <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Edit Booking</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $booking->member?->first_name }} {{ $booking->member?->last_name }} · {{ $booking->starts_at?->format('M d, Y h:i A') }}</p>
                    </div>
                    <button type="button" @click="bookingEditing = null" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button>
                </div>
                <form method="POST" action="{{ route('counselling.bookings.update', $booking) }}" class="space-y-4 p-5">
                    @csrf @method('PUT')
                    @include('counselling.partials.booking-form', ['booking' => $booking])
                    <div class="flex justify-between gap-3 border-t border-slate-100 pt-4">
                        @if(! in_array($booking->status, ['cancelled', 'completed'], true))
                            <button form="drawer-cancel-booking-{{ $booking->id }}" class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Cancel Booking</button>
                        @else
                            <span></span>
                        @endif
                        <div class="flex gap-3">
                            <button type="button" @click="bookingEditing = null" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Close</button>
                            <button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="check" class="size-4"></i>Save Booking</button>
                        </div>
                    </div>
                </form>
                <form id="drawer-cancel-booking-{{ $booking->id }}" method="POST" action="{{ route('counselling.bookings.destroy', $booking) }}" class="hidden">@csrf @method('DELETE')</form>
            </aside>
        @endforeach
    </div>
</x-app-layout>
