<x-app-layout title="Giving & Finance" :breadcrumbs="$breadcrumbs">
    @php($financeTitlePrefix = $financeCapabilities['restricted_mode'] ? 'Your ' : '')
    <div x-data="{ donationOpen: {{ $errors->any() ? 'true' : 'false' }}, donationEditing: null, transactionOpen: false, transactionEditing: null, fundOpen: false, fundEditing: null }" class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-xl bg-emerald-100 text-emerald-600"><i data-lucide="badge-dollar-sign" class="size-7"></i></div>
                <div>
                    <h1 class="text-2xl font-semibold text-slate-950">Giving & Finance</h1>
                    <p class="text-sm text-slate-500">{{ $terminology['campus_singular'] }} giving, {{ Str::lower($terminology['ministry_singular']) }} donations, income, expenses, funds, and export controls.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($financeCapabilities['can_view_sensitive_finance'])
                    <a href="{{ route('finance.overview') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i data-lucide="layout-dashboard" class="size-4"></i>Overview</a>
                @endif
                @if($financeCapabilities['can_record_donations'])
                    <button type="button" @click="donationOpen = true" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="plus" class="size-4"></i>Record Donation</button>
                @endif
                @if($financeCapabilities['can_record_transactions'])
                    <button type="button" @click="transactionOpen = true" class="inline-flex items-center justify-center gap-2 rounded-lg border border-emerald-200 bg-white px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50"><i data-lucide="receipt-text" class="size-4"></i>Income / Expense</button>
                @endif
                @if($financeCapabilities['can_manage_funds'])
                    <button type="button" @click="fundOpen = true" class="inline-flex items-center justify-center gap-2 rounded-lg border border-violet-200 bg-white px-4 py-2.5 text-sm font-semibold text-violet-700 hover:bg-violet-50"><i data-lucide="folder-plus" class="size-4"></i>Fund</button>
                @endif
                @if($financeCapabilities['can_export'])
                    <a href="{{ route('finance.export', request()->query()) }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i data-lucide="download" class="size-4"></i>Export</a>
                @endif
            </div>
        </div>

        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card :metric="['label' => $financeTitlePrefix.'Giving This Month', 'value' => $stats['month'], 'change' => null, 'period' => 'received giving', 'icon' => 'calendar-days', 'color' => 'emerald', 'route' => 'finance.index']" />
            <x-stat-card :metric="['label' => $financeTitlePrefix.'Giving This Year', 'value' => $stats['year'], 'change' => null, 'period' => 'year to date', 'icon' => 'chart-column', 'color' => 'purple', 'route' => 'finance.index']" />
            <x-stat-card :metric="['label' => $financeTitlePrefix.'Donation Records', 'value' => number_format($stats['count']), 'change' => null, 'period' => 'ledger records', 'icon' => 'receipt', 'color' => 'orange', 'route' => 'finance.index']" />
            <x-stat-card :metric="['label' => $financeTitlePrefix.'Average Gift', 'value' => $stats['average'], 'change' => null, 'period' => 'per donation', 'icon' => 'wallet', 'color' => 'teal', 'route' => 'finance.index']" />
        </div>

        <div class="grid gap-3 md:grid-cols-4">
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Today</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ $periodStats['today'] }}</p><p class="mt-1 text-sm text-slate-500">received giving</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">This Week</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ $periodStats['week'] }}</p><p class="mt-1 text-sm text-slate-500">weekly giving</p></div>
            @if($financeCapabilities['can_view_transactions'])
                <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $financeTitlePrefix }}Operating Income</p><p class="mt-2 text-2xl font-semibold text-emerald-700">{{ $transactionStats['income'] }}</p><p class="mt-1 text-sm text-slate-500">posted this month</p></div>
                <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $financeTitlePrefix }}Operating Expenses</p><p class="mt-2 text-2xl font-semibold text-rose-700">{{ $transactionStats['expenses'] }}</p><p class="mt-1 text-sm text-slate-500">posted this month</p></div>
            @endif
        </div>

        <div class="grid gap-3 md:grid-cols-{{ $financeCapabilities['can_view_transactions'] ? '4' : '2' }}">
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Digital Giving</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ $periodStats['online'] }}</p><p class="mt-1 text-sm text-slate-500">card, mobile, online</p></div>
            <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Anonymous</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($periodStats['anonymous']) }}</p><p class="mt-1 text-sm text-slate-500">unlinked records</p></div>
            @if($financeCapabilities['can_view_transactions'])
                <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $financeTitlePrefix }}Net Operating</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ $transactionStats['net'] }}</p><p class="mt-1 text-sm text-slate-500">income minus expenses</p></div>
                <div class="dashboard-card"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $financeTitlePrefix }}Pending Items</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ number_format($transactionStats['pending']) }}</p><p class="mt-1 text-sm text-slate-500">awaiting posting</p></div>
            @endif
        </div>

        <form method="GET" action="{{ route('finance.index') }}" class="dashboard-card grid gap-3 md:grid-cols-2 xl:grid-cols-7">
            <input name="q" value="{{ request('q') }}" class="h-10 rounded-lg border border-slate-200 px-3 text-sm" placeholder="Search giving...">
            <select name="fund_id" class="h-10 rounded-lg border border-slate-200 px-3 text-sm">
                <option value="">All Funds</option>
                @foreach($funds as $fund)<option value="{{ $fund->id }}" @selected((string) request('fund_id') === (string) $fund->id)>{{ $fund->name }}</option>@endforeach
            </select>
            <select name="method" class="h-10 rounded-lg border border-slate-200 px-3 text-sm">
                <option value="">Method</option>
                @foreach($methods as $method)<option value="{{ $method }}" @selected(request('method') === $method)>{{ Str::headline($method) }}</option>@endforeach
            </select>
            <select name="campus_id" class="h-10 rounded-lg border border-slate-200 px-3 text-sm">
                <option value="">{{ $terminology['campus_singular'] }}</option>
                @foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected((string) request('campus_id') === (string) $campus->id)>{{ $campus->name }}</option>@endforeach
            </select>
            <select name="ministry_id" class="h-10 rounded-lg border border-slate-200 px-3 text-sm">
                <option value="">{{ $terminology['ministry_singular'] }}</option>
                @foreach($ministries as $ministry)<option value="{{ $ministry->id }}" @selected((string) request('ministry_id') === (string) $ministry->id)>{{ $ministry->name }}</option>@endforeach
            </select>
            <button class="h-10 rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white">Apply</button>
            <a href="{{ route('finance.index') }}" class="inline-flex h-10 items-center px-3 text-sm font-semibold text-slate-500">Clear</a>
        </form>

        @if($financeCapabilities['can_view_sensitive_finance'] || $financeCapabilities['can_view_transactions'])
        <div class="grid gap-4 xl:grid-cols-[1fr_390px]">
            <section class="dashboard-card p-0">
                <div class="border-b border-slate-100 p-4">
                    <h2 class="text-base font-semibold text-slate-950">{{ $terminology['campus_singular'] }} Income & Expenses</h2>
                    <p class="mt-1 text-sm text-slate-500">Current month giving plus operating income, less posted expenses.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="table-compact min-w-[760px]">
                        <thead><tr><th>{{ $terminology['campus_singular'] }}</th><th>Donations</th><th>Income</th><th>Expenses</th><th>Net</th><th>Items</th></tr></thead>
                        <tbody>
                            @forelse($campusFinanceRows as $row)
                                <tr>
                                    <td class="font-semibold text-slate-950">{{ $row['campus'] }}</td>
                                    <td>{{ $row['donations'] }}</td>
                                    <td class="text-emerald-700">{{ $row['income'] }}</td>
                                    <td class="text-rose-700">{{ $row['expenses'] }}</td>
                                    <td class="font-semibold">{{ $row['net'] }}</td>
                                    <td>{{ number_format($row['records']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-10"><x-empty-state icon="building-2" title="No campus finance data" message="Add campuses and finance activity to see campus totals." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="dashboard-card">
                <h2 class="mb-4 text-base font-semibold text-slate-950">{{ $terminology['ministry_singular'] }} Giving</h2>
                <div class="space-y-3">
                    @forelse($ministryGivingRows as $row)
                        <div class="rounded-lg border border-slate-100 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-950">{{ $row['ministry'] }}</p>
                                    <p class="text-xs text-slate-500">{{ $row['campus'] }} - {{ $row['frequency'] }}</p>
                                </div>
                                <p class="font-semibold text-emerald-700">{{ $row['total'] }}</p>
                            </div>
                            <p class="mt-2 text-xs text-slate-500">{{ number_format($row['records']) }} donation records</p>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">No ministry-tagged giving this month.</div>
                    @endforelse
                </div>
            </section>
        </div>
        @else
            <section class="dashboard-card">
                <h2 class="text-base font-semibold text-slate-950">{{ $terminology['ministry_singular'] }} Contributions</h2>
                <p class="mt-1 text-sm text-slate-500">This view only shows contribution records you created. Full campus finance, funds, exports, income, and expense information is hidden by permission.</p>
            </section>
        @endif

        <section class="dashboard-card p-0">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">Donation Ledger</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ number_format($donations->total()) }} giving records across {{ number_format($funds->count()) }} funds</p>
                </div>
                @if($financeCapabilities['can_record_donations'])
                    <button type="button" @click="donationOpen = true" class="inline-flex items-center gap-2 rounded-lg border border-violet-200 px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50"><i data-lucide="plus" class="size-4"></i>Record Donation</button>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="table-compact min-w-[1180px]">
                    <thead><tr><th>Reference</th><th>Date</th><th>Member</th><th>Fund</th><th>{{ $terminology['ministry_singular'] }}</th><th>{{ $terminology['campus_singular'] }}</th><th>Frequency</th><th>Method</th><th>Amount</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse($donations as $donation)
                            <tr>
                                <td class="font-semibold text-slate-950">{{ $donation->reference }}</td>
                                <td>{{ $donation->received_at?->format('M d, Y h:i A') }}</td>
                                <td>{{ $donation->member ? $donation->member->first_name.' '.$donation->member->last_name : 'Anonymous' }}</td>
                                <td>{{ $donation->fund?->name ?? 'Unassigned' }}</td>
                                <td>{{ $donation->ministry?->name ?? 'No ministry' }}</td>
                                <td>{{ $donation->campus?->name ?? 'Unassigned' }}</td>
                                <td>{{ Str::headline($donation->giving_frequency ?: 'one_time') }}</td>
                                <td><x-status-badge :status="Str::headline($donation->method ?: 'Unknown')" /></td>
                                <td class="font-semibold">{{ $donation->currency }} {{ number_format((float) $donation->amount, 2) }}</td>
                                <td class="text-right">
                                    @php($canEditDonation = $financeCapabilities['can_manage_finance'] || ($financeCapabilities['can_record_donations'] && (int) $donation->created_by_user_id === (int) auth()->id()))
                                    @if($canEditDonation)
                                        <div class="flex justify-end gap-2">
                                            <button type="button" @click="donationEditing = '{{ $donation->opaqueId() }}'" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50" title="Edit donation"><i data-lucide="pencil" class="size-4"></i></button>
                                            <button form="donation-delete-{{ $donation->id }}" class="grid size-8 place-items-center rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50" title="Archive"><i data-lucide="archive" class="size-4"></i></button>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400">Read only</span>
                                    @endif
                                    <form id="donation-delete-{{ $donation->id }}" method="POST" action="{{ route('finance.donations.destroy', $donation) }}" class="hidden">@csrf @method('DELETE')</form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="px-4 py-12"><x-empty-state icon="badge-dollar-sign" title="No donations found" message="Record the first donation or adjust the filters." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-4">{{ $donations->links() }}</div>
        </section>

        @if($financeCapabilities['can_view_transactions'])
        <section class="dashboard-card p-0">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">Income & Expense Ledger</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $terminology['campus_singular'] }} and {{ Str::lower($terminology['ministry_singular']) }} operating records for simple finance tracking.</p>
                </div>
                <form method="GET" action="{{ route('finance.index') }}" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-[190px_140px_140px_auto]">
                    <input type="hidden" name="q" value="{{ request('q') }}">
                    <input name="finance_q" value="{{ request('finance_q') }}" class="h-10 rounded-lg border border-slate-200 px-3 text-sm" placeholder="Search finance...">
                    <select name="finance_type" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Type</option>@foreach($transactionTypes as $type)<option value="{{ $type }}" @selected(request('finance_type') === $type)>{{ Str::headline($type) }}</option>@endforeach</select>
                    <select name="finance_status" class="h-10 rounded-lg border border-slate-200 px-3 text-sm"><option value="">Status</option>@foreach($transactionStatuses as $status)<option value="{{ $status }}" @selected(request('finance_status') === $status)>{{ Str::headline($status) }}</option>@endforeach</select>
                    <button class="h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white">Filter</button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="table-compact min-w-[1120px]">
                    <thead><tr><th>Reference</th><th>Date</th><th>Type</th><th>Category</th><th>{{ $terminology['campus_singular'] }}</th><th>{{ $terminology['ministry_singular'] }}</th><th>Source / Vendor</th><th>Status</th><th>Amount</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse($transactions as $transaction)
                            <tr>
                                <td class="font-semibold text-slate-950">{{ $transaction->reference }}</td>
                                <td>{{ $transaction->occurred_at?->format('M d, Y h:i A') }}</td>
                                <td><x-status-badge :status="Str::headline($transaction->type)" /></td>
                                <td>{{ Str::headline($transaction->category) }}</td>
                                <td>{{ $transaction->campus?->name ?? 'Unassigned' }}</td>
                                <td>{{ $transaction->ministry?->name ?? 'No ministry' }}</td>
                                <td>{{ $transaction->vendor_or_source ?: 'Not specified' }}</td>
                                <td><x-status-badge :status="Str::headline($transaction->status)" /></td>
                                <td class="font-semibold {{ $transaction->type === 'expense' ? 'text-rose-700' : 'text-emerald-700' }}">{{ $transaction->type === 'expense' ? '-' : '+' }}{{ $transaction->currency }} {{ number_format((float) $transaction->amount, 2) }}</td>
                                <td class="text-right">
                                    @php($canEditTransaction = $financeCapabilities['can_manage_finance'] || ($financeCapabilities['can_record_transactions'] && (int) $transaction->created_by_user_id === (int) auth()->id()))
                                    @if($canEditTransaction)
                                        <div class="flex justify-end gap-2">
                                            <button type="button" @click="transactionEditing = '{{ $transaction->opaqueId() }}'" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50" title="Edit transaction"><i data-lucide="pencil" class="size-4"></i></button>
                                            <button form="transaction-delete-{{ $transaction->id }}" class="grid size-8 place-items-center rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50" title="Archive"><i data-lucide="archive" class="size-4"></i></button>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400">Read only</span>
                                    @endif
                                    <form id="transaction-delete-{{ $transaction->id }}" method="POST" action="{{ route('finance.transactions.destroy', $transaction) }}" class="hidden">@csrf @method('DELETE')</form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="px-4 py-12"><x-empty-state icon="receipt-text" title="No income or expenses found" message="Record campus income or expenses to start tracking operating activity." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-4">{{ $transactions->links() }}</div>
        </section>
        @endif

        @if($financeCapabilities['can_view_sensitive_finance'])
        <div class="grid gap-4 xl:grid-cols-[1fr_360px]">
            <section class="dashboard-card p-0">
                <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div><h2 class="text-base font-semibold text-slate-950">Funds</h2><p class="mt-1 text-sm text-slate-500">Giving designations, active status, and usage count.</p></div>
                    @if($financeCapabilities['can_manage_funds'])
                        <button type="button" @click="fundOpen = true" class="inline-flex items-center gap-2 rounded-lg border border-violet-200 px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50"><i data-lucide="folder-plus" class="size-4"></i>Add Fund</button>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="table-compact min-w-[760px]">
                        <thead><tr><th>Fund</th><th>Code</th><th>Status</th><th>Donations</th><th class="text-right">Actions</th></tr></thead>
                        <tbody>
                            @forelse($funds as $fund)
                                <tr>
                                    <td><div class="font-semibold text-slate-950">{{ $fund->name }}</div><div class="text-xs text-slate-500">{{ $fund->description ?: 'No description' }}</div></td>
                                    <td>{{ $fund->code ?: 'No code' }}</td>
                                    <td><x-status-badge :status="$fund->is_active ? 'Active' : 'Inactive'" /></td>
                                    <td>{{ number_format($fund->donations_count) }}</td>
                                    <td class="text-right">
                                        @if($financeCapabilities['can_manage_funds'])
                                            <div class="flex justify-end gap-2">
                                                <button type="button" @click="fundEditing = '{{ $fund->opaqueId() }}'" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50" title="Edit fund"><i data-lucide="pencil" class="size-4"></i></button>
                                                @if($fund->is_active)
                                                    <button form="fund-deactivate-{{ $fund->id }}" class="grid size-8 place-items-center rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50" title="Deactivate"><i data-lucide="ban" class="size-4"></i></button>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-xs text-slate-400">Read only</span>
                                        @endif
                                        <form id="fund-deactivate-{{ $fund->id }}" method="POST" action="{{ route('finance.funds.destroy', $fund) }}" class="hidden">@csrf @method('DELETE')</form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-12"><x-empty-state icon="folder-plus" title="No funds found" message="Create funds for tithes, offerings, missions, building campaigns, or other giving categories." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <aside class="space-y-4">
                <section class="dashboard-card"><h2 class="mb-4 text-base font-semibold text-slate-950">Giving by Fund</h2><div class="space-y-3">@forelse($fundRows as $row)<div class="flex justify-between gap-3 text-sm"><span class="truncate text-slate-600">{{ $row['label'] }}</span><span class="font-semibold">{{ $currency }} {{ number_format($row['value'], 2) }}</span></div>@empty<div class="text-sm text-slate-500">No fund giving yet.</div>@endforelse</div></section>
                <section class="dashboard-card"><h2 class="mb-4 text-base font-semibold text-slate-950">Payment Methods</h2><div class="space-y-3">@forelse($methodRows as $row)<div class="flex justify-between text-sm"><span class="text-slate-600">{{ $row['label'] }}</span><span class="font-semibold">{{ number_format($row['value']) }}</span></div>@empty<div class="text-sm text-slate-500">No method data yet.</div>@endforelse</div></section>
            </aside>
        </div>
        @endif

        <div x-cloak x-show="donationOpen || donationEditing || transactionOpen || transactionEditing || fundOpen || fundEditing" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/40" @click="donationOpen = false; donationEditing = null; transactionOpen = false; transactionEditing = null; fundOpen = false; fundEditing = null"></div>

        @if($financeCapabilities['can_record_donations'])
        <aside x-cloak x-show="donationOpen" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-xl overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="donationOpen = false">
            <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur"><div><h2 class="text-lg font-semibold text-slate-950">Record Donation</h2><p class="mt-1 text-sm text-slate-500">Create a donation tied to a member, fund, campus, ministry, and cadence.</p></div><button type="button" @click="donationOpen = false" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button></div>
            <form method="POST" action="{{ route('finance.donations.store') }}" class="space-y-4 p-5">@csrf @include('finance.partials.donation-form', ['donation' => null])<div class="flex justify-end gap-3 border-t border-slate-100 pt-4"><button type="button" @click="donationOpen = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button><button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="plus" class="size-4"></i>Record Donation</button></div></form>
        </aside>
        @endif

        @if($financeCapabilities['can_record_transactions'])
        <aside x-cloak x-show="transactionOpen" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-xl overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="transactionOpen = false">
            <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur"><div><h2 class="text-lg font-semibold text-slate-950">Record Income / Expense</h2><p class="mt-1 text-sm text-slate-500">Track campus and ministry operating activity.</p></div><button type="button" @click="transactionOpen = false" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button></div>
            <form method="POST" action="{{ route('finance.transactions.store') }}" class="space-y-4 p-5">@csrf @include('finance.partials.transaction-form', ['transaction' => null])<div class="flex justify-end gap-3 border-t border-slate-100 pt-4"><button type="button" @click="transactionOpen = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button><button class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"><i data-lucide="check" class="size-4"></i>Save Record</button></div></form>
        </aside>
        @endif

        @foreach($donations as $donation)
            <aside x-cloak x-show="donationEditing === '{{ $donation->opaqueId() }}'" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-xl overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="donationEditing = null">
                <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur"><div><h2 class="text-lg font-semibold text-slate-950">Edit Donation</h2><p class="mt-1 text-sm text-slate-500">{{ $donation->reference }} - {{ $donation->currency }} {{ number_format((float) $donation->amount, 2) }}</p></div><button type="button" @click="donationEditing = null" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button></div>
                <form method="POST" action="{{ route('finance.donations.update', $donation) }}" class="space-y-4 p-5">@csrf @method('PUT') @include('finance.partials.donation-form', ['donation' => $donation])<div class="flex justify-between gap-3 border-t border-slate-100 pt-4"><button form="drawer-delete-donation-{{ $donation->id }}" class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Archive</button><div class="flex gap-3"><button type="button" @click="donationEditing = null" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button><button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="check" class="size-4"></i>Save Changes</button></div></div></form>
                <form id="drawer-delete-donation-{{ $donation->id }}" method="POST" action="{{ route('finance.donations.destroy', $donation) }}" class="hidden">@csrf @method('DELETE')</form>
            </aside>
        @endforeach

        @foreach($transactions as $transaction)
            <aside x-cloak x-show="transactionEditing === '{{ $transaction->opaqueId() }}'" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-xl overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="transactionEditing = null">
                <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur"><div><h2 class="text-lg font-semibold text-slate-950">Edit Income / Expense</h2><p class="mt-1 text-sm text-slate-500">{{ $transaction->reference }} - {{ Str::headline($transaction->type) }}</p></div><button type="button" @click="transactionEditing = null" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button></div>
                <form method="POST" action="{{ route('finance.transactions.update', $transaction) }}" class="space-y-4 p-5">@csrf @method('PUT') @include('finance.partials.transaction-form', ['transaction' => $transaction])<div class="flex justify-between gap-3 border-t border-slate-100 pt-4"><button form="drawer-delete-transaction-{{ $transaction->id }}" class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Archive</button><div class="flex gap-3"><button type="button" @click="transactionEditing = null" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button><button class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"><i data-lucide="check" class="size-4"></i>Save Changes</button></div></div></form>
                <form id="drawer-delete-transaction-{{ $transaction->id }}" method="POST" action="{{ route('finance.transactions.destroy', $transaction) }}" class="hidden">@csrf @method('DELETE')</form>
            </aside>
        @endforeach

        @if($financeCapabilities['can_manage_funds'])
        <aside x-cloak x-show="fundOpen" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-md overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="fundOpen = false">
            <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur"><div><h2 class="text-lg font-semibold text-slate-950">Add Fund</h2><p class="mt-1 text-sm text-slate-500">Create a giving designation.</p></div><button type="button" @click="fundOpen = false" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button></div>
            <form method="POST" action="{{ route('finance.funds.store') }}" class="space-y-4 p-5">@csrf @include('finance.partials.fund-form', ['fund' => null])<div class="flex justify-end gap-3 border-t border-slate-100 pt-4"><button type="button" @click="fundOpen = false" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button><button class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700">Save Fund</button></div></form>
        </aside>

        @foreach($funds as $fund)
            <aside x-cloak x-show="fundEditing === '{{ $fund->opaqueId() }}'" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-md overflow-y-auto bg-white shadow-2xl" @keydown.escape.window="fundEditing = null">
                <div class="sticky top-0 z-10 flex items-start justify-between border-b border-slate-100 bg-white/95 p-5 backdrop-blur"><div><h2 class="text-lg font-semibold text-slate-950">Edit Fund</h2><p class="mt-1 text-sm text-slate-500">{{ $fund->name }}</p></div><button type="button" @click="fundEditing = null" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"><i data-lucide="x" class="size-4"></i></button></div>
                <form method="POST" action="{{ route('finance.funds.update', $fund) }}" class="space-y-4 p-5">@csrf @method('PUT') @include('finance.partials.fund-form', ['fund' => $fund])<div class="flex justify-between gap-3 border-t border-slate-100 pt-4">@if($fund->is_active)<button form="drawer-deactivate-fund-{{ $fund->id }}" class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Deactivate</button>@else<span></span>@endif<div class="flex gap-3"><button type="button" @click="fundEditing = null" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button><button class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700">Save Fund</button></div></div></form>
                <form id="drawer-deactivate-fund-{{ $fund->id }}" method="POST" action="{{ route('finance.funds.destroy', $fund) }}" class="hidden">@csrf @method('DELETE')</form>
            </aside>
        @endforeach
        @endif
    </div>
</x-app-layout>
