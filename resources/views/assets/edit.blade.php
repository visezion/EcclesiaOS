<x-app-layout title="Edit Asset" :breadcrumbs="$breadcrumbs">
    <div class="mx-auto max-w-4xl space-y-5">
        <div class="flex items-center justify-between gap-3">
            <div><h1 class="text-2xl font-semibold text-slate-950">Edit Asset</h1><p class="text-sm text-slate-500">{{ $asset->name }}</p></div>
            <a href="{{ route('assets.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700"><i data-lucide="list" class="size-4"></i>Register</a>
        </div>
        @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif
        <section class="dashboard-card">
            <form method="POST" action="{{ route('assets.update', $asset) }}" class="grid gap-4 md:grid-cols-2">
                @csrf @method('PUT')
                <label class="space-y-1 text-sm font-medium text-slate-700">Asset Name<input name="name" value="{{ old('name', $asset->name) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"></label>
                <label class="space-y-1 text-sm font-medium text-slate-700">Serial Number<input name="serial_number" value="{{ old('serial_number', $asset->serial_number) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"></label>
                <label class="space-y-1 text-sm font-medium text-slate-700">Category<select name="asset_category_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"><option value="">Uncategorized</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) old('asset_category_id', $asset->asset_category_id) === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></label>
                <label class="space-y-1 text-sm font-medium text-slate-700">Campus<select name="campus_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"><option value="">Unassigned</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected((string) old('campus_id', $asset->campus_id) === (string) $campus->id)>{{ $campus->name }}</option>@endforeach</select></label>
                <label class="space-y-1 text-sm font-medium text-slate-700">Status<select name="status" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">@foreach($statuses as $status)<option value="{{ $status }}" @selected(old('status', $asset->status) === $status)>{{ Str::headline($status) }}</option>@endforeach</select></label>
                <label class="space-y-1 text-sm font-medium text-slate-700">Condition<select name="condition" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">@foreach($conditions as $condition)<option value="{{ $condition }}" @selected(old('condition', $asset->condition) === $condition)>{{ Str::headline($condition) }}</option>@endforeach</select></label>
                <label class="space-y-1 text-sm font-medium text-slate-700">Purchased At<input name="purchased_at" type="date" value="{{ old('purchased_at', $asset->purchased_at?->format('Y-m-d')) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"></label>
                <label class="space-y-1 text-sm font-medium text-slate-700">Purchase Amount<input name="purchase_amount" type="number" step="0.01" value="{{ old('purchase_amount', $asset->purchase_amount) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"></label>
                <div class="md:col-span-2 flex justify-between gap-2"><button form="delete-asset" class="rounded-lg border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700">Archive</button><div class="flex gap-2"><a href="{{ route('assets.index') }}" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a><button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white"><i data-lucide="save" class="size-4"></i>Save Changes</button></div></div>
            </form>
            <form id="delete-asset" method="POST" action="{{ route('assets.destroy', $asset) }}" class="hidden">@csrf @method('DELETE')</form>
        </section>
    </div>
</x-app-layout>
