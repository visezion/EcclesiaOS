@php($libraryDefaults = $libraryDefaults ?? [])

<label class="space-y-1 text-sm font-medium text-slate-700">
    Product Name
    <input name="name" value="{{ old('name', $product?->name) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
</label>

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        SKU
        <input name="sku" value="{{ old('sku', $product?->sku) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Category
        <input name="category" value="{{ old('category', $product?->category) }}" list="bookstore-categories" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
        <datalist id="bookstore-categories">
            @foreach($categories as $category)
                <option value="{{ $category }}"></option>
            @endforeach
        </datalist>
    </label>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Author
        <input name="author" value="{{ old('author', $product?->author) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        ISBN
        <input name="isbn" value="{{ old('isbn', $product?->isbn) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
    </label>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Format
        <select name="format" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            @foreach($productFormats as $format)
                <option value="{{ $format }}" @selected(old('format', $product?->format ?? ($libraryDefaults['format'] ?? 'hardcopy')) === $format)>{{ Str::headline($format) }}</option>
            @endforeach
        </select>
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Publisher
        <input name="publisher" value="{{ old('publisher', $product?->publisher) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
    </label>
</div>

<x-searchable-select
    name="campus_id"
    label="Campus"
    empty-label="Unassigned"
    placeholder="Search campus"
    :selected="$product?->campus_id"
    :options="$campuses->map(fn ($campus) => [
        'value' => $campus->id,
        'label' => $campus->name,
        'meta' => trim(($campus->type ?? 'Campus').' - '.($campus->city ?? '')),
        'initials' => Str::substr($campus->name, 0, 2),
    ])->values()"
/>

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Price ({{ $currency }})
        <input name="price" type="number" min="0.01" step="0.01" value="{{ old('price', $product?->price) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Stock Quantity
        <input name="stock_quantity" type="number" min="0" value="{{ old('stock_quantity', $product?->stock_quantity ?? 0) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
    </label>
</div>

<div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Library Options</p>
    <div class="mt-3 grid gap-3 sm:grid-cols-3">
        <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
            <input type="checkbox" name="is_library_item" value="1" @checked(old('is_library_item', $product?->is_library_item ?? ($libraryDefaults['is_library_item'] ?? false))) class="rounded border-slate-300 text-violet-600">
            Library item
        </label>
        <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
            <input type="checkbox" name="borrowable" value="1" @checked(old('borrowable', $product?->borrowable ?? ($libraryDefaults['borrowable'] ?? false))) class="rounded border-slate-300 text-violet-600">
            Borrow
        </label>
        <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
            <input type="checkbox" name="rentable" value="1" @checked(old('rentable', $product?->rentable ?? ($libraryDefaults['rentable'] ?? false))) class="rounded border-slate-300 text-violet-600">
            Rent
        </label>
    </div>
    <div class="mt-3 grid gap-4 sm:grid-cols-2">
        <label class="space-y-1 text-sm font-medium text-slate-700">
            Rental Price ({{ $currency }})
            <input name="rental_price" type="number" min="0" step="0.01" value="{{ old('rental_price', $product?->rental_price) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
        </label>
        <label class="space-y-1 text-sm font-medium text-slate-700">
            Ebook / Online URL
            <input name="digital_url" type="url" value="{{ old('digital_url', $product?->digital_url) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="https://...">
        </label>
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Reorder Level
        <input name="reorder_level" type="number" min="0" value="{{ old('reorder_level', $product?->reorder_level ?? 5) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Status
        <select name="status" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            @foreach($productStatuses as $status)
                <option value="{{ $status }}" @selected(old('status', $product?->status ?? 'active') === $status)>{{ Str::headline($status) }}</option>
            @endforeach
        </select>
    </label>
</div>
