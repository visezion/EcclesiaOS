<x-app-layout title="Add Asset" :breadcrumbs="$breadcrumbs">
    <div class="mx-auto max-w-4xl space-y-5">
        <div class="flex items-center justify-between gap-3">
            <div><h1 class="text-2xl font-semibold text-slate-950">Add Asset</h1><p class="text-sm text-slate-500">Create a tracked inventory item with category, campus, condition, and value.</p></div>
            <a href="{{ route('assets.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700"><i data-lucide="list" class="size-4"></i>Register</a>
        </div>
        @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif
        <section class="dashboard-card">
            <form method="POST" action="{{ route('assets.store') }}" class="grid gap-4 md:grid-cols-2">
                @csrf
                <label class="space-y-1 text-sm font-medium text-slate-700">Asset Name<input name="name" value="{{ old('name') }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"></label>
                <label class="space-y-1 text-sm font-medium text-slate-700">Serial Number<input name="serial_number" value="{{ old('serial_number') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"></label>
                <label class="space-y-1 text-sm font-medium text-slate-700">Category<select name="asset_category_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"><option value="">Uncategorized</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('asset_category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select></label>
                <label class="space-y-1 text-sm font-medium text-slate-700">Campus<select name="campus_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"><option value="">Unassigned</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected(old('campus_id') == $campus->id)>{{ $campus->name }}</option>@endforeach</select></label>
                <label class="space-y-1 text-sm font-medium text-slate-700">Status<select name="status" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">@foreach($statuses as $status)<option value="{{ $status }}" @selected(old('status', 'available') === $status)>{{ Str::headline($status) }}</option>@endforeach</select></label>
                <label class="space-y-1 text-sm font-medium text-slate-700">Condition<select name="condition" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">@foreach($conditions as $condition)<option value="{{ $condition }}" @selected(old('condition', 'good') === $condition)>{{ Str::headline($condition) }}</option>@endforeach</select></label>
                <label class="space-y-1 text-sm font-medium text-slate-700">Purchased At<input name="purchased_at" type="date" value="{{ old('purchased_at') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"></label>
                <label class="space-y-1 text-sm font-medium text-slate-700">Purchase Amount<input name="purchase_amount" type="number" step="0.01" value="{{ old('purchase_amount') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"></label>
                <div class="md:col-span-2 flex justify-end gap-2"><a href="{{ route('assets.index') }}" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a><button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white"><i data-lucide="plus" class="size-4"></i>Add Asset</button></div>
            </form>
        </section>
    </div>
</x-app-layout>
