<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOperationalRecords;
use App\Models\Campus;
use App\Models\CareTask;
use App\Models\CounsellingBooking;
use App\Models\Member;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Csv;
use App\Support\OpaqueId;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CounsellingController extends Controller
{
    use ScopesOperationalRecords;

    private const TYPES = ['Counseling', 'Family Care'];

    private const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    private const STATUSES = ['pending', 'assigned', 'in-progress', 'on-hold', 'resolved'];

    private const BOOKING_STATUSES = ['scheduled', 'confirmed', 'completed', 'cancelled', 'no_show'];

    private const LOCATION_TYPES = ['in_person', 'phone', 'video'];

    public function index(Request $request): View
    {
        $this->authorizePermission($request, 'manage counselling');

        $query = $this->caseQuery($request)->with(['member.campus', 'campus', 'assignedUser', 'counsellingBookings']);
        $this->applyFilters($query, $request);

        $cases = $query->latest('due_at')->paginate(12)->withQueryString();
        $base = $this->caseQuery($request);

        return view('counselling.index', [
            'cases' => $cases,
            'members' => $this->visibleMembers($request)->with('campus')->limit(300)->get(),
            'users' => $this->visibleUsers($request)->limit(200)->get(),
            'campuses' => $this->visibleCampuses($request)->get(),
            'caseOptions' => $this->caseQuery($request)->with(['member', 'assignedUser'])->latest('due_at')->limit(300)->get(),
            'bookings' => $this->bookingQuery($request)->with(['case.member', 'member.campus', 'campus', 'counselor'])->orderBy('starts_at')->limit(12)->get(),
            'types' => self::TYPES,
            'priorities' => self::PRIORITIES,
            'statuses' => self::STATUSES,
            'bookingStatuses' => self::BOOKING_STATUSES,
            'locationTypes' => self::LOCATION_TYPES,
            'stats' => [
                'open' => (clone $base)->whereNot('status', 'resolved')->count(),
                'urgent' => (clone $base)->where('priority', 'urgent')->whereNot('status', 'resolved')->count(),
                'scheduled' => $this->bookingQuery($request)->whereBetween('starts_at', [now(), now()->addDays(14)])->whereIn('status', ['scheduled', 'confirmed'])->count(),
                'resolved' => (clone $base)->where('status', 'resolved')->whereDate('resolved_at', '>=', now()->startOfMonth())->count(),
            ],
            'bookingStats' => $this->bookingStats($request),
            'statusRows' => $this->statusRows($request),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Counselling', 'url' => null],
            ],
        ]);
    }

    public function overview(Request $request): View
    {
        $this->authorizePermission($request, 'manage counselling');

        $base = $this->caseQuery($request);

        return view('counselling.overview', [
            'stats' => [
                'open' => (clone $base)->whereNot('status', 'resolved')->count(),
                'urgent' => (clone $base)->where('priority', 'urgent')->whereNot('status', 'resolved')->count(),
                'scheduled' => $this->bookingQuery($request)->whereBetween('starts_at', [now(), now()->addDays(14)])->whereIn('status', ['scheduled', 'confirmed'])->count(),
                'resolved' => (clone $base)->where('status', 'resolved')->whereDate('resolved_at', '>=', now()->startOfMonth())->count(),
            ],
            'bookingStats' => $this->bookingStats($request),
            'statusRows' => $this->statusRows($request),
            'priorityRows' => $this->priorityRows($request),
            'recentCases' => $this->caseQuery($request)->with(['member', 'campus', 'assignedUser'])->latest('due_at')->limit(8)->get(),
            'upcomingBookings' => $this->bookingQuery($request)->with(['case.member', 'member', 'campus', 'counselor'])->orderBy('starts_at')->limit(8)->get(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Counselling', 'url' => route('counselling.index')],
                ['label' => 'Overview', 'url' => null],
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizePermission($request, 'manage counselling');

        return view('counselling.create', $this->caseFormData($request) + [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Counselling', 'url' => route('counselling.index')],
                ['label' => 'Create Case', 'url' => null],
            ],
        ]);
    }

    public function edit(Request $request, CareTask $case): View
    {
        $this->authorizePermission($request, 'manage counselling');
        $this->authorizeCaseRecord($request, $case);

        return view('counselling.edit', $this->caseFormData($request, $case) + [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Counselling', 'url' => route('counselling.index')],
                ['label' => $case->member?->first_name.' '.$case->member?->last_name, 'url' => null],
            ],
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage counselling');
        $validated = $this->validatedCase($request);
        $member = $this->visibleMembers($request)->findOrFail($validated['member_id']);
        $validated['church_id'] = $member->church_id;
        $validated['campus_id'] = $validated['campus_id'] ?? $member->campus_id;
        $case = CareTask::query()->create($validated);

        $activityLogger->log('Counselling', 'counselling_case_created', 'Counselling case created for '.$member->first_name.' '.$member->last_name.'.', $case, ['resource' => 'Counselling Case', 'risk' => $case->priority, 'status' => 'success'], $request);

        return back()->with('status', 'Counselling case created.');
    }

    public function update(Request $request, CareTask $case, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage counselling');
        $this->authorizeCaseRecord($request, $case);
        $validated = $this->validatedCase($request);
        $validated['resolved_at'] = $validated['status'] === 'resolved' ? now() : null;
        $case->update($validated);

        $activityLogger->log('Counselling', 'counselling_case_updated', 'Counselling case updated for '.$case->member?->first_name.' '.$case->member?->last_name.'.', $case, ['resource' => 'Counselling Case', 'risk' => $case->priority, 'status' => 'success'], $request);

        return back()->with('status', 'Counselling case updated.');
    }

    public function destroy(Request $request, CareTask $case, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage counselling');
        $this->authorizeCaseRecord($request, $case);
        $case->delete();

        $activityLogger->log('Counselling', 'counselling_case_archived', 'Counselling case was archived.', null, ['resource' => 'Counselling Case', 'risk' => 'medium', 'status' => 'success'], $request);

        return back()->with('status', 'Counselling case archived.');
    }

    public function storeBooking(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage counselling');
        $booking = CounsellingBooking::query()->create($this->validatedBooking($request));

        $activityLogger->log('Counselling', 'booking_scheduled', 'Counselling booking scheduled for '.$booking->member?->first_name.' '.$booking->member?->last_name.'.', $booking, ['resource' => 'Counselling Booking', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Counselling booking scheduled.');
    }

    public function updateBooking(Request $request, CounsellingBooking $booking, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage counselling');
        $this->authorizeBookingRecord($request, $booking);
        $booking->update($this->validatedBooking($request, $booking));

        $activityLogger->log('Counselling', 'booking_updated', 'Counselling booking was updated.', $booking, ['resource' => 'Counselling Booking', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Counselling booking updated.');
    }

    public function destroyBooking(Request $request, CounsellingBooking $booking, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage counselling');
        $this->authorizeBookingRecord($request, $booking);
        $booking->update(['status' => 'cancelled']);

        $activityLogger->log('Counselling', 'booking_cancelled', 'Counselling booking was cancelled.', $booking, ['resource' => 'Counselling Booking', 'risk' => 'medium', 'status' => 'success'], $request);

        return back()->with('status', 'Counselling booking cancelled.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizePermission($request, 'manage counselling');

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            Csv::write($handle, ['Member', 'Type', 'Priority', 'Status', 'Assigned To', 'Campus', 'Next Action', 'Due At', 'Resolved At', 'Notes']);
            $query = $this->caseQuery($request)->with(['member', 'campus', 'assignedUser'])->latest('due_at');
            $this->applyFilters($query, $request);
            $query->lazy(100)->each(fn (CareTask $case) => Csv::write($handle, [
                $case->member ? $case->member->first_name.' '.$case->member->last_name : '',
                $case->type,
                $case->priority,
                $case->status,
                $case->assignedUser?->name,
                $case->campus?->name,
                $case->next_action,
                $case->due_at?->format('Y-m-d H:i'),
                $case->resolved_at?->format('Y-m-d H:i'),
                $case->notes,
            ]));
            fclose($handle);
        }, 'counselling-cases-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function validatedCase(Request $request): array
    {
        $validated = $request->validate([
            'member_id' => ['required', 'string'],
            'campus_id' => ['nullable', 'string'],
            'assigned_user_id' => ['nullable', 'string'],
            'type' => ['required', Rule::in(self::TYPES)],
            'priority' => ['required', Rule::in(self::PRIORITIES)],
            'status' => ['required', Rule::in(self::STATUSES)],
            'next_action' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_at' => ['nullable', 'date'],
        ]);

        $validated['member_id'] = OpaqueId::decode($validated['member_id'], Member::class);
        if (! $validated['member_id']) {
            throw ValidationException::withMessages(['member_id' => 'Select a valid member.']);
        }

        $validated['campus_id'] = filled($validated['campus_id'] ?? null)
            ? OpaqueId::decode($validated['campus_id'], Campus::class)
            : null;
        if (filled($request->input('campus_id')) && ! $validated['campus_id']) {
            throw ValidationException::withMessages(['campus_id' => 'Select a valid campus.']);
        }

        $validated['assigned_user_id'] = filled($validated['assigned_user_id'] ?? null)
            ? OpaqueId::decode($validated['assigned_user_id'], User::class)
            : null;
        if (filled($request->input('assigned_user_id')) && ! $validated['assigned_user_id']) {
            throw ValidationException::withMessages(['assigned_user_id' => 'Select a valid assignee.']);
        }

        abort_unless($this->visibleMembers($request)->whereKey($validated['member_id'])->exists(), 403);
        if (! empty($validated['campus_id'])) {
            abort_unless($this->visibleCampuses($request)->whereKey($validated['campus_id'])->exists(), 403);
        }
        if (! empty($validated['assigned_user_id'])) {
            abort_unless($this->visibleUsers($request)->whereKey($validated['assigned_user_id'])->exists(), 403);
        }

        return $validated;
    }

    private function caseFormData(Request $request, ?CareTask $case = null): array
    {
        return [
            'case' => $case,
            'members' => $this->visibleMembers($request)->with('campus')->limit(300)->get(),
            'users' => $this->visibleUsers($request)->limit(200)->get(),
            'campuses' => $this->visibleCampuses($request)->get(),
            'types' => self::TYPES,
            'priorities' => self::PRIORITIES,
            'statuses' => self::STATUSES,
            'bookingStatuses' => self::BOOKING_STATUSES,
            'locationTypes' => self::LOCATION_TYPES,
        ];
    }

    private function validatedBooking(Request $request, ?CounsellingBooking $booking = null): array
    {
        $validated = $request->validate([
            'care_task_id' => ['required', 'string'],
            'campus_id' => ['nullable', 'string'],
            'counselor_user_id' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['required', Rule::in(self::BOOKING_STATUSES)],
            'location_type' => ['required', Rule::in(self::LOCATION_TYPES)],
            'location' => ['nullable', 'string', 'max:180'],
            'meeting_url' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1500'],
        ]);

        $caseId = OpaqueId::decode($validated['care_task_id'], CareTask::class);
        if (! $caseId) {
            throw ValidationException::withMessages(['care_task_id' => 'Select a valid counselling case.']);
        }

        $case = $this->caseQuery($request)->with('member')->findOrFail($caseId);

        $validated['care_task_id'] = $case->id;
        $validated['church_id'] = $case->church_id;
        $validated['member_id'] = $case->member_id;
        $validated['campus_id'] = filled($validated['campus_id'] ?? null)
            ? OpaqueId::decode($validated['campus_id'], Campus::class)
            : ($case->campus_id ?? $case->member?->campus_id);
        $validated['counselor_user_id'] = filled($validated['counselor_user_id'] ?? null)
            ? OpaqueId::decode($validated['counselor_user_id'], User::class)
            : $case->assigned_user_id;

        if (filled($request->input('campus_id')) && ! $validated['campus_id']) {
            throw ValidationException::withMessages(['campus_id' => 'Select a valid campus.']);
        }
        if (filled($request->input('counselor_user_id')) && ! $validated['counselor_user_id']) {
            throw ValidationException::withMessages(['counselor_user_id' => 'Select a valid counselor.']);
        }
        if (! empty($validated['campus_id'])) {
            abort_unless($this->visibleCampuses($request)->whereKey($validated['campus_id'])->exists(), 403);
        }
        if (! empty($validated['counselor_user_id'])) {
            abort_unless($this->visibleUsers($request)->whereKey($validated['counselor_user_id'])->exists(), 403);
        }

        $startsAt = Carbon::parse($validated['starts_at']);
        $endsAt = Carbon::parse($validated['ends_at']);
        $activeStatuses = ['scheduled', 'confirmed'];

        if (in_array($validated['status'], $activeStatuses, true)) {
            if (! empty($validated['counselor_user_id'])) {
                $counselorConflict = $this->bookingQuery($request)
                    ->where('counselor_user_id', $validated['counselor_user_id'])
                    ->whereIn('status', $activeStatuses)
                    ->where('starts_at', '<', $endsAt)
                    ->where('ends_at', '>', $startsAt)
                    ->when($booking, fn (Builder $query) => $query->whereKeyNot($booking->id))
                    ->exists();
                if ($counselorConflict) {
                    throw ValidationException::withMessages(['starts_at' => 'This counselor already has a booking during that time.']);
                }
            }

            $memberConflict = $this->bookingQuery($request)
                ->where('member_id', $validated['member_id'])
                ->whereIn('status', $activeStatuses)
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->when($booking, fn (Builder $query) => $query->whereKeyNot($booking->id))
                ->exists();
            if ($memberConflict) {
                throw ValidationException::withMessages(['starts_at' => 'This member already has a counselling booking during that time.']);
            }
        }

        return $validated;
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('q'), function (Builder $query) use ($request): void {
            $term = '%'.$request->string('q')->toString().'%';
            $query->whereHas('member', fn (Builder $member) => $member->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('email', 'like', $term));
        });
        $query->when($request->filled('type'), fn (Builder $query) => $query->where('type', $request->string('type')));
        $query->when($request->filled('priority'), fn (Builder $query) => $query->where('priority', $request->string('priority')));
        $query->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')));
        $query->when($request->filled('assigned_user_id'), function (Builder $query) use ($request): void {
            $id = OpaqueId::decode($request->query('assigned_user_id'), User::class);
            if ($id) {
                $query->where('assigned_user_id', $id);
            }
        });
    }

    private function caseQuery(Request $request): Builder
    {
        return $this->scopeChurchCampus(CareTask::query(), $request)->whereIn('type', self::TYPES);
    }

    private function bookingQuery(Request $request): Builder
    {
        return $this->scopeChurchCampus(CounsellingBooking::query(), $request)
            ->whereIn('status', self::BOOKING_STATUSES);
    }

    private function authorizeCaseRecord(Request $request, CareTask $case): void
    {
        abort_unless(in_array($case->type, self::TYPES, true), 404);
        $this->authorizeScopedRecord($request, $case);
    }

    private function authorizeBookingRecord(Request $request, CounsellingBooking $booking): void
    {
        $this->authorizeScopedRecord($request, $booking);
        abort_unless($this->caseQuery($request)->whereKey($booking->care_task_id)->exists(), 404);
    }

    private function statusRows(Request $request): array
    {
        $total = max($this->caseQuery($request)->count(), 1);

        return collect(self::STATUSES)->map(function (string $status) use ($request, $total): array {
            $count = $this->caseQuery($request)->where('status', $status)->count();

            return [
                'label' => str($status)->headline()->toString(),
                'value' => $count,
                'percent' => round(($count / $total) * 100, 1),
            ];
        })->all();
    }

    private function priorityRows(Request $request): array
    {
        $total = max($this->caseQuery($request)->count(), 1);

        return collect(self::PRIORITIES)->map(function (string $priority) use ($request, $total): array {
            $count = $this->caseQuery($request)->where('priority', $priority)->count();

            return [
                'label' => str($priority)->headline()->toString(),
                'value' => $count,
                'percent' => round(($count / $total) * 100, 1),
            ];
        })->all();
    }

    private function bookingStats(Request $request): array
    {
        $base = $this->bookingQuery($request);

        return [
            'today' => (clone $base)->whereDate('starts_at', today())->whereIn('status', ['scheduled', 'confirmed'])->count(),
            'week' => (clone $base)->whereBetween('starts_at', [now()->startOfDay(), now()->addWeek()])->whereIn('status', ['scheduled', 'confirmed'])->count(),
            'confirmed' => (clone $base)->where('status', 'confirmed')->count(),
            'completed_month' => (clone $base)->where('status', 'completed')->whereDate('starts_at', '>=', now()->startOfMonth())->count(),
        ];
    }
}
