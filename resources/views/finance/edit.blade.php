<x-app-layout title="Edit Donation" :breadcrumbs="$breadcrumbs">
    <div class="mx-auto max-w-4xl space-y-5">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-slate-950">Edit Donation</h1>
                <p class="text-sm text-slate-500">{{ $donation->reference }}</p>
            </div>
            <a href="{{ route('finance.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700"><i data-lucide="list" class="size-4"></i>Ledger</a>
        </div>
        @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif
        <section class="dashboard-card">
            <form method="POST" action="{{ route('finance.donations.update', $donation) }}" class="space-y-4">
                @csrf @method('PUT')
                @include('finance.partials.donation-form', ['donation' => $donation])
                <div class="flex justify-between gap-2 border-t border-slate-100 pt-4">
                    <button form="delete-donation" class="rounded-lg border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700">Archive</button>
                    <div class="flex gap-2">
                        <a href="{{ route('finance.index') }}" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a>
                        <button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white"><i data-lucide="save" class="size-4"></i>Save Changes</button>
                    </div>
                </div>
            </form>
            <form id="delete-donation" method="POST" action="{{ route('finance.donations.destroy', $donation) }}" class="hidden">@csrf @method('DELETE')</form>
        </section>
    </div>
</x-app-layout>
