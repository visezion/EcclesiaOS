<label class="space-y-1 text-sm font-medium text-slate-700">
    Member
    <select name="member_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
        <option value="">Select member</option>
        @foreach($members as $member)
            <option value="{{ $member->opaqueId() }}" @selected(old('member_id', $case?->member?->opaqueId()) === $member->opaqueId())>
                {{ $member->first_name }} {{ $member->last_name }}{{ $member->campus ? ' · '.$member->campus->name : '' }}
            </option>
        @endforeach
    </select>
</label>

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Type
        <select name="type" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            @foreach($types as $type)
                <option value="{{ $type }}" @selected(old('type', $case?->type ?? 'Counseling') === $type)>{{ $type }}</option>
            @endforeach
        </select>
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Priority
        <select name="priority" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            @foreach($priorities as $priority)
                <option value="{{ $priority }}" @selected(old('priority', $case?->priority ?? 'medium') === $priority)>{{ Str::headline($priority) }}</option>
            @endforeach
        </select>
    </label>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Status
        <select name="status" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            @foreach($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $case?->status ?? 'pending') === $status)>{{ Str::headline($status) }}</option>
            @endforeach
        </select>
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Campus
        <select name="campus_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            <option value="">Use member campus</option>
            @foreach($campuses as $campus)
                <option value="{{ $campus->opaqueId() }}" @selected(old('campus_id', $case?->campus?->opaqueId()) === $campus->opaqueId())>{{ $campus->name }}</option>
            @endforeach
        </select>
    </label>
</div>

<label class="space-y-1 text-sm font-medium text-slate-700">
    Assigned To
    <select name="assigned_user_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
        <option value="">Unassigned</option>
        @foreach($users as $user)
            <option value="{{ $user->opaqueId() }}" @selected(old('assigned_user_id', $case?->assignedUser?->opaqueId()) === $user->opaqueId())>{{ $user->name }}</option>
        @endforeach
    </select>
</label>

<label class="space-y-1 text-sm font-medium text-slate-700">
    Next Action
    <input name="next_action" value="{{ old('next_action', $case?->next_action) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Schedule appointment, call guardian, send resource...">
</label>

<label class="space-y-1 text-sm font-medium text-slate-700">
    Due At
    <input name="due_at" type="datetime-local" value="{{ old('due_at', $case?->due_at?->format('Y-m-d\\TH:i')) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
</label>

<label class="space-y-1 text-sm font-medium text-slate-700">
    Notes
    <textarea name="notes" rows="5" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Private care notes">{{ old('notes', $case?->notes) }}</textarea>
</label>
