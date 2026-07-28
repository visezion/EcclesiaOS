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

<x-searchable-select
    name="member_id"
    label="Member"
    empty-label="Anonymous giver"
    placeholder="Search givers by name, email, or campus"
    :selected="$donation?->member_id"
    :options="$members->map(fn ($member) => [
        'value' => $member->id,
        'label' => trim($member->first_name.' '.$member->last_name),
        'meta' => trim(($member->email ?: 'No email').' - '.($member->campus?->name ?? 'No campus')),
        'initials' => Str::substr($member->first_name, 0, 1).Str::substr($member->last_name, 0, 1),
    ])->values()"
/>

<div class="grid gap-4 sm:grid-cols-2">
    <x-searchable-select
        name="fund_id"
        label="Fund"
        empty-label="Unassigned"
        placeholder="Search funds"
        :selected="$donation?->fund_id"
        :options="$funds->map(fn ($fund) => [
            'value' => $fund->id,
            'label' => $fund->name,
            'meta' => $fund->is_active ? 'Active fund' : 'Inactive fund',
            'initials' => Str::substr($fund->name, 0, 2),
        ])->values()"
    />
    <x-searchable-select
        name="campus_id"
        label="Campus"
        empty-label="Unassigned"
        placeholder="Search campus"
        :selected="$donation?->campus_id"
        :options="$campuses->map(fn ($campus) => [
            'value' => $campus->id,
            'label' => $campus->name,
            'meta' => trim(($campus->type ?? 'Campus').' - '.($campus->city ?? '')),
            'initials' => Str::substr($campus->name, 0, 2),
        ])->values()"
    />
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <x-searchable-select
        name="ministry_id"
        label="Ministry / Department"
        empty-label="No ministry"
        placeholder="Search ministries or departments"
        :selected="$donation?->ministry_id"
        :options="$ministries->map(fn ($ministry) => [
            'value' => $ministry->id,
            'label' => $ministry->name,
            'meta' => $ministry->campus?->name ?? 'No campus',
            'initials' => Str::substr($ministry->name, 0, 2),
        ])->values()"
    />
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
