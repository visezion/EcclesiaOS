<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Approval;
use App\Models\BookstoreLibraryLoan;
use App\Models\BookstoreProduct;
use App\Models\EventRecurrenceRule;
use App\Models\FinancialAssistanceRequest;
use App\Models\ProgramSectionAssignment;
use App\Models\Role;
use App\Models\Workflow;
use App\Services\ActivityLogger;
use App\Services\Communications\DomainNotificationService;
use App\Support\OpaqueId;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class WorkflowController extends Controller
{
    public function __construct(private readonly DomainNotificationService $domainNotifications) {}

    public function index(Request $request): View
    {
        $this->authorizeWorkflow($request);

        $base = Approval::query()
            ->with(['workflow', 'requester', 'approver', 'approvable'])
            ->where(fn (Builder $query) => $this->scopeApprovalQuery($query, $request));

        $approvals = (clone $base)
            ->when(filled($request->query('status')), fn (Builder $query) => $query->where('status', $request->query('status')))
            ->latest('submitted_at')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $workflowQuery = Workflow::query()
            ->withCount([
                'approvals as active_instances_count',
                'approvals as pending_approvals_count' => fn (Builder $query) => $query->where('status', 'pending'),
            ])
            ->where(fn (Builder $query) => $this->scopeWorkflowQuery($query, $request))
            ->when(filled($request->query('q')), function (Builder $query) use ($request): void {
                $search = str((string) $request->query('q'))->lower()->trim()->toString();
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(module) LIKE ?', ['%'.$search.'%']);
                });
            })
            ->when(filled($request->query('module')), fn (Builder $query) => $query->where('module', $request->query('module')))
            ->when(in_array($request->query('workflow_status'), ['active', 'draft'], true), fn (Builder $query) => $query->where('status', $request->query('workflow_status')));

        $workflows = (clone $workflowQuery)
            ->latest('updated_at')
            ->paginate(6, ['*'], 'workflow_page')
            ->withQueryString();

        $selectedWorkflowId = OpaqueId::decode((string) $request->query('workflow'), Workflow::class);
        $selectedWorkflow = $selectedWorkflowId
            ? Workflow::query()
                ->withCount([
                    'approvals as active_instances_count',
                    'approvals as pending_approvals_count' => fn (Builder $query) => $query->where('status', 'pending'),
                ])
                ->where(fn (Builder $query) => $this->scopeWorkflowQuery($query, $request))
                ->find($selectedWorkflowId)
            : $workflows->first();

        $statusBreakdown = [
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
        ];
        $statusBreakdown['total'] = max(array_sum($statusBreakdown), 1);

        $approvedDurations = Approval::query()
            ->where(fn (Builder $query) => $this->scopeApprovalQuery($query, $request))
            ->where('status', 'approved')
            ->whereNotNull('submitted_at')
            ->whereNotNull('approved_at')
            ->get(['submitted_at', 'approved_at'])
            ->map(fn (Approval $approval): int => max(1, $approval->submitted_at->diffInHours($approval->approved_at)));

        return view('workflows.index', [
            'approvals' => $approvals,
            'workflows' => $workflows,
            'selectedWorkflow' => $selectedWorkflow,
            'pendingApprovals' => (clone $base)->where('status', 'pending')->latest('submitted_at')->limit(4)->get(),
            'statusBreakdown' => $statusBreakdown,
            'recentActivity' => ActivityLog::query()
                ->with('user')
                ->where(fn (Builder $query) => $this->scopeActivityQuery($query, $request))
                ->whereIn('module', ['Workflow & Approvals', 'Program Sections', 'Event Sessions'])
                ->latest()
                ->limit(5)
                ->get(),
            'templates' => $this->workflowTemplates(),
            'modules' => Workflow::query()
                ->where(fn (Builder $query) => $this->scopeWorkflowQuery($query, $request))
                ->toBase()
                ->select('module')
                ->distinct()
                ->pluck('module')
                ->filter()
                ->values(),
            'workflowRoleOptions' => $this->workflowRoleOptions(),
            'stats' => [
                'pending' => (clone $base)->where('status', 'pending')->count(),
                'in_progress' => (clone $base)->where('status', 'pending')->whereNotNull('workflow_id')->count(),
                'completed_20' => (clone $base)->whereIn('status', ['approved', 'rejected'])->where('updated_at', '>=', now()->subDays(20))->count(),
                'completed_30' => (clone $base)->whereIn('status', ['approved', 'rejected'])->where('updated_at', '>=', now()->subDays(30))->count(),
                'average_days' => $approvedDurations->isEmpty() ? 0 : round($approvedDurations->avg() / 24, 1),
                'overdue' => (clone $base)->where('status', 'pending')->where('submitted_at', '<=', now()->subHours(72))->count(),
                'active_workflows' => (clone $workflowQuery)->where('status', 'active')->count(),
                'total' => (clone $base)->count(),
            ],
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Workflow & Approvals', 'url' => null],
            ],
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeWorkflow($request);

        $validated = $this->validateWorkflowPayload($request);

        $workflow = Workflow::query()->create([
            'church_id' => $request->user()?->church_id ?? 1,
            'name' => $validated['name'],
            'module' => $validated['module'],
            'status' => $validated['status'],
            'steps' => [
                'description' => $validated['description'] ?? 'Approval workflow',
                'approval_type' => $validated['approval_type'],
                'timeout_hours' => (int) $validated['timeout_hours'],
                'steps' => $validated['steps'],
            ],
        ]);

        $activityLogger->log('Workflow & Approvals', 'workflow_created', $workflow->name.' workflow was created.', $workflow, ['resource' => 'Workflow', 'risk' => 'low', 'status' => 'success'], $request);

        return redirect()->route('workflows.index')->with('status', 'Workflow created.');
    }

    public function update(Request $request, Workflow $workflow, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeWorkflow($request);
        abort_unless($request->user()?->canAccessChurch($workflow->church_id), 403);

        $existingSteps = $workflow->steps ?? [];
        $fallbackSteps = array_is_list($existingSteps) ? $existingSteps : data_get($existingSteps, 'steps', []);
        $validated = $this->validateWorkflowPayload($request, $fallbackSteps);

        $workflow->update([
            'name' => $validated['name'],
            'module' => $validated['module'],
            'status' => $validated['status'],
            'steps' => [
                'description' => $validated['description'] ?? 'Approval workflow',
                'approval_type' => $validated['approval_type'],
                'timeout_hours' => (int) $validated['timeout_hours'],
                'steps' => $validated['steps'],
            ],
        ]);

        $activityLogger->log('Workflow & Approvals', 'workflow_updated', $workflow->name.' workflow was updated.', $workflow, ['resource' => 'Workflow', 'risk' => 'low', 'status' => 'success'], $request);

        return redirect()->route('workflows.index', ['workflow' => $workflow->opaqueId()])->with('status', 'Workflow updated.');
    }

    public function destroy(Request $request, Workflow $workflow, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeWorkflow($request);
        abort_unless($request->user()?->canAccessChurch($workflow->church_id), 403);

        $name = $workflow->name;
        $activityLogger->log('Workflow & Approvals', 'workflow_deleted', $name.' workflow was deleted.', $workflow, ['resource' => 'Workflow', 'risk' => 'medium', 'status' => 'success'], $request);
        $workflow->delete();

        return redirect()->route('workflows.index')->with('status', 'Workflow deleted.');
    }

    public function import(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeWorkflow($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'module' => ['required', 'string', 'max:80'],
            'definition' => ['nullable', 'string', 'max:4000'],
        ]);

        $definition = [];
        if (filled($validated['definition'] ?? null)) {
            $decoded = json_decode((string) $validated['definition'], true);
            abort_unless(is_array($decoded), 422, 'Workflow JSON must be a valid object.');
            $definition = $decoded;
        }

        $workflow = Workflow::query()->create([
            'church_id' => $request->user()?->church_id ?? 1,
            'name' => $validated['name'],
            'module' => $validated['module'],
            'status' => 'draft',
            'steps' => $definition ?: [
                'description' => 'Imported workflow draft',
                'approval_type' => 'sequential',
                'timeout_hours' => 72,
                'steps' => [],
            ],
        ]);

        $activityLogger->log('Workflow & Approvals', 'workflow_imported', $workflow->name.' workflow was imported.', $workflow, ['resource' => 'Workflow', 'risk' => 'low', 'status' => 'success'], $request);

        return redirect()->route('workflows.index')->with('status', 'Workflow imported as draft.');
    }

    public function approve(Request $request, Approval $approval, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeWorkflow($request);
        abort_unless($request->user()?->canAccessChurch($approval->church_id), 403);

        $approval->loadMissing('workflow');
        $payload = $approval->payload ?? [];
        $requiredSteps = $this->requiredApprovalSteps($approval->workflow);
        $currentStepIndex = max(0, (int) data_get($payload, '_workflow.required_step_index', 0));
        $currentStep = $requiredSteps->get($currentStepIndex);
        $resource = $approval->approvable;
        $history = collect(data_get($payload, '_workflow.history', []))
            ->push([
                'step_position' => $currentStep['position'] ?? null,
                'step_label' => $currentStep['label'] ?? 'Approval',
                'role' => $currentStep['role'] ?? null,
                'approved_by' => $request->user()?->id,
                'approved_at' => now()->toIso8601String(),
            ])
            ->all();

        if ($requiredSteps->count() > 0 && $currentStepIndex < $requiredSteps->count() - 1) {
            $nextStep = $requiredSteps->get($currentStepIndex + 1);
            data_set($payload, '_workflow', [
                'required_step_index' => $currentStepIndex + 1,
                'current_step' => $nextStep['position'] ?? $currentStepIndex + 2,
                'current_label' => $nextStep['label'] ?? 'Approval Step',
                'current_role' => $nextStep['role'] ?? 'Approver',
                'history' => $history,
            ]);

            $approval->update([
                'status' => 'pending',
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
                'notes' => 'Approved step: '.($currentStep['label'] ?? 'Approval').'. Awaiting '.($nextStep['role'] ?? 'next approver').'.',
                'payload' => $payload,
            ]);
            if ($resource instanceof FinancialAssistanceRequest) {
                $resource->update([
                    'status' => 'under_review',
                    'current_stage' => 'finance_review',
                    'decision_notes' => $request->input('notes'),
                ]);
                $resource->activities()->create([
                    'user_id' => $request->user()?->id,
                    'type' => 'campus_approved',
                    'description' => 'Campus review approved in Workflow & Approvals; request moved to finance authorization.',
                ]);
            }

            $activityLogger->log('Workflow & Approvals', 'approval_step_approved', 'Approval '.$approval->opaqueId().' advanced to '.($nextStep['label'] ?? 'next step').'.', $approval, ['resource' => 'Approval', 'risk' => 'medium', 'status' => 'success'], $request);
            $this->notifyRequester($approval, 'Approval advanced', 'Your request passed one approval step and is awaiting the next approver.');

            return back()->with('status', 'Approval step approved and moved to the next approver.');
        }

        data_set($payload, '_workflow.history', $history);

        if ($resource instanceof BookstoreLibraryLoan) {
            $this->approveLibraryLoan($request, $approval, $resource, $payload);

            $activityLogger->log('Workflow & Approvals', 'approval_approved', 'Approval '.$approval->opaqueId().' was approved.', $approval, ['resource' => 'Approval', 'risk' => 'medium', 'status' => 'success'], $request);
            $this->notifyRequester($approval, 'Request approved', 'Your library request was approved.');

            return back()->with('status', 'Approval approved and library loan activated.');
        }

        $approval->update([
            'status' => 'approved',
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
            'notes' => $request->input('notes', $approval->notes),
            'payload' => $payload,
        ]);

        if ($resource instanceof EventRecurrenceRule) {
            $resource->update(['status' => 'active']);
            $resource->sessions()->where('status', 'draft')->update(['status' => 'scheduled']);
        }
        if ($resource instanceof ProgramSectionAssignment) {
            $resource->update([
                'status' => 'assigned',
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
            ]);
            $this->notifyAssignment($resource);
        }
        if ($resource instanceof FinancialAssistanceRequest) {
            $resource->update([
                'status' => 'approved',
                'current_stage' => 'disbursement',
                'approved_amount' => $resource->amount,
                'decision_notes' => $request->input('notes'),
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
            ]);
            $resource->activities()->create([
                'user_id' => $request->user()?->id,
                'type' => 'approved',
                'description' => 'Finance authorization completed in Workflow & Approvals.',
            ]);
        }

        $activityLogger->log('Workflow & Approvals', 'approval_approved', 'Approval '.$approval->opaqueId().' was approved.', $approval, ['resource' => 'Approval', 'risk' => 'medium', 'status' => 'success'], $request);
        $this->notifyRequester($approval, 'Request approved', 'Your workflow request was approved.');

        return back()->with('status', 'Approval approved and resource updated.');
    }

    public function reject(Request $request, Approval $approval, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeWorkflow($request);
        abort_unless($request->user()?->canAccessChurch($approval->church_id), 403);

        $approval->update([
            'status' => 'rejected',
            'approved_by' => $request->user()?->id,
            'rejected_at' => now(),
            'notes' => $request->input('notes', 'Rejected by approver.'),
        ]);

        $resource = $approval->approvable;
        if ($resource instanceof EventRecurrenceRule) {
            $resource->update(['status' => 'rejected']);
            $resource->sessions()->where('status', 'draft')->update(['status' => 'cancelled']);
        }
        if ($resource instanceof ProgramSectionAssignment) {
            $resource->update(['status' => 'rejected']);
        }
        if ($resource instanceof BookstoreLibraryLoan) {
            $resource->update([
                'status' => 'cancelled',
                'approval_status' => 'rejected',
                'returned_at' => now(),
            ]);
            $this->notifyLibraryLoan($resource, 'Library request rejected', 'The '.$resource->loan_type.' request for '.$resource->product?->name.' was rejected.');
        }
        if ($resource instanceof FinancialAssistanceRequest) {
            $resource->update([
                'status' => 'rejected',
                'current_stage' => 'complete',
                'decision_notes' => $request->input('notes', 'Rejected by approver.'),
                'rejected_at' => now(),
            ]);
            $resource->activities()->create([
                'user_id' => $request->user()?->id,
                'type' => 'rejected',
                'description' => 'Request rejected in Workflow & Approvals.',
            ]);
        }

        $activityLogger->log('Workflow & Approvals', 'approval_rejected', 'Approval '.$approval->opaqueId().' was rejected.', $approval, ['resource' => 'Approval', 'risk' => 'medium', 'status' => 'success'], $request);
        $this->notifyRequester($approval, 'Request rejected', 'Your workflow request was rejected. Review the approval notes for details.');

        return back()->with('status', 'Approval rejected.');
    }

    public function acceptAssignment(Request $request, ProgramSectionAssignment $assignment, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeAssignmentActor($request, $assignment);

        $assignment->update(['status' => 'accepted', 'accepted_at' => now()]);

        $activityLogger->log('Program Sections', 'assignment_accepted', $assignment->role_title.' assignment was accepted.', $assignment, ['resource' => 'Program Section Assignment', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Assignment accepted.');
    }

    public function declineAssignment(Request $request, ProgramSectionAssignment $assignment, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeAssignmentActor($request, $assignment);

        $assignment->update(['status' => 'declined', 'declined_at' => now()]);

        $activityLogger->log('Program Sections', 'assignment_declined', $assignment->role_title.' assignment was declined.', $assignment, ['resource' => 'Program Section Assignment', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Assignment declined.');
    }

    private function notifyAssignment(ProgramSectionAssignment $assignment): void
    {
        $assignment->loadMissing(['section.event', 'user', 'member']);

        $message = 'You are assigned to '.$assignment->section->title.' as '.$assignment->role_title.'. '.Str::limit((string) $assignment->responsibility_notes, 140);
        if ($assignment->user) {
            $this->domainNotifications->user($assignment->user, 'ProgramSectionAssigned', 'volunteers', 'Program responsibility approved', $message, ['in_app'], ['url' => route('events.index')]);
        } elseif ($assignment->member) {
            $this->domainNotifications->member($assignment->member, 'ProgramSectionAssigned', 'volunteers', 'Program responsibility approved', $message, ['in_app'], ['url' => route('events.index')]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function approveLibraryLoan(Request $request, Approval $approval, BookstoreLibraryLoan $loan, array $payload): void
    {
        DB::transaction(function () use ($request, $approval, $loan, $payload): void {
            $loan = BookstoreLibraryLoan::query()->lockForUpdate()->findOrFail($loan->id);
            abort_if($loan->approval_status !== 'pending' || $loan->status !== 'pending_approval', 422, 'This library request is no longer waiting for approval.');

            $product = BookstoreProduct::query()->lockForUpdate()->findOrFail($loan->bookstore_product_id);
            abort_if(in_array($loan->loan_type, ['borrow', 'rent'], true) && $product->stock_quantity < 1, 422, 'No physical copies are available for this library action.');

            if (in_array($loan->loan_type, ['borrow', 'rent'], true)) {
                $product->decrement('stock_quantity');
                if ($product->fresh()->stock_quantity <= 0) {
                    $product->update(['status' => 'out_of_stock']);
                }
            }

            $approval->update([
                'status' => 'approved',
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
                'notes' => $request->input('notes', $approval->notes),
                'payload' => $payload,
            ]);

            $loan->update([
                'status' => 'active',
                'approval_status' => 'approved',
                'checked_out_at' => now(),
            ]);
        });

        $loan = $loan->fresh();
        $loan?->loadMissing('product');

        if ($loan) {
            $this->notifyLibraryLoan($loan, 'Library request approved', 'The '.$loan->loan_type.' request for '.$loan->product?->name.' has been approved and activated.');
        }
    }

    private function notifyLibraryLoan(BookstoreLibraryLoan $loan, string $subject, string $message): void
    {
        $loan->loadMissing(['handledBy', 'member', 'product']);

        if ($loan->handledBy) {
            $this->domainNotifications->user($loan->handledBy, 'LibraryLoanNotification', 'system', $subject, $message, ['in_app', 'email'], ['url' => route('bookstore.index')]);
        }

        if ($loan->member) {
            $this->domainNotifications->member($loan->member, 'LibraryLoanMemberNotification', 'system', $subject, $message, ['in_app', 'email'], ['url' => route('bookstore.index')]);
        }
    }

    private function notifyRequester(Approval $approval, string $subject, string $message): void
    {
        $approval->loadMissing(['requester', 'approvable']);
        if ($approval->requester) {
            $financialAssistance = $approval->approvable instanceof FinancialAssistanceRequest;
            $this->domainNotifications->user(
                $approval->requester,
                $financialAssistance ? 'FinancialAssistanceStatusChanged' : 'ApprovalDecision',
                'system',
                $subject,
                $message,
                $financialAssistance ? ['in_app', 'email', 'sms', 'whatsapp'] : ['in_app'],
                ['url' => $financialAssistance ? route('financial-assistance.show', $approval->approvable) : route('workflows.index')],
                true,
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $fallbackSteps
     * @return array{name: string, module: string, description?: string|null, status: string, approval_type: string, timeout_hours: int|string, steps: array<int, array<string, mixed>>}
     */
    private function validateWorkflowPayload(Request $request, ?array $fallbackSteps = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'module' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'draft'])],
            'approval_type' => ['required', Rule::in(['sequential', 'parallel'])],
            'timeout_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'steps' => ['nullable', 'array', 'max:12'],
            'steps.*.label' => ['required_with:steps', 'string', 'max:120'],
            'steps.*.role' => ['required_with:steps', 'string', 'max:120'],
            'steps.*.mode' => ['required_with:steps', Rule::in(['auto', 'required'])],
            'steps.*.instructions' => ['nullable', 'string', 'max:500'],
        ]);

        $rawSteps = $validated['steps'] ?? $fallbackSteps ?? $this->defaultWorkflowSteps();
        $validated['steps'] = $this->normalizeWorkflowSteps($rawSteps);

        return $validated;
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @return array<int, array<string, mixed>>
     */
    private function normalizeWorkflowSteps(array $steps): array
    {
        $normalized = collect($steps)
            ->filter(fn (array $step): bool => filled($step['label'] ?? null) || filled($step['role'] ?? null))
            ->values()
            ->map(function (array $step, int $index): array {
                $mode = ($step['mode'] ?? null) === 'auto' ? 'auto' : 'required';

                return [
                    'position' => $index + 1,
                    'label' => trim((string) ($step['label'] ?? 'Approval Step')),
                    'role' => trim((string) ($step['role'] ?? 'Approver')),
                    'mode' => $mode,
                    'required' => $mode === 'required',
                    'instructions' => filled($step['instructions'] ?? null) ? trim((string) $step['instructions']) : null,
                ];
            })
            ->all();

        return $normalized ?: $this->defaultWorkflowSteps();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultWorkflowSteps(): array
    {
        return [
            ['position' => 1, 'label' => 'Request Submitted', 'role' => 'Requester', 'mode' => 'auto', 'required' => false, 'instructions' => 'Capture the request and route it to the first approver.'],
            ['position' => 2, 'label' => 'Leader Review', 'role' => 'Ministry Leader', 'mode' => 'required', 'required' => true, 'instructions' => 'Review ministry impact, timing, and readiness before final approval.'],
            ['position' => 3, 'label' => 'Final Approval', 'role' => 'Administrator', 'mode' => 'required', 'required' => true, 'instructions' => 'Confirm policy, capacity, and final authorization.'],
        ];
    }

    /**
     * @return Collection<int, string>
     */
    private function workflowRoleOptions(): Collection
    {
        return Role::query()
            ->orderBy('name')
            ->pluck('name')
            ->merge([
                'Requester',
                'Super Administrator',
                'Church Administrator',
                'Senior Pastor',
                'Branch Pastor',
                'Finance Officer',
                'Membership Officer',
                'Asset Manager',
                'Book Store Manager',
                'Ministry Leader',
                'Staff',
                'Viewer',
            ])
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function requiredApprovalSteps(?Workflow $workflow)
    {
        $workflowSteps = $workflow?->steps ?? [];
        $steps = array_is_list($workflowSteps) ? $workflowSteps : data_get($workflowSteps, 'steps', []);

        return collect($steps)
            ->values()
            ->map(function (array $step, int $index): array {
                $mode = data_get($step, 'mode', data_get($step, 'required') ? 'required' : 'auto');

                return [
                    'position' => (int) data_get($step, 'position', $index + 1),
                    'label' => (string) data_get($step, 'label', data_get($step, 'role', 'Approval Step')),
                    'role' => (string) data_get($step, 'role', 'Approver'),
                    'mode' => $mode,
                ];
            })
            ->filter(fn (array $step): bool => $step['mode'] === 'required')
            ->values();
    }

    private function authorizeWorkflow(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('manage workflows'), 403);
    }

    private function authorizeAssignmentActor(Request $request, ProgramSectionAssignment $assignment): void
    {
        $user = $request->user();
        abort_unless($user?->isSuperAdministrator() || $user?->hasPermission('manage workflows') || $assignment->user_id === $user?->id, 403);
    }

    private function scopeApprovalQuery(Builder $query, Request $request): void
    {
        if ($request->user()?->isSuperAdministrator()) {
            return;
        }

        $query->where('church_id', $request->user()?->church_id);
    }

    private function scopeWorkflowQuery(Builder $query, Request $request): void
    {
        if ($request->user()?->isSuperAdministrator()) {
            return;
        }

        $query->where('church_id', $request->user()?->church_id);
    }

    private function scopeActivityQuery(Builder $query, Request $request): void
    {
        if ($request->user()?->isSuperAdministrator()) {
            return;
        }

        $query->where('church_id', $request->user()?->church_id);
    }

    private function workflowTemplates(): array
    {
        return [
            ['name' => 'Event Creation Approval', 'module' => 'events', 'steps' => 5, 'used' => 24],
            ['name' => 'Budget Approval', 'module' => 'finances', 'steps' => 4, 'used' => 18],
            ['name' => 'Volunteer Assignment', 'module' => 'volunteers', 'steps' => 3, 'used' => 32],
            ['name' => 'Facility Booking', 'module' => 'facilities', 'steps' => 4, 'used' => 15],
            ['name' => 'Ministry Request', 'module' => 'ministries', 'steps' => 3, 'used' => 21],
        ];
    }
}
