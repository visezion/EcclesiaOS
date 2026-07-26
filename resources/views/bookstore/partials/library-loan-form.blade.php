@php($isEdit = filled($loan))

@if(! $isEdit)
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Book / Resource
        <select name="bookstore_product_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            <option value="">Select library item</option>
            @foreach($libraryProductOptions as $productOption)
                <option value="{{ $productOption->id }}" @selected((string) old('bookstore_product_id') === (string) $productOption->id)>
                    {{ $productOption->name }}{{ $productOption->author ? ' - '.$productOption->author : '' }} - {{ Str::headline($productOption->format ?? 'hardcopy') }} - {{ number_format($productOption->stock_quantity) }} physical
                </option>
            @endforeach
        </select>
    </label>

    <label class="space-y-1 text-sm font-medium text-slate-700">
        Member
        <select name="member_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            <option value="">Select member</option>
            @foreach($members as $member)
                <option value="{{ $member->id }}" @selected((string) old('member_id') === (string) $member->id)>{{ $member->first_name }} {{ $member->last_name }}{{ $member->campus ? ' - '.$member->campus->name : '' }}</option>
            @endforeach
        </select>
    </label>

    <div class="grid gap-4 sm:grid-cols-2">
        <label class="space-y-1 text-sm font-medium text-slate-700">
            Type
            <select name="loan_type" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                @foreach($loanTypes as $type)
                    <option value="{{ $type }}" @selected(old('loan_type', 'borrow') === $type)>{{ Str::headline($type) }}</option>
                @endforeach
            </select>
        </label>
        <label class="space-y-1 text-sm font-medium text-slate-700">
            Campus
            <select name="campus_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                <option value="">Use member/book campus</option>
                @foreach($campuses as $campus)
                    <option value="{{ $campus->id }}" @selected((string) old('campus_id') === (string) $campus->id)>{{ $campus->name }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <label class="space-y-1 text-sm font-medium text-slate-700">
        Checked Out / Access Starts
        <input name="checked_out_at" type="datetime-local" value="{{ old('checked_out_at', now()->format('Y-m-d\\TH:i')) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
    </label>
@else
    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Record</p>
        <p class="mt-1 text-sm font-semibold text-slate-950">{{ $loan->loan_number }}</p>
        <p class="mt-1 text-sm text-slate-500">{{ $loan->product?->name }} - {{ $loan->member?->first_name }} {{ $loan->member?->last_name }}</p>
    </div>
@endif

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Status
        <select name="status" @disabled(! $isEdit) class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
            @foreach($loanStatuses as $status)
                <option value="{{ $status }}" @selected(old('status', $loan?->status ?? 'active') === $status)>{{ Str::headline($status) }}</option>
            @endforeach
        </select>
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Due / Access Ends
        <input name="due_at" type="datetime-local" value="{{ old('due_at', $loan?->due_at?->format('Y-m-d\\TH:i')) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
    </label>
</div>

@if(! $isEdit)
    <input type="hidden" name="status" value="active">
@endif

<div class="grid gap-4 sm:grid-cols-2">
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Returned At
        <input name="returned_at" type="datetime-local" value="{{ old('returned_at', $loan?->returned_at?->format('Y-m-d\\TH:i')) }}" @disabled(! $isEdit) class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
    </label>
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Rental Amount ({{ $currency }})
        <input name="rental_amount" type="number" min="0" step="0.01" value="{{ old('rental_amount', $loan?->rental_amount) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
    </label>
</div>

<input type="hidden" name="currency" value="{{ $loan?->currency ?: $currency }}">

<label class="space-y-1 text-sm font-medium text-slate-700">
    Notes
    <textarea name="notes" rows="4" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">{{ old('notes', $loan?->notes) }}</textarea>
</label>
