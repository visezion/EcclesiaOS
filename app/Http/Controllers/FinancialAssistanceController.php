<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Campus;
use App\Models\FinanceTransaction;
use App\Models\FinancialAssistanceAttachment;
use App\Models\FinancialAssistanceRequest;
use App\Models\Fund;
use App\Models\User;
use App\Models\Workflow;
use App\Services\ActivityLogger;
use App\Services\Communications\DomainNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class FinancialAssistanceController extends Controller
{
    private const CATEGORIES = [
        'emergency_relief' => 'Emergency help',
        'medical' => 'Medical and health support',
        'education' => 'Education and training',
        'housing' => 'Housing and shelter',
        'food' => 'Food and essential needs',
        'travel_transport' => 'Transport assistance',
        'funeral_bereavement' => 'Bereavement and funeral support',
        'ministry_project' => 'Ministry program or project',
        'community_outreach' => 'Community care and outreach',
        'missions_evangelism' => 'Missions and evangelism',
        'event_program_support' => 'Church event or program',
        'volunteer_support' => 'Volunteer and ministry team support',
        'operational_support' => 'Church or campus operations',
        'facility_repairs' => 'Facility repairs and maintenance',
        'equipment_technology' => 'Equipment, media and technology',
        'utilities_rent' => 'Utilities, rent and essential bills',
        'other' => 'Other assistance',
    ];

    private const BENEFICIARIES = [
        'member' => 'Church member',
        'family' => 'Family or household',
        'ministry' => 'Ministry, department or ministry team',
        'campus' => 'Church, campus or branch',
        'community' => 'Community person or group',
        'vendor' => 'Vendor or service provider',
        'other' => 'Other',
    ];

    private const URGENCIES = [
        'normal' => 'Normal',
        'important' => 'Important',
        'urgent' => 'Urgent',
        'critical' => 'Critical',
    ];

    private const STATUSES = [
        'submitted' => 'Submitted',
        'under_review' => 'Under review',
        'changes_requested' => 'Changes requested',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'disbursed' => 'Disbursed',
        'cancelled' => 'Cancelled',
    ];

    public function __construct(private readonly DomainNotificationService $notifications) {}

    public function index(Request $request): View
    {
        $this->authorizeAccess($request);
        $base = $this->visibleRequests($request);
        $query = $this->visibleRequests($request)->with(['requester', 'sourceCampus', 'targetCampus']);

        $query
            ->when($request->filled('status'), fn (Builder $builder) => $builder->where('status', $request->string('status')->toString()))
            ->when($request->filled('urgency'), fn (Builder $builder) => $builder->where('urgency', $request->string('urgency')->toString()))
            ->when($request->filled('campus'), fn (Builder $builder) => $builder->where('target_campus_id', $request->integer('campus')))
            ->when($request->filled('q'), function (Builder $builder) use ($request): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->string('q')->trim()->toString()).'%';
                $builder->where(fn (Builder $search) => $search
                    ->where('reference', 'like', $term)
                    ->orWhere('title', 'like', $term)
                    ->orWhere('beneficiary_name', 'like', $term));
            });

        return view('financial-assistance.index', [
            'requests' => $query->latest('submitted_at')->latest()->paginate(15)->withQueryString(),
            'statuses' => self::STATUSES,
            'urgencies' => self::URGENCIES,
            'categories' => self::CATEGORIES,
            'campuses' => $this->availableCampuses($request, true),
            'canCreate' => $this->canCreate($request),
            'currency' => $request->user()->church?->currency ?? 'USD',
            'stats' => [
                'open' => (clone $base)->whereIn('status', ['submitted', 'under_review', 'changes_requested'])->count(),
                'awaiting_me' => $this->awaitingActor($request)->count(),
                'approved' => (clone $base)->where('status', 'approved')->count(),
                'disbursed' => (clone $base)->where('status', 'disbursed')->sum('approved_amount'),
            ],
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Financial Assistance', 'url' => null],
            ],
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->canCreate($request), 403);
        abort_if($request->user()->church_id === null, 422, 'Your account must be assigned to a church before creating a request.');

        return view('financial-assistance.create', [
            'categories' => self::CATEGORIES,
            'beneficiaries' => self::BENEFICIARIES,
            'urgencies' => self::URGENCIES,
            'campuses' => $this->availableCampuses($request),
            'currency' => $request->user()->church?->currency ?? 'USD',
            'canRouteAcrossCampuses' => $this->canRouteAcrossCampuses($request),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Financial Assistance', 'url' => route('financial-assistance.index')],
                ['label' => 'New Request', 'url' => null],
            ],
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        abort_unless($this->canCreate($request), 403);
        $validated = $request->validate($this->requestRules());
        $targetCampus = Campus::query()
            ->where('church_id', $request->user()->church_id)
            ->whereKey($validated['target_campus_id'])
            ->where('status', 'active')
            ->firstOrFail();

        if (! $this->canRouteAcrossCampuses($request)) {
            abort_unless($request->user()->campus_id !== null && $targetCampus->id === $request->user()->campus_id, 403);
        }

        $assistance = DB::transaction(function () use ($request, $validated, $targetCampus): FinancialAssistanceRequest {
            $assistance = FinancialAssistanceRequest::query()->create([
                ...collect($validated)->except('evidence')->all(),
                'reference' => $this->newReference(),
                'church_id' => $request->user()->church_id,
                'requester_id' => $request->user()->id,
                'source_campus_id' => $request->user()->campus_id,
                'target_campus_id' => $targetCampus->id,
                'currency' => $request->user()->church?->currency ?? 'USD',
                'status' => 'submitted',
                'current_stage' => 'campus_review',
                'submitted_at' => now(),
            ]);
            $workflow = $this->workflowFor($assistance);
            $approval = Approval::query()->create([
                'church_id' => $assistance->church_id,
                'workflow_id' => $workflow->id,
                'approvable_type' => $assistance::class,
                'approvable_id' => $assistance->id,
                'action' => 'financial_assistance',
                'requested_by' => $request->user()->id,
                'status' => 'pending',
                'notes' => 'Campus review is required before finance authorization.',
                'payload' => $this->approvalPayload($assistance, 0),
                'submitted_at' => now(),
            ]);
            $assistance->activities()->create([
                'user_id' => $request->user()->id,
                'type' => 'submitted',
                'description' => 'Request submitted to '.$targetCampus->name.' for campus review.',
                'metadata' => ['approval_id' => $approval->id, 'amount' => $assistance->amount],
            ]);

            return $assistance;
        });

        try {
            $this->storeAttachments($validated['evidence'], $assistance, $request->user()->id);
        } catch (Throwable $exception) {
            $this->deleteStoredAttachments($assistance);
            $assistance->approval()->delete();
            $assistance->delete();
            report($exception);
            abort(503, 'The evidence files could not be stored. Please try again.');
        }

        $activityLogger->log('Financial Assistance', 'assistance_requested', $assistance->reference.' was submitted.', $assistance, [
            'amount' => $assistance->amount,
            'currency' => $assistance->currency,
            'target_campus_id' => $assistance->target_campus_id,
            'risk' => $assistance->urgency,
            'status' => 'success',
        ], $request);
        $this->notifyStageApprovers($assistance);

        return redirect()->route('financial-assistance.show', $assistance)->with('status', 'Financial assistance request submitted for campus review.');
    }

    public function show(Request $request, FinancialAssistanceRequest $assistance): View
    {
        $this->authorizeVisible($request, $assistance);
        $assistance->load([
            'requester', 'sourceCampus', 'targetCampus', 'approver', 'disburser', 'fund', 'financeTransaction',
            'approval.workflow', 'attachments.uploader',
            'activities' => fn ($query) => $query->with('user')->oldest(),
        ]);

        return view('financial-assistance.show', [
            'assistance' => $assistance,
            'statuses' => self::STATUSES,
            'categories' => self::CATEGORIES,
            'beneficiaries' => self::BENEFICIARIES,
            'urgencies' => self::URGENCIES,
            'canDecide' => $this->canDecide($request, $assistance),
            'canDisburse' => $this->canDisburse($request, $assistance),
            'funds' => Fund::query()
                ->where('church_id', $assistance->church_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'canResubmit' => $assistance->requester_id === $request->user()->id && $assistance->status === 'changes_requested',
            'canCancel' => $assistance->requester_id === $request->user()->id && in_array($assistance->status, ['submitted', 'under_review', 'changes_requested'], true),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Financial Assistance', 'url' => route('financial-assistance.index')],
                ['label' => $assistance->reference, 'url' => null],
            ],
        ]);
    }

    public function decide(Request $request, FinancialAssistanceRequest $assistance, ActivityLogger $activityLogger): RedirectResponse
    {
        abort_unless($this->canDecide($request, $assistance), 403);
        abort_if($assistance->requester_id === $request->user()->id, 422, 'You cannot approve your own request.');
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'request_changes', 'reject'])],
            'notes' => ['nullable', 'required_if:decision,request_changes,reject', 'string', 'max:5000'],
            'approved_amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999999999.99'],
        ]);

        DB::transaction(function () use ($request, $assistance, $validated): void {
            $assistance->refresh();
            $approval = $assistance->approval()->lockForUpdate()->firstOrFail();
            $decision = $validated['decision'];
            $notes = $validated['notes'] ?? null;

            if ($decision === 'request_changes') {
                $assistance->update([
                    'status' => 'changes_requested',
                    'current_stage' => 'requester_action',
                    'decision_notes' => $notes,
                ]);
                $approval->update(['notes' => $notes]);
                $this->activity($assistance, $request->user()->id, 'changes_requested', 'Changes were requested before approval can continue.', ['notes' => $notes]);

                return;
            }

            if ($decision === 'reject') {
                $assistance->update([
                    'status' => 'rejected',
                    'current_stage' => 'complete',
                    'decision_notes' => $notes,
                    'rejected_at' => now(),
                ]);
                $approval->update([
                    'status' => 'rejected',
                    'approved_by' => $request->user()->id,
                    'rejected_at' => now(),
                    'notes' => $notes,
                ]);
                $this->activity($assistance, $request->user()->id, 'rejected', 'Request rejected during '.str_replace('_', ' ', $assistance->getOriginal('current_stage')).'.', ['notes' => $notes]);

                return;
            }

            if ($assistance->current_stage === 'campus_review') {
                $assistance->update([
                    'status' => 'under_review',
                    'current_stage' => 'finance_review',
                    'decision_notes' => $notes,
                ]);
                $approval->update([
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                    'notes' => 'Campus review approved. Awaiting finance authorization.',
                    'payload' => $this->approvalPayload($assistance, 1, $approval->payload),
                ]);
                $this->activity($assistance, $request->user()->id, 'campus_approved', 'Campus review approved; request moved to finance authorization.', ['notes' => $notes]);

                return;
            }

            $approvedAmount = $validated['approved_amount'] ?? $assistance->amount;
            $assistance->update([
                'status' => 'approved',
                'current_stage' => 'disbursement',
                'approved_amount' => $approvedAmount,
                'decision_notes' => $notes,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
            $approval->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'notes' => $notes ?: 'Finance authorization completed.',
            ]);
            $this->activity($assistance, $request->user()->id, 'approved', 'Finance authorized '.$assistance->currency.' '.number_format((float) $approvedAmount, 2).'.', ['notes' => $notes]);
        });

        $assistance->refresh();
        $activityLogger->log('Financial Assistance', 'assistance_decision_recorded', $assistance->reference.' decision: '.$validated['decision'].'.', $assistance, [
            'stage' => $assistance->current_stage,
            'status' => $assistance->status,
            'risk' => 'medium',
        ], $request);

        if ($assistance->current_stage === 'finance_review') {
            $this->notifyStageApprovers($assistance);
        }
        $this->notifyRequester($assistance, 'Financial assistance request updated', $this->statusMessage($assistance), true);

        return back()->with('status', match ($assistance->status) {
            'changes_requested' => 'Changes requested from the requester.',
            'rejected' => 'Request rejected.',
            'approved' => 'Request approved and ready for disbursement.',
            default => 'Campus review approved; finance authorization is next.',
        });
    }

    public function resubmit(Request $request, FinancialAssistanceRequest $assistance, ActivityLogger $activityLogger): RedirectResponse
    {
        abort_unless($assistance->requester_id === $request->user()->id && $assistance->status === 'changes_requested', 403);
        $validated = $request->validate([
            'response' => ['required', 'string', 'max:5000'],
            'evidence' => ['nullable', 'array', 'max:5'],
            'evidence.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv,txt', 'max:10240'],
        ]);
        $assistance->update(['status' => 'submitted', 'current_stage' => 'campus_review', 'decision_notes' => null, 'submitted_at' => now()]);
        $assistance->approval?->update([
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'notes' => 'Revised request resubmitted for campus review.',
            'payload' => $this->approvalPayload($assistance, 0),
        ]);
        $this->activity($assistance, $request->user()->id, 'resubmitted', 'Requester supplied changes and resubmitted the request.', ['response' => $validated['response']]);
        $this->storeAttachments($validated['evidence'] ?? [], $assistance, $request->user()->id, 'additional_evidence');
        $activityLogger->log('Financial Assistance', 'assistance_resubmitted', $assistance->reference.' was resubmitted.', $assistance, ['status' => 'success', 'risk' => 'low'], $request);
        $this->notifyStageApprovers($assistance);

        return back()->with('status', 'Updated request resubmitted for campus review.');
    }

    public function disburse(Request $request, FinancialAssistanceRequest $assistance, ActivityLogger $activityLogger): RedirectResponse
    {
        abort_unless($this->canDisburse($request, $assistance), 403);
        $validated = $request->validate([
            'fund_id' => [
                'required',
                'integer',
                Rule::exists('funds', 'id')->where(fn ($query) => $query
                    ->where('church_id', $assistance->church_id)
                    ->where('is_active', true)),
            ],
            'disbursement_reference' => ['required', 'string', 'max:120'],
            'disbursement_notes' => ['nullable', 'string', 'max:5000'],
            'disbursed_at' => ['required', 'date', 'before_or_equal:now'],
        ]);
        $financeTransaction = DB::transaction(function () use ($request, $assistance, $validated): FinanceTransaction {
            $lockedAssistance = FinancialAssistanceRequest::query()->lockForUpdate()->findOrFail($assistance->id);
            abort_unless($this->canDisburse($request, $lockedAssistance), 409, 'This request has already been disbursed or is no longer ready for payment.');

            $fund = Fund::query()
                ->where('church_id', $lockedAssistance->church_id)
                ->where('is_active', true)
                ->findOrFail($validated['fund_id']);
            $amount = (float) ($lockedAssistance->approved_amount ?? $lockedAssistance->amount);
            $method = match ($lockedAssistance->preferred_payment_method) {
                'bank_transfer', 'vendor_payment' => 'bank',
                'cash' => 'cash',
                'cheque' => 'check',
                'mobile_money' => 'mobile',
                default => null,
            };

            $transaction = FinanceTransaction::query()->create([
                'church_id' => $lockedAssistance->church_id,
                'campus_id' => $lockedAssistance->target_campus_id,
                'fund_id' => $fund->id,
                'created_by_user_id' => $request->user()->id,
                'type' => 'expense',
                'category' => 'benevolence',
                'amount' => $amount,
                'currency' => $lockedAssistance->currency,
                'method' => $method,
                'frequency' => 'one_time',
                'occurred_at' => $validated['disbursed_at'],
                'reference' => $validated['disbursement_reference'],
                'vendor_or_source' => $lockedAssistance->payee_name ?: $lockedAssistance->beneficiary_name,
                'description' => 'Financial assistance '.$lockedAssistance->reference.': '.$lockedAssistance->title
                    .(filled($validated['disbursement_notes'] ?? null) ? ' — '.$validated['disbursement_notes'] : ''),
                'status' => 'posted',
            ]);

            $lockedAssistance->update([
                ...$validated,
                'finance_transaction_id' => $transaction->id,
                'status' => 'disbursed',
                'current_stage' => 'complete',
                'disbursed_by' => $request->user()->id,
            ]);
            $this->activity($lockedAssistance, $request->user()->id, 'disbursed', 'Approved assistance was disbursed and posted to the finance ledger.', [
                'reference' => $validated['disbursement_reference'],
                'fund_id' => $fund->id,
                'fund' => $fund->name,
                'finance_transaction_id' => $transaction->id,
                'amount' => $amount,
                'currency' => $lockedAssistance->currency,
            ]);

            return $transaction;
        });

        $assistance->refresh();
        $activityLogger->log('Financial Assistance', 'assistance_disbursed', $assistance->reference.' was disbursed and posted as expense '.$financeTransaction->reference.'.', $assistance, [
            'status' => 'success',
            'risk' => 'medium',
            'finance_transaction_id' => $financeTransaction->id,
            'fund_id' => $financeTransaction->fund_id,
            'amount' => $financeTransaction->amount,
            'currency' => $financeTransaction->currency,
        ], $request);
        $this->notifyRequester($assistance, 'Financial assistance disbursed', $this->statusMessage($assistance), true);

        return back()->with('status', 'Disbursement recorded, posted as a finance expense, and the requester was notified.');
    }

    public function cancel(Request $request, FinancialAssistanceRequest $assistance, ActivityLogger $activityLogger): RedirectResponse
    {
        abort_unless($assistance->requester_id === $request->user()->id && in_array($assistance->status, ['submitted', 'under_review', 'changes_requested'], true), 403);
        $assistance->update(['status' => 'cancelled', 'current_stage' => 'complete']);
        $assistance->approval?->update(['status' => 'rejected', 'notes' => 'Cancelled by requester.', 'rejected_at' => now()]);
        $this->activity($assistance, $request->user()->id, 'cancelled', 'Request cancelled by the requester.');
        $activityLogger->log('Financial Assistance', 'assistance_cancelled', $assistance->reference.' was cancelled.', $assistance, ['status' => 'success', 'risk' => 'low'], $request);

        return redirect()->route('financial-assistance.index')->with('status', 'Request cancelled.');
    }

    public function download(Request $request, FinancialAssistanceAttachment $attachment): StreamedResponse
    {
        $attachment->loadMissing('request');
        $this->authorizeVisible($request, $attachment->request);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless($request->user()->hasAnyPermission(['request financial assistance', 'approve financial assistance', 'manage financial assistance']), 403);
    }

    private function canCreate(Request $request): bool
    {
        return $request->user()->hasPermission('request financial assistance');
    }

    private function canRouteAcrossCampuses(Request $request): bool
    {
        return $request->user()->hasPermission('request cross-campus financial assistance');
    }

    private function canDecide(Request $request, FinancialAssistanceRequest $assistance): bool
    {
        if (! in_array($assistance->status, ['submitted', 'under_review'], true)) {
            return false;
        }
        if ($assistance->current_stage === 'finance_review') {
            return $request->user()->hasAnyPermission(['manage financial assistance', 'manage finance']);
        }

        return $request->user()->hasPermission('approve financial assistance')
            && ($request->user()->isSuperAdministrator()
                || $request->user()->hasPermission('manage financial assistance')
                || $request->user()->campus_id === null
                || $request->user()->campus_id === $assistance->target_campus_id);
    }

    private function canDisburse(Request $request, FinancialAssistanceRequest $assistance): bool
    {
        return $assistance->status === 'approved'
            && $request->user()->hasAnyPermission(['manage financial assistance', 'manage finance']);
    }

    private function visibleRequests(Request $request): Builder
    {
        $query = FinancialAssistanceRequest::query();
        if ($request->user()->isSuperAdministrator()) {
            return $query;
        }
        $query->where('church_id', $request->user()->church_id);
        if ($request->user()->hasPermission('manage financial assistance')) {
            return $query;
        }
        if ($request->user()->hasPermission('approve financial assistance')) {
            return $query->where(fn (Builder $scope) => $scope
                ->where('requester_id', $request->user()->id)
                ->orWhere('target_campus_id', $request->user()->campus_id));
        }

        return $query->where('requester_id', $request->user()->id);
    }

    private function awaitingActor(Request $request): Builder
    {
        $query = $this->visibleRequests($request)->whereIn('status', ['submitted', 'under_review']);
        if ($request->user()->hasAnyPermission(['manage financial assistance', 'manage finance'])) {
            return $query->where('current_stage', 'finance_review');
        }

        return $query->where('current_stage', 'campus_review')->where('target_campus_id', $request->user()->campus_id);
    }

    private function authorizeVisible(Request $request, FinancialAssistanceRequest $assistance): void
    {
        abort_unless((clone $this->visibleRequests($request))->whereKey($assistance->id)->exists(), 404);
    }

    private function availableCampuses(Request $request, bool $allVisible = false)
    {
        $query = Campus::query()->where('church_id', $request->user()->church_id)->where('status', 'active')->orderByRaw("case when lower(slug) = 'headquarters' or lower(type) like '%main%' then 0 else 1 end")->orderBy('name');
        if (! $allVisible && ! $this->canRouteAcrossCampuses($request)) {
            $query->whereKey($request->user()->campus_id);
        }

        return $query->get();
    }

    private function workflowFor(FinancialAssistanceRequest $assistance): Workflow
    {
        return Workflow::query()->firstOrCreate(
            ['church_id' => $assistance->church_id, 'module' => 'financial_assistance', 'name' => 'Financial Assistance Approval'],
            ['status' => 'active', 'steps' => [
                'description' => 'Campus review followed by finance authorization and disbursement.',
                'approval_type' => 'sequential',
                'timeout_hours' => 72,
                'steps' => [
                    ['position' => 1, 'label' => 'Campus review', 'role' => 'Campus Pastor / Approver', 'mode' => 'required', 'required' => true],
                    ['position' => 2, 'label' => 'Finance authorization', 'role' => 'Finance Officer', 'mode' => 'required', 'required' => true],
                ],
            ]],
        );
    }

    private function approvalPayload(FinancialAssistanceRequest $assistance, int $stepIndex, ?array $existing = null): array
    {
        return [
            'reference' => $assistance->reference,
            'title' => $assistance->title,
            'beneficiary' => $assistance->beneficiary_name,
            'amount' => $assistance->amount,
            'currency' => $assistance->currency,
            'target_campus' => $assistance->targetCampus?->name ?? Campus::query()->find($assistance->target_campus_id)?->name,
            '_workflow' => [
                'required_step_index' => $stepIndex,
                'current_step' => $stepIndex + 1,
                'current_label' => $stepIndex === 0 ? 'Campus review' : 'Finance authorization',
                'current_role' => $stepIndex === 0 ? 'Campus Pastor / Approver' : 'Finance Officer',
                'history' => data_get($existing, '_workflow.history', []),
            ],
        ];
    }

    private function notifyStageApprovers(FinancialAssistanceRequest $assistance): void
    {
        $assistance->loadMissing(['requester', 'targetCampus']);
        $query = User::query()->where('church_id', $assistance->church_id)->where('status', 'active')->whereKeyNot($assistance->requester_id);
        if ($assistance->current_stage === 'finance_review') {
            $query->where(fn (Builder $scope) => $scope
                ->whereHas('roles.permissions', fn (Builder $permissions) => $permissions->whereIn('name', ['manage financial assistance', 'manage finance']))
                ->orWhereHas('roles', fn (Builder $roles) => $roles->where('name', 'Super Administrator')));
        } else {
            $query->where(fn (Builder $scope) => $scope
                ->whereNull('campus_id')
                ->orWhere('campus_id', $assistance->target_campus_id)
                ->orWhereHas('roles.permissions', fn (Builder $permissions) => $permissions->where('name', 'manage financial assistance'))
                ->orWhereHas('roles', fn (Builder $roles) => $roles->where('name', 'Super Administrator')))
                ->where(fn (Builder $scope) => $scope
                    ->whereHas('roles.permissions', fn (Builder $permissions) => $permissions->where('name', 'approve financial assistance'))
                    ->orWhereHas('roles', fn (Builder $roles) => $roles->where('name', 'Super Administrator')));
        }
        $approvers = $query->get();
        $stage = $assistance->current_stage === 'finance_review' ? 'finance authorization' : 'campus review';
        $this->notifications->users(
            $approvers,
            'FinancialAssistanceApprovalRequired',
            'approvals',
            'Approval needed: '.$assistance->reference,
            $assistance->requester?->name.' requests '.$assistance->currency.' '.number_format((float) $assistance->amount, 2).' for '.$assistance->title.'. '.$stage.' is required.',
            ['in_app', 'email', 'sms', 'whatsapp'],
            ['url' => route('financial-assistance.show', $assistance), 'reference' => $assistance->reference, 'stage' => $assistance->current_stage],
            $assistance->urgency === 'critical',
        );
    }

    private function notifyRequester(FinancialAssistanceRequest $assistance, string $subject, string $message, bool $critical = false): void
    {
        $assistance->loadMissing('requester');
        if (! $assistance->requester) {
            return;
        }
        $this->notifications->user(
            $assistance->requester,
            'FinancialAssistanceStatusChanged',
            'financial_assistance',
            $subject,
            $message,
            ['in_app', 'email', 'sms', 'whatsapp'],
            ['url' => route('financial-assistance.show', $assistance), 'reference' => $assistance->reference, 'status' => $assistance->status],
            $critical,
        );
    }

    private function statusMessage(FinancialAssistanceRequest $assistance): string
    {
        return match ($assistance->status) {
            'changes_requested' => $assistance->reference.' needs additional information. Review the decision notes and resubmit.',
            'rejected' => $assistance->reference.' was not approved. Review the decision notes for details.',
            'approved' => $assistance->reference.' was approved for '.$assistance->currency.' '.number_format((float) $assistance->approved_amount, 2).' and is awaiting disbursement.',
            'disbursed' => $assistance->reference.' has been disbursed. Reference: '.$assistance->disbursement_reference.'.',
            default => $assistance->reference.' moved to finance authorization.',
        };
    }

    private function requestRules(): array
    {
        return [
            'target_campus_id' => ['required', 'integer', 'exists:campuses,id'],
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'beneficiary_type' => ['required', Rule::in(array_keys(self::BENEFICIARIES))],
            'beneficiary_name' => ['required', 'string', 'max:180'],
            'title' => ['required', 'string', 'min:5', 'max:180'],
            'purpose' => ['required', 'string', 'min:20', 'max:10000'],
            'justification' => ['required', 'string', 'min:20', 'max:10000'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'needed_by' => ['nullable', 'date', 'after_or_equal:today'],
            'urgency' => ['required', Rule::in(array_keys(self::URGENCIES))],
            'preferred_payment_method' => ['nullable', Rule::in(['bank_transfer', 'cash', 'cheque', 'vendor_payment', 'mobile_money', 'other'])],
            'payee_name' => ['nullable', 'string', 'max:180'],
            'evidence' => ['required', 'array', 'min:1', 'max:5'],
            'evidence.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv,txt', 'max:10240'],
        ];
    }

    private function newReference(): string
    {
        do {
            $reference = 'FAR-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (FinancialAssistanceRequest::query()->where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function storeAttachments(array $files, FinancialAssistanceRequest $assistance, int $userId, string $kind = 'evidence'): void
    {
        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension());
            $filename = (string) Str::uuid().($extension !== '' ? '.'.$extension : '');
            $hash = hash_file('sha256', $file->getRealPath());
            $path = $file->storeAs('financial-assistance/'.$assistance->id, $filename, 'local');
            if (! is_string($path) || $path === '' || ! is_string($hash)) {
                throw new \RuntimeException('Financial assistance evidence storage failed.');
            }
            $assistance->attachments()->create([
                'uploaded_by' => $userId,
                'kind' => $kind,
                'disk' => 'local',
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'sha256' => $hash,
            ]);
        }
    }

    private function deleteStoredAttachments(FinancialAssistanceRequest $assistance): void
    {
        $assistance->attachments()->get()->each(fn (FinancialAssistanceAttachment $attachment) => Storage::disk($attachment->disk)->delete($attachment->path));
    }

    private function activity(FinancialAssistanceRequest $assistance, ?int $userId, string $type, string $description, array $metadata = []): void
    {
        $assistance->activities()->create([
            'user_id' => $userId,
            'type' => $type,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
