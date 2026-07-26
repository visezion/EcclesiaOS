<label class="space-y-1 text-sm font-medium text-slate-700">
    Asset Name
    <input name="name" value="{{ old('name', $asset?->name) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
</label>

<label class="space-y-1 text-sm font-medium text-slate-700">
    Serial Number
    <input name="serial_number" value="{{ old('serial_number', $asset?->serial_number) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
</label>

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Category
        <select name="asset_category_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            <option value="">Uncategorized</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('asset_category_id', $asset?->asset_category_id) === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Campus
        <select name="campus_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            <option value="">Unassigned</option>
            @foreach($campuses as $campus)
                <option value="{{ $campus->id }}" @selected((string) old('campus_id', $asset?->campus_id) === (string) $campus->id)>{{ $campus->name }}</option>
            @endforeach
        </select>
    </label>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Status
        <select name="status" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            @foreach($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $asset?->status ?? 'available') === $status)>{{ Str::headline($status) }}</option>
            @endforeach
        </select>
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Condition
        <select name="condition" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            @foreach($conditions as $condition)
                <option value="{{ $condition }}" @selected(old('condition', $asset?->condition ?? 'good') === $condition)>{{ Str::headline($condition) }}</option>
            @endforeach
        </select>
    </label>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Purchased At
        <input name="purchased_at" type="date" value="{{ old('purchased_at', $asset?->purchased_at?->format('Y-m-d')) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Purchase Amount
        <input name="purchase_amount" type="number" step="0.01" value="{{ old('purchase_amount', $asset?->purchase_amount) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
    </label>
</div>
