<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Reference
        <input name="reference" value="{{ old('reference', $donation?->reference) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Auto generated if blank">
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Received At
        <input name="received_at" type="datetime-local" value="{{ old('received_at', $donation?->received_at?->format('Y-m-d\\TH:i') ?? now()->format('Y-m-d\\TH:i')) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
    </label>
</div>

<label class="space-y-1 text-sm font-medium text-slate-700">
    Member
    <select name="member_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
        <option value="">Anonymous giver</option>
        @foreach($members as $member)
            <option value="{{ $member->id }}" @selected((string) old('member_id', $donation?->member_id) === (string) $member->id)>{{ $member->first_name }} {{ $member->last_name }}{{ $member->campus ? ' - '.$member->campus->name : '' }}</option>
        @endforeach
    </select>
</label>

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Fund
        <select name="fund_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            <option value="">Unassigned</option>
            @foreach($funds as $fund)
                <option value="{{ $fund->id }}" @selected((string) old('fund_id', $donation?->fund_id) === (string) $fund->id)>{{ $fund->name }}{{ $fund->is_active ? '' : ' - inactive' }}</option>
            @endforeach
        </select>
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Campus
        <select name="campus_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            <option value="">Unassigned</option>
            @foreach($campuses as $campus)
                <option value="{{ $campus->id }}" @selected((string) old('campus_id', $donation?->campus_id) === (string) $campus->id)>{{ $campus->name }}</option>
            @endforeach
        </select>
    </label>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Ministry / Department
        <select name="ministry_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            <option value="">No ministry</option>
            @foreach($ministries as $ministry)
                <option value="{{ $ministry->id }}" @selected((string) old('ministry_id', $donation?->ministry_id) === (string) $ministry->id)>{{ $ministry->name }}{{ $ministry->campus ? ' - '.$ministry->campus->name : '' }}</option>
            @endforeach
        </select>
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Giving Frequency
        <select name="giving_frequency" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            @foreach($givingFrequencies as $frequency)
                <option value="{{ $frequency }}" @selected(old('giving_frequency', $donation?->giving_frequency ?? 'one_time') === $frequency)>{{ Str::headline($frequency) }}</option>
            @endforeach
        </select>
    </label>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Method
        <select name="method" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            @foreach($methods as $method)
                <option value="{{ $method }}" @selected(old('method', $donation?->method ?? 'cash') === $method)>{{ Str::headline($method) }}</option>
            @endforeach
        </select>
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Amount
        <input name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount', $donation?->amount) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
    </label>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Giving Source
        <select name="giving_source" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            @foreach($givingSources as $source)
                <option value="{{ $source }}" @selected(old('giving_source', $donation?->giving_source ?? 'member') === $source)>{{ Str::headline($source) }}</option>
            @endforeach
        </select>
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Notes
        <input name="notes" value="{{ old('notes', $donation?->notes) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Optional giving note">
    </label>
</div>

<input type="hidden" name="currency" value="{{ $donation?->currency ?: $currency }}">
