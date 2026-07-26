<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOperationalRecords;
use App\Models\Donation;
use App\Models\Fund;
use App\Models\Member;
use App\Services\ActivityLogger;
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

    private const METHODS = ['cash', 'card', 'bank', 'check', 'mobile', 'online'];

    public function index(Request $request): View
    {
        $this->authorizePermission($request, 'manage finance');

        $query = $this->scopeChurchCampus(Donation::query(), $request)->with(['member', 'fund', 'campus']);
        $this->applyFilters($query, $request);

        $donations = $query->latest('received_at')->paginate(12)->withQueryString();
        $base = $this->scopeChurchCampus(Donation::query(), $request);
        $currency = $this->currency($request);

        return view('finance.index', [
            'donations' => $donations,
            'funds' => $this->fundQuery($request)->withCount('donations')->orderBy('name')->get(),
            'members' => $this->visibleMembers($request)->limit(300)->get(),
            'campuses' => $this->visibleCampuses($request)->get(),
            'methods' => self::METHODS,
            'currency' => $currency,
            'stats' => [
                'month' => Number::currency((float) (clone $base)->whereBetween('received_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'), $currency),
                'year' => Number::currency((float) (clone $base)->whereYear('received_at', now()->year)->sum('amount'), $currency),
                'count' => (clone $base)->count(),
                'average' => Number::currency((float) (clone $base)->avg('amount'), $currency),
            ],
            'periodStats' => $this->periodStats($request),
            'fundRows' => $this->fundRows($request),
            'methodRows' => $this->methodRows($request),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Giving & Finance', 'url' => null],
            ],
        ]);
    }

    public function overview(Request $request): View
    {
        $this->authorizePermission($request, 'manage finance');

        $base = $this->scopeChurchCampus(Donation::query(), $request);
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
            'recentDonations' => $this->scopeChurchCampus(Donation::query(), $request)->with(['member', 'fund', 'campus'])->latest('received_at')->limit(8)->get(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Giving & Finance', 'url' => route('finance.index')],
                ['label' => 'Overview', 'url' => null],
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizePermission($request, 'manage finance');

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
        $this->authorizePermission($request, 'manage finance');
        $this->authorizeScopedRecord($request, $donation);

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
        $this->authorizePermission($request, 'manage finance');
        $donation = Donation::query()->create($this->validatedDonation($request));

        $activityLogger->log('Finance', 'donation_recorded', 'Donation '.$donation->reference.' was recorded.', $donation, ['resource' => 'Donation', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Donation recorded.');
    }

    public function update(Request $request, Donation $donation, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage finance');
        $this->authorizeScopedRecord($request, $donation);
        $donation->update($this->validatedDonation($request, $donation));

        $activityLogger->log('Finance', 'donation_updated', 'Donation '.$donation->reference.' was updated.', $donation, ['resource' => 'Donation', 'risk' => 'medium', 'status' => 'success'], $request);

        return back()->with('status', 'Donation updated.');
    }

    public function destroy(Request $request, Donation $donation, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage finance');
        $this->authorizeScopedRecord($request, $donation);
        $reference = $donation->reference;
        $donation->delete();

        $activityLogger->log('Finance', 'donation_archived', 'Donation '.$reference.' was archived.', null, ['resource' => 'Donation', 'risk' => 'medium', 'status' => 'success'], $request);

        return back()->with('status', 'Donation archived.');
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
        $this->authorizePermission($request, 'manage finance');

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Reference', 'Date', 'Member', 'Fund', 'Campus', 'Method', 'Amount', 'Currency']);
            $query = $this->scopeChurchCampus(Donation::query(), $request)->with(['member', 'fund', 'campus'])->latest('received_at');
            $this->applyFilters($query, $request);
            $query->lazy(100)->each(fn (Donation $donation) => fputcsv($handle, [
                $donation->reference,
                $donation->received_at?->format('Y-m-d H:i'),
                $donation->member ? $donation->member->first_name.' '.$donation->member->last_name : '',
                $donation->fund?->name,
                $donation->campus?->name,
                $donation->method,
                $donation->amount,
                $donation->currency,
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
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'currency' => ['required', 'string', 'size:3'],
            'method' => ['nullable', Rule::in(self::METHODS)],
            'received_at' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $validated['church_id'] = $this->defaultChurchId($request);
        $validated['campus_id'] = $this->validatedCampusId($request, $validated['campus_id'] ?? null);
        $validated['reference'] = filled($validated['reference'] ?? null)
            ? $validated['reference']
            : 'GIV-'.now()->format('YmdHis').'-'.random_int(100, 999);

        if (! empty($validated['member_id'])) {
            abort_unless($this->visibleMembers($request)->whereKey($validated['member_id'])->exists(), 403);
            $member = Member::query()->find($validated['member_id']);
            $validated['campus_id'] ??= $member?->campus_id;
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

    private function donationFormData(Request $request, ?Donation $donation = null): array
    {
        return [
            'donation' => $donation,
            'funds' => $this->fundQuery($request)->orderBy('name')->get(),
            'members' => $this->visibleMembers($request)->limit(300)->get(),
            'campuses' => $this->visibleCampuses($request)->get(),
            'methods' => self::METHODS,
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
                ->orWhereHas('member', fn (Builder $member) => $member->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('email', 'like', $term)));
        });
        $query->when($request->filled('fund_id'), fn (Builder $query) => $query->where('fund_id', (int) $request->query('fund_id')));
        $query->when($request->filled('method'), fn (Builder $query) => $query->where('method', $request->string('method')));
        $query->when($request->filled('campus_id'), fn (Builder $query) => $query->where('campus_id', (int) $request->query('campus_id')));
    }

    private function fundQuery(Request $request): Builder
    {
        $query = Fund::query();
        $user = $request->user();

        return $user?->isSuperAdministrator()
            ? $query
            : $query->where('church_id', $user?->church_id);
    }

    private function authorizeFundRecord(Request $request, Fund $fund): void
    {
        $user = $request->user();
        abort_unless($user?->isSuperAdministrator() || (int) $fund->church_id === (int) $user?->church_id, 403);
    }

    private function fundRows(Request $request): array
    {
        return $this->scopeChurchCampus(Donation::query(), $request)
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
        return $this->scopeChurchCampus(Donation::query(), $request)
            ->select('method', DB::raw('count(*) as total'))
            ->groupBy('method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => ['label' => str((string) ($row->method ?: 'unknown'))->headline()->toString(), 'value' => (int) $row->total])
            ->all();
    }

    private function periodStats(Request $request): array
    {
        $base = $this->scopeChurchCampus(Donation::query(), $request);
        $currency = $this->currency($request);

        return [
            'today' => Number::currency((float) (clone $base)->whereDate('received_at', now()->toDateString())->sum('amount'), $currency),
            'week' => Number::currency((float) (clone $base)->whereBetween('received_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('amount'), $currency),
            'online' => Number::currency((float) (clone $base)->whereIn('method', ['online', 'mobile', 'card'])->whereBetween('received_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'), $currency),
            'anonymous' => (clone $base)->whereNull('member_id')->count(),
        ];
    }

    private function currency(Request $request): string
    {
        return (string) ($request->user()?->church?->currency ?: config('church.currency', 'USD'));
    }
}
