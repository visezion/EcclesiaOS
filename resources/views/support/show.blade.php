<x-app-layout title="{{ $ticket->reference }}" :breadcrumbs="$breadcrumbs" main-class="px-4 py-5 sm:px-6 lg:px-7">
    @php
        $statusTone = match ($ticket->status) {
            'new' => 'bg-blue-50 text-blue-700 ring-blue-200',
            'triaged' => 'bg-violet-50 text-violet-700 ring-violet-200',
            'in_progress' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'waiting_on_church' => 'bg-orange-50 text-orange-700 ring-orange-200',
            'resolved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            default => 'bg-slate-100 text-slate-600 ring-slate-200',
        };
        $initialAttachments = $ticket->attachments->whereNull('support_ticket_reply_id');
    @endphp

    <div class="space-y-5">
        <x-support-nav />

        @if(session('status'))
            <div class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800"><i data-lucide="circle-check" class="mt-0.5 size-5 shrink-0"></i>{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><div class="font-black">Please correct the following:</div><ul class="mt-2 list-disc pl-5 text-xs">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <header class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-xs font-black tracking-wide text-violet-700">{{ $ticket->reference }}</span>
                        <span class="rounded-full px-2.5 py-1 text-[10px] font-black ring-1 ring-inset {{ $statusTone }}">{{ $statuses[$ticket->status] ?? str($ticket->status)->headline() }}</span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600">{{ $priorities[$ticket->priority] ?? str($ticket->priority)->headline() }} priority</span>
                    </div>
                    <h1 class="mt-3 text-2xl font-black text-slate-950">{{ $ticket->subject }}</h1>
                    <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs text-slate-500">
                        <span class="inline-flex items-center gap-1.5"><i data-lucide="tag" class="size-3.5"></i>{{ $categories[$ticket->category] ?? str($ticket->category)->headline() }}</span>
                        <span class="inline-flex items-center gap-1.5"><i data-lucide="user" class="size-3.5"></i>{{ $ticket->creator?->name ?? 'Former user' }}</span>
                        <span class="inline-flex items-center gap-1.5"><i data-lucide="building-2" class="size-3.5"></i>{{ $ticket->church?->name ?? 'Platform' }}</span>
                        <span class="inline-flex items-center gap-1.5"><i data-lucide="calendar-days" class="size-3.5"></i>Submitted {{ $ticket->created_at->format('M d, Y H:i') }}</span>
                    </div>
                </div>
                <a href="{{ route('support.index') }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 hover:border-violet-200 hover:text-violet-700"><i data-lucide="arrow-left" class="size-4"></i>All tickets</a>
            </div>

            <div class="mt-6 rounded-xl bg-slate-50 p-4">
                <div class="flex items-center justify-between text-xs"><span class="font-bold text-slate-600">Overall progress</span><span class="font-black text-slate-950">{{ $ticket->progress }}%</span></div>
                <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-violet-600 transition-all" style="width: {{ $ticket->progress }}%"></div></div>
                <div class="mt-2 flex flex-wrap justify-between gap-2 text-[10px] text-slate-500"><span>Assigned to: <strong class="text-slate-700">{{ $ticket->assignee?->name ?? 'Support queue' }}</strong></span><span>Last activity {{ ($ticket->last_activity_at ?? $ticket->updated_at)->diffForHumans() }}</span></div>
            </div>
        </header>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <main class="space-y-5">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="message-square-text" class="size-4"></i></span><div><h2 class="font-black text-slate-950">Request details</h2><p class="text-xs text-slate-500">Original ticket description</p></div></div>
                    <div class="mt-5 whitespace-pre-wrap text-sm leading-7 text-slate-700">{{ $ticket->description }}</div>
                    @if($ticket->expected_outcome)
                        <div class="mt-5 rounded-xl border border-violet-100 bg-violet-50/60 p-4"><h3 class="text-xs font-black uppercase tracking-wide text-violet-700">Expected outcome</h3><p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700">{{ $ticket->expected_outcome }}</p></div>
                    @endif
                    @if($ticket->page_url || $ticket->browser)
                        <dl class="mt-5 grid gap-3 border-t border-slate-100 pt-4 text-xs sm:grid-cols-2">
                            @if($ticket->page_url)<div><dt class="font-bold text-slate-500">Affected page</dt><dd class="mt-1 truncate"><a href="{{ $ticket->page_url }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-violet-700">{{ $ticket->page_url }}</a></dd></div>@endif
                            @if($ticket->browser)<div><dt class="font-bold text-slate-500">Browser/device</dt><dd class="mt-1 font-semibold text-slate-700">{{ $ticket->browser }}</dd></div>@endif
                        </dl>
                    @endif
                    @if($initialAttachments->isNotEmpty())
                        <div class="mt-5 border-t border-slate-100 pt-4"><h3 class="text-xs font-black text-slate-600">Attachments</h3><div class="mt-2 flex flex-wrap gap-2">@foreach($initialAttachments as $attachment)<a href="{{ route('support.attachments.download', $attachment) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-700 hover:border-violet-200 hover:text-violet-700"><i data-lucide="paperclip" class="size-3.5"></i>{{ $attachment->original_name }}<span class="font-normal text-slate-400">{{ \Illuminate\Support\Number::fileSize($attachment->size) }}</span></a>@endforeach</div></div>
                    @endif
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 p-5 sm:px-6"><h2 class="font-black text-slate-950">Conversation</h2><p class="mt-1 text-xs text-slate-500">{{ $ticket->replies->where('is_internal', false)->count() }} public replies</p></div>
                    <div class="divide-y divide-slate-100">
                        @forelse($ticket->replies as $reply)
                            <article class="p-5 sm:px-6 {{ $reply->is_internal ? 'bg-amber-50/60' : '' }}">
                                <div class="flex items-start gap-3">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-full {{ $reply->is_internal ? 'bg-amber-100 text-amber-700' : ($reply->user_id === $ticket->created_by ? 'bg-violet-100 text-violet-700' : 'bg-slate-900 text-white') }} text-xs font-black">{{ strtoupper(substr($reply->user?->name ?? 'S', 0, 1)) }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2"><span class="text-sm font-black text-slate-900">{{ $reply->user?->name ?? 'Support' }}</span>@if($reply->is_internal)<span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black text-amber-700">Internal note</span>@elseif($reply->user_id !== $ticket->created_by)<span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">Support team</span>@endif<span class="text-[10px] text-slate-400">{{ $reply->created_at->format('M d, Y H:i') }}</span></div>
                                        <p class="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-700">{{ $reply->body }}</p>
                                        @if($reply->attachments->isNotEmpty())<div class="mt-3 flex flex-wrap gap-2">@foreach($reply->attachments as $attachment)<a href="{{ route('support.attachments.download', $attachment) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-bold text-slate-600"><i data-lucide="paperclip" class="size-3"></i>{{ $attachment->original_name }}</a>@endforeach</div>@endif
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="p-8 text-center text-sm text-slate-500">No replies yet. Updates will appear here.</div>
                        @endforelse
                    </div>

                    @if($ticket->status !== 'closed')
                        <form method="POST" enctype="multipart/form-data" action="{{ route('support.tickets.replies.store', $ticket) }}" class="border-t border-slate-100 bg-slate-50/60 p-5 sm:p-6">
                            @csrf
                            <label class="text-sm font-black text-slate-800">{{ $canManage ? 'Add reply or note' : 'Reply to support' }}<textarea name="body" required rows="5" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm leading-6 outline-none focus:border-violet-400 focus:ring-4 focus:ring-violet-100" placeholder="Write an update or answer...">{{ old('body') }}</textarea></label>
                            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex flex-wrap items-center gap-3">
                                    <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-bold text-slate-600"><i data-lucide="paperclip" class="size-4"></i><span>Attach files</span><input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.txt,.csv,.log" class="sr-only"></label>
                                    @if($canManage)<label class="inline-flex items-center gap-2 text-xs font-bold text-amber-700"><input type="checkbox" name="is_internal" value="1" class="rounded border-amber-300 text-amber-600 focus:ring-amber-500">Internal note</label>@endif
                                </div>
                                <button class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-950 px-5 py-2.5 text-xs font-black text-white"><i data-lucide="send" class="size-3.5"></i>Send reply</button>
                            </div>
                        </form>
                    @else
                        <div class="border-t border-slate-100 bg-slate-50 p-5 text-center text-xs font-bold text-slate-500"><i data-lucide="lock-keyhole" class="mr-1 inline size-3.5"></i>This ticket is closed.</div>
                    @endif
                </section>
            </main>

            <aside class="space-y-5">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between"><div><h2 class="font-black text-slate-950">Delivery tracker</h2><p class="mt-1 text-xs text-slate-500">Completed and pending work</p></div><i data-lucide="route" class="size-5 text-violet-600"></i></div>
                    <ol class="mt-5 space-y-0">
                        @foreach($milestones as $index => $milestone)
                            <li class="relative flex gap-3 pb-5 last:pb-0">
                                @if(! $loop->last)<span class="absolute left-[13px] top-7 h-[calc(100%-20px)] w-px {{ $milestone['complete'] ? 'bg-emerald-300' : 'bg-slate-200' }}"></span>@endif
                                <span class="relative z-10 grid size-7 shrink-0 place-items-center rounded-full {{ $milestone['complete'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400' }}">@if($milestone['complete'])<i data-lucide="check" class="size-3.5"></i>@else<span class="size-2 rounded-full bg-current"></span>@endif</span>
                                <div class="pt-0.5"><h3 class="text-xs font-black {{ $milestone['complete'] ? 'text-slate-900' : 'text-slate-500' }}">{{ $milestone['label'] }}</h3><p class="mt-1 text-[10px] leading-4 text-slate-500">{{ $milestone['description'] }}</p><span class="mt-1 inline-block text-[9px] font-black uppercase tracking-wide {{ $milestone['complete'] ? 'text-emerald-600' : 'text-amber-600' }}">{{ $milestone['complete'] ? 'Completed' : 'Pending' }}</span></div>
                            </li>
                        @endforeach
                    </ol>
                </section>

                @if($canManage)
                    <section class="rounded-2xl border border-violet-200 bg-violet-50/50 p-5">
                        <h2 class="flex items-center gap-2 font-black text-violet-950"><i data-lucide="sliders-horizontal" class="size-4"></i>Update tracking</h2>
                        <form method="POST" action="{{ route('support.tickets.tracking.update', $ticket) }}" class="mt-4 space-y-3">
                            @csrf
                            @method('PATCH')
                            <label class="block text-xs font-bold text-slate-700">Status<select name="status" class="mt-1.5 h-10 w-full rounded-lg border border-violet-200 bg-white px-3 text-xs">@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected($ticket->status === $value)>{{ $label }}</option>@endforeach</select></label>
                            <label class="block text-xs font-bold text-slate-700">Priority<select name="priority" class="mt-1.5 h-10 w-full rounded-lg border border-violet-200 bg-white px-3 text-xs">@foreach($priorities as $value => $label)<option value="{{ $value }}" @selected($ticket->priority === $value)>{{ $label }}</option>@endforeach</select></label>
                            <label class="block text-xs font-bold text-slate-700">Assigned to<select name="assigned_to" class="mt-1.5 h-10 w-full rounded-lg border border-violet-200 bg-white px-3 text-xs"><option value="">Support queue</option>@foreach($supportUsers as $supportUser)<option value="{{ $supportUser->id }}" @selected($ticket->assigned_to === $supportUser->id)>{{ $supportUser->name }}</option>@endforeach</select></label>
                            <label class="block text-xs font-bold text-slate-700">Progress: <span id="support-progress-value">{{ $ticket->progress }}%</span><input type="range" name="progress" value="{{ $ticket->progress }}" min="0" max="100" step="5" oninput="document.getElementById('support-progress-value').textContent=this.value+'%'" class="mt-2 w-full accent-violet-600"></label>
                            <button class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-violet-600 text-xs font-black text-white"><i data-lucide="save" class="size-3.5"></i>Save tracking</button>
                        </form>
                    </section>
                @endif

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="flex items-center gap-2 font-black text-slate-950"><i data-lucide="history" class="size-4 text-slate-500"></i>Activity history</h2>
                    <div class="mt-4 space-y-4">
                        @foreach($ticket->activities->sortByDesc('created_at') as $activity)
                            <div class="flex gap-3"><span class="mt-1.5 size-2 shrink-0 rounded-full bg-violet-500"></span><div><p class="text-[11px] font-semibold leading-4 text-slate-700">{{ $activity->description }}</p><p class="mt-1 text-[9px] text-slate-400">{{ $activity->user?->name ?? 'System' }} · {{ $activity->created_at->format('M d, H:i') }}</p></div></div>
                        @endforeach
                    </div>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
