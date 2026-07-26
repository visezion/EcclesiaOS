<x-app-layout title="Record Donation" :breadcrumbs="$breadcrumbs">
    <div class="mx-auto max-w-4xl space-y-5">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-slate-950">Record Donation</h1>
                <p class="text-sm text-slate-500">Create a donation record tied to a member, fund, campus, ministry, and payment method.</p>
            </div>
            <a href="{{ route('finance.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700"><i data-lucide="list" class="size-4"></i>Ledger</a>
        </div>
        @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif
        <section class="dashboard-card">
            <form method="POST" action="{{ route('finance.donations.store') }}" class="space-y-4">
                @csrf
                @include('finance.partials.donation-form', ['donation' => null])
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <a href="{{ route('finance.index') }}" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a>
                    <button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white"><i data-lucide="plus" class="size-4"></i>Record Donation</button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
