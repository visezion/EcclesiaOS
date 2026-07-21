<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Campus;
use App\Models\Church;
use App\Models\Family;
use App\Models\Member;
use App\Services\ActivityLogger;
use App\Support\OpaqueId;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FamilyManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeMembers($request);

        $query = $this->scopeFamilies(Family::query(), $request)->with(['campus', 'primaryContact', 'members'])->withCount('members');
        $query->when($request->filled('q'), function ($query) use ($request): void {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where('name', 'like', $term)
                ->orWhere('address', 'like', $term)
                ->orWhereHas('primaryContact', fn ($query) => $query->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
        });
        $query->when($this->queryId($request, 'campus_id', Campus::class), fn ($query, int $campusId) => $query->where('campus_id', $campusId));

        $families = $query->latest()->paginate(10)->withQueryString();
        $selectedId = OpaqueId::decode($request->query('selected'), Family::class);
        $selected = $selectedId ? $this->scopeFamilies(Family::query()->with(['campus', 'primaryContact', 'members']), $request)->find($selectedId) : $families->first();

        return view('families.index', [
            'families' => $families,
            'selectedFamily' => $selected,
            'campuses' => $this->visibleCampuses($request)->get(),
            'churches' => $this->visibleChurches($request)->get(),
            'members' => $this->visibleMembers($request)->get(),
            'campusDistribution' => $this->campusDistribution($request),
            'familyTypeDistribution' => $this->familyTypeDistribution($request),
            'recentActivity' => ActivityLog::query()->with('user')->where('module', 'Families')->latest()->limit(6)->get(),
            'stats' => $this->stats($request),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Members', 'url' => route('members.index')],
                ['label' => 'Families & Households', 'url' => null],
            ],
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeMembers($request);
        $validated = $this->validatedFamily($request);
        $family = Family::query()->create($validated);
        $this->assignMembers($family, $request->input('member_ids', []), $request);

        $activityLogger->log('Families', 'family_created', $family->name.' household was created.', $family, ['resource' => 'Household', 'risk' => 'low', 'status' => 'success'], $request);

        return redirect()->route('families.index', ['selected' => OpaqueId::encode($family->id, Family::class)])->with('status', 'Household created.');
    }

    public function update(Request $request, Family $family, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeMembers($request);
        $this->authorizeFamilyRecord($request, $family);
        $family->update($this->validatedFamily($request));
        $this->assignMembers($family, $request->input('member_ids', []), $request);

        $activityLogger->log('Families', 'family_updated', $family->name.' household was updated.', $family, ['resource' => 'Household', 'risk' => 'low', 'status' => 'success'], $request);

        return redirect()->route('families.index', ['selected' => OpaqueId::encode($family->id, Family::class)])->with('status', 'Household updated.');
    }

    public function destroy(Request $request, Family $family, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeMembers($request);
        $this->authorizeFamilyRecord($request, $family);
        $name = $family->name;
        $family->members()->update(['family_id' => null]);
        $family->delete();
        $activityLogger->log('Families', 'family_deleted', $name.' household was removed.', null, ['resource' => 'Household', 'risk' => 'medium', 'status' => 'success'], $request);

        return redirect()->route('families.index')->with('status', 'Household removed.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeMembers($request);

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            fputcsv($handle, ['Household ID', 'Family Name', 'Head of Household', 'Members', 'Campus', 'Phone', 'Email', 'Address']);
            $this->scopeFamilies(Family::query(), $request)->with(['campus', 'primaryContact'])->withCount('members')->lazy(100)->each(function (Family $family) use ($handle): void {
                fputcsv($handle, [
                    'HH-'.str_pad((string) $family->id, 5, '0', STR_PAD_LEFT),
                    $family->name,
                    $family->primaryContact ? $family->primaryContact->first_name.' '.$family->primaryContact->last_name : '',
                    $family->members_count,
                    $family->campus?->name,
                    $family->primaryContact?->phone,
                    $family->primaryContact?->email,
                    $family->address,
                ]);
            });
            fclose($handle);
        }, 'families-households-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function authorizeMembers(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('manage members'), 403);
    }

    private function authorizeFamilyRecord(Request $request, Family $family): void
    {
        $user = $request->user();
        abort_unless($user?->canAccessChurch($family->church_id) && $user->canAccessCampus($family->campus_id), 403);
    }

    private function scopeFamilies(Builder $query, Request $request): Builder
    {
        $user = $request->user();

        if ($user?->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $user?->church_id);

        if ($user?->campus_id !== null) {
            $query->where(fn (Builder $campusQuery) => $campusQuery
                ->whereNull('campus_id')
                ->orWhere('campus_id', $user->campus_id));
        }

        return $query;
    }

    private function visibleMembers(Request $request): Builder
    {
        $query = Member::query()->orderBy('last_name')->orderBy('first_name');
        $user = $request->user();

        if ($user?->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $user?->church_id);

        if ($user?->campus_id !== null) {
            $query->where(fn (Builder $campusQuery) => $campusQuery
                ->whereNull('campus_id')
                ->orWhere('campus_id', $user->campus_id));
        }

        return $query;
    }

    private function visibleCampuses(Request $request): Builder
    {
        $query = Campus::query()->orderBy('name');
        $user = $request->user();

        if ($user?->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $user?->church_id);

        if ($user?->campus_id !== null) {
            $query->where('id', $user->campus_id);
        }

        return $query;
    }

    private function visibleChurches(Request $request): Builder
    {
        $query = Church::query()->orderBy('name');
        $user = $request->user();

        if ($user?->isSuperAdministrator()) {
            return $query;
        }

        return $query->where('id', $user?->church_id);
    }

    private function validatedFamily(Request $request): array
    {
        $user = $request->user();
        $church = $user?->isSuperAdministrator()
            ? Church::query()->firstOrFail()
            : Church::query()->findOrFail($user?->church_id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'campus_id' => ['nullable', 'exists:campuses,id'],
            'primary_contact_id' => ['nullable', 'exists:members,id'],
            'address' => ['nullable', 'string', 'max:255'],
        ]) + ['church_id' => $church->id];

        if (! $user?->isSuperAdministrator() && $user?->campus_id !== null) {
            $validated['campus_id'] = $user->campus_id;
        }

        abort_unless($user?->canAccessCampus($validated['campus_id'] ?? null), 403);

        if (! empty($validated['primary_contact_id'])) {
            abort_unless($this->visibleMembers($request)->where('id', $validated['primary_contact_id'])->exists(), 403);
        }

        return $validated;
    }

    private function assignMembers(Family $family, mixed $memberIds, Request $request): void
    {
        $ids = OpaqueId::decodeMany(Arr::wrap($memberIds), Member::class);
        if ($ids !== []) {
            $visibleIds = $this->visibleMembers($request)->whereIn('id', $ids)->pluck('id');
            Member::query()->whereIn('id', $visibleIds)->where('church_id', $family->church_id)->update(['family_id' => $family->id, 'campus_id' => $family->campus_id]);
        }
        if ($family->primary_contact_id) {
            $this->visibleMembers($request)->where('id', $family->primary_contact_id)->where('church_id', $family->church_id)->update(['family_id' => $family->id, 'campus_id' => $family->campus_id]);
        }
    }

    private function stats(Request $request): array
    {
        $households = $this->scopeFamilies(Family::query(), $request)->count();

        return [
            'households' => $households,
            'dependents' => max(0, $this->visibleMembers($request)->whereNotNull('family_id')->count() - $households),
            'new' => $this->scopeFamilies(Family::query(), $request)->whereDate('created_at', '>=', now()->startOfMonth())->count(),
            'follow_up' => $this->visibleMembers($request)->whereIn('status', ['inactive', 'follow-up'])->whereNotNull('family_id')->count(),
            'top_campus' => $this->visibleCampuses($request)->withCount('members')->orderByDesc('members_count')->first()?->name ?? 'No campus',
        ];
    }

    private function campusDistribution(Request $request): array
    {
        $total = max($this->visibleMembers($request)->whereNotNull('family_id')->count(), 1);

        return $this->visibleCampuses($request)->withCount(['members' => function (Builder $query) use ($request): void {
            $user = $request->user();

            if (! $user?->isSuperAdministrator()) {
                $query->where('church_id', $user?->church_id);
            }

            if (! $user?->isSuperAdministrator() && $user?->campus_id !== null) {
                $query->where('campus_id', $user->campus_id);
            }

            $query->whereNotNull('family_id');
        }])->orderByDesc('members_count')->limit(5)->get()->map(fn (Campus $campus): array => [
            'name' => $campus->name,
            'count' => $campus->members_count,
            'percent' => round(($campus->members_count / $total) * 100, 1),
        ])->all();
    }

    private function familyTypeDistribution(Request $request): array
    {
        $families = $this->scopeFamilies(Family::query(), $request)->withCount('members')->get();
        $total = max($families->count(), 1);

        return collect([
            'Nuclear Family' => $families->whereBetween('members_count', [3, 5])->count(),
            'Single Adult' => $families->where('members_count', 1)->count(),
            'Large Household' => $families->where('members_count', '>', 5)->count(),
            'Couple' => $families->where('members_count', 2)->count(),
        ])->map(fn (int $count, string $label): array => ['label' => $label, 'count' => $count, 'percent' => round(($count / $total) * 100, 1)])->values()->all();
    }

    private function queryId(Request $request, string $key, string $scope): ?int
    {
        return OpaqueId::decode($request->query($key), $scope);
    }
}
