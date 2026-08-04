<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Church;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\OrganizationTerminology;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CampusManagementController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorizeCampuses($request);

        $campuses = $this->campusQuery($request)
            ->with(['church', 'users.roles'])
            ->withCount(['users', 'members'])
            ->orderBy('name')
            ->get();
        $users = $this->userQuery($request)->with(['church', 'campus', 'roles'])->orderBy('name')->get();
        $roles = Role::query()->withCount('users')->orderBy('name')->get();
        $terminology = OrganizationTerminology::forRequest($request);

        return view('admin.campuses', [
            'churches' => $this->churchQuery($request)->withCount('campuses')->orderBy('name')->get(),
            'campuses' => $campuses,
            'users' => $users,
            'roles' => $roles,
            'terminology' => $terminology,
            'stats' => [
                'churches' => $this->churchQuery($request)->count(),
                'campuses' => $campuses->count(),
                'assigned' => $users->whereNotNull('campus_id')->count(),
                'active' => $users->where('status', 'active')->whereNotNull('campus_id')->count(),
                'pending' => $users->where('status', 'inactive')->count(),
                'unassigned' => $users->filter(fn (User $user): bool => $user->church_id === null || $user->campus_id === null)->count(),
            ],
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Churches & '.$terminology['campus_plural'], 'url' => null],
            ],
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeCampuses($request);
        abort_if(! $request->user()?->isSuperAdministrator() && $request->user()?->campus_id !== null, 403);

        $validated = $this->validatedCampus($request, true);

        if ($request->user()?->isSuperAdministrator()) {
            $church = filled($validated['church_id'] ?? null)
                ? Church::query()->findOrFail($validated['church_id'])
                : Church::query()->create([
                    'name' => $validated['church_name'],
                    'slug' => $this->uniqueSlug(Church::class, $validated['church_name']),
                    'timezone' => config('church.timezone'),
                    'currency' => config('church.currency'),
                ]);
        } else {
            $church = Church::query()->findOrFail($request->user()->church_id);
        }

        $campus = Campus::query()->create([
            'church_id' => $church->id,
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug(Campus::class, $validated['name'], ['church_id' => $church->id]),
            'type' => $validated['type'],
            'city' => $validated['city'],
            'country' => $validated['country'],
            'address' => $validated['address'],
            'capacity' => $validated['capacity'] ?? null,
            'status' => $validated['status'],
            'map_x' => random_int(28, 72),
            'map_y' => random_int(42, 72),
        ]);

        $activityLogger->log('Campuses', 'campus_created', $campus->name.' was created.', $campus, ['resource' => 'Campus', 'risk' => 'low', 'status' => 'success'], $request);

        $terminology = OrganizationTerminology::forRequest($request);

        return back()->with('status', $terminology['campus_singular'].' created.');
    }

    public function update(Request $request, Campus $campus, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeMutableCampus($request, $campus);
        $validated = $this->validatedCampus($request);
        $churchId = $request->user()?->isSuperAdministrator()
            ? (int) $validated['church_id']
            : (int) $request->user()->church_id;

        $campus->update([
            'church_id' => $churchId,
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug(Campus::class, $validated['name'], ['church_id' => $churchId], $campus->id),
            'type' => $validated['type'],
            'city' => $validated['city'],
            'country' => $validated['country'],
            'address' => $validated['address'],
            'capacity' => $validated['capacity'] ?? null,
            'status' => $validated['status'],
        ]);

        $activityLogger->log('Campuses', 'campus_updated', $campus->name.' was updated.', $campus, ['resource' => 'Campus', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', OrganizationTerminology::forRequest($request)['campus_singular'].' updated.');
    }

    public function destroy(Request $request, Campus $campus, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeMutableCampus($request, $campus);

        if ($this->dependentRecordCount('campus_id', $campus->id, ['campuses', 'activity_logs']) > 0) {
            throw ValidationException::withMessages([
                'campus' => 'Reassign or remove this campus\'s users and related records before deleting it.',
            ]);
        }

        $name = $campus->name;
        $campus->delete();
        $activityLogger->log('Campuses', 'campus_deleted', $name.' was deleted.', null, ['resource' => 'Campus', 'risk' => 'medium', 'status' => 'success'], $request);

        return back()->with('status', OrganizationTerminology::forRequest($request)['campus_singular'].' deleted.');
    }

    public function updateChurch(Request $request, Church $church, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeMutableChurch($request, $church);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'max:100'],
            'currency' => ['required', 'string', 'size:3'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $church->update([
            ...$validated,
            'currency' => Str::upper($validated['currency']),
            'slug' => $this->uniqueSlug(Church::class, $validated['name'], [], $church->id),
        ]);

        $activityLogger->log('Campuses', 'church_updated', $church->name.' was updated.', $church, ['resource' => 'Church', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Church updated.');
    }

    public function destroyChurch(Request $request, Church $church, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeMutableChurch($request, $church, true);

        if ($this->dependentRecordCount('church_id', $church->id, ['churches', 'activity_logs']) > 0) {
            throw ValidationException::withMessages([
                'church' => 'Delete or move this church\'s campuses, users, and related records before deleting it.',
            ]);
        }

        $name = $church->name;
        $church->delete();
        $activityLogger->log('Campuses', 'church_deleted', $name.' was deleted.', null, ['resource' => 'Church', 'risk' => 'high', 'status' => 'success'], $request);

        return back()->with('status', 'Church deleted.');
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorizeCampuses($request);
        abort_if(! $request->user()?->isSuperAdministrator() && $request->user()?->campus_id !== null, 403);

        $validated = $request->validate([
            'import_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $handle = fopen($validated['import_file']->getRealPath(), 'r');
        $imported = 0;

        if ($handle === false) {
            return back()->withErrors(['import_file' => 'The import file could not be opened.']);
        }

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 6 || strcasecmp((string) $row[0], 'church') === 0) {
                continue;
            }

            [$churchName, $campusName, $type, $city, $country, $status] = array_pad($row, 7, null);

            if (! filled($campusName) || ($request->user()?->isSuperAdministrator() && ! filled($churchName))) {
                continue;
            }

            if ($request->user()?->isSuperAdministrator()) {
                $church = Church::query()->firstOrCreate(
                    ['slug' => Str::slug((string) $churchName)],
                    [
                        'name' => (string) $churchName,
                        'timezone' => config('church.timezone'),
                        'currency' => config('church.currency'),
                    ],
                );
            } else {
                $church = Church::query()->findOrFail($request->user()->church_id);
            }

            Campus::query()->updateOrCreate(
                ['church_id' => $church->id, 'slug' => Str::slug((string) $campusName)],
                [
                    'name' => (string) $campusName,
                    'type' => filled($type) ? (string) $type : 'Regional Campus',
                    'city' => filled($city) ? (string) $city : 'Dallas',
                    'country' => filled($country) ? (string) $country : 'USA',
                    'address' => filled($city) ? (string) $city.', TX' : 'Dallas, TX',
                    'status' => strtolower((string) ($status ?: 'active')),
                    'map_x' => random_int(28, 72),
                    'map_y' => random_int(42, 72),
                ],
            );

            $imported++;
        }

        fclose($handle);

        $terminology = OrganizationTerminology::forRequest($request);

        return back()->with('status', number_format($imported).' '.Str::lower($terminology['campus_plural']).' imported.');
    }

    /**
     * @param  class-string<Church|Campus>  $model
     * @param  array<string, mixed>  $scope
     */
    private function uniqueSlug(string $model, string $name, array $scope = [], ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $index = 2;

        while ($model::query()->where($scope)->where('slug', $slug)->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$index}";
            $index++;
        }

        return $slug;
    }

    private function authorizeCampuses(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('manage campuses'), 403);
    }

    private function authorizeMutableCampus(Request $request, Campus $campus): void
    {
        $this->authorizeCampuses($request);
        abort_if(! $request->user()?->isSuperAdministrator() && $request->user()?->campus_id !== null, 403);
        abort_unless($this->campusQuery($request)->whereKey($campus->id)->exists(), 404);
    }

    private function authorizeMutableChurch(Request $request, Church $church, bool $deleting = false): void
    {
        $this->authorizeCampuses($request);
        abort_if($deleting && ! $request->user()?->isSuperAdministrator(), 403);
        abort_if(! $request->user()?->isSuperAdministrator() && $request->user()?->campus_id !== null, 403);
        abort_unless($this->churchQuery($request)->whereKey($church->id)->exists(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedCampus(Request $request, bool $creating = false): array
    {
        return $request->validate([
            'church_id' => [$creating ? 'nullable' : 'required', 'exists:churches,id'],
            'church_name' => [$creating ? 'required_without:church_id' : 'nullable', 'nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    /**
     * Count live records in every table that directly references the organization key.
     *
     * @param  list<string>  $excludedTables
     */
    private function dependentRecordCount(string $column, int $id, array $excludedTables): int
    {
        $count = 0;

        foreach (Schema::getTableListing() as $table) {
            $tableName = Str::afterLast((string) $table, '.');

            if (in_array($tableName, $excludedTables, true) || ! Schema::hasColumn($tableName, $column)) {
                continue;
            }

            $query = DB::table($tableName)->where($column, $id);

            if (Schema::hasColumn($tableName, 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            $count += $query->count();
        }

        return $count;
    }

    /**
     * @return Builder<Church>
     */
    private function churchQuery(Request $request): Builder
    {
        $query = Church::query();
        $user = $request->user();

        if ($user && ! $user->isSuperAdministrator()) {
            $query->whereKey($user->church_id);
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

        if ($user && ! $user->isSuperAdministrator()) {
            $query->where('church_id', $user->church_id);

            if ($user->campus_id) {
                $query->whereKey($user->campus_id);
            }
        }

        return $query;
    }

    /**
     * @return Builder<User>
     */
    private function userQuery(Request $request): Builder
    {
        $query = User::query();
        $user = $request->user();

        if ($user && ! $user->isSuperAdministrator()) {
            $query->where('church_id', $user->church_id);

            if ($user->campus_id) {
                $query->where('campus_id', $user->campus_id);
            }
        }

        return $query;
    }
}
