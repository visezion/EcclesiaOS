<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Member;
use App\Models\Ministry;
use App\Support\OrganizationTerminology;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class MinistryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeMinistries($request);

        $ministries = $this->ministryQuery($request)
            ->with(['church', 'campus', 'leader'])
            ->withCount(['volunteers'])
            ->orderBy('name')
            ->get();
        $terminology = OrganizationTerminology::forRequest($request);

        return view('ministries.index', [
            'ministries' => $ministries,
            'campuses' => $this->campusQuery($request)->orderBy('name')->get(),
            'leaders' => $this->leaderQuery($request)->orderBy('first_name')->orderBy('last_name')->get(),
            'terminology' => $terminology,
            'stats' => [
                'total' => $ministries->count(),
                'active' => $ministries->where('status', 'active')->count(),
                'campuses' => $ministries->pluck('campus_id')->filter()->unique()->count(),
                'volunteers' => $ministries->sum('volunteers_count'),
            ],
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => $terminology['ministry_plural'], 'url' => null],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeMinistries($request);
        $validated = $this->validatedMinistry($request);

        Ministry::query()->create($validated);
        $terminology = OrganizationTerminology::forRequest($request);

        return back()->with('status', $terminology['ministry_singular'].' created.');
    }

    public function update(Request $request, Ministry $ministry): RedirectResponse
    {
        $this->authorizeMinistries($request);
        $this->authorizeMinistryRecord($request, $ministry);
        $validated = $this->validatedMinistry($request, $ministry);

        $ministry->update($validated);
        $terminology = OrganizationTerminology::forRequest($request);

        return back()->with('status', $terminology['ministry_singular'].' updated.');
    }

    public function destroy(Request $request, Ministry $ministry): RedirectResponse
    {
        $this->authorizeMinistries($request);
        $this->authorizeMinistryRecord($request, $ministry);

        $ministry->delete();
        $terminology = OrganizationTerminology::forRequest($request);

        return back()->with('status', $terminology['ministry_singular'].' archived.');
    }

    private function authorizeMinistries(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('manage ministries'), 403);
    }

    private function authorizeMinistryRecord(Request $request, Ministry $ministry): void
    {
        $user = $request->user();
        abort_unless($user?->canAccessChurch($ministry->church_id) && $user->canAccessCampus($ministry->campus_id), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedMinistry(Request $request, ?Ministry $ministry = null): array
    {
        $validated = $request->validate([
            'church_id' => ['nullable', 'exists:churches,id'],
            'campus_id' => ['required', 'exists:campuses,id'],
            'name' => ['required', 'string', 'max:180'],
            'leader_id' => ['nullable', 'exists:members,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $actor = $request->user();
        if (! $actor?->isSuperAdministrator()) {
            $validated['church_id'] = $actor?->church_id;

            if ($actor?->campus_id !== null) {
                $validated['campus_id'] = $actor->campus_id;
            }
        }

        $campus = Campus::query()->findOrFail($validated['campus_id']);
        $validated['church_id'] = $campus->church_id;

        abort_unless($actor?->canAccessChurch($campus->church_id) && $actor->canAccessCampus($campus->id), 403);
        abort_unless($this->campusQuery($request)->whereKey($campus->id)->exists(), 403);

        if (! empty($validated['leader_id'])) {
            abort_unless(
                $this->leaderQuery($request)
                    ->whereKey($validated['leader_id'])
                    ->where('church_id', $validated['church_id'])
                    ->where('campus_id', $campus->id)
                    ->exists(),
                403,
            );
        }

        $duplicate = Ministry::query()
            ->where('church_id', $validated['church_id'])
            ->where('campus_id', $validated['campus_id'])
            ->whereRaw('LOWER(name) = ?', [strtolower((string) $validated['name'])])
            ->when($ministry, fn (Builder $query) => $query->whereKeyNot($ministry->id))
            ->exists();
        abort_if($duplicate, 422, 'A ministry with this name already exists for this campus.');

        return $validated;
    }

    /**
     * @return Builder<Ministry>
     */
    private function ministryQuery(Request $request): Builder
    {
        $query = Ministry::query();
        $user = $request->user();

        if ($user?->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $user?->church_id);

        if ($user?->campus_id !== null) {
            $query->where('campus_id', $user->campus_id);
        }

        return $query;
    }

    /**
     * @return Builder<Campus>
     */
    private function campusQuery(Request $request): Builder
    {
        $query = Campus::query();
        $user = $request->user();

        if ($user?->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $user?->church_id);

        if ($user?->campus_id !== null) {
            $query->whereKey($user->campus_id);
        }

        return $query;
    }

    /**
     * @return Builder<Member>
     */
    private function leaderQuery(Request $request): Builder
    {
        $query = Member::query()->where('status', '!=', 'archived');
        $user = $request->user();

        if ($user?->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $user?->church_id);

        if ($user?->campus_id !== null) {
            $query->where('campus_id', $user->campus_id);
        }

        return $query;
    }
}
