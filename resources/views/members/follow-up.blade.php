<x-app-layout title="Member Follow-up & Pastoral Care" :breadcrumbs="$breadcrumbs">
    @php
        $priorityClasses = ['urgent' => 'bg-rose-100 text-rose-700 ring-rose-200', 'high' => 'bg-rose-50 text-rose-700 ring-rose-200', 'medium' => 'bg-orange-50 text-orange-700 ring-orange-200', 'low' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'];
        $statusClasses = ['pending' => 'bg-orange-50 text-orange-700 ring-orange-200', 'assigned' => 'bg-violet-50 text-violet-700 ring-violet-200', 'in-progress' => 'bg-blue-50 text-blue-700 ring-blue-200', 'on-hold' => 'bg-slate-100 text-slate-600 ring-slate-200', 'resolved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'];
        $statusColors = ['pending' => '#f97316', 'assigned' => '#6d4aff', 'in-progress' => '#2477f2', 'on-hold' => '#94a3b8', 'resolved' => '#10b981'];
        $selectedCampusId = \App\Support\OpaqueId::decode(request('campus_id'), \App\Models\Campus::class);
        $selectedAssignedUserId = \App\Support\OpaqueId::decode(request('assigned_user_id'), \App\Models\User::class);
    @endphp

    <div x-data="{ selected: [], taskOpen: false, toggleAll(event) { const ids = Array.from(document.querySelectorAll('[data-care-checkbox]')).map(input => input.value); this.selected = event.target.checked ? ids : []; } }" class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-2xl bg-violet-100 text-violet-600"><i data-lucide="heart-handshake" class="size-7"></i></div>
                <div>
                    <h1 class="text-2xl font-semibold text-slate-950">Member Follow-up & Pastoral Care</h1>
                    <p class="text-sm text-slate-500">Track care requests, inactive members, visitation, counseling, and next steps.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('care-tasks.export', request()->query()) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"><i data-lucide="download" class="size-4"></i>Export Report</a>
                <button type="button" @click="taskOpen = true" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-violet-700"><i data-lucide="plus" class="size-4"></i>New Care Task</button>
            </div>
        </div>

        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
            <x-stat-card :metric="['label' => 'Open Follow-up Tasks', 'value' => number_format($stats['open']), 'change' => null, 'period' => 'needs action', 'icon' => 'users-round', 'color' => 'purple', 'route' => 'members.follow-up']" />
            <x-stat-card :metric="['label' => 'Inactive Members', 'value' => number_format($stats['inactive']), 'change' => null, 'period' => 'from member status', 'icon' => 'user-check', 'color' => 'emerald', 'route' => 'members.index']" />
            <x-stat-card :metric="['label' => 'Prayer Requests', 'value' => number_format($stats['prayer']), 'change' => null, 'period' => 'open requests', 'icon' => 'hand-heart', 'color' => 'rose', 'route' => 'prayer-requests.index']" />
            <x-stat-card :metric="['label' => 'Counseling Cases', 'value' => number_format($stats['counseling']), 'change' => null, 'period' => 'active cases', 'icon' => 'circle-help', 'color' => 'rose', 'route' => 'members.follow-up']" />
            <x-stat-card :metric="['label' => 'Hospital Visits', 'value' => number_format($stats['visits']), 'change' => null, 'period' => 'scheduled', 'icon' => 'calendar-days', 'color' => 'orange', 'route' => 'members.follow-up']" />
            <x-stat-card :metric="['label' => 'Resolved This Month', 'value' => number_format($stats['resolved']), 'change' => null, 'period' => 'completed', 'icon' => 'check-circle-2', 'color' => 'emerald', 'route' => 'members.follow-up']" />
        </div>

        <form method="GET" action="{{ route('members.follow-up') }}" class="dashboard-card grid gap-3 xl:grid-cols-[1fr_repeat(5,150px)_auto_auto]">
            <input name="q" value="{{ request('q') }}" class="h-10 rounded-lg border border-slate-200 px-3 text-sm" placeholder="Search members by name, email, phone...">
            <select name="campus_id" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Campus: All</option>@foreach ($campuses as $campus)<option value="{{ $campus->opaqueId() }}" @selected($selectedCampusId === $campus->id)>{{ $campus->name }}</option>@endforeach</select>
            <select name="type" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Care Type: All</option>@foreach ($types as $type)<option value="{{ $type }}" @selected(request('type') === $type)>{{ $type }}</option>@endforeach</select>
            <select name="status" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Status: All</option>@foreach ($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ Str::headline($status) }}</option>@endforeach</select>
            <select name="assigned_user_id" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Assigned To: All</option>@foreach ($users as $user)<option value="{{ $user->opaqueId() }}" @selected($selectedAssignedUserId === $user->id)>{{ $user->name }}</option>@endforeach</select>
            <select name="priority" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Urgency: All</option>@foreach ($priorities as $priority)<option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ Str::headline($priority) }}</option>@endforeach</select>
            <button class="h-10 rounded-lg bg-violet-600 px-4 text-sm font-medium text-white">Apply</button>
            <a href="{{ route('members.follow-up') }}" class="inline-flex h-10 items-center px-3 text-sm font-medium text-slate-500">Clear</a>
        </form>

        <div class="grid gap-4 xl:grid-cols-[1fr_390px]">
            <section class="dashboard-card p-0">
                <form method="POST" action="{{ route('care-tasks.bulk') }}">
                    @csrf
                    <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-2"><h2 class="text-base font-semibold text-slate-950">Members Needing Attention</h2><span class="rounded-md bg-violet-50 px-2 py-1 text-xs font-medium text-violet-700">{{ number_format($tasks->total()) }} tasks</span></div>
                        <div class="flex gap-2"><select name="action" required class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Bulk Actions</option>@foreach ($statuses as $status)<option value="{{ $status }}">{{ Str::headline($status) }}</option>@endforeach</select><button :disabled="selected.length === 0" class="h-10 rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-700 disabled:opacity-50">Apply</button></div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table-compact min-w-[980px]">
                            <thead><tr><th><input type="checkbox" @change="toggleAll($event)"></th><th>Member Name</th><th>Care Type</th><th>Priority</th><th>Assigned Pastor / Leader</th><th>Campus</th><th>Next Action</th><th>Status</th><th>Due Date</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($tasks as $task)
                                    <tr>
                                        <td><input data-care-checkbox type="checkbox" name="tasks[]" value="{{ $task->opaqueId() }}" x-model="selected"></td>
                                        <td><a class="font-semibold text-slate-950 hover:text-violet-600" href="{{ route('members.show', $task->member) }}">{{ $task->member?->first_name }} {{ $task->member?->last_name }}</a></td>
                                        <td>{{ $task->type }}</td>
                                        <td><span class="rounded-full px-2 py-1 text-[11px] font-medium ring-1 {{ $priorityClasses[$task->priority] ?? $priorityClasses['medium'] }}">{{ Str::headline($task->priority) }}</span></td>
                                        <td>{{ $task->assignedUser?->name ?? 'Unassigned' }}</td>
                                        <td>{{ $task->campus?->name ?? $task->member?->campus?->name ?? 'Unassigned' }}</td>
                                        <td>{{ $task->next_action ?? 'Not set' }}</td>
                                        <td><span class="rounded-full px-2 py-1 text-[11px] font-medium ring-1 {{ $statusClasses[$task->status] ?? $statusClasses['pending'] }}">{{ Str::headline($task->status) }}</span></td>
                                        <td>{{ $task->due_at?->format('M d, Y') ?? 'No due date' }}</td>
                                        <td><a href="{{ route('members.follow-up', ['task' => $task->opaqueId()]) }}" class="text-violet-600"><i data-lucide="eye" class="size-4"></i></a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="10" class="py-10 text-center text-sm text-slate-500">No care tasks match the current filters.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>
                <div class="border-t border-slate-100 p-4">{{ $tasks->links() }}</div>
            </section>

            <aside class="space-y-4">
                <section class="dashboard-card">
                    <h2 class="mb-4 text-base font-semibold text-slate-950">Care Status Breakdown</h2>
                    <div class="flex items-center gap-5">
                        <div class="grid size-28 place-items-center rounded-full" style="background: conic-gradient(@foreach($statusDistribution as $item) {{ $statusColors[$item['status']] ?? '#6d4aff' }} 0 {{ $item['percent'] }}%, @endforeach #eef2f7 0);"><div class="grid size-16 place-items-center rounded-full bg-white text-center text-xs font-medium text-slate-500"><span class="block text-lg font-semibold text-slate-950">{{ $tasks->total() }}</span>Total</div></div>
                        <div class="flex-1 space-y-2">@foreach($statusDistribution as $item)<div class="flex justify-between text-sm"><span class="flex items-center gap-2 text-slate-600"><span class="size-2 rounded-full" style="background: {{ $statusColors[$item['status']] ?? '#6d4aff' }}"></span>{{ $item['label'] }}</span><span class="font-semibold">{{ $item['count'] }}</span></div>@endforeach</div>
                    </div>
                </section>
                <section class="dashboard-card"><h2 class="mb-4 text-base font-semibold text-slate-950">Follow-up by Campus</h2><div class="space-y-3">@foreach($campusDistribution as $item)<div class="grid grid-cols-[1fr_auto] gap-2 text-sm"><span>{{ $item['name'] }}</span><span class="font-semibold">{{ $item['count'] }}</span><span class="col-span-2 h-1.5 rounded-full bg-slate-100"><span class="block h-full rounded-full bg-blue-500" style="width: {{ $item['percent'] }}%"></span></span></div>@endforeach</div></section>
                <section class="dashboard-card"><h2 class="mb-4 text-base font-semibold text-slate-950">Recent Pastoral Activity</h2><div class="space-y-4">@forelse($recentActivity as $log)<div class="flex gap-3 text-sm"><span class="grid size-8 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="badge-check" class="size-4"></i></span><div><div class="font-medium text-slate-900">{{ $log->description }}</div><div class="text-xs text-slate-500">{{ $log->created_at->format('M d, Y h:i A') }}</div></div></div>@empty<div class="text-sm text-slate-500">No pastoral activity yet.</div>@endforelse</div></section>
                @if($selectedTask)<section class="dashboard-card"><div class="mb-4 flex justify-between"><h2 class="text-base font-semibold text-slate-950">{{ $selectedTask->member?->first_name }} {{ $selectedTask->member?->last_name }}</h2><span class="rounded-full px-2 py-1 text-[11px] font-medium ring-1 {{ $statusClasses[$selectedTask->status] ?? $statusClasses['pending'] }}">{{ Str::headline($selectedTask->status) }}</span></div><p class="text-sm text-slate-500">{{ $selectedTask->notes ?: 'No notes recorded.' }}</p><div class="mt-4 text-sm"><div class="font-medium text-slate-950">Next Follow-up Appointment</div><div class="mt-1 text-slate-600">{{ $selectedTask->due_at?->format('M d, Y h:i A') ?? 'No due date set' }}</div></div></section>@endif
            </aside>
        </div>

        <div x-show="taskOpen" class="fixed inset-0 z-40 bg-slate-950/40" @click="taskOpen = false"></div>
        <aside x-show="taskOpen" class="fixed inset-y-0 right-0 z-50 w-full max-w-md overflow-y-auto bg-white p-6 shadow-2xl">
            <div class="mb-5 flex items-start justify-between"><div><h2 class="text-lg font-semibold text-slate-950">New Care Task</h2><p class="mt-1 text-sm text-slate-500">Create a real follow-up task.</p></div><button @click="taskOpen = false" class="grid size-9 place-items-center rounded-lg border border-slate-200"><i data-lucide="x" class="size-4"></i></button></div>
            <form method="POST" action="{{ route('care-tasks.store') }}" class="space-y-4">
                @csrf
                <select name="member_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">Select member</option>@foreach($members as $member)<option value="{{ $member->opaqueId() }}">{{ $member->first_name }} {{ $member->last_name }} · {{ $member->campus?->name }}</option>@endforeach</select>
                <select name="type" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">@foreach($types as $type)<option>{{ $type }}</option>@endforeach</select>
                <div class="grid gap-3 sm:grid-cols-2"><select name="priority" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm">@foreach($priorities as $priority)<option value="{{ $priority }}">{{ Str::headline($priority) }}</option>@endforeach</select><select name="status" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm">@foreach($statuses as $status)<option value="{{ $status }}">{{ Str::headline($status) }}</option>@endforeach</select></div>
                <select name="assigned_user_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">Assign pastor / leader</option>@foreach($users as $user)<option value="{{ $user->opaqueId() }}">{{ $user->name }}</option>@endforeach</select>
                <input name="next_action" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Next action">
                <input name="due_at" type="datetime-local" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <textarea name="notes" rows="5" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Care notes"></textarea>
                <button class="w-full rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white">Create Care Task</button>
            </form>
        </aside>
    </div>
</x-app-layout>
