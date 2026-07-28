<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Type
        <select name="type" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            @foreach($transactionTypes as $type)
                <option value="{{ $type }}" @selected(old('type', $transaction?->type ?? 'income') === $type)>{{ Str::headline($type) }}</option>
            @endforeach
        </select>
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Status
        <select name="status" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            @foreach($transactionStatuses as $status)
                <option value="{{ $status }}" @selected(old('status', $transaction?->status ?? 'posted') === $status)>{{ Str::headline($status) }}</option>
            @endforeach
        </select>
    </label>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <x-searchable-select
        name="campus_id"
        label="Campus"
        :required="true"
        placeholder="Search campus"
        :selected="$transaction?->campus_id"
        :options="$campuses->map(fn ($campus) => [
            'value' => $campus->id,
            'label' => $campus->name,
            'meta' => trim(($campus->type ?? 'Campus').' - '.($campus->city ?? '')),
            'initials' => Str::substr($campus->name, 0, 2),
        ])->values()"
    />
    <x-searchable-select
        name="ministry_id"
        label="Ministry / Department"
        empty-label="No ministry"
        placeholder="Search ministries or departments"
        :selected="$transaction?->ministry_id"
        :options="$ministries->map(fn ($ministry) => [
            'value' => $ministry->id,
            'label' => $ministry->name,
            'meta' => $ministry->campus?->name ?? 'No campus',
            'initials' => Str::substr($ministry->name, 0, 2),
        ])->values()"
    />
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Category
        <select name="category" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            @foreach($transactionCategories as $category)
                <option value="{{ $category }}" @selected(old('category', $transaction?->category ?? 'general') === $category)>{{ Str::headline($category) }}</option>
            @endforeach
        </select>
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Amount
        <input name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount', $transaction?->amount) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
    </label>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Method
        <select name="method" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            <option value="">Not specified</option>
            @foreach($methods as $method)
                <option value="{{ $method }}" @selected(old('method', $transaction?->method) === $method)>{{ Str::headline($method) }}</option>
            @endforeach
        </select>
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Frequency
        <select name="frequency" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            @foreach($givingFrequencies as $frequency)
                <option value="{{ $frequency }}" @selected(old('frequency', $transaction?->frequency ?? 'one_time') === $frequency)>{{ Str::headline($frequency) }}</option>
            @endforeach
        </select>
    </label>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Occurred At
        <input name="occurred_at" type="datetime-local" value="{{ old('occurred_at', $transaction?->occurred_at?->format('Y-m-d\\TH:i') ?? now()->format('Y-m-d\\TH:i')) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
    </label>
    <x-searchable-select
        name="fund_id"
        label="Fund"
        empty-label="No fund"
        placeholder="Search funds"
        :selected="$transaction?->fund_id"
        :options="$funds->map(fn ($fund) => [
            'value' => $fund->id,
            'label' => $fund->name,
            'meta' => $fund->is_active ? 'Active fund' : 'Inactive fund',
            'initials' => Str::substr($fund->name, 0, 2),
        ])->values()"
    />
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Reference
        <input name="reference" value="{{ old('reference', $transaction?->reference) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Auto generated if blank">
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Vendor / Source
        <input name="vendor_or_source" value="{{ old('vendor_or_source', $transaction?->vendor_or_source) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Vendor, donor group, department">
    </label>
</div>

<label class="space-y-1 text-sm font-medium text-slate-700">
    Description
    <textarea name="description" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Short note for audit trail">{{ old('description', $transaction?->description) }}</textarea>
</label>

<input type="hidden" name="currency" value="{{ $transaction?->currency ?: $currency }}">
