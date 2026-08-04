<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Member;
use App\Models\Ministry;
use App\Services\ActivityLogger;
use App\Support\OrganizationTerminology;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

    public function import(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeMinistries($request);
        $validated = $request->validate([
            'campus_id' => ['required', 'exists:campuses,id'],
            'import_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);
        $campus = $this->authorizedCampus($request, (int) $validated['campus_id']);
        $handle = fopen($validated['import_file']->getRealPath(), 'r');

        if ($handle === false) {
            return back()->withErrors(['import_file' => 'The ministry import file could not be opened.']);
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);

            return back()->withErrors(['import_file' => 'The ministry import file is empty.']);
        }

        $headers = array_map(fn ($header): string => $this->normalizeImportHeader((string) $header), $headers);
        $nameColumn = array_search('name', $headers, true);

        if ($nameColumn === false) {
            fclose($handle);

            throw ValidationException::withMessages([
                'import_file' => 'The CSV must include a name or ministry column.',
            ]);
        }

        $imported = 0;
        $skipped = 0;
        $rowNumber = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($rowNumber > 5001) {
                $skipped++;

                continue;
            }

            $values = array_pad($values, count($headers), null);
            $row = array_combine($headers, array_slice($values, 0, count($headers)));
            $name = trim((string) ($row['name'] ?? ''));
            $status = Str::lower(trim((string) ($row['status'] ?? 'active')));

            if ($name === '' || ! in_array($status, ['active', 'inactive'], true)) {
                $skipped++;

                continue;
            }

            $duplicate = Ministry::query()
                ->where('church_id', $campus->church_id)
                ->where('campus_id', $campus->id)
                ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
                ->exists();

            if ($duplicate) {
                $skipped++;

                continue;
            }

            $leaderId = null;
            $leaderEmail = trim((string) ($row['leader_email'] ?? ''));

            if ($leaderEmail !== '') {
                $leaderId = Member::query()
                    ->where('church_id', $campus->church_id)
                    ->where('campus_id', $campus->id)
                    ->whereRaw('LOWER(email) = ?', [Str::lower($leaderEmail)])
                    ->value('id');
            }

            Ministry::query()->create([
                'church_id' => $campus->church_id,
                'campus_id' => $campus->id,
                'name' => $name,
                'leader_id' => $leaderId,
                'description' => filled($row['description'] ?? null) ? trim((string) $row['description']) : null,
                'status' => $status,
            ]);
            $imported++;
        }

        fclose($handle);
        $activityLogger->log('Ministries', 'ministries_imported', $imported.' ministries were imported into '.$campus->name.'.', $campus, [
            'resource' => 'Ministry Import',
            'risk' => 'low',
            'status' => 'success',
            'imported' => $imported,
            'skipped' => $skipped,
        ], $request);

        return back()->with('status', $imported.' ministries imported into '.$campus->name.'; '.$skipped.' rows skipped.');
    }

    public function cloneCampus(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeMinistries($request);
        $validated = $request->validate([
            'source_campus_id' => ['required', 'exists:campuses,id'],
            'target_campus_id' => ['required', 'different:source_campus_id', 'exists:campuses,id'],
        ]);
        $source = $this->authorizedCampus($request, (int) $validated['source_campus_id']);
        $target = $this->authorizedCampus($request, (int) $validated['target_campus_id']);

        if ($source->church_id !== $target->church_id) {
            throw ValidationException::withMessages([
                'target_campus_id' => 'Ministries can only be cloned between campuses in the same church.',
            ]);
        }

        $sourceMinistries = $this->ministryQuery($request)
            ->where('campus_id', $source->id)
            ->orderBy('id')
            ->get();
        $targetNames = Ministry::query()
            ->where('church_id', $target->church_id)
            ->where('campus_id', $target->id)
            ->pluck('name')
            ->map(fn (string $name): string => Str::lower(trim($name)))
            ->all();
        $cloned = 0;
        $skipped = 0;

        DB::transaction(function () use ($sourceMinistries, $target, &$targetNames, &$cloned, &$skipped): void {
            foreach ($sourceMinistries as $ministry) {
                $normalizedName = Str::lower(trim($ministry->name));

                if (in_array($normalizedName, $targetNames, true)) {
                    $skipped++;

                    continue;
                }

                Ministry::query()->create([
                    'church_id' => $target->church_id,
                    'campus_id' => $target->id,
                    'name' => $ministry->name,
                    'leader_id' => null,
                    'description' => $ministry->description,
                    'status' => $ministry->status,
                ]);
                $targetNames[] = $normalizedName;
                $cloned++;
            }
        });

        $activityLogger->log('Ministries', 'campus_ministries_cloned', $cloned.' ministries were cloned from '.$source->name.' to '.$target->name.'.', $target, [
            'resource' => 'Ministry Clone',
            'risk' => 'medium',
            'status' => 'success',
            'source_campus_id' => $source->id,
            'target_campus_id' => $target->id,
            'cloned' => $cloned,
            'skipped' => $skipped,
        ], $request);

        return back()->with('status', $cloned.' ministries cloned to '.$target->name.'; '.$skipped.' existing '.Str::plural('ministry', $skipped).' skipped.');
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

    private function authorizedCampus(Request $request, int $campusId): Campus
    {
        $campus = $this->campusQuery($request)->whereKey($campusId)->first();
        abort_unless($campus, 403);

        return $campus;
    }

    private function normalizeImportHeader(string $header): string
    {
        $normalized = Str::of($header)
            ->replace("\xEF\xBB\xBF", '')
            ->trim()
            ->lower()
            ->replace([' ', '-'], '_')
            ->toString();

        return match ($normalized) {
            'ministry', 'ministry_name' => 'name',
            'leader', 'leaderemail', 'leader_e_mail' => 'leader_email',
            default => $normalized,
        };
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
