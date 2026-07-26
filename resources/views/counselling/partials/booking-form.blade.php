<label class="space-y-1 text-sm font-medium text-slate-700">
    Counselling Case
    <select name="care_task_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
        <option value="">Select case</option>
        @foreach($caseOptions as $caseOption)
            <option value="{{ $caseOption->opaqueId() }}" @selected(old('care_task_id', $booking?->case?->opaqueId()) === $caseOption->opaqueId())>
                {{ $caseOption->member?->first_name }} {{ $caseOption->member?->last_name }} · {{ $caseOption->type }} · {{ Str::headline($caseOption->status) }}
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

<label class="space-y-1 text-sm font-medium text-slate-700">
    Counselor
    <select name="counselor_user_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
        <option value="">Use case assignee</option>
        @foreach($users as $user)
            <option value="{{ $user->opaqueId() }}" @selected(old('counselor_user_id', $booking?->counselor?->opaqueId()) === $user->opaqueId())>{{ $user->name }}</option>
        @endforeach
    </select>
</label>

<label class="space-y-1 text-sm font-medium text-slate-700">
    Campus
    <select name="campus_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
        <option value="">Use case/member campus</option>
        @foreach($campuses as $campus)
            <option value="{{ $campus->opaqueId() }}" @selected(old('campus_id', $booking?->campus?->opaqueId()) === $campus->opaqueId())>{{ $campus->name }}</option>
        @endforeach
    </select>
</label>

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
