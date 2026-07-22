<x-app-layout title="Bulk Messaging" :breadcrumbs="$breadcrumbs">
    @php
        $cards = [
            ['label' => 'Active Campaigns', 'value' => $stats['active'], 'icon' => 'send', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Scheduled Campaigns', 'value' => $stats['scheduled'], 'icon' => 'calendar-clock', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Recipients Reached', 'value' => $stats['recipients'], 'icon' => 'users-round', 'tone' => 'bg-cyan-50 text-cyan-600 ring-cyan-100'],
            ['label' => 'Delivery Rate', 'value' => $stats['delivery_rate'].'%', 'icon' => 'shield-check', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Campaign Responses', 'value' => $stats['responses'], 'icon' => 'message-square-text', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Suppressed Recipients', 'value' => $stats['suppressed'], 'icon' => 'bell-off', 'tone' => 'bg-rose-50 text-rose-600 ring-rose-100'],
        ];
    @endphp
    <div class="space-y-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between"><div><h1 class="text-2xl font-semibold text-slate-950">Bulk Messaging</h1><p class="text-sm text-slate-500">Create, target, send, schedule, and monitor mass communications across all channels.</p></div><a href="{{ route('communications.delivery-logs.export') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700"><i data-lucide="download" class="size-4"></i>Export Results</a></div>
        @include('communications.partials.flash')
        @include('communications.partials.subnav')
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">@foreach($cards as $card)<article class="dashboard-card"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-lg ring-1 {{ $card['tone'] }}"><i data-lucide="{{ $card['icon'] }}" class="size-5"></i></span><div><div class="text-xs text-slate-500">{{ $card['label'] }}</div><div class="mt-1 text-2xl text-slate-950">{{ is_numeric($card['value']) ? number_format($card['value']) : $card['value'] }}</div></div></div></article>@endforeach</section>

        <form method="POST" action="{{ route('communications.campaigns.store') }}" class="rounded-lg border border-slate-200 bg-white shadow-sm">
            @csrf
            <div class="grid gap-4 p-4 xl:grid-cols-[1fr_330px_330px]">
                <section>
                    <h2 class="mb-3 flex items-center gap-2 text-base font-semibold text-slate-950"><i data-lucide="users" class="size-4 text-violet-600"></i>Build Audience</h2>
                    <div class="grid gap-3 md:grid-cols-3">
                        <label class="text-sm text-slate-600">Campus<select name="campus_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5"><option value="">All Campuses</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected(request('campus_id') == $campus->id)>{{ $campus->name }}</option>@endforeach</select></label>
                        <label class="text-sm text-slate-600">Member Status<select name="member_status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5"><option value="">All Statuses</option>@foreach(['active','inactive','new_member','follow-up'] as $status)<option value="{{ $status }}">{{ Str::headline($status) }}</option>@endforeach</select></label>
                        <label class="text-sm text-slate-600">Segment Name<input name="segment_name" value="Filtered members" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5"></label>
                    </div>
                    <div class="mt-4 rounded-lg bg-violet-50 p-4 text-sm text-violet-700"><span class="block text-xs text-violet-500">Estimated Audience</span><span class="text-xl text-violet-950">{{ number_format($audienceCount) }}</span> recipients from current member records</div>
                </section>
                <section>
                    <h2 class="mb-3 flex items-center gap-2 text-base font-semibold text-slate-950"><i data-lucide="message-square" class="size-4 text-violet-600"></i>Select Channels</h2>
                    <div class="grid gap-2">@foreach($channels as $key => $channel)<label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2.5 text-sm"><span class="inline-flex items-center gap-2"><i data-lucide="{{ $channel['icon'] }}" class="size-4 text-violet-600"></i>{{ $channel['label'] }}</span><input type="checkbox" name="channels[]" value="{{ $key }}" @checked(in_array($key, ['in_app','email'], true)) class="rounded border-slate-300 text-violet-600"></label>@endforeach</div>
                </section>
                <section>
                    <h2 class="mb-3 flex items-center gap-2 text-base font-semibold text-slate-950"><i data-lucide="clock" class="size-4 text-violet-600"></i>Send Mode</h2>
                    <div class="grid gap-2 text-sm">
                        <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-3"><input type="radio" name="send_mode" value="immediate" checked class="text-violet-600">Send Immediately</label>
                        <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-3"><input type="radio" name="send_mode" value="scheduled" class="text-violet-600">Schedule</label>
                        <input name="scheduled_at" type="datetime-local" class="rounded-lg border border-slate-200 px-3 py-2.5">
                    </div>
                </section>
            </div>
            <div class="border-t border-slate-100 p-4">
                <div class="grid gap-3 xl:grid-cols-[260px_1fr_1fr_auto] xl:items-start">
                    <label class="text-sm text-slate-600">Template<select name="template_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5"><option value="">No template</option>@foreach($templates as $template)<option value="{{ $template->id }}">{{ $template->name }}</option>@endforeach</select></label>
                    <label class="text-sm text-slate-600">Campaign Name<input name="name" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5" placeholder="Campaign name"></label>
                    <label class="text-sm text-slate-600">Subject<input name="subject" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5" placeholder="Subject line"></label>
                    <button class="mt-6 inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white"><i data-lucide="send" class="size-4"></i>Create Campaign</button>
                </div>
                <textarea name="body" required rows="5" class="mt-3 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Write the campaign message..."></textarea>
            </div>
        </form>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 p-4"><h2 class="text-base font-semibold text-slate-950">Campaigns</h2><span class="text-sm text-slate-500">{{ number_format($campaigns->total()) }} campaigns</span></div>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Campaign</th><th class="px-5 py-3">Target Audience</th><th class="px-5 py-3">Channel Mix</th><th class="px-5 py-3">Scheduled</th><th class="px-5 py-3">Recipients</th><th class="px-5 py-3">Delivered</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($campaigns as $campaign)<tr><td class="px-5 py-4"><div class="font-medium text-slate-950">{{ $campaign->name }}</div><div class="text-xs text-slate-500">{{ $campaign->template?->name ?? 'Custom message' }}</div></td><td class="px-5 py-4">{{ $campaign->segment_name }}</td><td class="px-5 py-4">@include('communications.partials.channel-chips', ['selected' => $campaign->channels ?? [], 'channels' => $channels])</td><td class="px-5 py-4">{{ $campaign->scheduled_at?->format('M d, h:i A') ?? 'Immediate' }}</td><td class="px-5 py-4">{{ number_format($campaign->recipient_count) }}</td><td class="px-5 py-4">{{ number_format($campaign->delivered_count) }}</td><td class="px-5 py-4"><span class="rounded-full bg-violet-50 px-2.5 py-1 text-xs text-violet-700">{{ Str::headline($campaign->status) }}</span></td><td class="px-5 py-4 text-right">@if(in_array($campaign->status, ['draft','scheduled','queued','failed'], true))<form method="POST" action="{{ route('communications.campaigns.send', $campaign) }}" class="inline">@csrf<button class="inline-grid size-8 place-items-center rounded-lg hover:bg-violet-50" title="Send now"><i data-lucide="send" class="size-4"></i></button></form>@endif @if(! in_array($campaign->status, ['sent','active'], true))<form method="POST" action="{{ route('communications.campaigns.destroy', $campaign) }}" class="inline" onsubmit="return confirm('Delete this campaign?')">@csrf @method('DELETE')<button class="inline-grid size-8 place-items-center rounded-lg hover:bg-rose-50" title="Delete"><i data-lucide="x" class="size-4"></i></button></form>@endif</td></tr>@empty<tr><td colspan="8" class="px-5 py-12 text-center"><x-empty-state icon="send" title="No campaigns yet" message="Create a campaign from real member records to begin tracked delivery." /></td></tr>@endforelse</tbody></table></div>
            <div class="border-t border-slate-100 p-4">{{ $campaigns->links() }}</div>
        </section>
    </div>
</x-app-layout>
