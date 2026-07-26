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
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Campus
        <select name="campus_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            <option value="">Select campus</option>
            @foreach($campuses as $campus)
                <option value="{{ $campus->id }}" @selected((string) old('campus_id', $transaction?->campus_id) === (string) $campus->id)>{{ $campus->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Ministry / Department
        <select name="ministry_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            <option value="">No ministry</option>
            @foreach($ministries as $ministry)
                <option value="{{ $ministry->id }}" @selected((string) old('ministry_id', $transaction?->ministry_id) === (string) $ministry->id)>{{ $ministry->name }}{{ $ministry->campus ? ' - '.$ministry->campus->name : '' }}</option>
            @endforeach
        </select>
    </label>
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
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Fund
        <select name="fund_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            <option value="">No fund</option>
            @foreach($funds as $fund)
                <option value="{{ $fund->id }}" @selected((string) old('fund_id', $transaction?->fund_id) === (string) $fund->id)>{{ $fund->name }}</option>
            @endforeach
        </select>
    </label>
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
