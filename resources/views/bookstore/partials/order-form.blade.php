@php($isEdit = filled($order))

@if(! $isEdit)
    <x-searchable-select
        name="bookstore_product_id"
        label="Product"
        :required="true"
        placeholder="Search products by title, author, SKU, or format"
        :selected="old('bookstore_product_id')"
        :options="$productOptions->map(fn ($productOption) => [
            'value' => $productOption->id,
            'label' => $productOption->name,
            'meta' => trim(($productOption->author ?: 'No author').' - '.$currency.' '.number_format((float) $productOption->price, 2).' - '.number_format($productOption->stock_quantity).' in stock'),
            'initials' => Str::substr($productOption->name, 0, 2),
            'search' => trim(($productOption->sku ?? '').' '.($productOption->isbn ?? '').' '.($productOption->format ?? '')),
        ])->values()"
    />

    <label class="space-y-1 text-sm font-medium text-slate-700">
        Quantity
        <input name="quantity" type="number" min="1" max="999" value="{{ old('quantity', 1) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
    </label>
@endif

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Status
        <select name="status" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            @foreach($orderStatuses as $status)
                <option value="{{ $status }}" @selected(old('status', $order?->status ?? 'paid') === $status)>{{ Str::headline($status) }}</option>
            @endforeach
        </select>
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Ordered At
        <input name="ordered_at" type="datetime-local" value="{{ old('ordered_at', $order?->ordered_at?->format('Y-m-d\\TH:i') ?? now()->format('Y-m-d\\TH:i')) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
    </label>
</div>

<x-searchable-select
    name="member_id"
    label="Member"
    empty-label="Walk-in sale"
    placeholder="Search members by name, email, or campus"
    :selected="$order?->member_id"
    :options="$members->map(fn ($member) => [
        'value' => $member->id,
        'label' => trim($member->first_name.' '.$member->last_name),
        'meta' => trim(($member->email ?: 'No email').' - '.($member->campus?->name ?? 'No campus')),
        'initials' => Str::substr($member->first_name, 0, 1).Str::substr($member->last_name, 0, 1),
    ])->values()"
/>

<x-searchable-select
    name="campus_id"
    label="Campus"
    empty-label="Use member/product campus"
    placeholder="Search campus"
    :selected="$order?->campus_id"
    :options="$campuses->map(fn ($campus) => [
        'value' => $campus->id,
        'label' => $campus->name,
        'meta' => trim(($campus->type ?? 'Campus').' - '.($campus->city ?? '')),
        'initials' => Str::substr($campus->name, 0, 2),
    ])->values()"
/>

@if($isEdit)
    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Items</p>
        <div class="mt-2 space-y-2 text-sm text-slate-700">
            @forelse($order->items as $item)
                <div class="flex items-center justify-between gap-3">
                    <span>{{ $item->product_name }} x{{ $item->quantity }}</span>
                    <span class="font-semibold">{{ $order->currency }} {{ number_format((float) $item->line_total, 2) }}</span>
                </div>
            @empty
                <p>No items recorded.</p>
            @endforelse
        </div>
    </div>
@endif
