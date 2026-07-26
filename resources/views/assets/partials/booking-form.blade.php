<label class="space-y-1 text-sm font-medium text-slate-700">
    Asset
    <select name="asset_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
        <option value="">Select asset</option>
        @foreach($assetOptions as $assetOption)
            <option value="{{ $assetOption->opaqueId() }}" @selected(old('asset_id', $booking?->asset?->opaqueId()) === $assetOption->opaqueId())>
                {{ $assetOption->name }}{{ $assetOption->serial_number ? ' · '.$assetOption->serial_number : '' }} · {{ $assetOption->campus?->name ?? 'Unassigned' }}
            </option>
        @endforeach
    </select>
</label>

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
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Campus
        <select name="campus_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            <option value="">Use asset campus</option>
            @foreach($campuses as $campus)
                <option value="{{ $campus->opaqueId() }}" @selected(old('campus_id', $booking?->campus?->opaqueId()) === $campus->opaqueId())>{{ $campus->name }}</option>
            @endforeach
        </select>
    </label>
</div>

<label class="space-y-1 text-sm font-medium text-slate-700">
    Member
    <select name="member_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
        <option value="">No member assigned</option>
        @foreach($members as $member)
            <option value="{{ $member->opaqueId() }}" @selected(old('member_id', $booking?->member?->opaqueId()) === $member->opaqueId())>{{ $member->first_name }} {{ $member->last_name }}{{ $member->campus ? ' · '.$member->campus->name : '' }}</option>
        @endforeach
    </select>
</label>

<label class="space-y-1 text-sm font-medium text-slate-700">
    Responsible User
    <select name="assigned_user_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
        <option value="">Unassigned</option>
        @foreach($users as $user)
            <option value="{{ $user->opaqueId() }}" @selected(old('assigned_user_id', $booking?->assignedUser?->opaqueId()) === $user->opaqueId())>{{ $user->name }}</option>
        @endforeach
    </select>
</label>

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
