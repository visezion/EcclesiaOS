<x-app-layout title="Book Store" :breadcrumbs="$breadcrumbs">
    <div x-data="{ productOpen: {{ $errors->any() ? 'true' : 'false' }}, productEditing: null, orderOpen: false, orderEditing: null }" class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-xl bg-amber-100 text-amber-600"><i data-lucide="book-open" class="size-7"></i></div>
                <div>
                    <h1 class="text-2xl font-semibold text-slate-950">Book Store</h1>
                    <p class="text-sm text-slate-500">Catalog, inventory, stock levels, sales, refunds, and member orders.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('bookstore.overview') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i data-lucide="layout-dashboard" class="size-4"></i>Overview</a>
                <a href="{{ route('bookstore.library') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-amber-200 bg-white px-4 py-2.5 text-sm font-semibold text-amber-700 hover:bg-amber-50"><i data-lucide="library" class="size-4"></i>Church Library</a>
                <button type="button" @click="productOpen = true" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="plus" class="size-4"></i>Add Product</button>
                <button type="button" @click="orderOpen = true" class="inline-flex items-center justify-center gap-2 rounded-lg border border-violet-200 bg-white px-4 py-2.5 text-sm font-semibold text-violet-700 hover:bg-violet-50"><i data-lucide="shopping-cart" class="size-4"></i>New Order</button>
                <a href="{{ route('bookstore.export', request()->query()) }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i data-lucide="download" class="size-4"></i>Export</a>
            </div>
        </div>

        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card :metric="['label' => 'Products', 'value' => number_format($stats['products']), 'change' => null, 'period' => 'catalog items', 'icon' => 'book-open', 'color' => 'amber', 'route' => 'bookstore.index']" />
            <x-stat-card :metric="['label' => 'Stock On Hand', 'value' => number_format($stats['stock']), 'change' => null, 'period' => $stats['inventory_value'].' value', 'icon' => 'boxes', 'color' => 'purple', 'route' => 'bookstore.index']" />
            <x-stat-card :metric="['label' => 'Low Stock', 'value' => number_format($stats['low_stock']), 'change' => null, 'period' => 'at reorder level', 'icon' => 'triangle-alert', 'color' => 'orange', 'route' => 'bookstore.index']" />
            <x-stat-card :metric="['label' => 'Month Sales', 'value' => $stats['month_sales'], 'change' => null, 'period' => 'bookstore orders', 'icon' => 'receipt', 'color' => 'emerald', 'route' => 'bookstore.index']" />
        </div>

        <div class="grid gap-3 md:grid-cols-4">
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Today</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($orderStats['today']) }}</p><p class="mt-1 text-sm text-slate-500">{{ $orderStats['today_sales'] }}</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">This Week</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($orderStats['week']) }}</p><p class="mt-1 text-sm text-slate-500">{{ $orderStats['week_sales'] }}</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Paid</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($orderStats['paid']) }}</p><p class="mt-1 text-sm text-slate-500">completed orders</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Pending</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($orderStats['pending']) }}</p><p class="mt-1 text-sm text-slate-500">awaiting payment</p></div>
        </div>

        <div class="grid gap-3 md:grid-cols-5">
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Library Catalog</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($libraryStats['catalog']) }}</p><p class="mt-1 text-sm text-slate-500">enabled books</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Borrow / Access</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($libraryStats['active']) }}</p><p class="mt-1 text-sm text-slate-500">active records</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Rentals</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($libraryStats['rentals']) }}</p><p class="mt-1 text-sm text-slate-500">active rentals</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Digital</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($libraryStats['digital']) }}</p><p class="mt-1 text-sm text-slate-500">ebook access</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Overdue</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($libraryStats['overdue']) }}</p><p class="mt-1 text-sm text-slate-500">needs follow-up</p></div>
        </div>

        <form method="GET" action="{{ route('bookstore.index') }}" class="dashboard-card grid gap-3 lg:grid-cols-[1fr_170px_150px_170px_auto_auto]">
            <input name="q" value="{{ request('q') }}" class="h-10 rounded-lg border border-slate-200 px-3 text-sm" placeholder="Search product, SKU, category...">
            <select name="category" class="h-10 rounded-lg border border-slate-200 px-3 text-sm">
                <option value="">Category</option>
                @foreach($categories as $category)<option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>@endforeach
            </select>
            <select name="status" class="h-10 rounded-lg border border-slate-200 px-3 text-sm">
                <option value="">Status</option>
                @foreach($productStatuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ Str::headline($status) }}</option>@endforeach
            </select>
            <select name="campus_id" class="h-10 rounded-lg border border-slate-200 px-3 text-sm">
                <option value="">{{ $terminology['campus_singular'] }}</option>
                @foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected((string) request('campus_id') === (string) $campus->id)>{{ $campus->name }}</option>@endforeach
            </select>
            <button class="h-10 rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white">Apply</button>
            <a href="{{ route('bookstore.index') }}" class="inline-flex h-10 items-center px-3 text-sm font-semibold text-slate-500">Clear</a>
        </form>

        <section class="dashboard-card p-0">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">Product Catalog</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ number_format($products->total()) }} products across {{ number_format($categoryRows ? count($categoryRows) : 0) }} categories</p>
                </div>
                <button type="button" @click="productOpen = true" class="inline-flex items-center gap-2 rounded-lg border border-violet-200 px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50"><i data-lucide="plus" class="size-4"></i>Add Product</button>
            </div>
            <div class="overflow-x-auto">
                <table class="table-compact min-w-[1040px]">
                    <thead><tr><th>Product</th><th>Format</th><th>Category</th><th>{{ $terminology['campus_singular'] }}</th><th>Price</th><th>Stock</th><th>Library</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse($products as $product)
                            <tr>
                                <td><div class="font-semibold text-slate-950">{{ $product->name }}</div><div class="text-xs text-slate-500">{{ $product->author ?: ($product->sku ?: 'No author or SKU') }}</div></td>
                                <td><x-status-badge :status="Str::headline($product->format ?? 'hardcopy')" /></td>
                                <td>{{ $product->category ?: 'Uncategorized' }}</td>
                                <td>{{ $product->campus?->name ?? 'Unassigned' }}</td>
                                <td class="font-semibold">{{ $currency }} {{ number_format((float) $product->price, 2) }}</td>
                                <td class="{{ $product->stock_quantity <= $product->reorder_level ? 'font-semibold text-rose-600' : 'font-semibold text-slate-900' }}">{{ number_format($product->stock_quantity) }}</td>
                                <td>{{ $product->borrowable ? 'Borrow' : '' }}{{ $product->borrowable && $product->rentable ? ' / ' : '' }}{{ $product->rentable ? 'Rent' : '' }}{{ (! $product->borrowable && ! $product->rentable && in_array($product->format, ['ebook', 'bundle'], true)) ? 'Digital' : '' }}{{ (! $product->borrowable && ! $product->rentable && ! in_array($product->format, ['ebook', 'bundle'], true)) ? '-' : '' }}</td>
                                <td><x-status-badge :status="Str::headline($product->status)" /></td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="productEditing = '{{ $product->opaqueId() }}'" title="Edit product" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50"><i data-lucide="pencil" class="size-4"></i></button>
                                        <button form="product-delete-{{ $product->id }}" title="Archive" class="grid size-8 place-items-center rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50"><i data-lucide="archive" class="size-4"></i></button>
                                    </div>
                                    <form id="product-delete-{{ $product->id }}" method="POST" action="{{ route('bookstore.products.destroy', $product) }}" class="hidden">@csrf @method('DELETE')</form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-12"><x-empty-state icon="book-open" title="No products found" message="Add the first bookstore product or adjust the filters." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-4">{{ $products->links() }}</div>
        </section>

        <div class="grid gap-4 xl:grid-cols-[1fr_360px]">
            <section class="dashboard-card p-0">
                <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Order Register</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ number_format($orders->total()) }} sales, walk-ins, payment states, refunds, and cancellations.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('bookstore.orders.export', request()->query()) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i data-lucide="download" class="size-4"></i>Export Orders</a>
                        <button type="button" @click="orderOpen = true" class="inline-flex items-center gap-2 rounded-lg border border-violet-200 px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50"><i data-lucide="shopping-cart" class="size-4"></i>Record Order</button>
                    </div>
                </div>
                <form method="GET" action="{{ route('bookstore.index') }}" class="grid gap-3 border-b border-slate-100 p-4 lg:grid-cols-[1fr_150px_170px_auto_auto]">
                    @foreach(request()->except(['order_q', 'order_status', 'order_campus_id', 'orders_page']) as $key => $value)
                        @if(is_scalar($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <input name="order_q" value="{{ request('order_q') }}" class="h-10 rounded-lg border border-slate-200 px-3 text-sm" placeholder="Search order, member, item...">
                    <select name="order_status" class="h-10 rounded-lg border border-slate-200 px-3 text-sm">
                        <option value="">Status</option>
                        @foreach($orderStatuses as $status)<option value="{{ $status }}" @selected(request('order_status') === $status)>{{ Str::headline($status) }}</option>@endforeach
                    </select>
                    <select name="order_campus_id" class="h-10 rounded-lg border border-slate-200 px-3 text-sm">
                        <option value="">{{ $terminology['campus_singular'] }}</option>
                        @foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected((string) request('order_campus_id') === (string) $campus->id)>{{ $campus->name }}</option>@endforeach
                    </select>
                    <button class="h-10 rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white">Apply</button>
                    <a href="{{ route('bookstore.index', request()->except(['order_q', 'order_status', 'order_campus_id', 'orders_page'])) }}" class="inline-flex h-10 items-center px-3 text-sm font-semibold text-slate-500">Clear</a>
                </form>
                <div class="overflow-x-auto">
                    <table class="table-compact min-w-[980px]">
                        <thead><tr><th>Order</th><th>Date</th><th>Member</th><th>Items</th><th>Status</th><th>Total</th><th class="text-right">Actions</th></tr></thead>
                        <tbody>
                            @forelse($orders as $order)
                                <tr>
                                    <td><div class="font-semibold text-slate-950">{{ $order->order_number }}</div><div class="text-xs text-slate-500">{{ $order->campus?->name ?? 'No campus' }}</div></td>
                                    <td>{{ $order->ordered_at?->format('M d, Y h:i A') }}</td>
                                    <td>{{ $order->member ? $order->member->first_name.' '.$order->member->last_name : 'Walk-in' }}</td>
                                    <td>{{ $order->items->map(fn($item) => $item->product_name.' x'.$item->quantity)->implode(', ') ?: 'No items' }}</td>
                                    <td><x-status-badge :status="Str::headline($order->status)" /></td>
                                    <td class="font-semibold">{{ $order->currency }} {{ number_format((float) $order->total_amount, 2) }}</td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <button type="button" @click="orderEditing = '{{ $order->opaqueId() }}'" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50" title="Edit order"><i data-lucide="pencil" class="size-4"></i></button>
                                            @if(! in_array($order->status, ['cancelled', 'refunded'], true))
                                                <button form="order-cancel-{{ $order->id }}" class="grid size-8 place-items-center rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50" title="Cancel order"><i data-lucide="ban" class="size-4"></i></button>
                                            @endif
                                        </div>
                                        <form id="order-cancel-{{ $order->id }}" method="POST" action="{{ route('bookstore.orders.destroy', $order) }}" class="hidden">@csrf @method('DELETE')</form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-12"><x-empty-state icon="receipt" title="No orders recorded" message="Record bookstore sales from the order drawer." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 p-4">{{ $orders->links() }}</div>
            </section>

            <section class="dashboard-card">
                <h2 class="mb-4 text-base font-semibold text-slate-950">Category Value</h2>
                <div class="space-y-3">
                    @forelse($categoryRows as $row)
                        <div class="grid grid-cols-[1fr_auto] gap-2 text-sm">
                            <span class="truncate text-slate-600">{{ $row['label'] }} <span class="text-xs text-slate-400">({{ $row['products'] }})</span></span>
                            <span class="font-semibold">{{ $currency }} {{ number_format($row['value'], 2) }}</span>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">No category data yet.</div>
                    @endforelse
                </div>
            </section>
        </div>

        <section class="dashboard-card p-0">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="text-base font-semibold text-slate-950">Active Library Circulation</h2><p class="mt-1 text-sm text-slate-500">Borrowed, rented, overdue, and ebook access records connected to Bookstore products.</p></div>
                <a href="{{ route('bookstore.library') }}" class="inline-flex items-center gap-2 rounded-lg border border-amber-200 px-4 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-50"><i data-lucide="library" class="size-4"></i>Open Library</a>
            </div>
            <div class="overflow-x-auto">
                <table class="table-compact min-w-[900px]">
                    <thead><tr><th>Record</th><th>Book</th><th>Member</th><th>Type</th><th>Due</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($activeLibraryLoans as $loan)
                            <tr>
                                <td><div class="font-semibold text-slate-950">{{ $loan->loan_number }}</div><div class="text-xs text-slate-500">{{ $loan->checked_out_at?->format('M d, Y') }}</div></td>
                                <td>{{ $loan->product?->name ?? 'Missing book' }}</td>
                                <td>{{ $loan->member ? $loan->member->first_name.' '.$loan->member->last_name : 'No member' }}</td>
                                <td><x-status-badge :status="Str::headline($loan->loan_type)" /></td>
                                <td>{{ $loan->due_at?->format('M d, Y') ?? 'No due date' }}</td>
                                <td><x-status-badge :status="Str::headline($loan->status)" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10"><x-empty-state icon="library" title="No active library records" message="Open the Church Library to check out, rent, or grant ebook access." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div x-cloak x-show="productOpen || productEditing || orderOpen || orderEditing" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/40" @click="productOpen = false; productEditing = null; orderOpen = false; orderEditing = null"></div>

        <aside x-cloak x-show="productOpen" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-xl overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="productOpen = false">
            <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur"><div><h2 class="text-lg font-semibold text-slate-950">Add Product</h2><p class="mt-1 text-sm text-slate-500">Create a bookstore inventory item with pricing and reorder controls.</p></div><button type="button" @click="productOpen = false" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button></div>
            <form method="POST" action="{{ route('bookstore.products.store') }}" class="space-y-4 p-5">@csrf @include('bookstore.partials.product-form', ['product' => null])<div class="flex justify-end gap-3 border-t border-slate-100 pt-4"><button type="button" @click="productOpen = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button><button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="plus" class="size-4"></i>Add Product</button></div></form>
        </aside>

        @foreach($products as $product)
            <aside x-cloak x-show="productEditing === '{{ $product->opaqueId() }}'" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-xl overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="productEditing = null">
                <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur"><div><h2 class="text-lg font-semibold text-slate-950">Edit Product</h2><p class="mt-1 text-sm text-slate-500">{{ $product->name }}</p></div><button type="button" @click="productEditing = null" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button></div>
                <form method="POST" action="{{ route('bookstore.products.update', $product) }}" class="space-y-4 p-5">@csrf @method('PUT') @include('bookstore.partials.product-form', ['product' => $product])<div class="flex justify-between gap-3 border-t border-slate-100 pt-4"><button form="drawer-delete-product-{{ $product->id }}" class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Archive</button><div class="flex gap-3"><button type="button" @click="productEditing = null" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button><button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="check" class="size-4"></i>Save Changes</button></div></div></form>
                <form id="drawer-delete-product-{{ $product->id }}" method="POST" action="{{ route('bookstore.products.destroy', $product) }}" class="hidden">@csrf @method('DELETE')</form>
            </aside>
        @endforeach

        <aside x-cloak x-show="orderOpen" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-xl overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="orderOpen = false">
            <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur"><div><h2 class="text-lg font-semibold text-slate-950">Record Order</h2><p class="mt-1 text-sm text-slate-500">Record a sale and reduce product inventory.</p></div><button type="button" @click="orderOpen = false" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button></div>
            <form method="POST" action="{{ route('bookstore.orders.store') }}" class="space-y-4 p-5">@csrf @include('bookstore.partials.order-form', ['order' => null])<div class="flex justify-end gap-3 border-t border-slate-100 pt-4"><button type="button" @click="orderOpen = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button><button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="receipt" class="size-4"></i>Record Order</button></div></form>
        </aside>

        @foreach($orders as $order)
            <aside x-cloak x-show="orderEditing === '{{ $order->opaqueId() }}'" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-xl overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="orderEditing = null">
                <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur"><div><h2 class="text-lg font-semibold text-slate-950">Edit Order</h2><p class="mt-1 text-sm text-slate-500">{{ $order->order_number }} &middot; {{ $order->currency }} {{ number_format((float) $order->total_amount, 2) }}</p></div><button type="button" @click="orderEditing = null" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button></div>
                <form method="POST" action="{{ route('bookstore.orders.update', $order) }}" class="space-y-4 p-5">@csrf @method('PUT') @include('bookstore.partials.order-form', ['order' => $order])<div class="flex justify-between gap-3 border-t border-slate-100 pt-4">@if(! in_array($order->status, ['cancelled', 'refunded'], true))<button form="drawer-cancel-order-{{ $order->id }}" class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Cancel Order</button>@else<span></span>@endif<div class="flex gap-3"><button type="button" @click="orderEditing = null" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Close</button><button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="check" class="size-4"></i>Save Order</button></div></div></form>
                <form id="drawer-cancel-order-{{ $order->id }}" method="POST" action="{{ route('bookstore.orders.destroy', $order) }}" class="hidden">@csrf @method('DELETE')</form>
            </aside>
        @endforeach
    </div>
</x-app-layout>
