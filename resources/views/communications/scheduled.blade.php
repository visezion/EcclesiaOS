<x-app-layout title="Scheduled Messages" :breadcrumbs="$breadcrumbs">
    @php
        $cards = [
            ['label' => 'Scheduled Today', 'value' => $stats['today'], 'icon' => 'calendar-days', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Upcoming This Week', 'value' => $stats['week'], 'icon' => 'clock', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Automation Rules Active', 'value' => $stats['rules'], 'icon' => 'settings', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Paused Automations', 'value' => $stats['paused'], 'icon' => 'minus', 'tone' => 'bg-rose-50 text-rose-600 ring-rose-100'],
            ['label' => 'Reminders Due Soon', 'value' => $stats['due'], 'icon' => 'bell', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100'],
            ['label' => 'Messages In Queue', 'value' => $stats['queue'], 'icon' => 'list-checks', 'tone' => 'bg-cyan-50 text-cyan-600 ring-cyan-100'],
        ];
    @endphp
    <div class="space-y-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between"><div><h1 class="text-2xl font-semibold text-slate-950">Scheduled Messages & Automation</h1><p class="text-sm text-slate-500">Manage scheduled communications, automations, and delivery queue state.</p></div><a href="{{ route('communications.bulk') }}" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white"><i data-lucide="calendar-plus" class="size-4"></i>Create Schedule</a></div>
        @include('communications.partials.flash')
        @include('communications.partials.subnav')
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">@foreach($cards as $card)<article class="dashboard-card"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-lg ring-1 {{ $card['tone'] }}"><i data-lucide="{{ $card['icon'] }}" class="size-5"></i></span><div><div class="text-xs text-slate-500">{{ $card['label'] }}</div><div class="mt-1 text-2xl text-slate-950">{{ number_format($card['value']) }}</div></div></div></article>@endforeach</section>

        <section class="grid gap-4 xl:grid-cols-[360px_1fr]">
            <aside class="dashboard-card">
                <h2 class="text-base font-semibold text-slate-950">Schedule Calendar</h2>
                <div class="mt-4 grid grid-cols-7 gap-1 text-center text-sm">
                    @foreach(['SU','MO','TU','WE','TH','FR','SA'] as $day)<div class="py-2 text-xs text-slate-500">{{ $day }}</div>@endforeach
                    @foreach(range(1, 35) as $day)
                        @php($date = now()->startOfMonth()->addDays($day - 1))
                        <div class="{{ $date->isToday() ? 'bg-violet-600 text-white' : 'bg-slate-50 text-slate-700' }} rounded-lg px-2 py-3">{{ $date->day }}</div>
                    @endforeach
                </div>
                <a href="{{ route('calendar.index') }}" class="mt-4 inline-flex text-sm text-violet-700">View full calendar</a>
            </aside>
            <main class="space-y-4">
                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 p-4"><h2 class="text-base font-semibold text-slate-950">Automation Rules</h2><a href="{{ route('communications.templates') }}" class="text-sm text-violet-700">View templates</a></div>
                    <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Domain Event</th><th class="px-5 py-3">Listener / Action</th><th class="px-5 py-3">Templates</th><th class="px-5 py-3">Next Run Preview</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($rules as $rule)<tr><td class="px-5 py-4"><span class="inline-flex items-center gap-2"><i data-lucide="calendar-clock" class="size-4 text-violet-600"></i>{{ $rule['event'] }}</span></td><td class="px-5 py-4">{{ $rule['listener'] }}</td><td class="px-5 py-4"><span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs text-emerald-700">{{ $rule['templates'] }} configured</span></td><td class="px-5 py-4">{{ $rule['next_run']->format('M d, h:i A') }}</td></tr>@endforeach</tbody></table></div>
                </section>
                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 p-4"><h2 class="text-base font-semibold text-slate-950">Scheduled Campaigns</h2></div>
                    <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Message Name</th><th class="px-5 py-3">Send Time</th><th class="px-5 py-3">Channels</th><th class="px-5 py-3">Audience</th><th class="px-5 py-3">Queue State</th><th class="px-5 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($campaigns as $campaign)<tr><td class="px-5 py-4"><div class="font-medium text-slate-950">{{ $campaign->name }}</div><div class="text-xs text-slate-500">{{ $campaign->segment_name }}</div></td><td class="px-5 py-4">{{ $campaign->scheduled_at?->format('M d, Y h:i A') ?? 'Not scheduled' }}</td><td class="px-5 py-4">@include('communications.partials.channel-chips', ['selected' => $campaign->channels ?? [], 'channels' => $channels])</td><td class="px-5 py-4">{{ number_format($campaign->recipient_count) }}</td><td class="px-5 py-4"><span class="rounded-full bg-violet-50 px-2.5 py-1 text-xs text-violet-700">{{ Str::headline($campaign->status) }}</span></td><td class="px-5 py-4 text-right"><form method="POST" action="{{ route('communications.campaigns.send', $campaign) }}" class="inline">@csrf<button class="inline-grid size-8 place-items-center rounded-lg hover:bg-violet-50"><i data-lucide="send" class="size-4"></i></button></form></td></tr>@empty<tr><td colspan="6" class="px-5 py-12 text-center"><x-empty-state icon="calendar-clock" title="No scheduled messages" message="Use Bulk Messaging to create a scheduled campaign." /></td></tr>@endforelse</tbody></table></div><div class="border-t border-slate-100 p-4">{{ $campaigns->links() }}</div>
                </section>
            </main>
        </section>
    </div>
</x-app-layout>
