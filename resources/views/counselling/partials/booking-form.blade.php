<x-searchable-select
    name="care_task_id"
    label="Counselling Case"
    :required="true"
    placeholder="Search cases by member, type, or status"
    :selected="$booking?->case?->opaqueId()"
    :options="$caseOptions->map(fn ($caseOption) => [
        'value' => $caseOption->opaqueId(),
        'label' => trim(($caseOption->member?->first_name ?? '').' '.($caseOption->member?->last_name ?? '')),
        'meta' => trim(($caseOption->type ?? 'Case').' - '.Str::headline($caseOption->status)),
        'initials' => Str::substr($caseOption->member?->first_name ?? 'C', 0, 1).Str::substr($caseOption->member?->last_name ?? 'A', 0, 1),
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
                <option value="{{ $status }}" @selected(old('status', $booking?->status ?? 'scheduled') === $status)>{{ Str::headline($status) }}</option>
            @endforeach
        </select>
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Location Type
        <select name="location_type" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            @foreach($locationTypes as $type)
                <option value="{{ $type }}" @selected(old('location_type', $booking?->location_type ?? 'in_person') === $type)>{{ Str::headline($type) }}</option>
            @endforeach
        </select>
    </label>
</div>

<x-searchable-select
    name="counselor_user_id"
    label="Counselor"
    empty-label="Use case assignee"
    placeholder="Search counselors by name, title, or email"
    :selected="$booking?->counselor?->opaqueId()"
    :options="$users->map(fn ($user) => [
        'value' => $user->opaqueId(),
        'label' => $user->name,
        'meta' => trim(($user->title ?: 'Team Member').' - '.($user->email ?: 'No email')),
        'avatar' => $user->avatar_src,
        'initials' => Str::of($user->name)->explode(' ')->filter()->map(fn ($part) => Str::substr($part, 0, 1))->take(2)->join(''),
    ])->values()"
/>

<x-searchable-select
    name="campus_id"
    label="Campus"
    empty-label="Use case/member campus"
    placeholder="Search campus"
    :selected="$booking?->campus?->opaqueId()"
    :options="$campuses->map(fn ($campus) => [
        'value' => $campus->opaqueId(),
        'label' => $campus->name,
        'meta' => trim(($campus->type ?? 'Campus').' - '.($campus->city ?? '')),
        'initials' => Str::substr($campus->name, 0, 2),
    ])->values()"
/>

<label class="space-y-1 text-sm font-medium text-slate-700">
    Location
    <input name="location" value="{{ old('location', $booking?->location) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Room, office, phone number, or meeting label">
</label>

<label class="space-y-1 text-sm font-medium text-slate-700">
    Meeting URL
    <input name="meeting_url" type="url" value="{{ old('meeting_url', $booking?->meeting_url) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="https://">
</label>

<label class="space-y-1 text-sm font-medium text-slate-700">
    Booking Notes
    <textarea name="notes" rows="4" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Preparation, confidentiality notes, or special instructions">{{ old('notes', $booking?->notes) }}</textarea>
</label>
