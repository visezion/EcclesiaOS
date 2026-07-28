@php($isEdit = filled($loan))

@if(! $isEdit)
    <x-searchable-select
        name="bookstore_product_id"
        label="Book / Resource"
        :required="true"
        placeholder="Search library items by title, author, ISBN, or format"
        :selected="old('bookstore_product_id')"
        :options="$libraryProductOptions->map(fn ($productOption) => [
            'value' => $productOption->id,
            'label' => $productOption->name,
            'meta' => trim(($productOption->author ?: 'No author').' - '.Str::headline($productOption->format ?? 'hardcopy').' - '.number_format($productOption->stock_quantity).' physical'),
            'initials' => Str::substr($productOption->name, 0, 2),
            'search' => trim(($productOption->isbn ?? '').' '.($productOption->sku ?? '')),
        ])->values()"
    />

    <x-searchable-select
        name="member_id"
        label="Member"
        :required="true"
        placeholder="Search members by name, email, or campus"
        :selected="old('member_id')"
        :options="$members->map(fn ($member) => [
            'value' => $member->id,
            'label' => trim($member->first_name.' '.$member->last_name),
            'meta' => trim(($member->email ?: 'No email').' - '.($member->campus?->name ?? 'No campus')),
            'initials' => Str::substr($member->first_name, 0, 1).Str::substr($member->last_name, 0, 1),
        ])->values()"
    />

    <div class="grid gap-4 sm:grid-cols-2">
        <label class="space-y-1 text-sm font-medium text-slate-700">
            Type
            <select name="loan_type" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                @foreach($loanTypes as $type)
                    <option value="{{ $type }}" @selected(old('loan_type', 'borrow') === $type)>{{ Str::headline($type) }}</option>
                @endforeach
            </select>
        </label>
        <x-searchable-select
            name="campus_id"
            label="Campus"
            empty-label="Use member/book campus"
            placeholder="Search campus"
            :selected="old('campus_id')"
            :options="$campuses->map(fn ($campus) => [
                'value' => $campus->id,
                'label' => $campus->name,
                'meta' => trim(($campus->type ?? 'Campus').' - '.($campus->city ?? '')),
                'initials' => Str::substr($campus->name, 0, 2),
            ])->values()"
        />
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
        <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Approval: {{ Str::headline($loan->approval_status ?? 'not_required') }}</p>
    </div>
@endif

@if($isEdit)
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="space-y-1 text-sm font-medium text-slate-700">
            Status
            <select name="status" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
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
@else
    <label class="space-y-1 text-sm font-medium text-slate-700">
        Due / Access Ends
        <input name="due_at" type="datetime-local" value="{{ old('due_at') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
    </label>
@endif

@if(! $isEdit)
    <input type="hidden" name="status" value="pending_approval">
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
