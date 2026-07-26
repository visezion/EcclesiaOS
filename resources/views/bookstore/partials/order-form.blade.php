@php($isEdit = filled($order))

@if(! $isEdit)
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Product
        <select name="bookstore_product_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            <option value="">Select product</option>
            @foreach($productOptions as $productOption)
                <option value="{{ $productOption->id }}" @selected((string) old('bookstore_product_id') === (string) $productOption->id)>
                    {{ $productOption->name }} &middot; {{ $currency }} {{ number_format((float) $productOption->price, 2) }} &middot; {{ number_format($productOption->stock_quantity) }} in stock
                </option>
            @endforeach
        </select>
    </label>

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

<label class="space-y-1 text-sm font-medium text-slate-700">
    Member
    <select name="member_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
        <option value="">Walk-in sale</option>
        @foreach($members as $member)
            <option value="{{ $member->id }}" @selected((string) old('member_id', $order?->member_id) === (string) $member->id)>{{ $member->first_name }} {{ $member->last_name }}{{ $member->campus ? ' - '.$member->campus->name : '' }}</option>
        @endforeach
    </select>
</label>

<label class="space-y-1 text-sm font-medium text-slate-700">
    Campus
    <select name="campus_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
        <option value="">Use member/product campus</option>
        @foreach($campuses as $campus)
            <option value="{{ $campus->id }}" @selected((string) old('campus_id', $order?->campus_id) === (string) $campus->id)>{{ $campus->name }}</option>
        @endforeach
    </select>
</label>

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
