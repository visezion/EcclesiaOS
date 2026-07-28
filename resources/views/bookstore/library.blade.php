<x-app-layout title="Church Library" :breadcrumbs="$breadcrumbs">
    <div x-data="{ productOpen: false, productEditing: null, loanOpen: {{ $errors->any() ? 'true' : 'false' }}, loanEditing: null }" class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-xl bg-amber-100 text-amber-600"><i data-lucide="library" class="size-7"></i></div>
                <div>
                    <h1 class="text-2xl font-semibold text-slate-950">Church Library</h1>
                    <p class="text-sm text-slate-500">Borrow, rent, sell, and provide digital access from the connected bookstore catalog.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('bookstore.index') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i data-lucide="store" class="size-4"></i>Bookstore</a>
                <button type="button" @click="productOpen = true" class="inline-flex items-center justify-center gap-2 rounded-lg border border-amber-200 bg-white px-4 py-2.5 text-sm font-semibold text-amber-700 hover:bg-amber-50"><i data-lucide="book-open" class="size-4"></i>Add Library Book</button>
                <button type="button" @click="loanOpen = true" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="book-plus" class="size-4"></i>Checkout / Access</button>
                <a href="{{ route('bookstore.library.export', request()->query()) }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i data-lucide="download" class="size-4"></i>Export</a>
            </div>
        </div>

        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-7">
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Store Catalog</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($libraryStats['bookstore']) }}</p><p class="mt-1 text-sm text-slate-500">bookstore products</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Library Enabled</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($libraryStats['catalog']) }}</p><p class="mt-1 text-sm text-slate-500">borrow/rent/digital</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Active</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($libraryStats['active']) }}</p><p class="mt-1 text-sm text-slate-500">borrow/rent/access</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Pending</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($libraryStats['pending']) }}</p><p class="mt-1 text-sm text-slate-500">awaiting approval</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Overdue</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($libraryStats['overdue']) }}</p><p class="mt-1 text-sm text-slate-500">needs follow-up</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Rentals</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($libraryStats['rentals']) }}</p><p class="mt-1 text-sm text-slate-500">active paid loans</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Digital</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($libraryStats['digital']) }}</p><p class="mt-1 text-sm text-slate-500">ebook access records</p></div>
        </div>

        <section class="dashboard-card p-0">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="text-base font-semibold text-slate-950">Circulation Register</h2><p class="mt-1 text-sm text-slate-500">{{ number_format($loans->total()) }} borrow, rent, return, and digital access records.</p></div>
                <button type="button" @click="loanOpen = true" class="inline-flex items-center gap-2 rounded-lg border border-violet-200 px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50"><i data-lucide="book-plus" class="size-4"></i>New Record</button>
            </div>
            <form method="GET" action="{{ route('bookstore.library') }}" class="grid gap-3 border-b border-slate-100 p-4 lg:grid-cols-[1fr_150px_150px_170px_170px_auto_auto]">
                <input name="loan_q" value="{{ request('loan_q') }}" class="h-10 rounded-lg border border-slate-200 px-3 text-sm" placeholder="Search loan, book, member...">
                <select name="loan_type" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Type</option>@foreach($loanTypes as $type)<option value="{{ $type }}" @selected(request('loan_type') === $type)>{{ Str::headline($type) }}</option>@endforeach</select>
                <select name="loan_status" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Status</option>@foreach($loanStatuses as $status)<option value="{{ $status }}" @selected(request('loan_status') === $status)>{{ Str::headline($status) }}</option>@endforeach</select>
                <select name="approval_status" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Approval</option>@foreach($approvalStatuses as $status)<option value="{{ $status }}" @selected(request('approval_status') === $status)>{{ Str::headline($status) }}</option>@endforeach</select>
                <select name="loan_campus_id" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Campus</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected((string) request('loan_campus_id') === (string) $campus->id)>{{ $campus->name }}</option>@endforeach</select>
                <button class="h-10 rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white">Apply</button>
                <a href="{{ route('bookstore.library') }}" class="inline-flex h-10 items-center px-3 text-sm font-semibold text-slate-500">Clear</a>
            </form>
            <div class="overflow-x-auto">
                <table class="table-compact min-w-[1120px]">
                    <thead><tr><th>Record</th><th>Book</th><th>Member</th><th>Type</th><th>Due</th><th>Status</th><th>Approval</th><th>Rental</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse($loans as $loan)
                            <tr>
                                <td><div class="font-semibold text-slate-950">{{ $loan->loan_number }}</div><div class="text-xs text-slate-500">{{ $loan->checked_out_at?->format('M d, Y h:i A') }}</div></td>
                                <td><div class="font-semibold text-slate-950">{{ $loan->product?->name }}</div><div class="text-xs text-slate-500">{{ $loan->product?->author ?: 'No author' }} - {{ Str::headline($loan->product?->format ?? 'hardcopy') }}</div></td>
                                <td>{{ $loan->member ? $loan->member->first_name.' '.$loan->member->last_name : 'No member' }}</td>
                                <td><x-status-badge :status="Str::headline($loan->loan_type)" /></td>
                                <td>{{ $loan->due_at?->format('M d, Y') ?? 'No due date' }}</td>
                                <td><x-status-badge :status="Str::headline($loan->status)" /></td>
                                <td><x-status-badge :status="Str::headline($loan->approval_status ?? 'not_required')" /></td>
                                <td>{{ $loan->rental_amount !== null ? $loan->currency.' '.number_format((float) $loan->rental_amount, 2) : '-' }}</td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="loanEditing = '{{ $loan->opaqueId() }}'" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50" title="Edit record"><i data-lucide="pencil" class="size-4"></i></button>
                                        @if(in_array($loan->status, ['active', 'overdue'], true))
                                            <button form="loan-return-{{ $loan->id }}" class="grid size-8 place-items-center rounded-lg border border-emerald-200 text-emerald-700 hover:bg-emerald-50" title="Return"><i data-lucide="rotate-ccw" class="size-4"></i></button>
                                        @endif
                                    </div>
                                    <form id="loan-return-{{ $loan->id }}" method="POST" action="{{ route('bookstore.library.loans.destroy', $loan) }}" class="hidden">@csrf @method('DELETE')</form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-12"><x-empty-state icon="library" title="No library records" message="Create the first borrow, rent, or ebook access record." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-4">{{ $loans->links() }}</div>
        </section>

        <section class="dashboard-card p-0">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="text-base font-semibold text-slate-950">Connected Bookstore Catalog</h2><p class="mt-1 text-sm text-slate-500">Every Bookstore product shows here. Enable library options to borrow, rent, or provide ebook access.</p></div>
                <button type="button" @click="productOpen = true" class="inline-flex items-center gap-2 rounded-lg border border-amber-200 px-4 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-50"><i data-lucide="book-open" class="size-4"></i>Add Library Book</button>
            </div>
            <div class="overflow-x-auto">
                <table class="table-compact min-w-[1120px]">
                    <thead><tr><th>Book</th><th>Format</th><th>Campus</th><th>Physical Stock</th><th>Library</th><th>Borrow</th><th>Rent</th><th>Buy</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse($catalogItems as $item)
                            <tr>
                                <td><div class="font-semibold text-slate-950">{{ $item->name }}</div><div class="text-xs text-slate-500">{{ $item->author ?: 'No author' }}{{ $item->isbn ? ' - ISBN '.$item->isbn : '' }}</div></td>
                                <td><x-status-badge :status="Str::headline($item->format ?? 'hardcopy')" /></td>
                                <td>{{ $item->campus?->name ?? 'Unassigned' }}</td>
                                <td class="font-semibold">{{ number_format($item->stock_quantity) }}</td>
                                <td><x-status-badge :status="$item->is_library_item || $item->borrowable || $item->rentable || in_array($item->format, ['ebook', 'bundle'], true) ? 'Enabled' : 'Store Only'" /></td>
                                <td>{{ $item->borrowable ? 'Yes' : 'No' }}</td>
                                <td>{{ $item->rentable ? $currency.' '.number_format((float) $item->rental_price, 2) : 'No' }}</td>
                                <td>{{ $currency }} {{ number_format((float) $item->price, 2) }}</td>
                                <td class="text-right"><button type="button" @click="productEditing = '{{ $item->opaqueId() }}'" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50" title="Edit book"><i data-lucide="pencil" class="size-4"></i></button></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-12"><x-empty-state icon="book-open" title="No books found" message="Add a bookstore product to make it available in the connected library catalog." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-4">{{ $catalogItems->links() }}</div>
        </section>

        <div x-cloak x-show="productOpen || productEditing || loanOpen || loanEditing" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/40" @click="productOpen = false; productEditing = null; loanOpen = false; loanEditing = null"></div>

        <aside x-cloak x-show="productOpen" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-xl overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="productOpen = false">
            <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur"><div><h2 class="text-lg font-semibold text-slate-950">Add Library Book</h2><p class="mt-1 text-sm text-slate-500">Creates a Bookstore item already enabled for the Church Library.</p></div><button type="button" @click="productOpen = false" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button></div>
            <form method="POST" action="{{ route('bookstore.products.store') }}" class="space-y-4 p-5">@csrf @include('bookstore.partials.product-form', ['product' => null, 'libraryDefaults' => ['is_library_item' => true, 'borrowable' => true, 'format' => 'hardcopy']])<div class="flex justify-end gap-3 border-t border-slate-100 pt-4"><button type="button" @click="productOpen = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button><button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="check" class="size-4"></i>Save Book</button></div></form>
        </aside>

        @foreach($catalogItems as $item)
            <aside x-cloak x-show="productEditing === '{{ $item->opaqueId() }}'" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-xl overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="productEditing = null">
                <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur"><div><h2 class="text-lg font-semibold text-slate-950">Edit Book</h2><p class="mt-1 text-sm text-slate-500">{{ $item->name }}</p></div><button type="button" @click="productEditing = null" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button></div>
                <form method="POST" action="{{ route('bookstore.products.update', $item) }}" class="space-y-4 p-5">@csrf @method('PUT') @include('bookstore.partials.product-form', ['product' => $item])<div class="flex justify-between gap-3 border-t border-slate-100 pt-4"><button form="drawer-delete-library-product-{{ $item->id }}" class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Archive</button><div class="flex gap-3"><button type="button" @click="productEditing = null" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button><button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="check" class="size-4"></i>Save Book</button></div></div></form>
                <form id="drawer-delete-library-product-{{ $item->id }}" method="POST" action="{{ route('bookstore.products.destroy', $item) }}" class="hidden">@csrf @method('DELETE')</form>
            </aside>
        @endforeach

        <aside x-cloak x-show="loanOpen" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-xl overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="loanOpen = false">
            <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur"><div><h2 class="text-lg font-semibold text-slate-950">Checkout / Access</h2><p class="mt-1 text-sm text-slate-500">Borrow, rent, or grant ebook access to a member.</p></div><button type="button" @click="loanOpen = false" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button></div>
            <form method="POST" action="{{ route('bookstore.library.loans.store') }}" class="space-y-4 p-5">@csrf @include('bookstore.partials.library-loan-form', ['loan' => null])<div class="flex justify-end gap-3 border-t border-slate-100 pt-4"><button type="button" @click="loanOpen = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button><button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="check" class="size-4"></i>Submit Record</button></div></form>
        </aside>

        @foreach($loans as $loan)
            <aside x-cloak x-show="loanEditing === '{{ $loan->opaqueId() }}'" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-xl overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="loanEditing = null">
                <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur"><div><h2 class="text-lg font-semibold text-slate-950">Edit Library Record</h2><p class="mt-1 text-sm text-slate-500">{{ $loan->loan_number }}</p></div><button type="button" @click="loanEditing = null" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button></div>
                <form method="POST" action="{{ route('bookstore.library.loans.update', $loan) }}" class="space-y-4 p-5">@csrf @method('PUT') @include('bookstore.partials.library-loan-form', ['loan' => $loan])<div class="flex justify-between gap-3 border-t border-slate-100 pt-4">@if(in_array($loan->status, ['active', 'overdue'], true))<button form="drawer-return-loan-{{ $loan->id }}" class="rounded-lg border border-emerald-200 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">Return</button>@else<span></span>@endif<div class="flex gap-3"><button type="button" @click="loanEditing = null" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Close</button><button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="check" class="size-4"></i>Save</button></div></div></form>
                <form id="drawer-return-loan-{{ $loan->id }}" method="POST" action="{{ route('bookstore.library.loans.destroy', $loan) }}" class="hidden">@csrf @method('DELETE')</form>
            </aside>
        @endforeach
    </div>
</x-app-layout>
