<x-app-layout title="Create Bookstore Order" :breadcrumbs="$breadcrumbs">
    <div class="mx-auto max-w-4xl space-y-5">
        <div class="flex items-center justify-between gap-3">
            <div><h1 class="text-2xl font-semibold text-slate-950">Create Order</h1><p class="text-sm text-slate-500">Record a bookstore sale and reduce product inventory.</p></div>
            <a href="{{ route('bookstore.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700"><i data-lucide="list" class="size-4"></i>Catalog</a>
        </div>
        @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif
        <section class="dashboard-card">
            <form method="POST" action="{{ route('bookstore.orders.store') }}" class="space-y-4">
                @csrf
                @include('bookstore.partials.order-form', ['order' => null])
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <a href="{{ route('bookstore.index') }}" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a>
                    <button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white"><i data-lucide="shopping-cart" class="size-4"></i>Create Order</button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
