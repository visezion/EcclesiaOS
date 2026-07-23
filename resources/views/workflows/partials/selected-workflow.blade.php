<article id="workflow-design" class="workflow-side-detail rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 p-4">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-base font-semibold text-slate-950">{{ $selectedWorkflow?->name ?? 'Workflow Design' }}</h2>
                    @if($selectedWorkflow)
                        <span class="rounded-full px-2.5 py-1 text-xs ring-1 {{ $statusTone[$selectedWorkflow->status] ?? $statusTone['draft'] }}">{{ Str::headline($selectedWorkflow->status) }}</span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-slate-500">Module: {{ Str::headline($selectedWorkflow?->module ?? 'Workflow') }} <span class="mx-2 text-slate-300">|</span> {{ $workflowDescription }}</p>
            </div>
            @if($selectedWorkflow)
                <div class="grid grid-cols-2 gap-2 text-xs sm:grid-cols-3">
                    <div class="rounded-lg bg-slate-50 px-3 py-2"><div class="text-slate-500">Instances</div><div class="font-semibold text-slate-950">{{ number_format($selectedWorkflow->active_instances_count ?? 0) }}</div></div>
                    <div class="rounded-lg bg-amber-50 px-3 py-2"><div class="text-amber-700">Pending</div><div class="font-semibold text-slate-950">{{ number_format($selectedWorkflow->pending_approvals_count ?? 0) }}</div></div>
                    <div class="rounded-lg bg-violet-50 px-3 py-2"><div class="text-violet-600">Steps</div><div class="font-semibold text-slate-950">{{ number_format($selectedSteps->count()) }}</div></div>
                </div>
            @endif
        </div>
    </div>
    <div class="p-4">
        <div class="workflow-step-flow">
            @forelse($selectedSteps as $index => $step)
                @php
                    $mode = data_get($step, 'mode', data_get($step, 'required') ? 'required' : 'auto');
                @endphp
                <div>
                    <div class="workflow-step-card">
                        <div class="grid size-10 place-items-center rounded-lg {{ $mode === 'auto' ? 'bg-emerald-50 text-emerald-600' : 'bg-violet-50 text-violet-600' }}"><i data-lucide="{{ $mode === 'auto' ? 'badge-check' : 'user-check' }}" class="size-4"></i></div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-500">Step {{ $index + 1 }}</span>
                                <p class="text-xs font-medium {{ $mode === 'auto' ? 'text-emerald-600' : 'text-orange-600' }}">{{ $mode === 'auto' ? 'Auto-advance' : 'Approval Required' }}</p>
                            </div>
                            <h3 class="mt-1 truncate text-sm font-semibold text-slate-950">{{ data_get($step, 'label', data_get($step, 'role', 'Approval Step')) }}</h3>
                            <p class="mt-1 text-xs text-slate-500">Approver Role: <span class="font-medium text-slate-700">{{ data_get($step, 'role', 'Approver') }}</span></p>
                            @if(filled(data_get($step, 'instructions')))
                                <p class="mt-2 text-xs leading-5 text-slate-500">{{ data_get($step, 'instructions') }}</p>
                            @endif
                        </div>
                        <i data-lucide="{{ $mode === 'auto' ? 'check-circle-2' : 'shield-alert' }}" class="size-4 {{ $mode === 'auto' ? 'text-emerald-600' : 'text-orange-600' }}"></i>
                    </div>
                    @if(! $loop->last)
                        <span class="workflow-step-connector py-1"><i data-lucide="chevron-down" class="size-4"></i></span>
                    @endif
                </div>
            @empty
                <div class="w-full rounded-lg border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500">Create or import a workflow to see approval steps.</div>
            @endforelse
        </div>
        <div class="workflow-settings-grid mt-4">
            <section class="rounded-lg border border-slate-200 p-4">
                <h3 class="text-sm font-semibold text-slate-950">Workflow Settings</h3>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-xs">
                    <div><dt class="text-slate-500">Approval Type</dt><dd class="mt-1 font-medium text-slate-800">{{ Str::headline((string) data_get($selectedMeta, 'approval_type', 'sequential')) }}</dd></div>
                    <div><dt class="text-slate-500">Timeout Duration</dt><dd class="mt-1 font-medium text-slate-800">{{ data_get($selectedMeta, 'timeout_hours', 72) }} Hours</dd></div>
                    <div><dt class="text-slate-500">Require Comments</dt><dd class="mt-1 font-medium text-slate-800">Yes</dd></div>
                    <div><dt class="text-slate-500">Notify Requester</dt><dd class="mt-1 font-medium text-slate-800">On All Actions</dd></div>
                </dl>
            </section>
            <section class="rounded-lg border border-slate-200 p-4">
                <h3 class="text-sm font-semibold text-slate-950">Workflow Rules</h3>
                <ul class="mt-3 space-y-2 text-xs text-slate-600">
                    @foreach(['Event start date must be in the future', 'Budget must be attached for paid events', 'Venue must be available', 'Ministry must be active'] as $rule)
                        <li class="flex items-center gap-2"><i data-lucide="check-circle-2" class="size-4 text-emerald-600"></i>{{ $rule }}</li>
                    @endforeach
                </ul>
            </section>
        </div>
    </div>
</article>
