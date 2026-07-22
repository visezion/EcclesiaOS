<x-app-layout title="Notifications Center" :breadcrumbs="$breadcrumbs">
    @php
        $cards = [
            ['label' => 'Unread Notifications', 'value' => $stats['unread'], 'icon' => 'bell', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Action-Required Alerts', 'value' => $stats['action_required'], 'icon' => 'triangle-alert', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100'],
            ['label' => 'Scheduled Reminders Today', 'value' => $stats['scheduled_today'], 'icon' => 'calendar-days', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Sent Today', 'value' => $stats['sent_today'], 'icon' => 'send', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Failed Today', 'value' => $stats['failed_today'], 'icon' => 'triangle-alert', 'tone' => 'bg-rose-50 text-rose-600 ring-rose-100'],
            ['label' => 'Archived Notifications', 'value' => $stats['archived'], 'icon' => 'inbox', 'tone' => 'bg-slate-50 text-slate-600 ring-slate-100'],
        ];
    @endphp

    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-semibold text-slate-950">Notifications Center</h1>
            <p class="text-sm text-slate-500">View, manage, and track in-app, email, SMS, WhatsApp, and push notifications across church operations.</p>
        </div>
        @include('communications.partials.flash')
        @include('communications.partials.subnav')

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            @foreach($cards as $card)
                <article class="dashboard-card">
                    <div class="flex items-center gap-3">
                        <span class="grid size-11 place-items-center rounded-lg ring-1 {{ $card['tone'] }}"><i data-lucide="{{ $card['icon'] }}" class="size-5"></i></span>
                        <div><div class="text-xs text-slate-500">{{ $card['label'] }}</div><div class="mt-1 text-2xl text-slate-950">{{ number_format($card['value']) }}</div></div>
                    </div>
                </article>
            @endforeach
        </section>

        <form method="GET" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid gap-3 lg:grid-cols-[1fr_180px_180px_auto] lg:items-end">
                <label class="text-sm text-slate-600">Search<input name="q" value="{{ request('q') }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Search recipient, subject, or event..."></label>
                <label class="text-sm text-slate-600">Channel<select name="channel" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"><option value="">All Channels</option>@foreach($channels as $key => $channel)<option value="{{ $key }}" @selected(request('channel') === $key)>{{ $channel['label'] }}</option>@endforeach</select></label>
                <label class="text-sm text-slate-600">Status<select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"><option value="">All Statuses</option>@foreach(['queued','delivered','failed'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ Str::headline($status) }}</option>@endforeach</select></label>
                <button class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white"><i data-lucide="sliders-horizontal" class="size-4"></i>Apply Filters</button>
            </div>
        </form>

        <section class="grid gap-4 xl:grid-cols-[1fr_360px]">
            <main class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 p-4">
                    <h2 class="text-base font-semibold text-slate-950">Notification Events</h2>
                    <span class="text-sm text-slate-500">Showing {{ $notifications->firstItem() ?? 0 }} to {{ $notifications->lastItem() ?? 0 }} of {{ number_format($notifications->total()) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3"></th><th class="px-5 py-3">Notification</th><th class="px-5 py-3">Recipients</th><th class="px-5 py-3">Channels</th><th class="px-5 py-3">Created</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Read</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($notifications as $notification)
                                <tr class="hover:bg-slate-50/70">
                                    <td class="px-5 py-4"><input type="checkbox" class="rounded border-slate-300 text-violet-600"></td>
                                    <td class="max-w-md px-5 py-4"><div class="font-medium text-slate-950">{{ $notification->subject ?? Str::headline($notification->event_type ?? 'Notification') }}</div><div class="truncate text-xs text-slate-500">{{ $notification->body_excerpt }}</div></td>
                                    <td class="px-5 py-4">{{ $notification->recipient_name }}</td>
                                    <td class="px-5 py-4">@include('communications.partials.channel-chips', ['selected' => [$notification->channel], 'channels' => $channels])</td>
                                    <td class="px-5 py-4">{{ $notification->created_at?->format('M d, h:i A') }}</td>
                                    <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs {{ $notification->status === 'delivered' ? 'bg-emerald-50 text-emerald-700' : ($notification->status === 'failed' ? 'bg-rose-50 text-rose-700' : 'bg-orange-50 text-orange-700') }}">{{ Str::headline($notification->status) }}</span></td>
                                    <td class="px-5 py-4">{{ $notification->read_at ? 'Read' : 'Unread' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-5 py-12 text-center"><x-empty-state icon="bell" title="No notifications found" message="Delivery events will appear here after a campaign, template test, or provider test runs." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 p-4">{{ $notifications->links() }}</div>
            </main>

            <aside class="space-y-4">
                <section class="dashboard-card">
                    <h2 class="text-base font-semibold text-slate-950">{{ $selected?->subject ?? 'Selected Notification' }}</h2>
                    @if($selected)
                        <p class="mt-2 text-sm text-slate-500">{{ $selected->body_excerpt }}</p>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between"><dt class="text-slate-500">Recipient</dt><dd>{{ $selected->recipient_name }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Channel</dt><dd>{{ $channels[$selected->channel]['label'] ?? Str::headline($selected->channel) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Provider</dt><dd>{{ $selected->provider }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Attempts</dt><dd>{{ $selected->attempt }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Status</dt><dd>{{ Str::headline($selected->status) }}</dd></div>
                        </dl>
                        @if($selected->status === 'failed')
                            <form method="POST" action="{{ route('communications.delivery-logs.retry', $selected) }}" class="mt-4">@csrf<button class="w-full rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white">Retry Delivery</button></form>
                        @endif
                    @else
                        <p class="mt-2 text-sm text-slate-500">Select or create communication activity to view details.</p>
                    @endif
                </section>
                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                    @foreach([['Priority Breakdown', $priorityBreakdown], ['Notification Status', $statusBreakdown]] as [$title, $rows])
                        <article class="dashboard-card">
                            <h2 class="text-base font-semibold text-slate-950">{{ $title }}</h2>
                            <div class="mt-4 grid gap-4 md:grid-cols-[120px_1fr] md:items-center">
                                <div class="relative h-32"><canvas data-chart="doughnut" data-labels='@json(collect($rows)->pluck("label"))' data-values='@json(collect($rows)->pluck("value"))' data-colors='@json(collect($rows)->pluck("color"))'></canvas></div>
                                <div class="space-y-2 text-sm">@foreach($rows as $row)<div class="flex justify-between"><span class="inline-flex items-center gap-2"><span class="size-2 rounded-full" style="background: {{ $row['color'] }}"></span>{{ $row['label'] }}</span><span>{{ number_format($row['value']) }}</span></div>@endforeach</div>
                            </div>
                        </article>
                    @endforeach
                </section>
            </aside>
        </section>
    </div>
</x-app-layout>
