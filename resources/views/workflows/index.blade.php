<x-app-layout title="Workflow & Approvals" :breadcrumbs="$breadcrumbs">
    @php
        $statusTone = [
            'pending' => 'bg-amber-50 text-amber-700 ring-amber-100',
            'approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
            'rejected' => 'bg-rose-50 text-rose-700 ring-rose-100',
            'active' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
            'draft' => 'bg-slate-100 text-slate-700 ring-slate-200',
        ];
        $moduleTone = [
            'programs' => ['icon' => 'calendar-check', 'tone' => 'bg-violet-50 text-violet-600'],
            'events' => ['icon' => 'calendar-plus', 'tone' => 'bg-violet-50 text-violet-600'],
            'finances' => ['icon' => 'badge-dollar-sign', 'tone' => 'bg-orange-50 text-orange-600'],
            'volunteers' => ['icon' => 'users-round', 'tone' => 'bg-emerald-50 text-emerald-600'],
            'facilities' => ['icon' => 'building-2', 'tone' => 'bg-blue-50 text-blue-600'],
            'ministries' => ['icon' => 'landmark', 'tone' => 'bg-cyan-50 text-cyan-600'],
        ];
        $cards = [
            ['label' => 'Pending Approvals', 'value' => $stats['pending'], 'hint' => 'awaiting decision', 'icon' => 'clipboard-check', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'In Progress', 'value' => $stats['in_progress'], 'hint' => 'active approval items', 'icon' => 'git-branch', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100'],
            ['label' => 'Completed (20 Days)', 'value' => $stats['completed_20'] ?? $stats['completed_30'], 'hint' => 'approved or rejected', 'icon' => 'calendar-check', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Average Approval Time', 'value' => $stats['average_days'].' days', 'hint' => 'approved requests', 'icon' => 'clock', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Overdue Approvals', 'value' => $stats['overdue'], 'hint' => 'over 72 hours', 'icon' => 'clock-3', 'tone' => 'bg-rose-50 text-rose-600 ring-rose-100'],
            ['label' => 'Active Workflows', 'value' => $stats['active_workflows'], 'hint' => 'across modules', 'icon' => 'network', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
        ];
        $approvalTotal = $stats['total'];
        $approvedPct = $approvalTotal > 0 ? round(($statusBreakdown['approved'] / $statusBreakdown['total']) * 100, 1) : 0;
        $pendingPct = $approvalTotal > 0 ? round(($statusBreakdown['pending'] / $statusBreakdown['total']) * 100, 1) : 0;
        $rejectedPct = $approvalTotal > 0 ? round(($statusBreakdown['rejected'] / $statusBreakdown['total']) * 100, 1) : 0;
        $donut = $approvalTotal > 0
            ? 'conic-gradient(#10b981 0 '.$approvedPct.'%, #f97316 '.$approvedPct.'% '.($approvedPct + $pendingPct).'%, #ef4444 '.($approvedPct + $pendingPct).'% 100%)'
            : 'conic-gradient(#e2e8f0 0 100%)';
        $selectedMeta = $selectedWorkflow?->steps ?? [];
        $selectedSteps = $selectedWorkflow
            ? collect(array_is_list($selectedMeta) ? $selectedMeta : data_get($selectedMeta, 'steps', []))
            : collect();
        $workflowDescription = $selectedWorkflow
            ? (data_get($selectedMeta, 'description') ?: 'Approval workflow for '.$selectedWorkflow->module)
            : 'Create a workflow to define approval steps.';
        $activeWorkflowFilters = filled(request('q')) || filled(request('module')) || filled(request('workflow_status'));
        $workflowFormState = (string) old('_workflow_form', '');
        $initialEditOpen = str_starts_with($workflowFormState, 'edit-') ? substr($workflowFormState, 5) : null;
        $defaultWorkflowSteps = [
            ['label' => 'Request Submitted', 'role' => 'Requester', 'mode' => 'auto', 'instructions' => 'Capture the request and route it to the first approver.'],
            ['label' => 'Leader Review', 'role' => 'Ministry Leader', 'mode' => 'required', 'instructions' => 'Review ministry impact, timing, and readiness.'],
            ['label' => 'Final Approval', 'role' => 'Administrator', 'mode' => 'required', 'instructions' => 'Confirm policy and final authorization.'],
        ];
        $createWorkflowSteps = $workflowFormState === 'create' ? old('steps', $defaultWorkflowSteps) : $defaultWorkflowSteps;
        $workflowRoleOptions = ['Requester', 'Super Administrator', 'Church Administrator', 'Senior Pastor', 'Branch Pastor', 'Finance Officer', 'Membership Officer', 'Asset Manager', 'Book Store Manager', 'Ministry Leader', 'Staff', 'Viewer'];
    @endphp

    <div x-data="{ createOpen: @js($workflowFormState === 'create'), importOpen: @js($workflowFormState === 'import'), editOpen: @js($initialEditOpen) }" class="space-y-5">
        <datalist id="workflow-role-options">
            @foreach($workflowRoleOptions as $roleOption)
                <option value="{{ $roleOption }}"></option>
            @endforeach
        </datalist>
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-950">Workflow & Approvals</h1>
                <p class="mt-1 text-sm text-slate-500">Design, manage and monitor all approval workflows across the system.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="importOpen = true" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                    <i data-lucide="download" class="size-4"></i>
                    Import Workflow
                </button>
                <button type="button" @click="createOpen = true" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white hover:bg-violet-700">
                    <i data-lucide="plus" class="size-4"></i>
                    New Workflow
                </button>
            </div>
        </div>

        @if(session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ $errors->first() }}</div>
        @endif

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
            @foreach($cards as $card)
                <article class="dashboard-card">
                    <div class="flex items-center gap-3">
                        <span class="grid size-11 shrink-0 place-items-center rounded-lg ring-1 {{ $card['tone'] }}"><i data-lucide="{{ $card['icon'] }}" class="size-5"></i></span>
                        <div class="min-w-0">
                            <div class="truncate text-xs text-slate-500">{{ $card['label'] }}</div>
                            <div class="mt-1 truncate text-2xl font-semibold text-slate-950">{{ is_numeric($card['value']) ? number_format($card['value']) : $card['value'] }}</div>
                            <div class="truncate text-xs text-slate-500">{{ $card['hint'] }}</div>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('workflows.index') }}" class="workflow-filter-grid">
                <label class="text-sm text-slate-600">
                    Search Workflows
                    <span class="relative mt-1 block">
                        <input name="q" value="{{ request('q') }}" class="w-full rounded-lg border border-slate-200 px-4 py-2.5 pl-10 text-sm" placeholder="Search by workflow or module...">
                        <i data-lucide="search" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
                    </span>
                </label>
                <label class="text-sm text-slate-600">
                    Module
                    <select name="module" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        <option value="">All Modules</option>
                        @foreach($modules as $module)
                            <option value="{{ $module }}" @selected(request('module') === $module)>{{ Str::headline($module) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm text-slate-600">
                    Status
                    <select name="workflow_status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        <option value="">All Statuses</option>
                        @foreach(['active', 'draft'] as $status)
                            <option value="{{ $status }}" @selected(request('workflow_status') === $status)>{{ Str::headline($status) }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white">
                    <i data-lucide="sliders-horizontal" class="size-4"></i>
                    Apply
                </button>
                @if($activeWorkflowFilters)
                    <a href="{{ route('workflows.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">Clear</a>
                @endif
            </form>
        </section>
        <section class="workflow-detail-grid">
            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-slate-950">Workflow Templates</h2>
                    <button type="button" @click="createOpen = true" class="text-xs font-medium text-violet-600">View All Templates</button>
                </div>
                <div class="workflow-template-grid mt-4">
                    @foreach($templates as $template)
                        @php
                            $templateMeta = $moduleTone[$template['module']] ?? ['icon' => 'git-branch', 'tone' => 'bg-slate-50 text-slate-600'];
                        @endphp
                        <form method="POST" action="{{ route('workflows.store') }}" class="rounded-lg border border-slate-200 p-4 transition hover:-translate-y-0.5 hover:border-violet-200 hover:shadow-sm">
                            @csrf
                            <input type="hidden" name="name" value="{{ $template['name'] }}">
                            <input type="hidden" name="module" value="{{ $template['module'] }}">
                            <input type="hidden" name="description" value="{{ $template['name'] }} template">
                            <input type="hidden" name="status" value="draft">
                            <input type="hidden" name="approval_type" value="sequential">
                            <input type="hidden" name="timeout_hours" value="72">
                            <span class="mb-3 grid size-10 place-items-center rounded-lg {{ $templateMeta['tone'] }}"><i data-lucide="{{ $templateMeta['icon'] }}" class="size-5"></i></span>
                            <h3 class="text-sm font-semibold text-slate-950">{{ $template['name'] }}</h3>
                            <p class="mt-2 text-xs text-slate-500">{{ $template['steps'] }} Steps</p>
                            <p class="mt-1 text-xs text-slate-500">Used {{ $template['used'] }} times</p>
                            <button class="mt-4 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-violet-600 hover:bg-violet-50">Use Template</button>
                        </form>
                    @endforeach
                </div>
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-base font-semibold text-slate-950">Quick Actions</h2>
                <div class="workflow-actions-grid mt-5 text-center text-xs font-semibold text-slate-700">
                    <button type="button" @click="createOpen = true" class="rounded-lg p-2 transition hover:bg-violet-50"><span class="mx-auto grid size-16 place-items-center rounded-lg bg-violet-50 text-violet-600 ring-1 ring-violet-100"><i data-lucide="plus" class="size-8"></i></span><span class="mt-2 block">Create Workflow</span></button>
                    <a href="#workflow-design" class="rounded-lg p-2 transition hover:bg-orange-50"><span class="mx-auto grid size-16 place-items-center rounded-lg bg-orange-50 text-orange-600 ring-1 ring-orange-100"><i data-lucide="layout-grid" class="size-8"></i></span><span class="mt-2 block">Approval Matrix</span></a>
                    <a href="{{ route('roles.index') }}" class="rounded-lg p-2 transition hover:bg-emerald-50"><span class="mx-auto grid size-16 place-items-center rounded-lg bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100"><i data-lucide="user-round" class="size-8"></i></span><span class="mt-2 block">Permission Roles</span></a>
                    <a href="{{ route('settings.index') }}#workflow" class="rounded-lg p-2 transition hover:bg-blue-50"><span class="mx-auto grid size-16 place-items-center rounded-lg bg-blue-50 text-blue-600 ring-1 ring-blue-100"><i data-lucide="settings" class="size-8"></i></span><span class="mt-2 block">Settings</span></a>
                    <a href="{{ route('audit-logs.index') }}" class="rounded-lg p-2 transition hover:bg-violet-50"><span class="mx-auto grid size-16 place-items-center rounded-lg bg-violet-50 text-violet-600 ring-1 ring-violet-100"><i data-lucide="pie-chart" class="size-8"></i></span><span class="mt-2 block">Audit Reports</span></a>
                </div>
            </article>

             
        </section>
        <section class="workflow-main-grid">
            <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 p-4">
                    <div>
                        <h2 class="inline-flex items-center gap-2 text-base font-semibold text-slate-950"><i data-lucide="layout-list" class="size-4 text-violet-600"></i>Active Workflows</h2>
                        <div class="mt-1 text-xs text-slate-500">Showing {{ $workflows->firstItem() ?? 0 }} to {{ $workflows->lastItem() ?? 0 }} of {{ number_format($workflows->total()) }}</div>
                    </div>
                    @if($selectedWorkflow)
                        <span class="rounded-lg bg-violet-50 px-3 py-1.5 text-xs font-semibold text-violet-700 ring-1 ring-violet-100">Selected workflow</span>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="workflow-table w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Workflow Name</th>
                                <th class="px-4 py-3">Module</th>
                                <th class="px-4 py-3">Description</th>
                                <th class="px-4 py-3">Active</th>
                                <th class="px-4 py-3">Pending</th>
                                <th class="px-4 py-3">Last Updated</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($workflows as $workflow)
                                @php
                                    $meta = $moduleTone[$workflow->module] ?? ['icon' => 'git-branch', 'tone' => 'bg-slate-50 text-slate-600'];
                                    $rowSelected = $selectedWorkflow?->is($workflow);
                                    $rowDescription = data_get($workflow->steps ?? [], 'description', 'Approval workflow for '.$workflow->module);
                                @endphp
                                <tr class="{{ $rowSelected ? 'bg-violet-50/70' : 'hover:bg-slate-50/70' }}">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="grid size-8 place-items-center rounded-lg {{ $meta['tone'] }}"><i data-lucide="{{ $meta['icon'] }}" class="size-4"></i></span>
                                            <div>
                                                <a href="{{ route('workflows.index', array_merge(request()->except('page'), ['workflow' => $workflow->opaqueId()])) }}#workflow-design" class="font-medium text-slate-950 hover:text-violet-600">{{ $workflow->name }}</a>
                                                @if($rowSelected)
                                                    <div class="mt-0.5 text-xs font-medium text-violet-600">Selected workflow</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ Str::headline($workflow->module) }}</td>
                                    <td class="max-w-xs px-4 py-3 text-xs text-slate-500">{{ Str::limit($rowDescription, 86) }}</td>
                                    <td class="px-4 py-3 text-slate-900">{{ number_format($workflow->active_instances_count) }}</td>
                                    <td class="px-4 py-3 text-slate-900">{{ number_format($workflow->pending_approvals_count) }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-500">{{ $workflow->updated_at->format('M d, Y') }}<br>{{ $workflow->updated_at->format('h:i A') }}</td>
                                    <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs ring-1 {{ $statusTone[$workflow->status] ?? $statusTone['draft'] }}">{{ Str::headline($workflow->status) }}</span></td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex items-center justify-end gap-1">
                                            <a href="{{ route('workflows.index', array_merge(request()->except('page'), ['workflow' => $workflow->opaqueId()])) }}#workflow-design" class="inline-grid size-8 place-items-center rounded-lg text-slate-500 hover:bg-violet-100 hover:text-violet-600" title="View workflow design"><i data-lucide="eye" class="size-4"></i></a>
                                            <button type="button" @click="editOpen = '{{ $workflow->opaqueId() }}'" class="inline-grid size-8 place-items-center rounded-lg text-slate-500 hover:bg-blue-50 hover:text-blue-600" title="Edit workflow"><i data-lucide="pencil" class="size-4"></i></button>
                                            <form method="POST" action="{{ route('workflows.destroy', $workflow) }}" onsubmit="return confirm('Delete {{ addslashes($workflow->name) }} workflow? Existing approval history will remain available in logs.')" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button class="inline-grid size-8 place-items-center rounded-lg text-slate-500 hover:bg-rose-50 hover:text-rose-600" title="Delete workflow"><i data-lucide="trash-2" class="size-4"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">No workflows found for the current filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 p-4">{{ $workflows->links() }}</div>
            </article>

            @include('workflows.partials.selected-workflow')
        </section>

        <section class="workflow-overview-grid">
        
            <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 p-4">
                    <h2 class="inline-flex items-center gap-2 text-base font-semibold text-slate-950"><i data-lucide="sliders-horizontal" class="size-4 text-violet-600"></i>My Pending Approvals</h2>
                    <a href="{{ route('workflows.index', ['status' => 'pending']) }}" class="text-xs font-medium text-violet-600">View All ({{ $stats['pending'] }})</a>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($pendingApprovals as $approval)
                        @php
                            $payload = $approval->payload ?? [];
                            $ageHours = (int) ($approval->submitted_at ?? $approval->created_at)->diffInHours(now());
                            $priority = $ageHours >= 72 ? ['High', 'bg-rose-50 text-rose-700'] : ($ageHours >= 24 ? ['Medium', 'bg-orange-50 text-orange-700'] : ['Low', 'bg-emerald-50 text-emerald-700']);
                        @endphp
                        <div class="workflow-pending-row p-4">
                            <div class="flex items-start gap-3">
                                <span class="grid size-10 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="user-check" class="size-4"></i></span>
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="font-semibold text-slate-950">{{ $payload['title'] ?? $payload['section'] ?? Str::headline((string) $approval->action) }}</div>
                                        <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $priority[1] }}">{{ $priority[0] }}</span>
                                    </div>
                                    <div class="text-xs text-slate-500">{{ Str::headline((string) $approval->action) }} / {{ $ageHours < 1 ? 'Just now' : $ageHours.'h ago' }}</div>
                                    <div class="mt-1 text-xs text-slate-500">Requested by {{ $approval->requester?->name ?? 'System' }}</div>
                                </div>
                            </div>
                            <div class="flex gap-2 sm:justify-end">
                                <form method="POST" action="{{ route('workflows.approvals.approve', $approval) }}">@csrf<button class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-medium text-white">Approve</button></form>
                                <form method="POST" action="{{ route('workflows.approvals.reject', $approval) }}">@csrf<button class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-medium text-rose-700">Reject</button></form>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center">
                            <div class="relative mx-auto mb-4 grid h-28 w-44 place-items-center overflow-hidden rounded-2xl bg-violet-50">
                                <span class="absolute -left-4 top-10 h-10 w-16 rounded-full bg-violet-100/80"></span>
                                <span class="absolute -right-5 top-7 h-14 w-20 rounded-full bg-violet-100/80"></span>
                                <span class="grid size-20 place-items-center rounded-2xl bg-white text-violet-500 shadow-sm ring-1 ring-violet-100"><i data-lucide="clipboard-check" class="size-10"></i></span>
                                <span class="absolute bottom-6 right-12 grid size-10 place-items-center rounded-full bg-violet-600 text-white ring-4 ring-violet-50"><i data-lucide="check" class="size-5"></i></span>
                            </div>
                            <div class="text-base font-semibold text-slate-700">No pending approvals.</div>
                            <p class="mt-1 text-sm text-slate-500">New approval requests will appear here.</p>
                        </div>
                    @endforelse
                </div>
            </article>    
            <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 p-4">
                    <h2 class="text-base font-semibold text-slate-950">Recent Activity</h2>
                    <a href="{{ route('audit-logs.index') }}" class="text-xs font-medium text-violet-600">View All</a>
                </div>
                <div class="relative">
                    @forelse($recentActivity as $activity)
                        @php
                            $activityTone = ['bg-blue-50 text-blue-600 ring-blue-100', 'bg-blue-50 text-blue-700', 'calendar-days'];

                            if (str_contains((string) $activity->action, 'reject')) {
                                $activityTone = ['bg-rose-50 text-rose-600 ring-rose-100', 'bg-rose-50 text-rose-700', 'circle-alert'];
                            } elseif (str_contains((string) $activity->action, 'approve')) {
                                $activityTone = ['bg-emerald-50 text-emerald-600 ring-emerald-100', 'bg-emerald-50 text-emerald-700', 'badge-check'];
                            } elseif ($activity->module === 'Program Sections') {
                                $activityTone = ['bg-emerald-50 text-emerald-600 ring-emerald-100', 'bg-emerald-50 text-emerald-700', 'book-open'];
                            }
                        @endphp
                        <div class="workflow-activity-row relative border-b border-slate-100 p-4 text-sm last:border-b-0">
                            @if(! $loop->last)
                                <span class="workflow-activity-line absolute bottom-0 top-12 w-px bg-slate-200"></span>
                            @endif
                            <span class="relative z-10 grid size-10 place-items-center rounded-lg ring-1 {{ $activityTone[0] }}"><i data-lucide="{{ $activityTone[2] }}" class="size-4"></i></span>
                            <div>
                                <span class="mb-1 inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $activityTone[1] }}">{{ Str::headline(Str::before($activity->action, '_')) }}</span>
                                <div class="font-medium text-slate-950">{{ $activity->description }}</div>
                                <div class="text-xs text-slate-500">{{ $activity->module }}</div>
                            </div>
                            <div class="text-right text-xs text-slate-500">{{ $activity->created_at->format('M d, Y') }}<br>{{ $activity->created_at->format('h:i A') }}</div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-sm text-slate-500">No workflow activity yet.</div>
                    @endforelse
                </div>
            </article>

        </section>

     

        

        <div x-cloak x-show="createOpen || importOpen || editOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/40" @click="createOpen = false; importOpen = false; editOpen = null"></div>
        <aside x-cloak x-show="createOpen" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-lg overflow-y-auto bg-white p-6 shadow-2xl">
            <div class="mb-5 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">New Workflow</h2>
                    <p class="text-sm text-slate-500">Create an approval chain for any operational module.</p>
                </div>
                <button type="button" @click="createOpen = false" class="rounded-lg p-2 hover:bg-slate-100" aria-label="Close"><i data-lucide="x" class="size-5"></i></button>
            </div>
            <form method="POST" action="{{ route('workflows.store') }}" class="space-y-4" x-data="workflowBuilder(@js($createWorkflowSteps))">
                @csrf
                <input type="hidden" name="_workflow_form" value="create">
                <label class="block text-sm font-medium text-slate-700">
                    Workflow Name
                    <input name="name" required value="{{ old('name') }}" placeholder="Example: Event Creation Approval" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                    <span class="mt-1 block text-xs font-normal text-slate-500">Name shown in requests, reports, and audit logs.</span>
                </label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-700">
                        Module
                        <select name="module" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            @foreach(['events', 'programs', 'finances', 'volunteers', 'facilities', 'ministries'] as $module)
                                <option value="{{ $module }}" @selected(old('module', 'events') === $module)>{{ Str::headline($module) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-700">
                        Status
                        <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            <option value="draft" @selected(old('status', 'draft') === 'draft')>Draft</option>
                            <option value="active" @selected(old('status', 'draft') === 'active')>Active</option>
                        </select>
                    </label>
                </div>
                <label class="block text-sm font-medium text-slate-700">
                    Description
                    <textarea name="description" rows="3" placeholder="What does this workflow approve?" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">{{ old('description') }}</textarea>
                </label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-700">
                        Approval Type
                        <select name="approval_type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            <option value="sequential" @selected(old('approval_type', 'sequential') === 'sequential')>Sequential</option>
                            <option value="parallel" @selected(old('approval_type', 'sequential') === 'parallel')>Parallel</option>
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-700">
                        Timeout Hours
                        <input name="timeout_hours" required type="number" min="1" max="720" value="{{ old('timeout_hours', 72) }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                    </label>
                </div>
                <section class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-950">Approval Path</h3>
                            <p class="mt-1 text-xs text-slate-500" x-text="`${steps.length} step${steps.length === 1 ? '' : 's'} configured`"></p>
                        </div>
                        <button type="button" @click="addStep()" class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-2 text-xs font-semibold text-violet-700 ring-1 ring-slate-200 hover:bg-violet-50">
                            <i data-lucide="plus" class="size-4"></i>
                            Add
                        </button>
                    </div>
                    <div class="mt-3 space-y-3">
                        <template x-for="(step, index) in steps" :key="step.uid">
                            <article class="rounded-lg border border-slate-200 bg-white p-3">
                                <div class="mb-3 flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold uppercase text-violet-600" x-text="`Step ${index + 1}`"></span>
                                    <div class="flex gap-1">
                                        <button type="button" @click="moveStep(index, -1)" :disabled="index === 0" class="rounded border border-slate-200 px-2 py-1 text-xs text-slate-600 disabled:opacity-40">Up</button>
                                        <button type="button" @click="moveStep(index, 1)" :disabled="index === steps.length - 1" class="rounded border border-slate-200 px-2 py-1 text-xs text-slate-600 disabled:opacity-40">Down</button>
                                        <button type="button" @click="removeStep(index)" :disabled="steps.length === 1" class="rounded border border-rose-100 px-2 py-1 text-xs text-rose-600 disabled:opacity-40">Remove</button>
                                    </div>
                                </div>
                                <div class="grid gap-2">
                                    <input x-model="step.label" :name="`steps[${index}][label]`" required maxlength="120" placeholder="Step name" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <input x-model="step.role" :name="`steps[${index}][role]`" required maxlength="120" list="workflow-role-options" placeholder="Approver role" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <select x-model="step.mode" :name="`steps[${index}][mode]`" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                        <option value="required">Approval Required</option>
                                        <option value="auto">Auto-advance</option>
                                    </select>
                                    <textarea x-model="step.instructions" :name="`steps[${index}][instructions]`" rows="2" maxlength="500" placeholder="Responsibility notes for this approver" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
                                </div>
                            </article>
                        </template>
                    </div>
                </section>
                <button class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white"><i data-lucide="save" class="size-4"></i>Create Workflow</button>
            </form>
        </aside>
        @foreach($workflows as $workflow)
            @php
                $workflowMeta = $workflow->steps ?? [];
            @endphp
            <aside x-cloak x-show="editOpen === '{{ $workflow->opaqueId() }}'" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-lg overflow-y-auto bg-white p-6 shadow-2xl">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Edit Workflow</h2>
                        <p class="text-sm text-slate-500">{{ $workflow->name }}</p>
                    </div>
                    <button type="button" @click="editOpen = null" class="rounded-lg p-2 hover:bg-slate-100" aria-label="Close"><i data-lucide="x" class="size-5"></i></button>
                </div>
                <form method="POST" action="{{ route('workflows.update', $workflow) }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input name="name" required value="{{ $workflow->name }}" placeholder="Workflow name" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <select name="module" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            @foreach(['events', 'programs', 'finances', 'volunteers', 'facilities', 'ministries'] as $module)
                                <option value="{{ $module }}" @selected($workflow->module === $module)>{{ Str::headline($module) }}</option>
                            @endforeach
                        </select>
                        <select name="status" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            <option value="active" @selected($workflow->status === 'active')>Active</option>
                            <option value="draft" @selected($workflow->status === 'draft')>Draft</option>
                        </select>
                    </div>
                    <textarea name="description" rows="3" placeholder="Description" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">{{ data_get($workflowMeta, 'description', 'Approval workflow for '.$workflow->module) }}</textarea>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <select name="approval_type" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            <option value="sequential" @selected(data_get($workflowMeta, 'approval_type', 'sequential') === 'sequential')>Sequential</option>
                            <option value="parallel" @selected(data_get($workflowMeta, 'approval_type', 'sequential') === 'parallel')>Parallel</option>
                        </select>
                        <input name="timeout_hours" required type="number" min="1" max="720" value="{{ data_get($workflowMeta, 'timeout_hours', 72) }}" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                    </div>
                    <button class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white"><i data-lucide="save" class="size-4"></i>Save Changes</button>
                </form>
            </aside>
        @endforeach
        <aside x-cloak x-show="importOpen" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-lg overflow-y-auto bg-white p-6 shadow-2xl">
            <div class="mb-5 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Import Workflow</h2>
                    <p class="text-sm text-slate-500">Paste a workflow JSON definition or create an empty imported draft.</p>
                </div>
                <button type="button" @click="importOpen = false" class="rounded-lg p-2 hover:bg-slate-100" aria-label="Close"><i data-lucide="x" class="size-5"></i></button>
            </div>
            <form method="POST" action="{{ route('workflows.import') }}" class="space-y-4">
                @csrf
                <input name="name" required placeholder="Imported workflow name" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                <select name="module" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                    @foreach(['events', 'programs', 'finances', 'volunteers', 'facilities', 'ministries'] as $module)
                        <option value="{{ $module }}">{{ Str::headline($module) }}</option>
                    @endforeach
                </select>
                <textarea name="definition" rows="8" placeholder='{"description":"Workflow description","approval_type":"sequential","timeout_hours":72,"steps":[]}' class="w-full rounded-lg border border-slate-200 px-3 py-2.5 font-mono text-xs"></textarea>
                <button class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white"><i data-lucide="download" class="size-4"></i>Import Workflow</button>
            </form>
        </aside>
    </div>
</x-app-layout>
