<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOperationalRecords;
use App\Models\Donation;
use App\Models\FinanceTransaction;
use App\Models\Fund;
use App\Models\Member;
use App\Models\Ministry;
use App\Services\ActivityLogger;
use App\Support\Csv;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FinanceController extends Controller
{
    use ScopesOperationalRecords;

    private const ACCESS_PERMISSIONS = ['manage finance', 'view finance', 'record finance entries', 'view ministry finance', 'record ministry contributions'];

    private const METHODS = ['cash', 'card', 'bank', 'check', 'mobile', 'online'];

    private const GIVING_FREQUENCIES = ['one_time', 'weekly', 'monthly', 'quarterly', 'annual', 'custom'];

    private const GIVING_SOURCES = ['member', 'ministry', 'department', 'anonymous'];

    private const TRANSACTION_TYPES = ['income', 'expense'];

    private const TRANSACTION_STATUSES = ['posted', 'pending', 'void'];

    private const TRANSACTION_CATEGORIES = ['general', 'tithe', 'offering', 'missions', 'outreach', 'events', 'utilities', 'salary', 'maintenance', 'rent', 'supplies', 'transport', 'media', 'benevolence', 'other'];

    public function index(Request $request): View
    {
        $this->authorizeFinanceAccess($request);
        $capabilities = $this->financeCapabilities($request);

        $query = $this->donationVisibilityQuery($request)->with(['member', 'fund', 'campus', 'ministry']);
        $this->applyFilters($query, $request);

        $transactionQuery = $this->transactionVisibilityQuery($request)->with(['campus', 'ministry', 'fund', 'createdBy']);
        $this->applyTransactionFilters($transactionQuery, $request);

        $donations = $query->latest('received_at')->paginate(12)->withQueryString();
        $transactions = $transactionQuery->latest('occurred_at')->paginate(10, ['*'], 'transactions_page')->withQueryString();
        $base = $this->donationVisibilityQuery($request);
        $currency = $this->currency($request);
        $funds = $capabilities['can_view_sensitive_finance']
            ? $this->fundQuery($request)->withCount('donations')->orderBy('name')->get()
            : $this->fundQuery($request)->orderBy('name')->get();

        return view('finance.index', [
            'donations' => $donations,
            'transactions' => $transactions,
            'funds' => $funds,
            'members' => $this->visibleMembers($request)->limit(300)->get(),
            'ministries' => $this->visibleMinistries($request)->with('campus')->get(),
            'campuses' => $this->visibleCampuses($request)->get(),
            'methods' => self::METHODS,
            'givingFrequencies' => self::GIVING_FREQUENCIES,
            'givingSources' => self::GIVING_SOURCES,
            'transactionTypes' => self::TRANSACTION_TYPES,
            'transactionStatuses' => self::TRANSACTION_STATUSES,
            'transactionCategories' => self::TRANSACTION_CATEGORIES,
            'currency' => $currency,
            'stats' => [
                'month' => Number::currency((float) (clone $base)->whereBetween('received_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'), $currency),
                'year' => Number::currency((float) (clone $base)->whereYear('received_at', now()->year)->sum('amount'), $currency),
                'count' => (clone $base)->count(),
                'average' => Number::currency((float) (clone $base)->avg('amount'), $currency),
            ],
            'periodStats' => $this->periodStats($request),
            'transactionStats' => $this->transactionStats($request),
            'campusFinanceRows' => $this->campusFinanceRows($request),
            'ministryGivingRows' => $this->ministryGivingRows($request),
            'fundRows' => $this->fundRows($request),
            'methodRows' => $this->methodRows($request),
            'financeCapabilities' => $capabilities,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Giving & Finance', 'url' => null],
            ],
        ]);
    }

    public function overview(Request $request): View
    {
        $this->authorizeAnyFinancePermission($request, ['manage finance', 'view finance']);

        $base = $this->donationVisibilityQuery($request);
        $currency = $this->currency($request);

        return view('finance.overview', [
            'currency' => $currency,
            'stats' => [
                'month' => Number::currency((float) (clone $base)->whereBetween('received_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'), $currency),
                'year' => Number::currency((float) (clone $base)->whereYear('received_at', now()->year)->sum('amount'), $currency),
                'count' => (clone $base)->count(),
                'average' => Number::currency((float) (clone $base)->avg('amount'), $currency),
            ],
            'fundRows' => $this->fundRows($request),
            'methodRows' => $this->methodRows($request),
            'recentDonations' => $this->donationVisibilityQuery($request)->with(['member', 'fund', 'campus', 'ministry'])->latest('received_at')->limit(8)->get(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Giving & Finance', 'url' => route('finance.index')],
                ['label' => 'Overview', 'url' => null],
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeAnyFinancePermission($request, ['manage finance', 'record finance entries', 'record ministry contributions']);

        return view('finance.create', $this->donationFormData($request) + [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Giving & Finance', 'url' => route('finance.index')],
                ['label' => 'Record Donation', 'url' => null],
            ],
        ]);
    }

    public function edit(Request $request, Donation $donation): View
    {
        $this->authorizeDonationMutation($request, $donation);

        return view('finance.edit', $this->donationFormData($request, $donation) + [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Giving & Finance', 'url' => route('finance.index')],
                ['label' => $donation->reference, 'url' => null],
            ],
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeAnyFinancePermission($request, ['manage finance', 'record finance entries', 'record ministry contributions']);
        $donation = Donation::query()->create($this->validatedDonation($request));

        $activityLogger->log('Finance', 'donation_recorded', 'Donation '.$donation->reference.' was recorded.', $donation, ['resource' => 'Donation', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Donation recorded.');
    }

    public function update(Request $request, Donation $donation, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeDonationMutation($request, $donation);
        $donation->update($this->validatedDonation($request, $donation));

        $activityLogger->log('Finance', 'donation_updated', 'Donation '.$donation->reference.' was updated.', $donation, ['resource' => 'Donation', 'risk' => 'medium', 'status' => 'success'], $request);

        return back()->with('status', 'Donation updated.');
    }

    public function destroy(Request $request, Donation $donation, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeDonationMutation($request, $donation);
        $reference = $donation->reference;
        $donation->delete();

        $activityLogger->log('Finance', 'donation_archived', 'Donation '.$reference.' was archived.', null, ['resource' => 'Donation', 'risk' => 'medium', 'status' => 'success'], $request);

        return back()->with('status', 'Donation archived.');
    }

    public function storeTransaction(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeAnyFinancePermission($request, ['manage finance', 'record finance entries']);
        $transaction = FinanceTransaction::query()->create($this->validatedTransaction($request));

        $activityLogger->log('Finance', 'transaction_recorded', 'Finance transaction '.$transaction->reference.' was recorded.', $transaction, ['resource' => 'FinanceTransaction', 'risk' => 'medium', 'status' => 'success'], $request);

        return back()->with('status', 'Income or expense recorded.');
    }

    public function updateTransaction(Request $request, FinanceTransaction $transaction, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeTransactionMutation($request, $transaction);
        $transaction->update($this->validatedTransaction($request, $transaction));

        $activityLogger->log('Finance', 'transaction_updated', 'Finance transaction '.$transaction->reference.' was updated.', $transaction, ['resource' => 'FinanceTransaction', 'risk' => 'medium', 'status' => 'success'], $request);

        return back()->with('status', 'Income or expense updated.');
    }

    public function destroyTransaction(Request $request, FinanceTransaction $transaction, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeTransactionMutation($request, $transaction);
        $reference = $transaction->reference;
        $transaction->delete();

        $activityLogger->log('Finance', 'transaction_archived', 'Finance transaction '.$reference.' was archived.', null, ['resource' => 'FinanceTransaction', 'risk' => 'medium', 'status' => 'success'], $request);

        return back()->with('status', 'Income or expense archived.');
    }

    public function storeFund(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'manage finance');
        $validated = $this->validatedFund($request);

        Fund::query()->updateOrCreate(
            ['church_id' => $this->defaultChurchId($request), 'name' => $validated['name']],
            [
                'code' => $validated['code'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ],
        );

        return back()->with('status', 'Fund saved.');
    }

    public function updateFund(Request $request, Fund $fund): RedirectResponse
    {
        $this->authorizePermission($request, 'manage finance');
        $this->authorizeFundRecord($request, $fund);

        $fund->update($this->validatedFund($request, $fund));

        return back()->with('status', 'Fund updated.');
    }

    public function destroyFund(Request $request, Fund $fund): RedirectResponse
    {
        $this->authorizePermission($request, 'manage finance');
        $this->authorizeFundRecord($request, $fund);

        $fund->update(['is_active' => false]);

        return back()->with('status', 'Fund deactivated.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeAnyFinancePermission($request, ['manage finance']);

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            Csv::write($handle, ['Reference', 'Date', 'Member', 'Fund', 'Campus', 'Ministry', 'Method', 'Source', 'Frequency', 'Amount', 'Currency', 'Notes']);
            $query = $this->donationVisibilityQuery($request)->with(['member', 'fund', 'campus', 'ministry'])->latest('received_at');
            $this->applyFilters($query, $request);
            $query->lazy(100)->each(fn (Donation $donation) => Csv::write($handle, [
                $donation->reference,
                $donation->received_at?->format('Y-m-d H:i'),
                $donation->member ? $donation->member->first_name.' '.$donation->member->last_name : '',
                $donation->fund?->name,
                $donation->campus?->name,
                $donation->ministry?->name,
                $donation->method,
                $donation->giving_source,
                $donation->giving_frequency,
                $donation->amount,
                $donation->currency,
                $donation->notes,
            ]));
            fclose($handle);
        }, 'giving-finance-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function validatedDonation(Request $request, ?Donation $donation = null): array
    {
        $validated = $request->validate([
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'member_id' => ['nullable', 'integer', 'exists:members,id'],
            'fund_id' => ['nullable', 'integer', 'exists:funds,id'],
            'ministry_id' => ['nullable', 'integer', 'exists:ministries,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'currency' => ['required', 'string', 'size:3'],
            'method' => ['nullable', Rule::in(self::METHODS)],
            'giving_source' => ['nullable', Rule::in(self::GIVING_SOURCES)],
            'giving_frequency' => ['nullable', Rule::in(self::GIVING_FREQUENCIES)],
            'received_at' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['church_id'] = $this->defaultChurchId($request);
        $validated['campus_id'] = $this->validatedCampusId($request, $validated['campus_id'] ?? null);
        $validated['created_by_user_id'] = $donation?->created_by_user_id ?? $request->user()?->id;
        $validated['giving_source'] = $validated['giving_source'] ?? 'member';
        $validated['giving_frequency'] = $validated['giving_frequency'] ?? 'one_time';
        $validated['reference'] = filled($validated['reference'] ?? null)
            ? $validated['reference']
            : 'GIV-'.now()->format('YmdHis').'-'.random_int(100, 999);

        if (! empty($validated['member_id'])) {
            abort_unless($this->visibleMembers($request)->whereKey($validated['member_id'])->exists(), 403);
            $member = Member::query()->find($validated['member_id']);
            $validated['campus_id'] ??= $member?->campus_id;
        }

        if ($this->hasFinancePermission($request, ['record ministry contributions']) && ! $this->hasFinancePermission($request, ['manage finance', 'record finance entries'])) {
            abort_unless(filled($validated['ministry_id'] ?? null), 422, 'Select a ministry or department for this contribution.');
            $validated['giving_source'] = in_array($validated['giving_source'], ['ministry', 'department'], true)
                ? $validated['giving_source']
                : 'ministry';
        }

        if (! empty($validated['ministry_id'])) {
            $ministry = $this->visibleMinistries($request)->whereKey($validated['ministry_id'])->firstOrFail();
            abort_if($validated['campus_id'] !== null && $ministry->campus_id !== null && (int) $validated['campus_id'] !== (int) $ministry->campus_id, 422, 'The ministry must belong to the selected campus.');
            $validated['campus_id'] ??= $ministry->campus_id;
        }

        if (! empty($validated['fund_id'])) {
            abort_unless($this->fundQuery($request)->whereKey($validated['fund_id'])->exists(), 403);
        }

        $duplicate = Donation::query()
            ->where('reference', $validated['reference'])
            ->when($donation, fn (Builder $query) => $query->whereKeyNot($donation->id))
            ->exists();
        abort_if($duplicate, 422, 'A donation with this reference already exists.');

        return $validated;
    }

    private function validatedTransaction(Request $request, ?FinanceTransaction $transaction = null): array
    {
        $validated = $request->validate([
            'campus_id' => ['required', 'integer', 'exists:campuses,id'],
            'ministry_id' => ['nullable', 'integer', 'exists:ministries,id'],
            'fund_id' => ['nullable', 'integer', 'exists:funds,id'],
            'type' => ['required', Rule::in(self::TRANSACTION_TYPES)],
            'category' => ['required', Rule::in(self::TRANSACTION_CATEGORIES)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'currency' => ['required', 'string', 'size:3'],
            'method' => ['nullable', Rule::in(self::METHODS)],
            'frequency' => ['required', Rule::in(self::GIVING_FREQUENCIES)],
            'occurred_at' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
            'vendor_or_source' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(self::TRANSACTION_STATUSES)],
        ]);

        $validated['church_id'] = $this->defaultChurchId($request);
        $validated['campus_id'] = $this->validatedCampusId($request, $validated['campus_id']);
        $validated['created_by_user_id'] = $transaction?->created_by_user_id ?? $request->user()?->id;
        $validated['reference'] = filled($validated['reference'] ?? null)
            ? $validated['reference']
            : 'FIN-'.now()->format('YmdHis').'-'.random_int(100, 999);

        if (! empty($validated['ministry_id'])) {
            $ministry = $this->visibleMinistries($request)->whereKey($validated['ministry_id'])->firstOrFail();
            abort_if($ministry->campus_id !== null && (int) $validated['campus_id'] !== (int) $ministry->campus_id, 422, 'The ministry must belong to the selected campus.');
        }

        if (! empty($validated['fund_id'])) {
            abort_unless($this->fundQuery($request)->whereKey($validated['fund_id'])->exists(), 403);
        }

        $duplicate = FinanceTransaction::query()
            ->where('reference', $validated['reference'])
            ->when($transaction, fn (Builder $query) => $query->whereKeyNot($transaction->id))
            ->exists();
        abort_if($duplicate, 422, 'A finance transaction with this reference already exists.');

        return $validated;
    }

    private function donationFormData(Request $request, ?Donation $donation = null): array
    {
        return [
            'donation' => $donation,
            'funds' => $this->fundQuery($request)->orderBy('name')->get(),
            'members' => $this->visibleMembers($request)->limit(300)->get(),
            'ministries' => $this->visibleMinistries($request)->with('campus')->get(),
            'campuses' => $this->visibleCampuses($request)->get(),
            'methods' => self::METHODS,
            'givingFrequencies' => self::GIVING_FREQUENCIES,
            'givingSources' => self::GIVING_SOURCES,
            'financeCapabilities' => $this->financeCapabilities($request),
            'currency' => $this->currency($request),
        ];
    }

    private function validatedFund(Request $request, ?Fund $fund = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['church_id'] = $this->defaultChurchId($request);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        $duplicate = $this->fundQuery($request)
            ->where('name', $validated['name'])
            ->when($fund, fn (Builder $query) => $query->whereKeyNot($fund->id))
            ->exists();
        abort_if($duplicate, 422, 'A fund with this name already exists.');

        return $validated;
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('q'), function (Builder $query) use ($request): void {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(fn (Builder $search) => $search
                ->where('reference', 'like', $term)
                ->orWhere('notes', 'like', $term)
                ->orWhereHas('member', fn (Builder $member) => $member->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('email', 'like', $term))
                ->orWhereHas('ministry', fn (Builder $ministry) => $ministry->where('name', 'like', $term)));
        });
        $query->when($request->filled('fund_id'), fn (Builder $query) => $query->where('fund_id', (int) $request->query('fund_id')));
        $query->when($request->filled('method'), fn (Builder $query) => $query->where('method', $request->string('method')));
        $query->when($request->filled('campus_id'), fn (Builder $query) => $query->where('campus_id', (int) $request->query('campus_id')));
        $query->when($request->filled('ministry_id'), fn (Builder $query) => $query->where('ministry_id', (int) $request->query('ministry_id')));
        $query->when($request->filled('giving_frequency'), fn (Builder $query) => $query->where('giving_frequency', $request->string('giving_frequency')));
    }

    private function applyTransactionFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('finance_q'), function (Builder $query) use ($request): void {
            $term = '%'.$request->string('finance_q')->toString().'%';
            $query->where(fn (Builder $search) => $search
                ->where('reference', 'like', $term)
                ->orWhere('vendor_or_source', 'like', $term)
                ->orWhere('description', 'like', $term)
                ->orWhereHas('ministry', fn (Builder $ministry) => $ministry->where('name', 'like', $term)));
        });
        $query->when($request->filled('finance_type'), fn (Builder $query) => $query->where('type', $request->string('finance_type')));
        $query->when($request->filled('finance_status'), fn (Builder $query) => $query->where('status', $request->string('finance_status')));
        $query->when($request->filled('finance_campus_id'), fn (Builder $query) => $query->where('campus_id', (int) $request->query('finance_campus_id')));
        $query->when($request->filled('finance_ministry_id'), fn (Builder $query) => $query->where('ministry_id', (int) $request->query('finance_ministry_id')));
    }

    private function fundQuery(Request $request): Builder
    {
        $query = Fund::query();
        $user = $request->user();

        return $user?->isSuperAdministrator()
            ? $query
            : $query->where('church_id', $user?->church_id);
    }

    private function authorizeFinanceAccess(Request $request): void
    {
        $this->authorizeAnyFinancePermission($request, self::ACCESS_PERMISSIONS);
    }

    private function authorizeAnyFinancePermission(Request $request, array $permissions): void
    {
        abort_unless($this->hasFinancePermission($request, $permissions), 403);
    }

    private function hasFinancePermission(Request $request, array $permissions): bool
    {
        $user = $request->user();

        return $user?->isSuperAdministrator() || (bool) $user?->hasAnyPermission($permissions);
    }

    private function financeCapabilities(Request $request): array
    {
        $canManage = $this->hasFinancePermission($request, ['manage finance']);
        $canView = $this->hasFinancePermission($request, ['view finance']);
        $canRecordEntries = $this->hasFinancePermission($request, ['record finance entries']);
        $canViewMinistry = $this->hasFinancePermission($request, ['view ministry finance']);
        $canRecordMinistry = $this->hasFinancePermission($request, ['record ministry contributions']);

        return [
            'can_view_sensitive_finance' => $canManage || $canView,
            'can_manage_finance' => $canManage,
            'can_record_donations' => $canManage || $canRecordEntries || $canRecordMinistry,
            'can_record_transactions' => $canManage || $canRecordEntries,
            'can_manage_funds' => $canManage,
            'can_export' => $canManage,
            'can_view_transactions' => $canManage || $canView || $canRecordEntries,
            'can_view_ministry_finance' => $canViewMinistry || $canRecordMinistry,
            'restricted_mode' => ! ($canManage || $canView),
        ];
    }

    private function donationVisibilityQuery(Request $request): Builder
    {
        $query = $this->scopeChurchCampus(Donation::query(), $request);

        if ($this->hasFinancePermission($request, ['manage finance', 'view finance'])) {
            return $query;
        }

        return $query->where('created_by_user_id', $request->user()?->id);
    }

    private function transactionVisibilityQuery(Request $request): Builder
    {
        $query = $this->scopeChurchCampus(FinanceTransaction::query(), $request);

        if ($this->hasFinancePermission($request, ['manage finance', 'view finance'])) {
            return $query;
        }

        if ($this->hasFinancePermission($request, ['record finance entries'])) {
            return $query->where('created_by_user_id', $request->user()?->id);
        }

        return $query->whereRaw('1 = 0');
    }

    private function authorizeDonationMutation(Request $request, Donation $donation): void
    {
        $this->authorizeScopedRecord($request, $donation);

        if ($this->hasFinancePermission($request, ['manage finance'])) {
            return;
        }

        abort_unless(
            $this->hasFinancePermission($request, ['record finance entries', 'record ministry contributions'])
            && (int) $donation->created_by_user_id === (int) $request->user()?->id,
            403,
        );
    }

    private function authorizeTransactionMutation(Request $request, FinanceTransaction $transaction): void
    {
        $this->authorizeScopedRecord($request, $transaction);

        if ($this->hasFinancePermission($request, ['manage finance'])) {
            return;
        }

        abort_unless(
            $this->hasFinancePermission($request, ['record finance entries'])
            && (int) $transaction->created_by_user_id === (int) $request->user()?->id,
            403,
        );
    }

    private function visibleMinistries(Request $request): Builder
    {
        return $this->scopeChurchCampus(Ministry::query(), $request)->orderBy('name');
    }

    private function authorizeFundRecord(Request $request, Fund $fund): void
    {
        $user = $request->user();
        abort_unless($user?->isSuperAdministrator() || (int) $fund->church_id === (int) $user?->church_id, 403);
    }

    private function fundRows(Request $request): array
    {
        return $this->scopedDonationAggregateQuery($request)
            ->join('funds', 'funds.id', '=', 'donations.fund_id')
            ->select('funds.name', DB::raw('sum(donations.amount) as total'))
            ->groupBy('funds.name')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn ($row): array => ['label' => $row->name, 'value' => (float) $row->total])
            ->all();
    }

    private function methodRows(Request $request): array
    {
        return $this->donationVisibilityQuery($request)
            ->select('method', DB::raw('count(*) as total'))
            ->groupBy('method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => ['label' => str((string) ($row->method ?: 'unknown'))->headline()->toString(), 'value' => (int) $row->total])
            ->all();
    }

    private function periodStats(Request $request): array
    {
        $base = $this->donationVisibilityQuery($request);
        $currency = $this->currency($request);

        return [
            'today' => Number::currency((float) (clone $base)->whereDate('received_at', now()->toDateString())->sum('amount'), $currency),
            'week' => Number::currency((float) (clone $base)->whereBetween('received_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('amount'), $currency),
            'online' => Number::currency((float) (clone $base)->whereIn('method', ['online', 'mobile', 'card'])->whereBetween('received_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'), $currency),
            'anonymous' => (clone $base)->whereNull('member_id')->count(),
        ];
    }

    private function transactionStats(Request $request): array
    {
        $base = $this->transactionVisibilityQuery($request)
            ->whereBetween('occurred_at', [now()->startOfMonth(), now()->endOfMonth()]);
        $currency = $this->currency($request);
        $income = (float) (clone $base)->where('type', 'income')->where('status', 'posted')->sum('amount');
        $expenses = (float) (clone $base)->where('type', 'expense')->where('status', 'posted')->sum('amount');

        return [
            'income' => Number::currency($income, $currency),
            'expenses' => Number::currency($expenses, $currency),
            'net' => Number::currency($income - $expenses, $currency),
            'pending' => (clone $base)->where('status', 'pending')->count(),
        ];
    }

    private function campusFinanceRows(Request $request): array
    {
        $currency = $this->currency($request);

        return $this->visibleCampuses($request)->get()->map(function ($campus) use ($request, $currency): array {
            $donations = (float) $this->donationVisibilityQuery($request)
                ->where('campus_id', $campus->id)
                ->whereBetween('received_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount');
            $income = (float) $this->transactionVisibilityQuery($request)
                ->where('campus_id', $campus->id)
                ->where('type', 'income')
                ->where('status', 'posted')
                ->whereBetween('occurred_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount');
            $expenses = (float) $this->transactionVisibilityQuery($request)
                ->where('campus_id', $campus->id)
                ->where('type', 'expense')
                ->where('status', 'posted')
                ->whereBetween('occurred_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount');

            return [
                'campus' => $campus->name,
                'donations' => Number::currency($donations, $currency),
                'income' => Number::currency($income, $currency),
                'expenses' => Number::currency($expenses, $currency),
                'net' => Number::currency($donations + $income - $expenses, $currency),
                'records' => $this->transactionVisibilityQuery($request)->where('campus_id', $campus->id)->count(),
            ];
        })->all();
    }

    private function ministryGivingRows(Request $request): array
    {
        $currency = $this->currency($request);

        return $this->scopedDonationAggregateQuery($request)
            ->join('ministries', 'ministries.id', '=', 'donations.ministry_id')
            ->leftJoin('campuses', 'campuses.id', '=', 'donations.campus_id')
            ->whereBetween('donations.received_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->select('ministries.name as ministry_name', 'campuses.name as campus_name', 'donations.giving_frequency', DB::raw('sum(donations.amount) as total'), DB::raw('count(*) as records'))
            ->groupBy('ministries.name', 'campuses.name', 'donations.giving_frequency')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => [
                'ministry' => $row->ministry_name,
                'campus' => $row->campus_name ?: 'Unassigned',
                'frequency' => str((string) $row->giving_frequency)->headline()->toString(),
                'total' => Number::currency((float) $row->total, $currency),
                'records' => (int) $row->records,
            ])
            ->all();
    }

    private function scopedDonationAggregateQuery(Request $request): Builder
    {
        $query = Donation::query();
        $user = $request->user();

        if (! $user?->isSuperAdministrator()) {
            $query->where('donations.church_id', $user?->church_id);

            if ($user?->campus_id !== null) {
                $query->where(fn (Builder $campusQuery) => $campusQuery
                    ->whereNull('donations.campus_id')
                    ->orWhere('donations.campus_id', $user->campus_id));
            }
        }

        if (! $this->hasFinancePermission($request, ['manage finance', 'view finance'])) {
            $query->where('donations.created_by_user_id', $user?->id);
        }

        return $query;
    }

    private function currency(Request $request): string
    {
        return (string) ($request->user()?->church?->currency ?: config('church.currency', 'USD'));
    }
}
