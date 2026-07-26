<x-app-layout title="Asset Inventory" :breadcrumbs="$breadcrumbs">
    @php
        $bookingStatusClasses = ['reserved' => 'bg-blue-50 text-blue-700 ring-blue-100', 'checked_out' => 'bg-violet-50 text-violet-700 ring-violet-100', 'returned' => 'bg-emerald-50 text-emerald-700 ring-emerald-100', 'cancelled' => 'bg-rose-50 text-rose-700 ring-rose-100', 'overdue' => 'bg-orange-50 text-orange-700 ring-orange-100'];
    @endphp

    <div x-data="{ createOpen: {{ $errors->any() ? 'true' : 'false' }}, editing: null, bookingOpen: false, bookingEditing: null, categoryOpen: false }" class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-xl bg-violet-100 text-violet-600"><i data-lucide="package-check" class="size-7"></i></div>
                <div>
                    <h1 class="text-2xl font-semibold text-slate-950">Asset Inventory</h1>
                    <p class="text-sm text-slate-500">Inventory, condition, reservations, check-outs, category, campus, and value.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('assets.overview') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i data-lucide="layout-dashboard" class="size-4"></i>Overview</a>
                <button type="button" @click="createOpen = true" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="plus" class="size-4"></i>Add Asset</button>
                <button type="button" @click="bookingOpen = true" class="inline-flex items-center justify-center gap-2 rounded-lg border border-violet-200 bg-white px-4 py-2.5 text-sm font-semibold text-violet-700 hover:bg-violet-50"><i data-lucide="calendar-plus" class="size-4"></i>Reserve</button>
                <a href="{{ route('assets.export', request()->query()) }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i data-lucide="download" class="size-4"></i>Export</a>
            </div>
        </div>

        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card :metric="['label' => 'Total Assets', 'value' => number_format($stats['total']), 'change' => null, 'period' => 'tracked records', 'icon' => 'package-check', 'color' => 'purple', 'route' => 'assets.index']" />
            <x-stat-card :metric="['label' => 'Available', 'value' => number_format($stats['available']), 'change' => null, 'period' => 'ready for use', 'icon' => 'check-circle-2', 'color' => 'emerald', 'route' => 'assets.index']" />
            <x-stat-card :metric="['label' => 'Maintenance', 'value' => number_format($stats['maintenance']), 'change' => null, 'period' => 'needs action', 'icon' => 'wrench', 'color' => 'orange', 'route' => 'assets.index']" />
            <x-stat-card :metric="['label' => 'Inventory Value', 'value' => '$'.number_format($stats['value'], 2), 'change' => null, 'period' => 'purchase value', 'icon' => 'badge-dollar-sign', 'color' => 'teal', 'route' => 'assets.index']" />
        </div>

        <div class="grid gap-3 md:grid-cols-4">
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Today</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($bookingStats['today']) }}</p><p class="mt-1 text-sm text-slate-500">reservations</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">This Week</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($bookingStats['week']) }}</p><p class="mt-1 text-sm text-slate-500">reserved/check-out</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Checked Out</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($bookingStats['checked_out']) }}</p><p class="mt-1 text-sm text-slate-500">active handovers</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Overdue</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($bookingStats['overdue']) }}</p><p class="mt-1 text-sm text-slate-500">needs follow-up</p></div>
        </div>

        <form method="GET" action="{{ route('assets.index') }}" class="dashboard-card grid gap-3 lg:grid-cols-[1fr_160px_160px_180px_160px_auto_auto]">
            <input name="q" value="{{ request('q') }}" class="h-10 rounded-lg border border-slate-200 px-3 text-sm" placeholder="Search name, serial, category...">
            <select name="status" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Status</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ Str::headline($status) }}</option>@endforeach</select>
            <select name="condition" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Condition</option>@foreach($conditions as $condition)<option value="{{ $condition }}" @selected(request('condition') === $condition)>{{ Str::headline($condition) }}</option>@endforeach</select>
            <select name="category_id" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select>
            <select name="campus_id" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Campus</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected((string) request('campus_id') === (string) $campus->id)>{{ $campus->name }}</option>@endforeach</select>
            <button class="h-10 rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white">Apply</button>
            <a href="{{ route('assets.index') }}" class="inline-flex h-10 items-center px-3 text-sm font-semibold text-slate-500">Clear</a>
        </form>

        <section class="dashboard-card p-0">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="text-base font-semibold text-slate-950">Asset Register</h2><p class="mt-1 text-sm text-slate-500">{{ number_format($assets->total()) }} inventory records</p></div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="categoryOpen = true" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i data-lucide="folder-plus" class="size-4"></i>Category</button>
                    <button type="button" @click="createOpen = true" class="inline-flex items-center gap-2 rounded-lg border border-violet-200 px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50"><i data-lucide="plus" class="size-4"></i>Add Asset</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="table-compact min-w-[1000px]">
                    <thead><tr><th>Asset</th><th>Category</th><th>Campus</th><th>Status</th><th>Condition</th><th>Value</th><th>Bookings</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse($assets as $asset)
                            <tr>
                                <td><div class="font-semibold text-slate-950">{{ $asset->name }}</div><div class="text-xs text-slate-500">{{ $asset->serial_number ?: 'No serial number' }} · {{ $asset->purchased_at?->format('M d, Y') ?? 'No purchase date' }}</div></td>
                                <td>{{ $asset->category?->name ?? 'Uncategorized' }}</td>
                                <td>{{ $asset->campus?->name ?? 'Unassigned' }}</td>
                                <td><x-status-badge :status="Str::headline($asset->status)" /></td>
                                <td><x-status-badge :status="Str::headline($asset->condition)" /></td>
                                <td class="font-semibold">${{ number_format((float) $asset->purchase_amount, 2) }}</td>
                                <td>{{ number_format($asset->bookings->whereIn('status', ['reserved', 'checked_out', 'overdue'])->count()) }} active</td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="editing = '{{ $asset->opaqueId() }}'" title="Edit asset" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50"><i data-lucide="pencil" class="size-4"></i></button>
                                        <button form="asset-delete-{{ $asset->id }}" title="Archive" class="grid size-8 place-items-center rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50"><i data-lucide="archive" class="size-4"></i></button>
                                    </div>
                                    <form id="asset-delete-{{ $asset->id }}" method="POST" action="{{ route('assets.destroy', $asset) }}" class="hidden">@csrf @method('DELETE')</form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-12"><x-empty-state icon="package-check" title="No assets found" message="Add the first asset for this church or adjust the filters." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-4">{{ $assets->links() }}</div>
        </section>

        <section class="dashboard-card p-0">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="text-base font-semibold text-slate-950">Reservation Schedule</h2><p class="mt-1 text-sm text-slate-500">Conflict-safe asset reservations, check-outs, returns, and overdue handovers.</p></div>
                <button type="button" @click="bookingOpen = true" class="inline-flex items-center gap-2 rounded-lg border border-violet-200 px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50"><i data-lucide="calendar-plus" class="size-4"></i>Reserve Asset</button>
            </div>
            <div class="overflow-x-auto">
                <table class="table-compact min-w-[1020px]">
                    <thead><tr><th>Asset</th><th>Window</th><th>Assigned</th><th>Location</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse($bookings as $booking)
                            <tr>
                                <td><div class="font-semibold text-slate-950">{{ $booking->asset?->name }}</div><div class="text-xs text-slate-500">{{ $booking->asset?->category?->name ?? 'Uncategorized' }}</div></td>
                                <td><div>{{ $booking->starts_at?->format('M d, Y') }}</div><div class="text-xs text-slate-500">{{ $booking->starts_at?->format('h:i A') }} - {{ $booking->ends_at?->format('h:i A') }}</div></td>
                                <td><div>{{ $booking->member ? $booking->member->first_name.' '.$booking->member->last_name : ($booking->assignedUser?->name ?? 'Unassigned') }}</div><div class="text-xs text-slate-500">{{ $booking->purpose ?: 'No purpose' }}</div></td>
                                <td>{{ $booking->location ?: ($booking->campus?->name ?? 'No location') }}</td>
                                <td><span class="rounded-full px-2 py-1 text-[11px] font-semibold ring-1 {{ $bookingStatusClasses[$booking->status] ?? $bookingStatusClasses['reserved'] }}">{{ Str::headline($booking->status) }}</span></td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="bookingEditing = '{{ $booking->opaqueId() }}'" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50" title="Edit reservation"><i data-lucide="pencil" class="size-4"></i></button>
                                        @if(! in_array($booking->status, ['cancelled', 'returned'], true))
                                            <button form="booking-cancel-{{ $booking->id }}" class="grid size-8 place-items-center rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50" title="Cancel reservation"><i data-lucide="calendar-x" class="size-4"></i></button>
                                        @endif
                                    </div>
                                    <form id="booking-cancel-{{ $booking->id }}" method="POST" action="{{ route('assets.bookings.destroy', $booking) }}" class="hidden">@csrf @method('DELETE')</form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-12"><x-empty-state icon="calendar-plus" title="No reservations scheduled" message="Reserve assets for services, ministries, events, or maintenance windows." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div x-cloak x-show="createOpen || editing || bookingOpen || bookingEditing || categoryOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/40" @click="createOpen = false; editing = null; bookingOpen = false; bookingEditing = null; categoryOpen = false"></div>

        <aside x-cloak x-show="createOpen" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-xl overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="createOpen = false">
            <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur"><div><h2 class="text-lg font-semibold text-slate-950">Add Asset</h2><p class="mt-1 text-sm text-slate-500">Create a tracked inventory record.</p></div><button type="button" @click="createOpen = false" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button></div>
            <form method="POST" action="{{ route('assets.store') }}" class="space-y-4 p-5">@csrf @include('assets.partials.asset-form', ['asset' => null])<div class="flex justify-end gap-3 border-t border-slate-100 pt-4"><button type="button" @click="createOpen = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button><button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="check" class="size-4"></i>Add Asset</button></div></form>
        </aside>

        @foreach($assets as $asset)
            <aside x-cloak x-show="editing === '{{ $asset->opaqueId() }}'" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-xl overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="editing = null">
                <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur"><div><h2 class="text-lg font-semibold text-slate-950">Edit Asset</h2><p class="mt-1 text-sm text-slate-500">{{ $asset->name }}</p></div><button type="button" @click="editing = null" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button></div>
                <form method="POST" action="{{ route('assets.update', $asset) }}" class="space-y-4 p-5">@csrf @method('PUT') @include('assets.partials.asset-form', ['asset' => $asset])<div class="flex justify-between gap-3 border-t border-slate-100 pt-4"><button form="drawer-delete-asset-{{ $asset->id }}" class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Archive</button><div class="flex gap-3"><button type="button" @click="editing = null" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button><button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="check" class="size-4"></i>Save Changes</button></div></div></form>
                <form id="drawer-delete-asset-{{ $asset->id }}" method="POST" action="{{ route('assets.destroy', $asset) }}" class="hidden">@csrf @method('DELETE')</form>
            </aside>
        @endforeach

        <aside x-cloak x-show="bookingOpen" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-xl overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="bookingOpen = false">
            <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur"><div><h2 class="text-lg font-semibold text-slate-950">Reserve Asset</h2><p class="mt-1 text-sm text-slate-500">Book an asset and prevent conflicting reservations.</p></div><button type="button" @click="bookingOpen = false" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button></div>
            <form method="POST" action="{{ route('assets.bookings.store') }}" class="space-y-4 p-5">@csrf @include('assets.partials.booking-form', ['booking' => null])<div class="flex justify-end gap-3 border-t border-slate-100 pt-4"><button type="button" @click="bookingOpen = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button><button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="calendar-plus" class="size-4"></i>Reserve Asset</button></div></form>
        </aside>

        @foreach($bookings as $booking)
            <aside x-cloak x-show="bookingEditing === '{{ $booking->opaqueId() }}'" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-xl overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="bookingEditing = null">
                <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur"><div><h2 class="text-lg font-semibold text-slate-950">Edit Reservation</h2><p class="mt-1 text-sm text-slate-500">{{ $booking->asset?->name }} · {{ $booking->starts_at?->format('M d, Y h:i A') }}</p></div><button type="button" @click="bookingEditing = null" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button></div>
                <form method="POST" action="{{ route('assets.bookings.update', $booking) }}" class="space-y-4 p-5">@csrf @method('PUT') @include('assets.partials.booking-form', ['booking' => $booking])<div class="flex justify-between gap-3 border-t border-slate-100 pt-4">@if(! in_array($booking->status, ['cancelled', 'returned'], true))<button form="drawer-cancel-booking-{{ $booking->id }}" class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Cancel Reservation</button>@else<span></span>@endif<div class="flex gap-3"><button type="button" @click="bookingEditing = null" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Close</button><button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="check" class="size-4"></i>Save Reservation</button></div></div></form>
                <form id="drawer-cancel-booking-{{ $booking->id }}" method="POST" action="{{ route('assets.bookings.destroy', $booking) }}" class="hidden">@csrf @method('DELETE')</form>
            </aside>
        @endforeach

        <aside x-cloak x-show="categoryOpen" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-md overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="categoryOpen = false">
            <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur"><div><h2 class="text-lg font-semibold text-slate-950">Add Category</h2><p class="mt-1 text-sm text-slate-500">Create an inventory category.</p></div><button type="button" @click="categoryOpen = false" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button></div>
            <form method="POST" action="{{ route('asset-categories.store') }}" class="space-y-4 p-5">@csrf<label class="space-y-1 text-sm font-medium text-slate-700">Name<input name="name" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"></label><label class="space-y-1 text-sm font-medium text-slate-700">Description<textarea name="description" rows="4" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"></textarea></label><div class="flex justify-end gap-3 border-t border-slate-100 pt-4"><button type="button" @click="categoryOpen = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button><button class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700">Save Category</button></div></form>
        </aside>
    </div>
</x-app-layout>
