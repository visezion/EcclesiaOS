<label class="space-y-1 text-sm font-medium text-slate-700">
    Asset Name
    <input name="name" value="{{ old('name', $asset?->name) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
</label>

<label class="space-y-1 text-sm font-medium text-slate-700">
    Serial Number
    <input name="serial_number" value="{{ old('serial_number', $asset?->serial_number) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
</label>

<div class="grid gap-4 sm:grid-cols-2">
    <x-searchable-select
        name="asset_category_id"
        label="Category"
        empty-label="Uncategorized"
        placeholder="Search asset categories"
        :selected="$asset?->asset_category_id"
        :options="$categories->map(fn ($category) => [
            'value' => $category->id,
            'label' => $category->name,
            'meta' => $category->description ?? 'Asset category',
            'initials' => Str::substr($category->name, 0, 2),
        ])->values()"
    />
    <x-searchable-select
        name="campus_id"
        label="Campus"
        empty-label="Unassigned"
        placeholder="Search campus"
        :selected="$asset?->campus_id"
        :options="$campuses->map(fn ($campus) => [
            'value' => $campus->id,
            'label' => $campus->name,
            'meta' => trim(($campus->type ?? 'Campus').' - '.($campus->city ?? '')),
            'initials' => Str::substr($campus->name, 0, 2),
        ])->values()"
    />
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
