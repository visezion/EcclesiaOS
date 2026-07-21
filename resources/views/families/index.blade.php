<x-app-layout title="Families & Households Management" :breadcrumbs="$breadcrumbs">
    @php
        $currentQuery = request()->query();
        $selectedMembers = $selectedFamily?->members ?? collect();
        $selectedHead = $selectedFamily?->primaryContact;
        $selectedCampusId = \App\Support\OpaqueId::decode(request('campus_id'), \App\Models\Campus::class);
    @endphp

    <div x-data="{ householdOpen: false, selected: [] }" class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-2xl bg-violet-100 text-violet-600"><i data-lucide="users-round" class="size-7"></i></div>
                <div><h1 class="text-2xl font-semibold text-slate-950">Families & Households Management</h1><p class="text-sm text-slate-500">Manage family units, dependents, guardians, and household engagement.</p></div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="householdOpen = true" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"><i data-lucide="upload" class="size-4"></i>Import Households</button>
                <a href="{{ route('families.export', $currentQuery) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"><i data-lucide="download" class="size-4"></i>Export</a>
                <button type="button" @click="householdOpen = true" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-violet-700"><i data-lucide="plus" class="size-4"></i>Add Household</button>
            </div>
        </div>

        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <x-stat-card :metric="['label' => 'Total Households', 'value' => number_format($stats['households']), 'change' => null, 'period' => 'View all households', 'icon' => 'users-round', 'color' => 'purple', 'route' => 'families.index']" />
            <x-stat-card :metric="['label' => 'Total Dependents', 'value' => number_format($stats['dependents']), 'change' => null, 'period' => 'linked members', 'icon' => 'user-check', 'color' => 'emerald', 'route' => 'families.index']" />
            <x-stat-card :metric="['label' => 'New Families This Month', 'value' => number_format($stats['new']), 'change' => $stats['new'] ? '+'.$stats['new'] : null, 'period' => 'created this month', 'icon' => 'sparkles', 'color' => 'indigo', 'route' => 'families.index']" />
            <x-stat-card :metric="['label' => 'Follow-up Needed', 'value' => number_format($stats['follow_up']), 'change' => null, 'period' => 'members in care status', 'icon' => 'clipboard-check', 'color' => 'orange', 'route' => 'members.follow-up']" />
            <x-stat-card :metric="['label' => 'Households by Campus', 'value' => $stats['top_campus'], 'change' => null, 'period' => 'highest member count', 'icon' => 'user-round', 'color' => 'teal', 'route' => 'campuses.index']" />
        </div>

        <form method="GET" action="{{ route('families.index') }}" class="dashboard-card grid gap-3 lg:grid-cols-[1fr_180px_auto_auto]">
            <input name="q" value="{{ request('q') }}" class="h-10 rounded-lg border border-slate-200 px-3 text-sm" placeholder="Search households by name, head, phone, email...">
            <select name="campus_id" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Campus: All</option>@foreach($campuses as $campus)<option value="{{ $campus->opaqueId() }}" @selected($selectedCampusId === $campus->id)>{{ $campus->name }}</option>@endforeach</select>
            <button class="h-10 rounded-lg bg-violet-600 px-4 text-sm font-medium text-white">Apply</button>
            <a href="{{ route('families.index') }}" class="inline-flex h-10 items-center px-3 text-sm font-medium text-slate-500">Clear</a>
        </form>

        <div class="grid gap-4 xl:grid-cols-[1fr_340px_360px]">
            <section class="dashboard-card p-0 xl:col-span-2">
                <div class="flex items-center justify-between border-b border-slate-100 p-4"><div class="flex items-center gap-2"><h2 class="text-base font-semibold text-slate-950">Households Directory</h2><span class="rounded-md bg-violet-50 px-2 py-1 text-xs font-medium text-violet-700">{{ number_format($families->total()) }} households</span></div></div>
                <div class="overflow-x-auto">
                    <table class="table-compact min-w-[980px]">
                        <thead><tr><th></th><th>Household ID</th><th>Family Name</th><th>Head of Household</th><th>Members</th><th>Primary Campus</th><th>Phone</th><th>Email</th><th>Last Attendance</th><th></th></tr></thead>
                        <tbody>
                            @forelse($families as $family)
                                @php $head = $family->primaryContact; $lastAttendance = \App\Models\AttendanceRecord::query()->whereIn('member_id', $family->members->pluck('id'))->latest('service_date')->value('service_date'); @endphp
                                <tr>
                                    <td><input type="checkbox" x-model="selected" value="{{ $family->opaqueId() }}"></td>
                                    <td><a href="{{ route('families.index', array_merge($currentQuery, ['selected' => $family->opaqueId()])) }}" class="font-medium text-violet-600">HH-{{ str_pad((string) $family->id, 5, '0', STR_PAD_LEFT) }}</a></td>
                                    <td class="font-semibold text-slate-950">{{ $family->name }}</td>
                                    <td>{{ $head ? $head->first_name.' '.$head->last_name : 'Not assigned' }}</td>
                                    <td>{{ $family->members_count }}</td>
                                    <td>{{ $family->campus?->name ?? 'Unassigned' }}</td>
                                    <td>{{ $head?->phone ?? 'No phone' }}</td>
                                    <td>{{ $head?->email ?? 'No email' }}</td>
                                    <td>{{ $lastAttendance ? \Illuminate\Support\Carbon::parse($lastAttendance)->format('M d, Y') : 'No attendance' }}</td>
                                    <td class="text-right"><a href="{{ route('families.index', ['selected' => $family->opaqueId()]) }}" class="text-violet-600"><i data-lucide="eye" class="size-4"></i></a></td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="py-10 text-center text-sm text-slate-500">No households found. Add a household to start linking members.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 p-4">{{ $families->links() }}</div>
            </section>

            <aside class="space-y-4">
                <section class="dashboard-card"><h2 class="mb-4 text-base font-semibold text-slate-950">Households by Campus</h2><div class="space-y-3">@foreach($campusDistribution as $item)<div class="grid grid-cols-[1fr_auto] gap-2 text-sm"><span>{{ $item['name'] }}</span><span class="font-semibold">{{ $item['count'] }} ({{ $item['percent'] }}%)</span><span class="col-span-2 h-1.5 rounded-full bg-slate-100"><span class="block h-full rounded-full bg-blue-500" style="width: {{ $item['percent'] }}%"></span></span></div>@endforeach</div></section>
                <section class="dashboard-card"><h2 class="mb-4 text-base font-semibold text-slate-950">Family Type Breakdown</h2><div class="space-y-3">@foreach($familyTypeDistribution as $item)<div class="flex justify-between text-sm"><span class="text-slate-600">{{ $item['label'] }}</span><span class="font-semibold">{{ $item['count'] }} ({{ $item['percent'] }}%)</span></div>@endforeach</div></section>
                <section class="dashboard-card"><h2 class="mb-4 text-base font-semibold text-slate-950">Recent Family Activity</h2><div class="space-y-4">@forelse($recentActivity as $log)<div class="flex gap-3 text-sm"><span class="grid size-8 place-items-center rounded-lg bg-emerald-50 text-emerald-600"><i data-lucide="badge-check" class="size-4"></i></span><div><div class="font-medium text-slate-900">{{ $log->description }}</div><div class="text-xs text-slate-500">{{ $log->created_at->format('M d, Y h:i A') }}</div></div></div>@empty<div class="text-sm text-slate-500">No household activity yet.</div>@endforelse</div></section>
            </aside>
        </div>

        @if($selectedFamily)
            <section class="dashboard-card">
                <div class="mb-4 flex items-start justify-between gap-4"><div><h2 class="text-base font-semibold text-slate-950">Selected Household</h2><p class="mt-1 text-sm text-slate-500">{{ $selectedFamily->name }} · HH-{{ str_pad((string) $selectedFamily->id, 5, '0', STR_PAD_LEFT) }}</p></div><form method="POST" action="{{ route('families.destroy', $selectedFamily) }}" onsubmit="return confirm('Remove this household?')">@csrf @method('DELETE')<button class="text-sm font-medium text-rose-600">Remove</button></form></div>
                <div class="grid gap-4 lg:grid-cols-3">
                    <div class="rounded-lg border border-slate-200 p-4"><h3 class="mb-3 text-sm font-semibold text-slate-950">Household Summary</h3><dl class="space-y-2 text-sm"><div class="flex justify-between"><dt class="text-slate-500">Head</dt><dd class="font-medium">{{ $selectedHead ? $selectedHead->first_name.' '.$selectedHead->last_name : 'Not assigned' }}</dd></div><div class="flex justify-between"><dt class="text-slate-500">Members</dt><dd class="font-medium">{{ $selectedMembers->count() }}</dd></div><div class="flex justify-between"><dt class="text-slate-500">Campus</dt><dd class="font-medium">{{ $selectedFamily->campus?->name ?? 'Unassigned' }}</dd></div><div class="flex justify-between"><dt class="text-slate-500">Status</dt><dd class="font-medium text-emerald-600">Good Standing</dd></div></dl></div>
                    <div class="rounded-lg border border-slate-200 p-4"><h3 class="mb-3 text-sm font-semibold text-slate-950">Household Members</h3><div class="space-y-3">@forelse($selectedMembers as $member)<a href="{{ route('members.show', $member) }}" class="flex items-center justify-between gap-3 text-sm"><span>{{ $member->first_name }} {{ $member->last_name }}</span><span class="text-slate-500">{{ $member->status }}</span></a>@empty<div class="text-sm text-slate-500">No linked members.</div>@endforelse</div></div>
                    <div class="rounded-lg border border-slate-200 p-4"><h3 class="mb-3 text-sm font-semibold text-slate-950">Contact & Address</h3><div class="space-y-2 text-sm text-slate-600"><div class="flex gap-2"><i data-lucide="phone" class="size-4 text-violet-600"></i>{{ $selectedHead?->phone ?? 'No phone' }}</div><div class="flex gap-2"><i data-lucide="mail" class="size-4 text-violet-600"></i>{{ $selectedHead?->email ?? 'No email' }}</div><div class="flex gap-2"><i data-lucide="map-pin" class="size-4 text-violet-600"></i>{{ $selectedFamily->address ?? 'No address' }}</div></div></div>
                </div>
            </section>
        @endif

        <div x-show="householdOpen" class="fixed inset-0 z-40 bg-slate-950/40" @click="householdOpen = false"></div>
        <aside x-show="householdOpen" class="fixed inset-y-0 right-0 z-50 w-full max-w-md overflow-y-auto bg-white p-6 shadow-2xl">
            <div class="mb-5 flex items-start justify-between"><div><h2 class="text-lg font-semibold text-slate-950">Add Household</h2><p class="mt-1 text-sm text-slate-500">Create a household and link real members.</p></div><button @click="householdOpen = false" class="grid size-9 place-items-center rounded-lg border border-slate-200"><i data-lucide="x" class="size-4"></i></button></div>
            <form method="POST" action="{{ route('families.store') }}" class="space-y-4">
                @csrf
                <input name="name" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Household name">
                <select name="campus_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">Select campus</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}">{{ $campus->name }}</option>@endforeach</select>
                <select name="primary_contact_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">Head of household</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->first_name }} {{ $member->last_name }}</option>@endforeach</select>
                <select name="member_ids[]" multiple class="h-36 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">@foreach($members as $member)<option value="{{ $member->opaqueId() }}">{{ $member->first_name }} {{ $member->last_name }}</option>@endforeach</select>
                <textarea name="address" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Household address"></textarea>
                <button class="w-full rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white">Create Household</button>
            </form>
        </aside>
    </div>
</x-app-layout>
