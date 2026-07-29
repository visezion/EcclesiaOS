<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOperationalRecords;
use App\Models\ChildrenYouthRecord;
use App\Models\Member;
use App\Services\ActivityLogger;
use App\Support\Csv;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ChildrenYouthController extends Controller
{
    use ScopesOperationalRecords;

    private const AGE_GROUPS = ['nursery', 'preschool', 'elementary', 'middle_school', 'high_school', 'young_adult'];

    private const CONSENT_STATUSES = ['pending', 'approved', 'expired', 'declined'];

    private const CHECK_IN_STATUSES = ['not_checked_in', 'checked_in', 'checked_out'];

    private const STATUSES = ['active', 'inactive', 'archived'];

    public function index(Request $request): View
    {
        $this->authorizePermission($request, 'manage youth');

        $query = $this->scopeChurchCampus(ChildrenYouthRecord::query(), $request)->with(['campus', 'member', 'guardian']);
        $this->applyFilters($query, $request);

        $records = $query->latest()->paginate(12)->withQueryString();
        $base = $this->scopeChurchCampus(ChildrenYouthRecord::query(), $request);

        return view('children-youth.index', [
            'records' => $records,
            'members' => $this->visibleMembers($request)->limit(300)->get(),
            'campuses' => $this->visibleCampuses($request)->get(),
            'ageGroups' => self::AGE_GROUPS,
            'consentStatuses' => self::CONSENT_STATUSES,
            'checkInStatuses' => self::CHECK_IN_STATUSES,
            'statuses' => self::STATUSES,
            'stats' => [
                'total' => (clone $base)->where('status', 'active')->count(),
                'checked_in' => (clone $base)->where('check_in_status', 'checked_in')->count(),
                'consent_pending' => (clone $base)->where('consent_status', 'pending')->count(),
                'medical_notes' => (clone $base)->whereNotNull('medical_notes')->where('medical_notes', '!=', '')->count(),
            ],
            'ageRows' => $this->ageRows($request),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Child & Youth', 'url' => null],
            ],
        ]);
    }

    public function overview(Request $request): View
    {
        $this->authorizePermission($request, 'manage youth');

        $base = $this->scopeChurchCampus(ChildrenYouthRecord::query(), $request);

        return view('children-youth.overview', [
            'stats' => [
                'total' => (clone $base)->where('status', 'active')->count(),
                'checked_in' => (clone $base)->where('check_in_status', 'checked_in')->count(),
                'consent_pending' => (clone $base)->where('consent_status', 'pending')->count(),
                'medical_notes' => (clone $base)->whereNotNull('medical_notes')->where('medical_notes', '!=', '')->count(),
            ],
            'ageRows' => $this->ageRows($request),
            'consentRows' => $this->consentRows($request),
            'recentRecords' => $this->scopeChurchCampus(ChildrenYouthRecord::query(), $request)->with(['campus', 'guardian'])->latest()->limit(8)->get(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Child & Youth', 'url' => route('children-youth.index')],
                ['label' => 'Overview', 'url' => null],
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizePermission($request, 'manage youth');

        return view('children-youth.create', $this->recordFormData($request) + [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Child & Youth', 'url' => route('children-youth.index')],
                ['label' => 'Create', 'url' => null],
            ],
        ]);
    }

    public function edit(Request $request, ChildrenYouthRecord $record): View
    {
        $this->authorizePermission($request, 'manage youth');
        $this->authorizeScopedRecord($request, $record);

        return view('children-youth.edit', $this->recordFormData($request, $record) + [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Child & Youth', 'url' => route('children-youth.index')],
                ['label' => $record->first_name.' '.$record->last_name, 'url' => null],
            ],
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage youth');
        $record = ChildrenYouthRecord::query()->create($this->validatedRecord($request));

        $activityLogger->log('Children & Youth', 'youth_record_created', $record->first_name.' '.$record->last_name.' was added.', $record, ['resource' => 'Youth Record', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Child/youth record added.');
    }

    public function update(Request $request, ChildrenYouthRecord $record, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage youth');
        $this->authorizeScopedRecord($request, $record);
        $record->update($this->validatedRecord($request, $record));

        $activityLogger->log('Children & Youth', 'youth_record_updated', $record->first_name.' '.$record->last_name.' was updated.', $record, ['resource' => 'Youth Record', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Child/youth record updated.');
    }

    public function destroy(Request $request, ChildrenYouthRecord $record, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage youth');
        $this->authorizeScopedRecord($request, $record);
        $name = $record->first_name.' '.$record->last_name;
        $record->delete();

        $activityLogger->log('Children & Youth', 'youth_record_archived', $name.' was archived.', null, ['resource' => 'Youth Record', 'risk' => 'medium', 'status' => 'success'], $request);

        return back()->with('status', 'Child/youth record archived.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizePermission($request, 'manage youth');

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            Csv::write($handle, ['Name', 'Age Group', 'Campus', 'Guardian', 'Guardian Phone', 'Consent', 'Check-in', 'Pickup Code', 'Status']);
            $query = $this->scopeChurchCampus(ChildrenYouthRecord::query(), $request)->with(['campus', 'guardian'])->latest();
            $this->applyFilters($query, $request);
            $query->lazy(100)->each(fn (ChildrenYouthRecord $record) => Csv::write($handle, [
                $record->first_name.' '.$record->last_name,
                $record->age_group,
                $record->campus?->name,
                $record->guardian ? $record->guardian->first_name.' '.$record->guardian->last_name : $record->guardian_name,
                $record->guardian_phone,
                $record->consent_status,
                $record->check_in_status,
                $record->pickup_code,
                $record->status,
            ]));
            fclose($handle);
        }, 'children-youth-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function validatedRecord(Request $request, ?ChildrenYouthRecord $record = null): array
    {
        $validated = $request->validate([
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'member_id' => ['nullable', 'integer', 'exists:members,id'],
            'guardian_member_id' => ['nullable', 'integer', 'exists:members,id'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'age_group' => ['required', Rule::in(self::AGE_GROUPS)],
            'guardian_name' => ['nullable', 'string', 'max:160'],
            'guardian_phone' => ['nullable', 'string', 'max:80'],
            'consent_status' => ['required', Rule::in(self::CONSENT_STATUSES)],
            'check_in_status' => ['required', Rule::in(self::CHECK_IN_STATUSES)],
            'pickup_code' => ['nullable', 'string', 'max:80'],
            'medical_notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        $validated['church_id'] = $this->defaultChurchId($request);
        $validated['campus_id'] = $this->validatedCampusId($request, $validated['campus_id'] ?? null);

        if (! empty($validated['member_id'])) {
            abort_unless($this->visibleMembers($request)->whereKey($validated['member_id'])->exists(), 403);
            $member = Member::query()->find($validated['member_id']);
            $validated['campus_id'] ??= $member?->campus_id;
            $duplicate = ChildrenYouthRecord::query()
                ->where('member_id', $validated['member_id'])
                ->when($record, fn (Builder $query) => $query->whereKeyNot($record->id))
                ->exists();
            abort_if($duplicate, 422, 'This member already has a children/youth record.');
        }

        if (! empty($validated['guardian_member_id'])) {
            abort_unless($this->visibleMembers($request)->whereKey($validated['guardian_member_id'])->exists(), 403);
        }

        return $validated;
    }

    private function recordFormData(Request $request, ?ChildrenYouthRecord $record = null): array
    {
        return [
            'record' => $record,
            'members' => $this->visibleMembers($request)->limit(300)->get(),
            'campuses' => $this->visibleCampuses($request)->get(),
            'ageGroups' => self::AGE_GROUPS,
            'consentStatuses' => self::CONSENT_STATUSES,
            'checkInStatuses' => self::CHECK_IN_STATUSES,
            'statuses' => self::STATUSES,
        ];
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('q'), function (Builder $query) use ($request): void {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(fn (Builder $search) => $search
                ->where('first_name', 'like', $term)
                ->orWhere('last_name', 'like', $term)
                ->orWhere('guardian_name', 'like', $term)
                ->orWhere('guardian_phone', 'like', $term));
        });
        $query->when($request->filled('age_group'), fn (Builder $query) => $query->where('age_group', $request->string('age_group')));
        $query->when($request->filled('consent_status'), fn (Builder $query) => $query->where('consent_status', $request->string('consent_status')));
        $query->when($request->filled('check_in_status'), fn (Builder $query) => $query->where('check_in_status', $request->string('check_in_status')));
        $query->when($request->filled('campus_id'), fn (Builder $query) => $query->where('campus_id', (int) $request->query('campus_id')));
    }

    private function ageRows(Request $request): array
    {
        $total = max($this->scopeChurchCampus(ChildrenYouthRecord::query(), $request)->count(), 1);

        return $this->scopeChurchCampus(ChildrenYouthRecord::query(), $request)
            ->select('age_group', DB::raw('count(*) as total'))
            ->groupBy('age_group')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'label' => str((string) $row->age_group)->headline()->toString(),
                'value' => (int) $row->total,
                'percent' => round(((int) $row->total / $total) * 100, 1),
            ])
            ->all();
    }

    private function consentRows(Request $request): array
    {
        $total = max($this->scopeChurchCampus(ChildrenYouthRecord::query(), $request)->count(), 1);

        return collect(self::CONSENT_STATUSES)->map(function (string $status) use ($request, $total): array {
            $count = $this->scopeChurchCampus(ChildrenYouthRecord::query(), $request)->where('consent_status', $status)->count();

            return [
                'label' => str($status)->headline()->toString(),
                'value' => $count,
                'percent' => round(($count / $total) * 100, 1),
            ];
        })->all();
    }
}
