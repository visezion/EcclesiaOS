<x-app-layout title="Child & Youth" :breadcrumbs="$breadcrumbs">
    <div class="space-y-5">
        <div class="responsive-page-header">
            <div class="responsive-page-title">
                <div class="grid size-12 shrink-0 place-items-center rounded-xl bg-sky-100 text-sky-600 sm:size-14"><i data-lucide="baby" class="size-7"></i></div>
                <div><h1 class="text-2xl font-semibold text-slate-950">Child & Youth</h1><p class="text-sm text-slate-500">Manage child and youth records, guardian contacts, consent, medical notes, and check-in state.</p></div>
            </div>
            <div class="responsive-page-actions">
                <a href="{{ route('children-youth.overview') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i data-lucide="layout-dashboard" class="size-4"></i>Overview</a>
                <a href="{{ route('children-youth.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white"><i data-lucide="plus" class="size-4"></i>Add Record</a>
                <a href="{{ route('children-youth.export', request()->query()) }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i data-lucide="download" class="size-4"></i>Export</a>
            </div>
        </div>

        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif

        <div class="responsive-stat-grid">
            <x-stat-card :metric="['label' => 'Active Records', 'value' => number_format($stats['total']), 'change' => null, 'period' => 'children and youth', 'icon' => 'users-round', 'color' => 'purple', 'route' => 'children-youth.index']" />
            <x-stat-card :metric="['label' => 'Checked In', 'value' => number_format($stats['checked_in']), 'change' => null, 'period' => 'currently present', 'icon' => 'clipboard-check', 'color' => 'emerald', 'route' => 'children-youth.index']" />
            <x-stat-card :metric="['label' => 'Consent Pending', 'value' => number_format($stats['consent_pending']), 'change' => null, 'period' => 'needs guardian action', 'icon' => 'file-warning', 'color' => 'orange', 'route' => 'children-youth.index']" />
            <x-stat-card :metric="['label' => 'Medical Notes', 'value' => number_format($stats['medical_notes']), 'change' => null, 'period' => 'safety records', 'icon' => 'heart-pulse', 'color' => 'rose', 'route' => 'children-youth.index']" />
        </div>

        <div class="responsive-content-sidebar" style="--responsive-sidebar-width: 390px;">
            <main class="min-w-0 space-y-4">
                <form method="GET" action="{{ route('children-youth.index') }}" class="dashboard-card responsive-filter-grid">
                    <input name="q" value="{{ request('q') }}" class="h-10 rounded-lg border border-slate-200 px-3 text-sm" placeholder="Search name, guardian, phone...">
                    <select name="age_group" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Age Group</option>@foreach($ageGroups as $group)<option value="{{ $group }}" @selected(request('age_group') === $group)>{{ Str::headline($group) }}</option>@endforeach</select>
                    <select name="consent_status" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Consent</option>@foreach($consentStatuses as $status)<option value="{{ $status }}" @selected(request('consent_status') === $status)>{{ Str::headline($status) }}</option>@endforeach</select>
                    <select name="check_in_status" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Check-in</option>@foreach($checkInStatuses as $status)<option value="{{ $status }}" @selected(request('check_in_status') === $status)>{{ Str::headline($status) }}</option>@endforeach</select>
                    <select name="campus_id" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">{{ $terminology['campus_singular'] }}</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected((string) request('campus_id') === (string) $campus->id)>{{ $campus->name }}</option>@endforeach</select>
                    <button class="h-10 rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white">Apply</button>
                    <a href="{{ route('children-youth.index') }}" class="inline-flex h-10 items-center px-3 text-sm font-semibold text-slate-500">Clear</a>
                </form>

                <section class="dashboard-card p-0">
                    <div class="flex items-center justify-between border-b border-slate-100 p-4"><h2 class="text-base font-semibold text-slate-950">Children & Youth Register</h2><span class="text-sm font-semibold text-violet-600">{{ number_format($records->total()) }} records</span></div>
                    <div class="responsive-table-scroll">
                        <table class="table-compact min-w-[1100px]">
                            <thead><tr><th>Name</th><th>Age Group</th><th>{{ $terminology['campus_singular'] }}</th><th>Guardian</th><th>Consent</th><th>Check-in</th><th>Pickup</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                            <tbody>
                                @forelse($records as $record)
                                    <tr>
                                        <td><div class="font-semibold text-slate-950">{{ $record->first_name }} {{ $record->last_name }}</div><div class="text-xs text-slate-500">{{ $record->date_of_birth?->format('M d, Y') ?? 'No birthdate' }}</div></td>
                                        <td>{{ Str::headline($record->age_group) }}</td>
                                        <td>{{ $record->campus?->name ?? 'Unassigned' }}</td>
                                        <td><div>{{ $record->guardian ? $record->guardian->first_name.' '.$record->guardian->last_name : ($record->guardian_name ?: 'Not recorded') }}</div><div class="text-xs text-slate-500">{{ $record->guardian_phone }}</div></td>
                                        <td><x-status-badge :status="Str::headline($record->consent_status)" /></td>
                                        <td><x-status-badge :status="Str::headline($record->check_in_status)" /></td>
                                        <td>{{ $record->pickup_code ?: 'None' }}</td>
                                        <td><x-status-badge :status="Str::headline($record->status)" /></td>
                                        <td class="text-right"><div class="flex justify-end gap-2"><a href="{{ route('children-youth.edit', $record) }}" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50" title="Edit page"><i data-lucide="pencil" class="size-4"></i></a><button form="youth-update-{{ $record->id }}" class="grid size-8 place-items-center rounded-lg border border-violet-200 text-violet-700 hover:bg-violet-50" title="Save"><i data-lucide="save" class="size-4"></i></button><button form="youth-delete-{{ $record->id }}" class="grid size-8 place-items-center rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50" title="Archive"><i data-lucide="archive" class="size-4"></i></button></div></td>
                                    </tr>
                                    <tr>
                                        <td colspan="9" class="bg-slate-50/70">
                                            <form id="youth-update-{{ $record->id }}" method="POST" action="{{ route('children-youth.update', $record) }}" class="grid gap-2 py-3 lg:grid-cols-[1fr_1fr_140px_150px_150px_150px_140px]">
                                                @csrf @method('PUT')
                                                <input name="first_name" value="{{ $record->first_name }}" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                                <input name="last_name" value="{{ $record->last_name }}" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                                <input name="date_of_birth" type="date" value="{{ $record->date_of_birth?->format('Y-m-d') }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                                <select name="age_group" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">@foreach($ageGroups as $group)<option value="{{ $group }}" @selected($record->age_group === $group)>{{ Str::headline($group) }}</option>@endforeach</select>
                                                <select name="campus_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">{{ $terminology['campus_singular'] }}</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected($record->campus_id === $campus->id)>{{ $campus->name }}</option>@endforeach</select>
                                                <select name="consent_status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">@foreach($consentStatuses as $status)<option value="{{ $status }}" @selected($record->consent_status === $status)>{{ Str::headline($status) }}</option>@endforeach</select>
                                                <select name="check_in_status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">@foreach($checkInStatuses as $status)<option value="{{ $status }}" @selected($record->check_in_status === $status)>{{ Str::headline($status) }}</option>@endforeach</select>
                                                <select name="member_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">Linked member</option>@foreach($members as $member)<option value="{{ $member->id }}" @selected($record->member_id === $member->id)>{{ $member->first_name }} {{ $member->last_name }}</option>@endforeach</select>
                                                <select name="guardian_member_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">Guardian member</option>@foreach($members as $member)<option value="{{ $member->id }}" @selected($record->guardian_member_id === $member->id)>{{ $member->first_name }} {{ $member->last_name }}</option>@endforeach</select>
                                                <input name="guardian_name" value="{{ $record->guardian_name }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Guardian name">
                                                <input name="guardian_phone" value="{{ $record->guardian_phone }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Guardian phone">
                                                <input name="pickup_code" value="{{ $record->pickup_code }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Pickup code">
                                                <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">@foreach($statuses as $status)<option value="{{ $status }}" @selected($record->status === $status)>{{ Str::headline($status) }}</option>@endforeach</select>
                                                <textarea name="medical_notes" rows="2" class="rounded-lg border border-slate-200 px-3 py-2 text-sm lg:col-span-3" placeholder="Medical or safety notes">{{ $record->medical_notes }}</textarea>
                                            </form>
                                            <form id="youth-delete-{{ $record->id }}" method="POST" action="{{ route('children-youth.destroy', $record) }}" class="hidden">@csrf @method('DELETE')</form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" class="px-4 py-12"><x-empty-state icon="baby" title="No children or youth records" message="Add the first record to manage check-in, consent, and guardian details." /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-100 p-4">{{ $records->links() }}</div>
                </section>
            </main>

            <aside class="space-y-4">
                <section class="dashboard-card">
                    <h2 class="mb-4 text-base font-semibold text-slate-950">Add Record</h2>
                    <form method="POST" action="{{ route('children-youth.store') }}" class="space-y-3">@csrf<div class="grid gap-3 sm:grid-cols-2"><input name="first_name" required class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="First name"><input name="last_name" required class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Last name"></div><div class="grid gap-3 sm:grid-cols-2"><input name="date_of_birth" type="date" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm"><select name="age_group" required class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm">@foreach($ageGroups as $group)<option value="{{ $group }}">{{ Str::headline($group) }}</option>@endforeach</select></div><select name="campus_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"><option value="">{{ $terminology['campus_singular'] }}</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}">{{ $campus->name }}</option>@endforeach</select><div class="grid gap-3 sm:grid-cols-2"><select name="member_id" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm"><option value="">Linked member</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->first_name }} {{ $member->last_name }}</option>@endforeach</select><select name="guardian_member_id" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm"><option value="">Guardian member</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->first_name }} {{ $member->last_name }}</option>@endforeach</select></div><div class="grid gap-3 sm:grid-cols-2"><input name="guardian_name" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Guardian name"><input name="guardian_phone" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Guardian phone"></div><div class="grid gap-3 sm:grid-cols-2"><select name="consent_status" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm">@foreach($consentStatuses as $status)<option value="{{ $status }}">{{ Str::headline($status) }}</option>@endforeach</select><select name="check_in_status" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm">@foreach($checkInStatuses as $status)<option value="{{ $status }}">{{ Str::headline($status) }}</option>@endforeach</select></div><div class="grid gap-3 sm:grid-cols-2"><input name="pickup_code" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Pickup code"><select name="status" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm">@foreach($statuses as $status)<option value="{{ $status }}">{{ Str::headline($status) }}</option>@endforeach</select></div><textarea name="medical_notes" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Medical or safety notes"></textarea><button class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white"><i data-lucide="plus" class="size-4"></i>Add Record</button></form>
                </section>
                <section class="dashboard-card"><h2 class="mb-4 text-base font-semibold text-slate-950">Age Group Mix</h2><div class="space-y-3">@forelse($ageRows as $row)<div class="grid grid-cols-[1fr_auto] gap-2 text-sm"><span class="text-slate-600">{{ $row['label'] }}</span><span class="font-semibold">{{ $row['value'] }}</span><span class="col-span-2 h-1.5 rounded-full bg-slate-100"><span class="block h-full rounded-full bg-sky-500" style="width: {{ $row['percent'] }}%"></span></span></div>@empty<div class="text-sm text-slate-500">No age group data yet.</div>@endforelse</div></section>
            </aside>
        </div>
    </div>
</x-app-layout>
