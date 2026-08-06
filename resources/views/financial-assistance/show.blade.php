<x-app-layout title="{{ $assistance->reference }}" :breadcrumbs="$breadcrumbs">
    @php
        $statusTone = [
            'submitted' => 'bg-blue-50 text-blue-700 ring-blue-200', 'under_review' => 'bg-violet-50 text-violet-700 ring-violet-200',
            'changes_requested' => 'bg-amber-50 text-amber-700 ring-amber-200', 'approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'disbursed' => 'bg-teal-50 text-teal-700 ring-teal-200', 'rejected' => 'bg-rose-50 text-rose-700 ring-rose-200',
            'cancelled' => 'bg-slate-100 text-slate-600 ring-slate-200',
        ];
        $steps = [
            ['submitted', 'Submitted', 'Request and evidence received.', true],
            ['campus_review', 'Campus review', 'Need and local context verified.', in_array($assistance->current_stage, ['finance_review','disbursement','complete']) || in_array($assistance->status, ['approved','disbursed'])],
            ['finance_review', 'Finance authorization', 'Funding and policy authorization.', in_array($assistance->current_stage, ['disbursement','complete']) && in_array($assistance->status, ['approved','disbursed'])],
            ['disbursement', 'Disbursement', 'Payment and reference recorded.', $assistance->status === 'disbursed'],
        ];
    @endphp
    <div class="space-y-5">
        @if(session('status'))<x-alert type="success">{{ session('status') }}</x-alert>@endif
        @if($errors->any())<x-alert type="error">{{ $errors->first() }}</x-alert>@endif

        <section class="dashboard-card overflow-hidden p-0">
            <div class="p-5">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex min-w-0 items-start gap-4"><span class="grid size-12 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="hand-coins" class="size-6"></i></span><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="font-mono text-[11px] font-black text-violet-600">{{ $assistance->reference }}</span><span class="rounded-full px-2.5 py-1 text-[10px] font-bold ring-1 {{ $statusTone[$assistance->status] ?? $statusTone['cancelled'] }}">{{ $statuses[$assistance->status] ?? Str::headline($assistance->status) }}</span></div><h1 class="mt-2 text-xl font-black text-slate-950 sm:text-2xl">{{ $assistance->title }}</h1><p class="mt-2 text-sm text-slate-500">Requested by <span class="font-bold text-slate-700">{{ $assistance->requester?->name ?? 'Former user' }}</span> for {{ $assistance->beneficiary_name }}</p></div></div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-5 py-4 lg:min-w-52"><div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Requested amount</div><div class="mt-1 text-2xl font-black text-slate-950">{{ $assistance->currency }} {{ number_format((float) $assistance->amount, 2) }}</div>@if($assistance->approved_amount)<div class="mt-2 text-xs font-semibold text-emerald-600">Approved: {{ $assistance->currency }} {{ number_format((float) $assistance->approved_amount, 2) }}</div>@endif</div>
                </div>
            </div>
        </section>

        <section class="dashboard-card">
            <div class="mb-5 flex items-center justify-between"><div><h2 class="font-black text-slate-950">Approval progress</h2><p class="text-xs text-slate-500">Current stage: {{ Str::headline($assistance->current_stage) }}</p></div><i data-lucide="git-branch" class="size-5 text-violet-500"></i></div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($steps as [$key,$label,$description,$complete])
                    @php($active = $assistance->current_stage === $key || ($key === 'submitted' && $assistance->status === 'submitted'))
                    <div class="relative rounded-xl border p-4 {{ $complete ? 'border-emerald-200 bg-emerald-50/60' : ($active ? 'border-violet-300 bg-violet-50' : 'border-slate-200 bg-slate-50') }}"><span class="grid size-7 place-items-center rounded-full {{ $complete ? 'bg-emerald-500 text-white' : ($active ? 'bg-violet-600 text-white' : 'bg-white text-slate-400 ring-1 ring-slate-200') }}"><i data-lucide="{{ $complete ? 'check' : ($active ? 'loader-circle' : 'circle') }}" class="size-3.5"></i></span><div class="mt-3 text-xs font-black text-slate-900">{{ $label }}</div><p class="mt-1 text-[10px] leading-4 text-slate-500">{{ $description }}</p></div>
                @endforeach
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="space-y-5">
                <section class="dashboard-card">
                    <h2 class="font-black text-slate-950">Request information</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        @foreach([
                            ['Category', $categories[$assistance->category] ?? Str::headline($assistance->category)],
                            ['Beneficiary type', $beneficiaries[$assistance->beneficiary_type] ?? Str::headline($assistance->beneficiary_type)],
                            ['From campus', $assistance->sourceCampus?->name ?? 'Not assigned'],
                            ['Receiving campus', $assistance->targetCampus?->name],
                            ['Urgency', $urgencies[$assistance->urgency] ?? Str::headline($assistance->urgency)],
                            ['Needed by', $assistance->needed_by?->format('M d, Y') ?? 'No fixed date'],
                            ['Preferred payment', $assistance->preferred_payment_method ? Str::headline($assistance->preferred_payment_method) : 'No preference'],
                            ['Payee', $assistance->payee_name ?: 'To be confirmed'],
                        ] as [$label,$value])
                            <div class="rounded-xl bg-slate-50 px-4 py-3"><dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $label }}</dt><dd class="mt-1 text-sm font-bold text-slate-800">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                    <div class="mt-5 border-t border-slate-100 pt-5"><h3 class="text-xs font-black uppercase tracking-wider text-slate-500">Purpose</h3><p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $assistance->purpose }}</p></div>
                    <div class="mt-5 border-t border-slate-100 pt-5"><h3 class="text-xs font-black uppercase tracking-wider text-slate-500">Why assistance is necessary</h3><p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $assistance->justification }}</p></div>
                    @if($assistance->decision_notes)<div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4"><h3 class="flex items-center gap-2 text-xs font-black text-amber-900"><i data-lucide="message-square" class="size-4"></i>Decision notes</h3><p class="mt-2 whitespace-pre-line text-sm text-amber-800">{{ $assistance->decision_notes }}</p></div>@endif
                </section>

                <section class="dashboard-card">
                    <div class="flex items-center justify-between"><div><h2 class="font-black text-slate-950">Evidence</h2><p class="text-xs text-slate-500">{{ $assistance->attachments->count() }} private supporting files</p></div><i data-lucide="paperclip" class="size-5 text-slate-400"></i></div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach($assistance->attachments as $attachment)
                            <a href="{{ route('financial-assistance.attachments.download', $attachment) }}" class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-violet-300 hover:bg-violet-50"><span class="grid size-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-600"><i data-lucide="file-down" class="size-4"></i></span><span class="min-w-0 flex-1"><span class="block truncate text-xs font-black text-slate-800">{{ $attachment->original_name }}</span><span class="mt-1 block text-[10px] text-slate-400">{{ number_format($attachment->size / 1024, 1) }} KB · {{ Str::headline($attachment->kind) }}</span></span></a>
                        @endforeach
                    </div>
                </section>

                <section class="dashboard-card">
                    <h2 class="font-black text-slate-950">Activity and decisions</h2>
                    <div class="mt-5 space-y-0">
                        @foreach($assistance->activities as $activity)
                            <div class="relative flex gap-3 pb-6 last:pb-0"><span class="grid size-8 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-600"><i data-lucide="{{ match($activity->type) {'approved','campus_approved' => 'badge-check', 'rejected','cancelled' => 'x-circle', 'disbursed' => 'badge-dollar-sign', 'changes_requested' => 'message-square', default => 'clock-3'} }}" class="size-4"></i></span>@if(!$loop->last)<span class="absolute left-4 top-8 h-[calc(100%-2rem)] w-px bg-slate-200"></span>@endif<div><div class="text-xs font-black text-slate-800">{{ $activity->description }}</div><div class="mt-1 text-[10px] text-slate-400">{{ $activity->user?->name ?? 'System' }} · {{ $activity->created_at->format('M d, Y g:i A') }}</div>@if(data_get($activity->metadata, 'notes'))<p class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">{{ data_get($activity->metadata, 'notes') }}</p>@endif</div></div>
                        @endforeach
                    </div>
                </section>
            </div>

            <aside class="space-y-5">
                @if($canDecide)
                    <section class="dashboard-card border-violet-200">
                        <div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="badge-check" class="size-5"></i></span><div><h2 class="font-black text-slate-950">{{ $assistance->current_stage === 'finance_review' ? 'Finance authorization' : 'Campus review' }}</h2><p class="text-[10px] text-slate-500">Your decision is logged and the requester is notified.</p></div></div>
                        <form method="POST" action="{{ route('financial-assistance.decide', $assistance) }}" class="mt-4 space-y-3">@csrf
                            @if($assistance->current_stage === 'finance_review')<label><span class="text-xs font-bold text-slate-700">Approved amount</span><div class="mt-1 flex rounded-xl border border-slate-200"><span class="grid place-items-center border-r border-slate-200 px-3 text-[10px] font-black text-slate-500">{{ $assistance->currency }}</span><input name="approved_amount" type="number" min="0.01" step="0.01" value="{{ old('approved_amount', $assistance->amount) }}" class="w-full border-0 text-sm focus:ring-0"></div></label>@endif
                            <label><span class="text-xs font-bold text-slate-700">Decision notes</span><textarea name="notes" rows="4" maxlength="5000" placeholder="Explain approval conditions, required changes, or the reason for rejection." class="mt-1 w-full rounded-xl border-slate-200 text-sm">{{ old('notes') }}</textarea></label>
                            <div class="grid gap-2"><button name="decision" value="approve" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 text-xs font-bold text-white hover:bg-violet-700"><i data-lucide="check" class="size-4"></i>{{ $assistance->current_stage === 'finance_review' ? 'Authorize request' : 'Approve campus review' }}</button><button name="decision" value="request_changes" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 text-xs font-bold text-amber-800"><i data-lucide="message-square" class="size-4"></i>Request changes</button><button name="decision" value="reject" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-rose-200 bg-white px-4 text-xs font-bold text-rose-700"><i data-lucide="x" class="size-4"></i>Reject request</button></div>
                        </form>
                    </section>
                @endif

                @if($canResubmit)
                    <section class="dashboard-card border-amber-200"><h2 class="font-black text-slate-950">Information required</h2><p class="mt-1 text-xs text-slate-500">Respond to the decision notes and optionally add more evidence.</p><form method="POST" action="{{ route('financial-assistance.resubmit', $assistance) }}" enctype="multipart/form-data" class="mt-4 space-y-3">@csrf<textarea name="response" rows="4" required maxlength="5000" placeholder="Describe what you changed or clarify the request." class="w-full rounded-xl border-slate-200 text-sm"></textarea><input type="file" name="evidence[]" multiple class="block w-full text-xs text-slate-500"><button class="w-full rounded-xl bg-amber-500 px-4 py-2.5 text-xs font-black text-white">Resubmit request</button></form></section>
                @endif

                @if($canDisburse)
                    <section class="dashboard-card border-emerald-200"><h2 class="flex items-center gap-2 font-black text-slate-950"><i data-lucide="badge-dollar-sign" class="size-4 text-emerald-600"></i>Record disbursement</h2><p class="mt-1 text-xs text-slate-500">Only record this after payment has actually been completed.</p><form method="POST" action="{{ route('financial-assistance.disburse', $assistance) }}" class="mt-4 space-y-3">@csrf<label><span class="text-xs font-bold text-slate-700">Payment reference *</span><input name="disbursement_reference" required maxlength="120" class="mt-1 w-full rounded-xl border-slate-200 text-sm"></label><label><span class="text-xs font-bold text-slate-700">Payment date and time *</span><input name="disbursed_at" type="datetime-local" required value="{{ now()->format('Y-m-d\TH:i') }}" class="mt-1 w-full rounded-xl border-slate-200 text-sm"></label><label><span class="text-xs font-bold text-slate-700">Notes</span><textarea name="disbursement_notes" rows="3" maxlength="5000" class="mt-1 w-full rounded-xl border-slate-200 text-sm"></textarea></label><button class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-black text-white">Confirm disbursement</button></form></section>
                @endif

                @if($assistance->status === 'disbursed')
                    <section class="rounded-xl border border-emerald-200 bg-emerald-50 p-4"><div class="flex gap-3"><i data-lucide="badge-check" class="size-5 text-emerald-600"></i><div><div class="text-xs font-black text-emerald-900">Disbursement complete</div><p class="mt-1 text-[10px] leading-4 text-emerald-800">Reference: {{ $assistance->disbursement_reference }}<br>{{ $assistance->disbursed_at?->format('M d, Y g:i A') }}</p></div></div></section>
                @endif

                @if($canCancel)
                    <form method="POST" action="{{ route('financial-assistance.cancel', $assistance) }}" onsubmit="return confirm('Cancel this request? This cannot be undone.')">@csrf @method('DELETE')<button class="w-full rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-xs font-bold text-rose-700">Cancel my request</button></form>
                @endif

                <a href="{{ route('financial-assistance.index') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600"><i data-lucide="arrow-left" class="size-4"></i>Back to all requests</a>
            </aside>
        </div>
    </div>
</x-app-layout>
