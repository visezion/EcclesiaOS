<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOperationalRecords;
use App\Models\Asset;
use App\Models\AssetBooking;
use App\Models\AssetCategory;
use App\Models\Campus;
use App\Models\Member;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\OpaqueId;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AssetInventoryController extends Controller
{
    use ScopesOperationalRecords;

    private const STATUSES = ['available', 'in_use', 'maintenance', 'retired'];

    private const CONDITIONS = ['excellent', 'good', 'fair', 'maintenance', 'critical'];

    private const BOOKING_STATUSES = ['reserved', 'checked_out', 'returned', 'cancelled', 'overdue'];

    public function index(Request $request): View
    {
        $this->authorizePermission($request, 'manage assets');

        $query = $this->scopeChurchCampus(Asset::query(), $request)->with(['campus', 'category', 'bookings']);
        $this->applyFilters($query, $request);

        $assets = $query->latest()->paginate(12)->withQueryString();
        $allAssets = $this->scopeChurchCampus(Asset::query(), $request);

        return view('assets.index', [
            'assets' => $assets,
            'categories' => $this->categoryQuery($request)->withCount('assets')->orderBy('name')->get(),
            'campuses' => $this->visibleCampuses($request)->get(),
            'members' => $this->visibleMembers($request)->with('campus')->limit(300)->get(),
            'users' => $this->visibleUsers($request)->limit(200)->get(),
            'assetOptions' => $this->scopeChurchCampus(Asset::query(), $request)->with(['campus', 'category'])->orderBy('name')->limit(500)->get(),
            'bookings' => $this->bookingQuery($request)->with(['asset.category', 'asset.campus', 'member', 'assignedUser', 'campus'])->orderBy('starts_at')->limit(12)->get(),
            'statuses' => self::STATUSES,
            'conditions' => self::CONDITIONS,
            'bookingStatuses' => self::BOOKING_STATUSES,
            'stats' => [
                'total' => (clone $allAssets)->count(),
                'available' => (clone $allAssets)->where('status', 'available')->count(),
                'maintenance' => (clone $allAssets)->where('status', 'maintenance')->count(),
                'value' => (float) (clone $allAssets)->sum('purchase_amount'),
            ],
            'bookingStats' => $this->bookingStats($request),
            'conditionRows' => $this->conditionRows($request),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Asset Inventory', 'url' => null],
            ],
        ]);
    }

    public function overview(Request $request): View
    {
        $this->authorizePermission($request, 'manage assets');

        $base = $this->scopeChurchCampus(Asset::query(), $request);

        return view('assets.overview', [
            'stats' => [
                'total' => (clone $base)->count(),
                'available' => (clone $base)->where('status', 'available')->count(),
                'maintenance' => (clone $base)->where('status', 'maintenance')->count(),
                'retired' => (clone $base)->where('status', 'retired')->count(),
                'value' => (float) (clone $base)->sum('purchase_amount'),
            ],
            'conditionRows' => $this->conditionRows($request),
            'statusRows' => $this->assetStatusRows($request),
            'categoryRows' => $this->categoryQuery($request)->withCount('assets')->orderByDesc('assets_count')->limit(8)->get(),
            'recentAssets' => $this->scopeChurchCampus(Asset::query(), $request)->with(['campus', 'category'])->latest()->limit(8)->get(),
            'upcomingBookings' => $this->bookingQuery($request)->with(['asset.category', 'member', 'assignedUser', 'campus'])->orderBy('starts_at')->limit(8)->get(),
            'bookingStats' => $this->bookingStats($request),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Asset Inventory', 'url' => route('assets.index')],
                ['label' => 'Overview', 'url' => null],
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizePermission($request, 'manage assets');

        return view('assets.create', $this->assetFormData($request) + [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Asset Inventory', 'url' => route('assets.index')],
                ['label' => 'Create', 'url' => null],
            ],
        ]);
    }

    public function edit(Request $request, Asset $asset): View
    {
        $this->authorizePermission($request, 'manage assets');
        $this->authorizeScopedRecord($request, $asset);

        return view('assets.edit', $this->assetFormData($request, $asset) + [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Asset Inventory', 'url' => route('assets.index')],
                ['label' => $asset->name, 'url' => null],
            ],
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage assets');
        $asset = Asset::query()->create($this->validatedAsset($request));

        $activityLogger->log('Assets', 'asset_created', $asset->name.' was added to inventory.', $asset, ['resource' => 'Asset', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Asset added.');
    }

    public function update(Request $request, Asset $asset, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage assets');
        $this->authorizeScopedRecord($request, $asset);
        $asset->update($this->validatedAsset($request, $asset));

        $activityLogger->log('Assets', 'asset_updated', $asset->name.' was updated.', $asset, ['resource' => 'Asset', 'risk' => $asset->status === 'maintenance' ? 'medium' : 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Asset updated.');
    }

    public function destroy(Request $request, Asset $asset, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage assets');
        $this->authorizeScopedRecord($request, $asset);
        $name = $asset->name;
        $asset->delete();

        $activityLogger->log('Assets', 'asset_archived', $name.' was archived.', null, ['resource' => 'Asset', 'risk' => 'medium', 'status' => 'success'], $request);

        return back()->with('status', 'Asset archived.');
    }

    public function storeBooking(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage assets');
        $booking = AssetBooking::query()->create($this->validatedBooking($request));

        $activityLogger->log('Assets', 'asset_booking_created', $booking->asset?->name.' was reserved.', $booking, ['resource' => 'Asset Booking', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Asset booking saved.');
    }

    public function updateBooking(Request $request, AssetBooking $booking, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage assets');
        $this->authorizeBookingRecord($request, $booking);
        $booking->update($this->validatedBooking($request, $booking));

        $activityLogger->log('Assets', 'asset_booking_updated', $booking->asset?->name.' booking was updated.', $booking, ['resource' => 'Asset Booking', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Asset booking updated.');
    }

    public function destroyBooking(Request $request, AssetBooking $booking, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage assets');
        $this->authorizeBookingRecord($request, $booking);
        $booking->update(['status' => 'cancelled']);

        $activityLogger->log('Assets', 'asset_booking_cancelled', $booking->asset?->name.' booking was cancelled.', $booking, ['resource' => 'Asset Booking', 'risk' => 'medium', 'status' => 'success'], $request);

        return back()->with('status', 'Asset booking cancelled.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'manage assets');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        AssetCategory::query()->updateOrCreate(
            ['church_id' => $this->defaultChurchId($request), 'name' => $validated['name']],
            ['description' => $validated['description'] ?? null],
        );

        return back()->with('status', 'Asset category saved.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizePermission($request, 'manage assets');

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Name', 'Serial Number', 'Category', 'Campus', 'Status', 'Condition', 'Purchased At', 'Purchase Amount']);
            $query = $this->scopeChurchCampus(Asset::query(), $request)->with(['campus', 'category'])->latest();
            $this->applyFilters($query, $request);
            $query->lazy(100)->each(fn (Asset $asset) => fputcsv($handle, [
                $asset->name,
                $asset->serial_number,
                $asset->category?->name,
                $asset->campus?->name,
                $asset->status,
                $asset->condition,
                $asset->purchased_at?->format('Y-m-d'),
                $asset->purchase_amount,
            ]));
            fclose($handle);
        }, 'assets-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function validatedAsset(Request $request, ?Asset $asset = null): array
    {
        $validated = $request->validate([
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'asset_category_id' => ['nullable', 'integer', 'exists:asset_categories,id'],
            'name' => ['required', 'string', 'max:180'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'condition' => ['required', Rule::in(self::CONDITIONS)],
            'purchased_at' => ['nullable', 'date'],
            'purchase_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
        ]);

        $validated['church_id'] = $this->defaultChurchId($request);
        $validated['campus_id'] = $this->validatedCampusId($request, $validated['campus_id'] ?? null);

        if (! empty($validated['asset_category_id'])) {
            abort_unless($this->categoryQuery($request)->whereKey($validated['asset_category_id'])->exists(), 403);
        }

        if (filled($validated['serial_number'] ?? null)) {
            $duplicate = Asset::query()
                ->where('serial_number', $validated['serial_number'])
                ->when($asset, fn (Builder $query) => $query->whereKeyNot($asset->id))
                ->exists();
            abort_if($duplicate, 422, 'An asset with this serial number already exists.');
        }

        return $validated;
    }

    private function assetFormData(Request $request, ?Asset $asset = null): array
    {
        return [
            'asset' => $asset,
            'categories' => $this->categoryQuery($request)->orderBy('name')->get(),
            'campuses' => $this->visibleCampuses($request)->get(),
            'statuses' => self::STATUSES,
            'conditions' => self::CONDITIONS,
            'bookingStatuses' => self::BOOKING_STATUSES,
        ];
    }

    private function validatedBooking(Request $request, ?AssetBooking $booking = null): array
    {
        $validated = $request->validate([
            'asset_id' => ['required', 'string'],
            'campus_id' => ['nullable', 'string'],
            'member_id' => ['nullable', 'string'],
            'assigned_user_id' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['required', Rule::in(self::BOOKING_STATUSES)],
            'purpose' => ['nullable', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:1500'],
        ]);

        $assetId = OpaqueId::decode($validated['asset_id'], Asset::class);
        if (! $assetId) {
            throw ValidationException::withMessages(['asset_id' => 'Select a valid asset.']);
        }

        $asset = $this->scopeChurchCampus(Asset::query(), $request)->findOrFail($assetId);
        abort_if($asset->status === 'retired', 422, 'Retired assets cannot be booked.');

        $validated['asset_id'] = $asset->id;
        $validated['church_id'] = $asset->church_id;
        $validated['campus_id'] = filled($validated['campus_id'] ?? null)
            ? OpaqueId::decode($validated['campus_id'], Campus::class)
            : $asset->campus_id;
        $validated['member_id'] = filled($validated['member_id'] ?? null)
            ? OpaqueId::decode($validated['member_id'], Member::class)
            : null;
        $validated['assigned_user_id'] = filled($validated['assigned_user_id'] ?? null)
            ? OpaqueId::decode($validated['assigned_user_id'], User::class)
            : null;

        if (filled($request->input('campus_id')) && ! $validated['campus_id']) {
            throw ValidationException::withMessages(['campus_id' => 'Select a valid campus.']);
        }
        if (filled($request->input('member_id')) && ! $validated['member_id']) {
            throw ValidationException::withMessages(['member_id' => 'Select a valid member.']);
        }
        if (filled($request->input('assigned_user_id')) && ! $validated['assigned_user_id']) {
            throw ValidationException::withMessages(['assigned_user_id' => 'Select a valid assignee.']);
        }
        if (! empty($validated['campus_id'])) {
            abort_unless($this->visibleCampuses($request)->whereKey($validated['campus_id'])->exists(), 403);
        }
        if (! empty($validated['member_id'])) {
            abort_unless($this->visibleMembers($request)->whereKey($validated['member_id'])->exists(), 403);
        }
        if (! empty($validated['assigned_user_id'])) {
            abort_unless($this->visibleUsers($request)->whereKey($validated['assigned_user_id'])->exists(), 403);
        }

        $startsAt = Carbon::parse($validated['starts_at']);
        $endsAt = Carbon::parse($validated['ends_at']);
        $activeStatuses = ['reserved', 'checked_out', 'overdue'];

        if (in_array($validated['status'], $activeStatuses, true)) {
            $assetConflict = $this->bookingQuery($request)
                ->where('asset_id', $asset->id)
                ->whereIn('status', $activeStatuses)
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->when($booking, fn (Builder $query) => $query->whereKeyNot($booking->id))
                ->exists();

            if ($assetConflict) {
                throw ValidationException::withMessages(['starts_at' => 'This asset is already reserved during that time.']);
            }
        }

        return $validated;
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('q'), function (Builder $query) use ($request): void {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(fn (Builder $search) => $search
                ->where('name', 'like', $term)
                ->orWhere('serial_number', 'like', $term)
                ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $term)));
        });
        $query->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')));
        $query->when($request->filled('condition'), fn (Builder $query) => $query->where('condition', $request->string('condition')));
        $query->when($request->filled('category_id'), fn (Builder $query) => $query->where('asset_category_id', (int) $request->query('category_id')));
        $query->when($request->filled('campus_id'), fn (Builder $query) => $query->where('campus_id', (int) $request->query('campus_id')));
    }

    private function categoryQuery(Request $request): Builder
    {
        $query = AssetCategory::query();
        $user = $request->user();

        return $user?->isSuperAdministrator()
            ? $query
            : $query->where('church_id', $user?->church_id);
    }

    private function bookingQuery(Request $request): Builder
    {
        return $this->scopeChurchCampus(AssetBooking::query(), $request)
            ->whereIn('status', self::BOOKING_STATUSES);
    }

    private function authorizeBookingRecord(Request $request, AssetBooking $booking): void
    {
        $this->authorizeScopedRecord($request, $booking);
        abort_unless($this->scopeChurchCampus(Asset::query(), $request)->whereKey($booking->asset_id)->exists(), 404);
    }

    private function conditionRows(Request $request): array
    {
        $total = max($this->scopeChurchCampus(Asset::query(), $request)->count(), 1);

        return $this->scopeChurchCampus(Asset::query(), $request)
            ->select('condition', DB::raw('count(*) as total'))
            ->groupBy('condition')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'label' => str((string) $row->condition)->headline()->toString(),
                'value' => (int) $row->total,
                'percent' => round(((int) $row->total / $total) * 100, 1),
            ])
            ->all();
    }

    private function assetStatusRows(Request $request): array
    {
        $total = max($this->scopeChurchCampus(Asset::query(), $request)->count(), 1);

        return collect(self::STATUSES)->map(function (string $status) use ($request, $total): array {
            $count = $this->scopeChurchCampus(Asset::query(), $request)->where('status', $status)->count();

            return [
                'label' => str($status)->headline()->toString(),
                'value' => $count,
                'percent' => round(($count / $total) * 100, 1),
            ];
        })->all();
    }

    private function bookingStats(Request $request): array
    {
        $base = $this->bookingQuery($request);

        return [
            'today' => (clone $base)->whereDate('starts_at', today())->whereIn('status', ['reserved', 'checked_out'])->count(),
            'week' => (clone $base)->whereBetween('starts_at', [now()->startOfDay(), now()->addWeek()])->whereIn('status', ['reserved', 'checked_out'])->count(),
            'checked_out' => (clone $base)->where('status', 'checked_out')->count(),
            'overdue' => (clone $base)->where(fn (Builder $query) => $query
                ->where('status', 'overdue')
                ->orWhere(fn (Builder $overdue) => $overdue->whereIn('status', ['reserved', 'checked_out'])->where('ends_at', '<', now())))->count(),
        ];
    }
}
