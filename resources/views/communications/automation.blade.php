<x-app-layout title="Notification Automation" :breadcrumbs="$breadcrumbs">
    @php
        $cards = [
            ['label' => 'Active Automations', 'value' => $stats['enabled'], 'note' => 'rules currently listening', 'icon' => 'workflow', 'tone' => 'bg-violet-50 text-violet-600'],
            ['label' => 'Paused Rules', 'value' => $stats['disabled'], 'note' => 'events intentionally muted', 'icon' => 'pause-circle', 'tone' => 'bg-slate-100 text-slate-600'],
            ['label' => 'Healthy Rules', 'value' => $stats['healthy'], 'note' => 'last run succeeded', 'icon' => 'circle-check-big', 'tone' => 'bg-emerald-50 text-emerald-600'],
            ['label' => 'Failed Deliveries', 'value' => $stats['failed_deliveries'], 'note' => 'available for safe retry', 'icon' => 'triangle-alert', 'tone' => 'bg-rose-50 text-rose-600'],
        ];
        $categoryTone = [
            'events' => 'bg-blue-50 text-blue-700',
            'attendance' => 'bg-cyan-50 text-cyan-700',
            'care' => 'bg-pink-50 text-pink-700',
            'volunteers' => 'bg-orange-50 text-orange-700',
            'registration' => 'bg-emerald-50 text-emerald-700',
            'system' => 'bg-violet-50 text-violet-700',
        ];
        $statusTone = [
            'success' => 'bg-emerald-50 text-emerald-700',
            'failed' => 'bg-rose-50 text-rose-700',
            'skipped' => 'bg-slate-100 text-slate-600',
        ];
    @endphp

    <div class="space-y-4">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-violet-600">Communications control center</p>
                <h1 class="mt-1 text-2xl font-semibold text-slate-950">Notification Automation</h1>
                <p class="mt-1 max-w-3xl text-sm text-slate-500">Choose which operational events notify people, who receives them, and which approved channels and templates are used.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('communications.delivery-logs') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm hover:bg-slate-50">
                    <i data-lucide="clipboard-list" class="size-4"></i> Delivery logs
                </a>
                <form method="POST" action="{{ route('communications.automation.retry-failed') }}">
                    @csrf
                    <button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white shadow-sm hover:bg-violet-700">
                        <i data-lucide="refresh-cw" class="size-4"></i> Retry failed
                    </button>
                </form>
            </div>
        </div>

        @include('communications.partials.flash')
        @include('communications.partials.subnav')

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($cards as $card)
                <article class="dashboard-card flex items-center gap-3 p-4">
                    <span class="grid size-11 shrink-0 place-items-center rounded-full {{ $card['tone'] }}"><i data-lucide="{{ $card['icon'] }}" class="size-5"></i></span>
                    <div class="min-w-0">
                        <p class="truncate text-xs text-slate-500">{{ $card['label'] }}</p>
                        <p class="text-2xl font-semibold text-slate-950">{{ number_format($card['value']) }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $card['note'] }}</p>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="dashboard-card overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-semibold text-slate-950">Event rules</h2>
                    <p class="text-sm text-slate-500">Changes apply to new events. Existing queued notifications keep their recorded settings.</p>
                </div>
                <div class="inline-flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                    <span class="size-2 rounded-full bg-emerald-500"></span>
                    Scheduler checks every minute
                </div>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach($rules as $rule)
                    <details class="group" @if($errors->any() && old('event_type') === $rule->event_type) open @endif>
                        <summary class="flex cursor-pointer list-none flex-col gap-3 px-5 py-4 hover:bg-slate-50/70 lg:flex-row lg:items-center">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full {{ $categoryTone[$rule->category] ?? 'bg-slate-100 text-slate-600' }}">
                                <i data-lucide="{{ $rule->event_type === 'EventReminderDue' ? 'alarm-clock' : 'zap' }}" class="size-4"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-slate-950">{{ $rule->name }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-[11px] {{ $rule->enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $rule->enabled ? 'Active' : 'Paused' }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-[11px] {{ $categoryTone[$rule->category] ?? 'bg-slate-100 text-slate-600' }}">{{ Str::headline($rule->category) }}</span>
                                </span>
                                <span class="mt-1 block truncate text-xs text-slate-500">{{ $rule->event_type }} · {{ Str::headline($rule->audience) }} · {{ collect($rule->channels)->map(fn ($channel) => Str::headline($channel))->join(', ') }}</span>
                            </span>
                            <span class="grid grid-cols-2 gap-4 text-xs lg:min-w-72">
                                <span><span class="block text-slate-400">Last run</span><span class="mt-1 block text-slate-700">{{ $rule->last_run_at?->diffForHumans() ?? 'Not run yet' }}</span></span>
                                <span><span class="block text-slate-400">Result</span><span class="mt-1 inline-flex rounded-full px-2 py-0.5 {{ $statusTone[$rule->last_status] ?? 'bg-slate-100 text-slate-600' }}">{{ Str::headline($rule->last_status ?? 'Ready') }}</span></span>
                            </span>
                            <i data-lucide="chevron-down" class="size-4 text-slate-400 transition group-open:rotate-180"></i>
                        </summary>

                        <div class="border-t border-slate-100 bg-slate-50/60 px-5 py-5">
                            <form method="POST" action="{{ route('communications.automation.update', $rule) }}" class="space-y-4">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="event_type" value="{{ $rule->event_type }}">
                                <div class="grid gap-4 lg:grid-cols-4">
                                    <label class="text-xs text-slate-500 lg:col-span-2">Rule name
                                        <input name="name" value="{{ $rule->name }}" required class="mt-1 w-full rounded-lg border-slate-200 bg-white text-sm">
                                    </label>
                                    <label class="text-xs text-slate-500">Audience
                                        <select name="audience" class="mt-1 w-full rounded-lg border-slate-200 bg-white text-sm">
                                            @foreach(['event_recipients' => 'Event recipients', 'all_users' => 'All system users', 'all_members' => 'All active members', 'administrators' => 'Communication administrators'] as $value => $label)
                                                <option value="{{ $value }}" @selected($rule->audience === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="text-xs text-slate-500">Approved template
                                        <select name="communication_template_id" class="mt-1 w-full rounded-lg border-slate-200 bg-white text-sm">
                                            <option value="">Use event message</option>
                                            @foreach($templates as $template)
                                                <option value="{{ $template->id }}" @selected($rule->communication_template_id === $template->id)>{{ $template->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>

                                <input type="hidden" name="category" value="{{ $rule->category }}">
                                <div class="grid gap-4 lg:grid-cols-[1fr_auto]">
                                    <fieldset>
                                        <legend class="text-xs text-slate-500">Delivery channels</legend>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach($channels as $channel)
                                                <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:border-violet-300">
                                                    <input type="checkbox" name="channels[]" value="{{ $channel }}" class="rounded border-slate-300 text-violet-600" @checked(in_array($channel, $rule->channels ?? [], true))>
                                                    {{ Str::headline($channel) }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                    @if($rule->event_type === 'EventReminderDue')
                                        <label class="text-xs text-slate-500">Send before event
                                            <select name="reminder_minutes" class="mt-1 block rounded-lg border-slate-200 bg-white text-sm">
                                                @foreach([30 => '30 minutes', 60 => '1 hour', 180 => '3 hours', 1440 => '1 day', 2880 => '2 days', 10080 => '1 week'] as $minutes => $label)
                                                    <option value="{{ $minutes }}" @selected($rule->reminder_minutes === $minutes)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                    @endif
                                </div>

                                <div class="flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex flex-wrap gap-4">
                                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                            <input type="checkbox" name="enabled" value="1" class="rounded border-slate-300 text-violet-600" @checked($rule->enabled)> Enable this automation
                                        </label>
                                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                            <input type="checkbox" name="critical" value="1" class="rounded border-slate-300 text-violet-600" @checked($rule->critical)> Critical alert
                                        </label>
                                    </div>
                                    <div class="flex gap-2">
                                        <button form="test-rule-{{ $rule->id }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"><i data-lucide="send" class="size-4"></i> Send test</button>
                                        <button class="inline-flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2 text-sm text-white hover:bg-slate-800"><i data-lucide="save" class="size-4"></i> Save rule</button>
                                    </div>
                                </div>
                            </form>
                            <form id="test-rule-{{ $rule->id }}" method="POST" action="{{ route('communications.automation.test', $rule) }}" class="hidden">@csrf</form>
                            @if($rule->last_error)
                                <p class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700"><strong>Last error:</strong> {{ $rule->last_error }}</p>
                            @endif
                        </div>
                    </details>
                @endforeach
            </div>
        </section>
    </div>
</x-app-layout>
