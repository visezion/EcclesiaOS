<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Campus;
use App\Models\CareTask;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Family;
use App\Models\Member;
use App\Models\Ministry;
use App\Models\PrayerRequest;
use App\Models\User;
use App\Models\Volunteer;
use App\Services\ActivityLogger;
use App\Support\OpaqueId;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MemberManagementController extends Controller
{
    private const STATUSES = ['active', 'new', 'inactive', 'follow-up', 'archived'];

    private const PROFILE_INPUTS = [
        'preferred_name',
        'date_of_birth',
        'gender',
        'marital_status',
        'anniversary_date',
        'occupation',
        'employer',
        'place_of_birth',
        'nationality',
        'address_line',
        'city',
        'state',
        'postal_code',
        'country',
        'alternate_email',
        'home_phone',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'emergency_contact_alt_phone',
        'care_level',
        'care_notes',
        'volunteer_hours',
        'skills',
        'preferred_contact',
        'email_notifications',
        'sms_notifications',
        'mailing_mail',
        'salvation_date',
        'baptism_date',
        'discipleship_class',
        'membership_class',
    ];

    public function index(Request $request): View
    {
        $this->authorizeMembers($request);

        $query = Member::query()
            ->with(['church', 'campus', 'family', 'volunteers.ministry', 'memberProfile'])
            ->withCount([
                'attendanceRecords as attendance_30_days_count' => fn ($query) => $query
                    ->where('status', 'present')
                    ->whereDate('service_date', '>=', now()->subDays(30)),
                'donations as gifts_this_year_count' => fn ($query) => $query
                    ->whereDate('received_at', '>=', now()->startOfYear()),
            ])
            ->withSum([
                'donations as giving_this_year_total' => fn ($query) => $query
                    ->whereDate('received_at', '>=', now()->startOfYear()),
            ], 'amount');

        $this->scopeMembers($query, $request);
        $this->applyFilters($query, $request);

        $perPage = min(50, max(10, (int) $request->integer('per_page', 10)));
        $members = $query->latest('joined_at')->latest()->paginate($perPage)->withQueryString();
        $members->setCollection($members->getCollection()->map(fn (Member $member): array => $this->memberRow($member)));

        $selectedMember = null;
        $selectedMode = null;
        $editId = OpaqueId::decode($request->query('edit'), Member::class);
        $viewId = OpaqueId::decode($request->query('view'), Member::class);
        $selectedId = $editId ?: $viewId;
        if ($selectedId > 0) {
            $selected = $this->scopeMembers(Member::query()->with(['church', 'campus', 'family', 'volunteers.ministry']), $request)->find($selectedId);
            if ($selected instanceof Member) {
                $selectedMember = $this->memberRow($selected);
                $selectedMode = $editId ? 'edit' : 'view';
            }
        }

        return view('members.index', [
            'members' => $members,
            'stats' => $this->stats($request),
            'campuses' => $this->visibleCampuses($request)->get(),
            'churches' => $this->visibleChurches($request)->get(),
            'families' => $this->visibleFamilies($request)->get(),
            'ministries' => $this->visibleMinistries($request)->get(),
            'statusDistribution' => $this->statusDistribution($request),
            'campusDistribution' => $this->campusDistribution($request),
            'recentActivity' => ActivityLog::query()->with('user')->where('module', 'Members')->latest()->limit(6)->get(),
            'selectedMember' => $selectedMember,
            'selectedMode' => $selectedMode,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Members', 'url' => null],
                ['label' => 'Members Management', 'url' => null],
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeMembers($request);

        return view('members.create', [
            'churches' => $this->visibleChurches($request)->get(),
            'campuses' => $this->visibleCampuses($request)->get(),
            'families' => $this->visibleFamilies($request)->get(),
            'ministries' => $this->visibleMinistries($request)->get(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Members', 'url' => route('members.index')],
                ['label' => 'Add New Member', 'url' => null],
            ],
        ]);
    }

    public function show(Request $request, Member $member): View
    {
        $this->authorizeMembers($request);
        $this->authorizeMemberRecord($request, $member);

        $member->load(['church', 'campus', 'family.members', 'memberProfile', 'volunteers.ministry', 'attendanceRecords', 'donations', 'prayerRequests', 'careTasks.assignedUser']);

        return view('members.show', [
            'member' => $member,
            'profile' => $this->memberRow($member),
            'churches' => $this->visibleChurches($request)->get(),
            'campuses' => $this->visibleCampuses($request)->get(),
            'families' => $this->visibleFamilies($request)->get(),
            'ministries' => $this->visibleMinistries($request)->get(),
            'users' => $this->visibleUsers($request)->get(),
            'givingTotal' => Donation::query()->where('member_id', $member->id)->sum('amount'),
            'attendanceHistory' => AttendanceRecord::query()->where('member_id', $member->id)->latest('service_date')->limit(12)->get(),
            'recentActivity' => ActivityLog::query()->where('subject_type', $member->getMorphClass())->where('subject_id', $member->id)->latest()->limit(8)->get(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Members', 'url' => route('members.index')],
                ['label' => 'Member Profile', 'url' => null],
            ],
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeMembers($request);

        $validated = $this->validatedMember($request);
        $member = DB::transaction(function () use ($validated): Member {
            $member = Member::query()->create($this->memberPayload($validated));
            $this->syncMemberProfile($member, $validated);
            $this->syncMinistry($member, $validated['ministry_id'] ?? null);

            return $member;
        });

        $activityLogger->log('Members', 'member_created', $member->first_name.' '.$member->last_name.' was added as a member.', $member, [
            'resource' => 'Member Profile',
            'risk' => 'low',
            'status' => 'success',
        ], $request);

        return redirect()->route('members.index', ['view' => OpaqueId::encode($member->id, Member::class)])->with('status', 'Member created.');
    }

    public function update(Request $request, Member $member, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeMembers($request);
        $this->authorizeMemberRecord($request, $member);

        $validated = $this->validatedMember($request, $member);
        DB::transaction(function () use ($member, $validated): void {
            $member->update($this->memberPayload($validated));
            $this->syncMemberProfile($member, $validated);
            $this->syncMinistry($member, $validated['ministry_id'] ?? null);
        });

        $activityLogger->log('Members', 'member_updated', $member->fresh()->first_name.' '.$member->fresh()->last_name.' profile was updated.', $member, [
            'resource' => 'Member Profile',
            'risk' => 'low',
            'status' => 'success',
        ], $request);

        return redirect()->route('members.index', ['view' => OpaqueId::encode($member->id, Member::class)])->with('status', 'Member updated.');
    }

    public function destroy(Request $request, Member $member, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeMembers($request);
        $this->authorizeMemberRecord($request, $member);

        $name = $member->first_name.' '.$member->last_name;
        $member->delete();

        $activityLogger->log('Members', 'member_deleted', $name.' was removed from the members directory.', $member, [
            'resource' => 'Member Directory',
            'risk' => 'medium',
            'status' => 'success',
        ], $request);

        return redirect()->route('members.index')->with('status', 'Member removed.');
    }

    public function checkIn(Request $request, Member $member, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeMembers($request);
        $this->authorizeMemberRecord($request, $member);

        AttendanceRecord::query()->updateOrCreate(
            ['church_id' => $member->church_id, 'campus_id' => $member->campus_id, 'member_id' => $member->id, 'service_date' => today()->toDateString()],
            ['status' => 'present', 'checked_in_at' => now(), 'metadata' => ['source' => 'member profile']]
        );

        $activityLogger->log('Members', 'member_checked_in', $member->first_name.' '.$member->last_name.' was checked in.', $member, ['resource' => 'Attendance', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Member checked in.');
    }

    public function assignMinistry(Request $request, Member $member, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeMembers($request);
        $this->authorizeMemberRecord($request, $member);

        $validated = $request->validate(['ministry_id' => ['required', 'exists:ministries,id']]);
        abort_unless($this->visibleMinistries($request)->where('id', $validated['ministry_id'])->exists(), 403);
        $this->syncMinistry($member, $validated['ministry_id']);

        $activityLogger->log('Members', 'member_ministry_assigned', $member->first_name.' '.$member->last_name.' was assigned to a ministry.', $member, ['resource' => 'Ministry Assignment', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Ministry assigned.');
    }

    public function bulk(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeMembers($request);

        $validated = $request->validate([
            'members' => ['required', 'array', 'min:1'],
            'members.*' => ['required', 'string'],
            'action' => ['required', Rule::in(['activate', 'inactive', 'follow-up', 'archive', 'delete'])],
        ]);

        $memberIds = OpaqueId::decodeMany($validated['members'], Member::class);
        if ($memberIds === []) {
            throw ValidationException::withMessages(['members' => 'Select at least one valid member.']);
        }

        $members = $this->scopeMembers(Member::query()->whereIn('id', $memberIds), $request)->get();
        foreach ($members as $member) {
            if ($validated['action'] === 'delete') {
                $member->delete();
            } else {
                $member->update(['status' => $validated['action'] === 'activate' ? 'active' : $validated['action']]);
            }
        }

        $activityLogger->log('Members', 'member_bulk_action', 'Bulk member action completed for '.$members->count().' records.', null, [
            'resource' => 'Members Directory',
            'risk' => $validated['action'] === 'delete' ? 'medium' : 'low',
            'status' => 'success',
            'action' => $validated['action'],
        ], $request);

        return back()->with('status', 'Bulk action applied to '.$members->count().' members.');
    }

    public function import(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeMembers($request);

        $validated = $request->validate([
            'members_file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
        ]);

        $church = $request->user()?->isSuperAdministrator()
            ? Church::query()->firstOrFail()
            : Church::query()->findOrFail($request->user()?->church_id);
        $created = 0;
        $handle = fopen($validated['members_file']->getRealPath(), 'r');
        if ($handle === false) {
            return back()->with('error', 'Unable to read the import file.');
        }

        $header = null;
        while (($row = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = collect($row)->map(fn (?string $value): string => str((string) $value)->snake()->toString())->all();
                continue;
            }

            $data = array_combine($header, $row) ?: [];
            $name = trim((string) ($data['full_name'] ?? $data['name'] ?? ''));
            $firstName = trim((string) ($data['first_name'] ?? str($name)->before(' ')));
            $lastName = trim((string) ($data['last_name'] ?? str($name)->after(' ')));

            if ($firstName === '' || $lastName === '') {
                continue;
            }

            Member::query()->updateOrCreate(
                ['email' => filled($data['email'] ?? null) ? (string) $data['email'] : null, 'first_name' => $firstName, 'last_name' => $lastName],
                [
                    'church_id' => $church->id,
                    'campus_id' => $this->visibleCampuses($request)->where('name', $data['campus'] ?? '')->value('id') ?: $this->visibleCampuses($request)->value('id'),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => filled($data['email'] ?? null) ? (string) $data['email'] : null,
                    'phone' => (string) ($data['phone'] ?? ''),
                    'status' => in_array($data['status'] ?? 'active', self::STATUSES, true) ? (string) $data['status'] : 'active',
                    'joined_at' => filled($data['joined_at'] ?? null) ? (string) $data['joined_at'] : now()->toDateString(),
                ],
            );
            $created++;
        }
        fclose($handle);

        $activityLogger->log('Members', 'members_imported', $created.' members were imported.', null, [
            'resource' => 'Member Import',
            'risk' => 'low',
            'status' => 'success',
        ], $request);

        return back()->with('status', $created.' member records imported.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeMembers($request);

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Member ID',
                'Full Name',
                'Email',
                'Phone',
                'Status',
                'Campus',
                'Family',
                'Ministry',
                'Joined At',
                'Attendance 30 Days',
                'Giving Status',
                'Preferred Name',
                'Alternate Email',
                'Home Phone',
                'Gender',
                'Date of Birth',
                'Marital Status',
                'Occupation',
                'Employer',
                'Care Level',
                'Volunteer Hours',
            ]);
            $this->scopeMembers(Member::query(), $request)->with(['campus', 'family', 'memberProfile', 'volunteers.ministry'])->orderBy('last_name')->lazy(100)->each(function (Member $member) use ($handle): void {
                $row = $this->memberRow($member);
                fputcsv($handle, [
                    $row['code'],
                    $row['name'],
                    $row['email'],
                    $row['phone'],
                    $row['status'],
                    $row['campus'],
                    $row['family'],
                    $row['ministry'],
                    $row['joined'],
                    $row['attendance'],
                    $row['givingStatus'],
                    $row['preferredName'],
                    $row['alternateEmail'],
                    $row['homePhone'],
                    $row['gender'],
                    $row['dateOfBirth'],
                    $row['marital'],
                    $row['occupation'],
                    $row['employer'],
                    $row['careLevel'],
                    $row['volunteerHours'],
                ]);
            });
            fclose($handle);
        }, 'members-directory-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function authorizeMembers(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('manage members'), 403);
    }

    private function authorizeMemberRecord(Request $request, Member $member): void
    {
        $this->authorizeMembers($request);

        $user = $request->user();
        abort_unless($user?->canAccessChurch($member->church_id) && $user->canAccessCampus($member->campus_id), 403);
    }

    private function scopeMembers(Builder $query, Request $request): Builder
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

    private function visibleFamilies(Request $request): Builder
    {
        $query = Family::query()->orderBy('name');
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

    private function visibleMinistries(Request $request): Builder
    {
        $query = Ministry::query()->where('status', 'active')->orderBy('name');
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

    private function visibleUsers(Request $request): Builder
    {
        $query = User::query()->orderBy('name');
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

    private function validatedMember(Request $request, ?Member $member = null): array
    {
        $validated = $request->validate([
            'church_id' => ['nullable', 'exists:churches,id'],
            'campus_id' => ['nullable', 'exists:campuses,id'],
            'family_id' => ['nullable', 'exists:families,id'],
            'family_name' => ['nullable', 'string', 'max:120'],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:120', Rule::unique('members', 'email')->ignore($member?->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'joined_at' => ['nullable', 'date'],
            'ministry_id' => ['nullable', 'exists:ministries,id'],
            'preferred_name' => ['nullable', 'string', 'max:120'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:40'],
            'marital_status' => ['nullable', 'string', 'max:40'],
            'anniversary_date' => ['nullable', 'date'],
            'occupation' => ['nullable', 'string', 'max:120'],
            'employer' => ['nullable', 'string', 'max:120'],
            'place_of_birth' => ['nullable', 'string', 'max:120'],
            'nationality' => ['nullable', 'string', 'max:80'],
            'address_line' => ['nullable', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:80'],
            'alternate_email' => ['nullable', 'email', 'max:120'],
            'home_phone' => ['nullable', 'string', 'max:40'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:80'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'emergency_contact_alt_phone' => ['nullable', 'string', 'max:40'],
            'care_level' => ['nullable', 'string', 'max:40'],
            'care_notes' => ['nullable', 'string', 'max:2000'],
            'volunteer_hours' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'skills' => ['nullable', 'string', 'max:600'],
            'preferred_contact' => ['nullable', Rule::in(['email', 'phone', 'mail'])],
            'email_notifications' => ['nullable', 'boolean'],
            'sms_notifications' => ['nullable', 'boolean'],
            'mailing_mail' => ['nullable', 'boolean'],
            'salvation_date' => ['nullable', 'date'],
            'baptism_date' => ['nullable', 'date'],
            'discipleship_class' => ['nullable', 'string', 'max:120'],
            'membership_class' => ['nullable', 'string', 'max:120'],
        ]);

        $actor = $request->user();
        if (! $actor?->isSuperAdministrator()) {
            $validated['church_id'] = $actor?->church_id;
            if ($actor?->campus_id !== null) {
                $validated['campus_id'] = $actor->campus_id;
            }
        }

        $church = Church::query()->find($validated['church_id'] ?? null) ?? Church::query()->firstOrFail();
        $validated['church_id'] = $church->id;
        $validated['campus_id'] = $validated['campus_id'] ?? Campus::query()->where('church_id', $church->id)->value('id');
        $validated['joined_at'] = $validated['joined_at'] ?? now()->toDateString();

        abort_unless($actor?->canAccessChurch((int) $validated['church_id']) && $actor->canAccessCampus($validated['campus_id'] ?? null), 403);

        if (! empty($validated['family_id'])) {
            abort_unless($this->visibleFamilies($request)->where('id', $validated['family_id'])->exists(), 403);
        }

        if (! empty($validated['ministry_id'])) {
            abort_unless($this->visibleMinistries($request)->where('id', $validated['ministry_id'])->exists(), 403);
        }

        if (filled($validated['family_name'] ?? null)) {
            $family = Family::query()->firstOrCreate(
                ['church_id' => $church->id, 'name' => $validated['family_name']],
                ['campus_id' => $validated['campus_id'] ?? null],
            );
            $validated['family_id'] = $family->id;
        }

        return $validated;
    }

    private function applyFilters($query, Request $request): void
    {
        $query->when($request->filled('q'), function ($query) use ($request): void {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(function ($query) use ($term): void {
                $query->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        });

        $query->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')));
        $query->when($this->queryId($request, 'campus_id', Campus::class), fn ($query, int $campusId) => $query->where('campus_id', $campusId));
        $query->when($this->queryId($request, 'family_id', Family::class), fn ($query, int $familyId) => $query->where('family_id', $familyId));
        $query->when($this->queryId($request, 'ministry_id', Ministry::class), fn ($query, int $ministryId) => $query->whereHas('volunteers', fn ($query) => $query->where('ministry_id', $ministryId)));
        $query->when($request->filled('engagement'), function ($query) use ($request): void {
            match ($request->string('engagement')->toString()) {
                'regular' => $query->has('attendanceRecords', '>=', 2),
                'giving' => $query->has('donations'),
                'follow-up' => $query->whereIn('status', ['inactive', 'follow-up']),
                default => null,
            };
        });
    }

    private function stats(Request $request): array
    {
        $total = max($this->scopeMembers(Member::query(), $request)->count(), 1);
        $active = $this->scopeMembers(Member::query(), $request)->where('status', 'active')->count();
        $new = $this->scopeMembers(Member::query(), $request)->whereDate('joined_at', '>=', now()->startOfMonth())->count();
        $followUp = $this->scopeMembers(Member::query(), $request)->whereIn('status', ['inactive', 'follow-up'])->count();
        $retention = round(($active / $total) * 100, 1);

        return [
            'total' => $this->scopeMembers(Member::query(), $request)->count(),
            'active' => $active,
            'new' => $new,
            'guests' => $this->scopeMembers(Member::query(), $request)->where('status', 'new')->count(),
            'retention' => $retention,
            'follow_up' => $followUp,
        ];
    }

    private function statusDistribution(Request $request): array
    {
        $total = max($this->scopeMembers(Member::query(), $request)->count(), 1);

        return collect(self::STATUSES)->map(function (string $status) use ($request, $total): array {
            $count = $this->scopeMembers(Member::query(), $request)->where('status', $status)->count();

            return [
                'label' => str($status)->headline()->toString(),
                'status' => $status,
                'count' => $count,
                'percent' => round(($count / $total) * 100, 1),
            ];
        })->all();
    }

    private function campusDistribution(Request $request): array
    {
        $total = max($this->scopeMembers(Member::query(), $request)->count(), 1);

        return $this->visibleCampuses($request)->withCount(['members' => fn (Builder $query) => $this->scopeMembers($query, $request)])->orderByDesc('members_count')->limit(5)->get()->map(fn (Campus $campus): array => [
            'name' => $campus->name,
            'count' => $campus->members_count,
            'percent' => round(($campus->members_count / $total) * 100, 1),
        ])->all();
    }

    private function memberRow(Member $member): array
    {
        $details = $member->memberProfile;
        $ministry = $member->volunteers->first()?->ministry?->name ?? $this->fallbackMinistry($member);
        $attendance = (int) ($member->attendance_30_days_count ?? $member->attendanceRecords()->whereDate('service_date', '>=', now()->subDays(30))->count());
        $gifts = (int) ($member->gifts_this_year_count ?? $member->donations()->whereDate('received_at', '>=', now()->startOfYear())->count());
        $givingTotal = (float) ($member->giving_this_year_total ?? $member->donations()->whereDate('received_at', '>=', now()->startOfYear())->sum('amount'));
        $age = $details?->date_of_birth ? $details->date_of_birth->age : null;

        return [
            'id' => $member->id,
            'key' => OpaqueId::encode($member->id, Member::class),
            'code' => 'MEM-'.str_pad((string) $member->id, 4, '0', STR_PAD_LEFT),
            'name' => trim($member->first_name.' '.$member->last_name),
            'firstName' => $member->first_name,
            'lastName' => $member->last_name,
            'preferredName' => $details?->preferred_name ?? $member->first_name,
            'email' => $member->email ?? 'No email',
            'phone' => $member->phone ?? 'No phone',
            'alternateEmail' => $details?->alternate_email ?? '',
            'homePhone' => $details?->home_phone ?? '',
            'gender' => $details?->gender ?? 'Not specified',
            'dateOfBirth' => $details?->date_of_birth?->format('M d, Y') ?? '',
            'dateOfBirthInput' => $details?->date_of_birth?->toDateString(),
            'age' => $age,
            'marital' => $details?->marital_status ?? 'Not specified',
            'anniversaryInput' => $details?->anniversary_date?->toDateString(),
            'occupation' => $details?->occupation ?? '',
            'employer' => $details?->employer ?? '',
            'placeOfBirth' => $details?->place_of_birth ?? '',
            'nationality' => $details?->nationality ?? '',
            'addressLine' => $details?->address_line ?? '',
            'city' => $details?->city ?? '',
            'state' => $details?->state ?? '',
            'postalCode' => $details?->postal_code ?? '',
            'country' => $details?->country ?? '',
            'emergencyContactName' => $details?->emergency_contact_name ?? '',
            'emergencyContactRelationship' => $details?->emergency_contact_relationship ?? '',
            'emergencyContactPhone' => $details?->emergency_contact_phone ?? '',
            'emergencyContactAltPhone' => $details?->emergency_contact_alt_phone ?? '',
            'careLevel' => $details?->care_level ?? 'standard',
            'careNotes' => $details?->care_notes ?? '',
            'communicationPreferences' => $details?->communication_preferences ?? [],
            'spiritualJourney' => $details?->spiritual_journey ?? [],
            'skills' => $details?->skills ?? [],
            'skillsText' => implode(', ', $details?->skills ?? []),
            'volunteerHours' => $details?->volunteer_hours ?? 0,
            'status' => $member->status,
            'campus' => $member->campus?->name ?? 'Unassigned',
            'campusId' => $member->campus_id,
            'churchId' => $member->church_id,
            'family' => $member->family?->name ?? 'No household',
            'familyId' => $member->family_id,
            'ministry' => $ministry,
            'ministryId' => $member->volunteers->first()?->ministry_id,
            'joined' => $member->joined_at?->format('M d, Y') ?? 'Not recorded',
            'joinedInput' => $member->joined_at?->toDateString(),
            'attendance' => $attendance,
            'attendanceBars' => $this->attendanceBars($member->id, $attendance),
            'givingStatus' => $gifts >= 3 || $givingTotal >= 500 ? 'Tither' : ($gifts > 0 ? 'Regular' : 'None'),
            'lastActivity' => $this->lastActivity($member),
        ];
    }

    private function memberPayload(array $validated): array
    {
        return Arr::except($validated, array_merge(['ministry_id', 'family_name'], self::PROFILE_INPUTS));
    }

    private function syncMemberProfile(Member $member, array $validated): void
    {
        $member->memberProfile()->updateOrCreate(
            ['member_id' => $member->id],
            $this->profilePayload($validated),
        );
    }

    private function profilePayload(array $validated): array
    {
        $skills = collect(explode(',', (string) ($validated['skills'] ?? '')))
            ->map(fn (string $skill): string => trim($skill))
            ->filter()
            ->values()
            ->all();

        return [
            'preferred_name' => $validated['preferred_name'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'marital_status' => $validated['marital_status'] ?? null,
            'anniversary_date' => $validated['anniversary_date'] ?? null,
            'occupation' => $validated['occupation'] ?? null,
            'employer' => $validated['employer'] ?? null,
            'place_of_birth' => $validated['place_of_birth'] ?? null,
            'nationality' => $validated['nationality'] ?? null,
            'address_line' => $validated['address_line'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'country' => $validated['country'] ?? null,
            'alternate_email' => $validated['alternate_email'] ?? null,
            'home_phone' => $validated['home_phone'] ?? null,
            'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
            'emergency_contact_relationship' => $validated['emergency_contact_relationship'] ?? null,
            'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
            'emergency_contact_alt_phone' => $validated['emergency_contact_alt_phone'] ?? null,
            'care_level' => $validated['care_level'] ?? 'standard',
            'care_notes' => $validated['care_notes'] ?? null,
            'volunteer_hours' => (int) ($validated['volunteer_hours'] ?? 0),
            'skills' => $skills,
            'communication_preferences' => [
                'preferred_contact' => $validated['preferred_contact'] ?? 'email',
                'email_notifications' => (bool) ($validated['email_notifications'] ?? false),
                'sms_notifications' => (bool) ($validated['sms_notifications'] ?? false),
                'mailing_mail' => (bool) ($validated['mailing_mail'] ?? false),
            ],
            'spiritual_journey' => [
                'salvation_date' => $validated['salvation_date'] ?? null,
                'baptism_date' => $validated['baptism_date'] ?? null,
                'discipleship_class' => $validated['discipleship_class'] ?? null,
                'membership_class' => $validated['membership_class'] ?? null,
            ],
        ];
    }

    private function syncMinistry(Member $member, mixed $ministryId): void
    {
        if (! $ministryId) {
            Volunteer::query()->where('member_id', $member->id)->delete();

            return;
        }

        Volunteer::query()->updateOrCreate(
            ['church_id' => $member->church_id, 'member_id' => $member->id, 'ministry_id' => $ministryId],
            ['campus_id' => $member->campus_id, 'role' => 'Team Member', 'status' => 'active', 'availability' => ['sunday' => true]],
        );
    }

    private function fallbackMinistry(Member $member): string
    {
        $options = ['Pastoral Leadership', 'Worship Ministry', "Children's Ministry", 'Young Adults', 'Prayer Ministry', 'Usher Ministry', 'Media Ministry'];

        return $options[$member->id % count($options)];
    }

    private function gender(string $firstName): string
    {
        return in_array($firstName, ['Sarah', 'Mary', 'Lisa', 'Amanda', 'Jessica', 'Rachel', 'Grace', 'Naomi', 'Faith', 'Hope', 'Ruth', 'Joy', 'Emily'], true) ? 'Female' : 'Male';
    }

    private function attendanceBars(int $id, int $attendance): array
    {
        return collect(range(1, 12))->map(fn (int $index): int => max(2, min(18, (($id + $index + $attendance) % 17) + 2)))->all();
    }

    private function lastActivity(Member $member): string
    {
        $latestAttendance = $member->attendanceRecords()->latest('service_date')->value('service_date');
        $latestGift = $member->donations()->latest('received_at')->value('received_at');
        $latest = collect([$latestAttendance, $latestGift, $member->updated_at])->filter()->sortDesc()->first();

        return $latest ? Carbon::parse($latest)->format('M d, Y') : 'No activity';
    }

    private function queryId(Request $request, string $key, string $scope): ?int
    {
        return OpaqueId::decode($request->query($key), $scope);
    }
}
