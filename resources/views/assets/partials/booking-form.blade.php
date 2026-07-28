<x-searchable-select
    name="asset_id"
    label="Asset"
    :required="true"
    placeholder="Search assets by name, serial, or campus"
    :selected="$booking?->asset?->opaqueId()"
    :options="$assetOptions->map(fn ($assetOption) => [
        'value' => $assetOption->opaqueId(),
        'label' => $assetOption->name,
        'meta' => trim(($assetOption->serial_number ?: 'No serial').' - '.($assetOption->campus?->name ?? 'Unassigned')),
        'initials' => Str::substr($assetOption->name, 0, 2),
    ])->values()"
/>

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Starts At
        <input name="starts_at" type="datetime-local" required value="{{ old('starts_at', $booking?->starts_at?->format('Y-m-d\\TH:i')) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Ends At
        <input name="ends_at" type="datetime-local" required value="{{ old('ends_at', $booking?->ends_at?->format('Y-m-d\\TH:i')) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
    </label>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Status
        <select name="status" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            @foreach($bookingStatuses as $status)
                <option value="{{ $status }}" @selected(old('status', $booking?->status ?? 'reserved') === $status)>{{ Str::headline($status) }}</option>
            @endforeach
        </select>
    </label>
    <x-searchable-select
        name="campus_id"
        label="Campus"
        empty-label="Use asset campus"
        placeholder="Search campus"
        :selected="$booking?->campus?->opaqueId()"
        :options="$campuses->map(fn ($campus) => [
            'value' => $campus->opaqueId(),
            'label' => $campus->name,
            'meta' => trim(($campus->type ?? 'Campus').' - '.($campus->city ?? '')),
            'initials' => Str::substr($campus->name, 0, 2),
        ])->values()"
    />
</div>

<x-searchable-select
    name="member_id"
    label="Member"
    empty-label="No member assigned"
    placeholder="Search members by name, email, or campus"
    :selected="$booking?->member?->opaqueId()"
    :options="$members->map(fn ($member) => [
        'value' => $member->opaqueId(),
        'label' => trim($member->first_name.' '.$member->last_name),
        'meta' => trim(($member->email ?: 'No email').' - '.($member->campus?->name ?? 'No campus')),
        'initials' => Str::substr($member->first_name, 0, 1).Str::substr($member->last_name, 0, 1),
    ])->values()"
/>

<x-searchable-select
    name="assigned_user_id"
    label="Responsible User"
    empty-label="Unassigned"
    placeholder="Search users by name, title, or email"
    :selected="$booking?->assignedUser?->opaqueId()"
    :options="$users->map(fn ($user) => [
        'value' => $user->opaqueId(),
        'label' => $user->name,
        'meta' => trim(($user->title ?: 'Team Member').' - '.($user->email ?: 'No email')),
        'avatar' => $user->avatar_src,
        'initials' => Str::of($user->name)->explode(' ')->filter()->map(fn ($part) => Str::substr($part, 0, 1))->take(2)->join(''),
    ])->values()"
/>

<label class="space-y-1 text-sm font-medium text-slate-700">
    Purpose
    <input name="purpose" value="{{ old('purpose', $booking?->purpose) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Service, outreach, rehearsal, maintenance...">
</label>

<label class="space-y-1 text-sm font-medium text-slate-700">
    Location
    <input name="location" value="{{ old('location', $booking?->location) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Room, venue, vehicle destination...">
</label>

<label class="space-y-1 text-sm font-medium text-slate-700">
    Notes
    <textarea name="notes" rows="4" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Handover, condition, accessories, return instructions">{{ old('notes', $booking?->notes) }}</textarea>
</label>
