<x-searchable-select
    name="member_id"
    label="Member"
    :required="true"
    placeholder="Search members by name, email, or campus"
    :selected="$case?->member?->opaqueId()"
    :options="$members->map(fn ($member) => [
        'value' => $member->opaqueId(),
        'label' => trim($member->first_name.' '.$member->last_name),
        'meta' => trim(($member->email ?: 'No email').' - '.($member->campus?->name ?? 'No campus')),
        'initials' => Str::substr($member->first_name, 0, 1).Str::substr($member->last_name, 0, 1),
    ])->values()"
/>

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
    <x-searchable-select
        name="campus_id"
        label="Campus"
        empty-label="Use member campus"
        placeholder="Search campus"
        :selected="$case?->campus?->opaqueId()"
        :options="$campuses->map(fn ($campus) => [
            'value' => $campus->opaqueId(),
            'label' => $campus->name,
            'meta' => trim(($campus->type ?? 'Campus').' - '.($campus->city ?? '')),
            'initials' => Str::substr($campus->name, 0, 2),
        ])->values()"
    />
</div>

<x-searchable-select
    name="assigned_user_id"
    label="Assigned To"
    empty-label="Unassigned"
    placeholder="Search users by name, title, or email"
    :selected="$case?->assignedUser?->opaqueId()"
    :options="$users->map(fn ($user) => [
        'value' => $user->opaqueId(),
        'label' => $user->name,
        'meta' => trim(($user->title ?: 'Team Member').' - '.($user->email ?: 'No email')),
        'avatar' => $user->avatar_src,
        'initials' => Str::of($user->name)->explode(' ')->filter()->map(fn ($part) => Str::substr($part, 0, 1))->take(2)->join(''),
    ])->values()"
/>

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
