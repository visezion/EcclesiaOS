<x-app-layout title="Bulk Messaging" :breadcrumbs="$breadcrumbs">
    @php
        $canManageCommunicationIntegrations = auth()->user()?->isSuperAdministrator() || auth()->user()?->hasPermission('manage settings');
        $selectedCampaign = $selectedCampaign ?? $campaigns->getCollection()->first();
        $selectedRecipients = (int) ($selectedCampaign?->recipient_count ?? 0);
        $selectedSent = (int) ($selectedCampaign?->sent_count ?? 0);
        $selectedDelivered = (int) ($selectedCampaign?->delivered_count ?? 0);
        $selectedFailed = (int) ($selectedCampaign?->failed_count ?? 0);
        $selectedOpened = (int) ($selectedCampaign?->opened_count ?? 0);
        $selectedClicked = (int) ($selectedCampaign?->clicked_count ?? 0);
        $selectedResponses = $selectedOpened + $selectedClicked;
        $selectedBase = max($selectedRecipients, $selectedSent, 1);
        $actualChannelTotal = (int) collect($channelMix)->sum('value');
        $channelTotal = max($actualChannelTotal, 1);
        $channelIconByLabel = collect($channels)->mapWithKeys(fn (array $meta): array => [$meta['label'] => $meta['icon']])->all();
        $cards = [
            ['label' => 'Active Campaigns', 'value' => $stats['active'], 'hint' => '+ '.number_format(max($stats['active'] - 6, 0)).' vs last 30 days', 'icon' => 'send', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Scheduled Campaigns', 'value' => $stats['scheduled'], 'hint' => '+ '.number_format($stats['scheduled']).' vs last 30 days', 'icon' => 'calendar', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Recipients Reached', 'value' => $stats['recipients'], 'hint' => '+ 18.5% vs last 30 days', 'icon' => 'users-round', 'tone' => 'bg-cyan-50 text-cyan-600 ring-cyan-100'],
            ['label' => 'Delivery Rate', 'value' => $stats['delivery_rate'].'%', 'hint' => '+ 1.32% vs last 30 days', 'icon' => 'shield-check', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Campaign Responses', 'value' => $stats['responses'], 'hint' => '+ 23.7% vs last 30 days', 'icon' => 'message-square-text', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Suppressed Recipients', 'value' => $stats['suppressed'], 'hint' => '- 2.1% vs last 30 days', 'icon' => 'bell-off', 'tone' => 'bg-rose-50 text-rose-600 ring-rose-100'],
        ];
        $funnel = [
            ['label' => 'Recipients', 'value' => $selectedRecipients, 'color' => 'bg-violet-600', 'width' => 100],
            ['label' => 'Sent', 'value' => $selectedSent, 'color' => 'bg-blue-500', 'width' => $selectedSent > 0 ? max(28, round(($selectedSent / $selectedBase) * 100)) : 18],
            ['label' => 'Delivered', 'value' => $selectedDelivered, 'color' => 'bg-emerald-500', 'width' => $selectedDelivered > 0 ? max(24, round(($selectedDelivered / $selectedBase) * 100)) : 16],
            ['label' => 'Opened / Clicked', 'value' => $selectedOpened + $selectedClicked, 'color' => 'bg-teal-500', 'width' => $selectedResponses > 0 ? max(20, round(($selectedResponses / $selectedBase) * 100)) : 14],
            ['label' => 'Responded', 'value' => $selectedResponses, 'color' => 'bg-orange-500', 'width' => $selectedResponses > 0 ? max(16, round(($selectedResponses / $selectedBase) * 100)) : 12],
            ['label' => 'Bounced / Failed', 'value' => $selectedFailed, 'color' => 'bg-rose-500', 'width' => $selectedFailed > 0 ? max(14, round(($selectedFailed / $selectedBase) * 100)) : 10],
        ];
        $reasonMeta = [
            'Provider disabled' => ['icon' => 'plug-zap', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100'],
            'Enable the channel before sending template tests.' => ['icon' => 'power-off', 'tone' => 'bg-rose-50 text-rose-600 ring-rose-100'],
            'Channel is not enabled in communication integrations.' => ['icon' => 'radio-tower', 'tone' => 'bg-amber-50 text-amber-600 ring-amber-100'],
            'Invalid Number' => ['icon' => 'phone-off', 'tone' => 'bg-rose-50 text-rose-600 ring-rose-100'],
            'Email Hard Bounce' => ['icon' => 'mail-x', 'tone' => 'bg-red-50 text-red-600 ring-red-100'],
            'Opted Out' => ['icon' => 'bell-off', 'tone' => 'bg-slate-100 text-slate-600 ring-slate-200'],
        ];
    @endphp

    <div class="space-y-4">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-normal text-slate-950">Bulk Messaging</h1>
                <p class="mt-1 text-sm text-slate-500">Create, target, and monitor mass communications across all enabled channels.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('communications.delivery-logs.export') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm hover:border-violet-200 hover:text-violet-700">
                    <i data-lucide="download" class="size-4"></i>
                    Export Results
                </a>
                @if($canManageCommunicationIntegrations)
                    <a href="{{ route('communications.integrations') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm hover:border-violet-200 hover:text-violet-700">
                        <i data-lucide="badge-check" class="size-4"></i>
                        Test Send
                    </a>
                @endif
                <a href="#campaign-form" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white shadow-sm hover:bg-violet-700">
                    <i data-lucide="plus" class="size-4"></i>
                    Create Campaign
                </a>
            </div>
        </div>

        @include('communications.partials.flash')

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            @foreach($cards as $card)
                <article class="dashboard-card p-4">
                    <div class="flex items-center gap-3">
                        <span class="grid size-12 shrink-0 place-items-center rounded-full ring-1 {{ $card['tone'] }}">
                            <i data-lucide="{{ $card['icon'] }}" class="size-5"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="truncate text-xs text-slate-500">{{ $card['label'] }}</div>
                            <div class="mt-1 text-2xl font-semibold text-slate-950">{{ is_numeric($card['value']) ? number_format($card['value']) : $card['value'] }}</div>
                            <div class="mt-1 text-xs text-emerald-600">{{ $card['hint'] }}</div>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <div class="grid gap-4 xl:grid-cols-[1fr_360px]">
            <div class="space-y-4">
                <form id="campaign-form" method="POST" action="{{ route('communications.campaigns.store') }}" class="grid gap-4 xl:grid-cols-[1.35fr_0.78fr_0.92fr]">
                    @csrf
                    <section class="dashboard-card overflow-hidden">
                        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-3">
                            <i data-lucide="filter" class="size-5 text-violet-600"></i>
                            <h2 class="text-base font-semibold text-slate-950">Build Audience</h2>
                        </div>
                        <div class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-4">
                            <label class="space-y-1 text-xs text-slate-500">
                                <span>Campus</span>
                                <select name="campus_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                                    <option value="">All Campuses</option>
                                    @foreach($campuses as $campus)
                                        <option value="{{ $campus->id }}" @selected(old('campus_id', request('campus_id')) == $campus->id)>{{ $campus->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1 text-xs text-slate-500">
                                <span>Ministry</span>
                                <select name="ministry" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                                    <option value="">All Ministries</option>
                                    <option value="pastoral-care" @selected(request('ministry') === 'pastoral-care')>Pastoral Care</option>
                                    <option value="youth-ministry" @selected(request('ministry') === 'youth-ministry')>Youth Ministry</option>
                                    <option value="worship-ministry" @selected(request('ministry') === 'worship-ministry')>Worship Ministry</option>
                                </select>
                            </label>
                            <label class="space-y-1 text-xs text-slate-500">
                                <span>Role</span>
                                <select name="audience_role" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                                    <option value="">All Roles</option>
                                    <option value="members" @selected(request('audience_role') === 'members')>Members</option>
                                    <option value="volunteers" @selected(request('audience_role') === 'volunteers')>Volunteers</option>
                                    <option value="ministry-leaders" @selected(request('audience_role') === 'ministry-leaders')>Ministry Leaders</option>
                                </select>
                            </label>
                            <label class="space-y-1 text-xs text-slate-500">
                                <span>Attendance Status</span>
                                <select name="member_status" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                                    <option value="">All Statuses</option>
                                    @foreach(['active', 'inactive', 'new_member', 'follow-up'] as $status)
                                        <option value="{{ $status }}" @selected(old('member_status') === $status)>{{ Str::headline($status) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1 text-xs text-slate-500">
                                <span>Volunteers</span>
                                <select name="volunteer_status" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                                    <option value="">All Volunteers</option>
                                    <option value="active" @selected(request('volunteer_status') === 'active')>Active Volunteers</option>
                                    <option value="none" @selected(request('volunteer_status') === 'none')>Not Volunteering</option>
                                </select>
                            </label>
                            <label class="space-y-1 text-xs text-slate-500">
                                <span>Absentees</span>
                                <select name="absentee_window" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                                    <option value="30" @selected(request('absentee_window', '30') === '30')>In the last 30 days</option>
                                    <option value="60" @selected(request('absentee_window') === '60')>In the last 60 days</option>
                                    <option value="never" @selected(request('absentee_window') === 'never')>Never attended</option>
                                </select>
                            </label>
                            <label class="space-y-1 text-xs text-slate-500">
                                <span>Registrants</span>
                                <select name="registration_status" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                                    <option value="">All Registrants</option>
                                    <option value="upcoming" @selected(request('registration_status') === 'upcoming')>Upcoming Events</option>
                                    <option value="pending-payment" @selected(request('registration_status') === 'pending-payment')>Pending Payment</option>
                                </select>
                            </label>
                            <label class="space-y-1 text-xs text-slate-500">
                                <span>Guests</span>
                                <select name="guest_type" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                                    <option value="">All Guests</option>
                                    <option value="first-time" @selected(request('guest_type') === 'first-time')>First-Time Guests</option>
                                    <option value="follow-up" @selected(request('guest_type') === 'follow-up')>Follow-up Needed</option>
                                </select>
                            </label>
                            <label class="space-y-1 text-xs text-slate-500">
                                <span>Follow-up Needs</span>
                                <select name="follow_up_need" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                                    <option value="">All Needs</option>
                                    <option value="pastoral-care" @selected(request('follow_up_need') === 'pastoral-care')>Pastoral Care</option>
                                    <option value="prayer" @selected(request('follow_up_need') === 'prayer')>Prayer</option>
                                    <option value="counseling" @selected(request('follow_up_need') === 'counseling')>Counseling</option>
                                </select>
                            </label>
                            <button type="submit" formmethod="GET" formaction="{{ route('communications.bulk') }}" formnovalidate class="mt-5 inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 hover:border-violet-200 hover:text-violet-700">
                                <i data-lucide="plus" class="size-4"></i>
                                Add Filter
                            </button>
                            <a href="{{ route('communications.bulk') }}" class="mt-5 inline-flex items-center justify-center rounded-lg border border-rose-200 px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50">Clear All</a>
                            <button type="submit" formmethod="GET" formaction="{{ route('communications.bulk') }}" formnovalidate class="mt-5 inline-flex items-center justify-center gap-2 rounded-lg border border-violet-200 px-4 py-2.5 text-sm text-violet-700 hover:bg-violet-50">
                                <i data-lucide="save" class="size-4"></i>
                                Save Segment
                            </button>
                        </div>
                        <div class="grid gap-3 border-t border-slate-100 bg-violet-50/70 px-4 py-3 sm:grid-cols-2">
                            <div>
                                <div class="text-xs text-violet-600">Estimated Audience</div>
                                <div class="text-lg font-semibold text-violet-950">{{ number_format($audienceCount) }} <span class="text-sm font-normal text-violet-600">recipients</span></div>
                            </div>
                            <label class="space-y-1 text-xs text-violet-600">
                                <span>Segment Name</span>
                                <input name="segment_name" value="{{ old('segment_name', 'Mother\'s Day Outreach') }}" class="w-full rounded-lg border border-violet-100 bg-white px-3 py-2 text-sm text-violet-950">
                            </label>
                        </div>
                    </section>

                    <section class="dashboard-card overflow-hidden">
                        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-3">
                            <span class="grid size-6 place-items-center rounded-full bg-violet-600 text-xs text-white">2</span>
                            <h2 class="text-base font-semibold text-slate-950">Select Channels</h2>
                        </div>
                        <div class="divide-y divide-slate-100">
                            @foreach($channels as $key => $channel)
                                <label class="flex items-center justify-between px-4 py-3 text-sm">
                                    <span class="inline-flex items-center gap-3 text-slate-700">
                                        <i data-lucide="{{ $channel['icon'] }}" class="size-5 text-violet-600"></i>
                                        {{ $channel['label'] }} {{ $key === 'in_app' ? 'Message' : '' }}
                                    </span>
                                    <input type="checkbox" name="channels[]" value="{{ $key }}" @checked(in_array($key, old('channels', array_keys($channels)), true)) class="peer sr-only">
                                    <span class="relative h-5 w-9 rounded-full bg-slate-200 transition after:absolute after:left-0.5 after:top-0.5 after:size-4 after:rounded-full after:bg-white after:shadow-sm after:transition peer-checked:bg-violet-600 peer-checked:after:translate-x-4"></span>
                                </label>
                            @endforeach
                        </div>
                        <div class="border-t border-slate-100 p-4">
                            <div class="text-xs font-semibold text-slate-700">Channel Preview</div>
                            <div class="mt-3 flex flex-wrap gap-3">
                                @foreach($channels as $channel)
                                    <span class="grid size-10 place-items-center rounded-full {{ $channel['tone'] }}">
                                        <i data-lucide="{{ $channel['icon'] }}" class="size-5"></i>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </section>

                    <section class="dashboard-card overflow-hidden">
                        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-3">
                            <span class="grid size-6 place-items-center rounded-full bg-violet-600 text-xs text-white">3</span>
                            <h2 class="text-base font-semibold text-slate-950">Send Mode</h2>
                        </div>
                        <div class="space-y-2 p-4 text-sm">
                            <label class="flex items-start gap-3 rounded-lg border border-violet-200 bg-violet-50/40 px-3 py-3">
                                <input type="radio" name="send_mode" value="immediate" checked class="mt-1 text-violet-600">
                                <span><span class="block text-slate-900">Send Immediately</span><span class="text-xs text-slate-500">Send now to all recipients</span></span>
                            </label>
                            <label class="flex items-start gap-3 rounded-lg border border-slate-200 px-3 py-3">
                                <input type="radio" name="send_mode" value="scheduled" class="mt-1 text-violet-600">
                                <span class="w-full">
                                    <span class="block text-slate-900">Schedule</span>
                                    <span class="text-xs text-slate-500">Choose date and time</span>
                                    <input name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at') }}" class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700">
                                </span>
                            </label>
                            <div class="rounded-lg border border-slate-200 px-3 py-3">
                                <div class="flex items-start gap-3">
                                    <i data-lucide="droplets" class="mt-0.5 size-4 text-slate-400"></i>
                                    <div>
                                        <div class="text-slate-900">Drip Campaign</div>
                                        <div class="text-xs text-slate-500">Create multiple schedules from Scheduled Messages after this campaign is saved.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="border-t border-slate-100 p-4">
                            <div class="text-xs font-semibold text-slate-700">Compliance</div>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-lg bg-emerald-50 p-3">
                                    <div class="flex items-center gap-2 text-sm text-emerald-700"><i data-lucide="check-circle-2" class="size-4"></i>Opt-In Compliance</div>
                                    <div class="mt-1 text-xs text-slate-500">All selected members are checked against preferences.</div>
                                </div>
                                <div class="rounded-lg bg-emerald-50 p-3">
                                    <div class="flex items-center gap-2 text-sm text-emerald-700"><i data-lucide="moon" class="size-4"></i>Quiet Hours Protection</div>
                                    <div class="mt-1 text-xs text-slate-500">10:00 PM - 6:00 AM local time.</div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="dashboard-card xl:col-span-3">
                        <div class="grid gap-3 border-b border-slate-100 p-4 xl:grid-cols-[180px_1fr_auto_auto] xl:items-center">
                            <div class="flex items-center gap-2">
                                <span class="grid size-6 place-items-center rounded-full bg-violet-600 text-xs text-white">4</span>
                                <h2 class="text-base font-semibold text-slate-950">Compose Message</h2>
                            </div>
                            <select name="template_id" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                                <option value="">Select Template (Optional)</option>
                                @foreach($templates as $template)
                                    <option value="{{ $template->id }}" @selected(old('template_id') == $template->id)>{{ $template->name }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm text-violet-700 hover:bg-violet-50">
                                <i data-lucide="braces" class="size-4"></i>
                                Insert Merge Field
                            </button>
                            <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm text-violet-700 hover:bg-violet-50">
                                <i data-lucide="paperclip" class="size-4"></i>
                                Attach Media
                            </button>
                        </div>
                        <div class="grid gap-4 p-4 xl:grid-cols-[1fr_1.5fr_auto]">
                            <label class="space-y-1 text-xs text-slate-500">
                                <span>Campaign Name</span>
                                <input name="name" required value="{{ old('name') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700" placeholder="Campaign name">
                            </label>
                            <label class="space-y-1 text-xs text-slate-500">
                                <span>Subject</span>
                                <input name="subject" value="{{ old('subject') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700" placeholder="Message subject">
                            </label>
                            <button class="mt-5 inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-5 py-2.5 text-sm text-white shadow-sm hover:bg-violet-700">
                                <i data-lucide="send" class="size-4"></i>
                                Create Campaign
                            </button>
                            <div class="xl:col-span-3">
                                <div class="mb-2 flex rounded-lg bg-slate-50 p-1 text-sm">
                                    @foreach($channels as $key => $channel)
                                        <button type="button" class="flex-1 rounded-md px-3 py-2 text-slate-600 first:bg-violet-100 first:text-violet-700">{{ $channel['label'] }}</button>
                                    @endforeach
                                </div>
                                <textarea name="body" required rows="5" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700" placeholder="Write the campaign message...">{{ old('body') }}</textarea>
                            </div>
                        </div>
                    </section>
                </form>

                <section class="dashboard-card overflow-hidden">
                    <div class="grid gap-3 border-b border-slate-100 p-4 xl:grid-cols-[1fr_220px_auto_auto] xl:items-center">
                        <div>
                            <h2 class="text-base font-semibold text-slate-950">Campaigns</h2>
                            <p class="text-sm text-slate-500">Showing {{ $campaigns->firstItem() ?? 0 }} to {{ $campaigns->lastItem() ?? 0 }} of {{ number_format($campaigns->total()) }} campaigns</p>
                        </div>
                        <div class="relative">
                            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
                            <input class="w-full rounded-lg border border-slate-200 py-2.5 pl-9 pr-3 text-sm" placeholder="Search campaigns...">
                        </div>
                        <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 hover:border-violet-200 hover:text-violet-700">
                            <i data-lucide="sliders-horizontal" class="size-4"></i>
                            Filters
                        </button>
                        <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 hover:border-violet-200 hover:text-violet-700">
                            <i data-lucide="columns-3" class="size-4"></i>
                            Columns
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1120px] text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Campaign Name</th>
                                    <th class="px-4 py-3">Target Audience</th>
                                    <th class="px-4 py-3">Channel Mix</th>
                                    <th class="px-4 py-3">Scheduled Time</th>
                                    <th class="px-4 py-3 text-right">Recipients</th>
                                    <th class="px-4 py-3 text-right">Sent</th>
                                    <th class="px-4 py-3 text-right">Delivered</th>
                                    <th class="px-4 py-3 text-right">Failed</th>
                                    <th class="px-4 py-3 text-right">Clicked / Opened</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($campaigns as $campaign)
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-3">
                                                <span class="grid size-8 place-items-center rounded-full bg-violet-50 text-violet-600"><i data-lucide="users-round" class="size-4"></i></span>
                                                <div class="min-w-0">
                                                    <div class="truncate font-semibold text-slate-900">{{ $campaign->name }}</div>
                                                    <div class="truncate text-xs text-slate-500">{{ $campaign->template?->name ?? 'Custom message' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">{{ $campaign->segment_name }}</td>
                                        <td class="px-4 py-3">@include('communications.partials.channel-chips', ['selected' => $campaign->channels ?? [], 'channels' => $channels])</td>
                                        <td class="px-4 py-3 text-slate-600">{{ $campaign->scheduled_at?->format('M d, Y h:i A') ?? 'Immediate' }}</td>
                                        <td class="px-4 py-3 text-right text-slate-900">{{ number_format($campaign->recipient_count) }}</td>
                                        <td class="px-4 py-3 text-right text-slate-900">{{ number_format($campaign->sent_count) }}</td>
                                        <td class="px-4 py-3 text-right text-slate-900">{{ number_format($campaign->delivered_count) }}</td>
                                        <td class="px-4 py-3 text-right text-slate-900">{{ number_format($campaign->failed_count) }}</td>
                                        <td class="px-4 py-3 text-right text-slate-900">{{ number_format($campaign->clicked_count + $campaign->opened_count) }}</td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full px-3 py-1 text-xs {{ in_array($campaign->status, ['sent', 'active'], true) ? 'bg-blue-50 text-blue-700' : ($campaign->status === 'scheduled' ? 'bg-violet-50 text-violet-700' : ($campaign->status === 'failed' ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600')) }}">{{ Str::headline($campaign->status) }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <div x-data="{ open: false }" class="relative inline-flex justify-end">
                                                <button type="button" @click="open = ! open" @keydown.escape.window="open = false" class="grid size-8 place-items-center rounded-lg border border-slate-200 text-slate-600 hover:border-violet-200 hover:bg-violet-50 hover:text-violet-700" aria-haspopup="menu" :aria-expanded="open.toString()" title="Campaign actions">
                                                    <i data-lucide="ellipsis" class="size-4"></i>
                                                </button>
                                                <div x-cloak x-show="open" @click.outside="open = false" x-transition.origin.top.right class="absolute right-0 top-10 z-30 w-48 overflow-hidden rounded-lg border border-slate-200 bg-white text-left shadow-xl">
                                                    <a href="{{ route('communications.delivery-logs') }}" class="flex items-center gap-2 px-3 py-2.5 text-sm text-slate-700 hover:bg-slate-50" role="menuitem">
                                                        <i data-lucide="bar-chart-3" class="size-4 text-slate-500"></i>
                                                        View delivery logs
                                                    </a>
                                                    <a href="{{ route('communications.bulk', ['campaign' => $campaign->opaqueId()]) }}" class="flex items-center gap-2 px-3 py-2.5 text-sm text-slate-700 hover:bg-slate-50" role="menuitem">
                                                        <i data-lucide="eye" class="size-4 text-slate-500"></i>
                                                        View campaign
                                                    </a>
                                                    <a href="#campaign-form" class="flex items-center gap-2 px-3 py-2.5 text-sm text-slate-700 hover:bg-slate-50" role="menuitem">
                                                        <i data-lucide="copy-plus" class="size-4 text-slate-500"></i>
                                                        Create similar
                                                    </a>
                                                @if(in_array($campaign->status, ['draft', 'scheduled', 'queued', 'failed'], true))
                                                    <form method="POST" action="{{ route('communications.campaigns.send', $campaign) }}">
                                                        @csrf
                                                        <button class="flex w-full items-center gap-2 px-3 py-2.5 text-sm text-violet-700 hover:bg-violet-50" role="menuitem">
                                                            <i data-lucide="send" class="size-4"></i>
                                                            Send now
                                                        </button>
                                                    </form>
                                                @endif
                                                @if(! in_array($campaign->status, ['sent', 'active'], true))
                                                    <form method="POST" action="{{ route('communications.campaigns.destroy', $campaign) }}" onsubmit="return confirm('Delete this campaign?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="flex w-full items-center gap-2 px-3 py-2.5 text-sm text-rose-600 hover:bg-rose-50" role="menuitem">
                                                            <i data-lucide="trash-2" class="size-4"></i>
                                                            Delete campaign
                                                        </button>
                                                    </form>
                                                @endif
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="px-5 py-12 text-center">
                                            <x-empty-state icon="send" title="No campaigns yet" message="Create a campaign from member records to begin tracked delivery." />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-100 p-4">{{ $campaigns->links() }}</div>
                </section>
            </div>

            <aside class="space-y-4">
                <section class="dashboard-card p-4">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-slate-950">Campaign Performance</h2>
                        <span class="rounded-lg bg-emerald-50 px-3 py-1 text-sm text-emerald-700">{{ Str::headline($selectedCampaign?->status ?? 'Active') }}</span>
                    </div>
                    <select class="mt-4 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                        @foreach($campaigns as $campaign)
                            <option @selected($campaign->is($selectedCampaign))>{{ $campaign->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-3 text-xs text-slate-500">As of {{ optional($selectedCampaign?->updated_at)->format('M d, Y h:i A') ?? now()->format('M d, Y h:i A') }}</p>
                    <div class="mt-4 grid grid-cols-2 gap-2 text-center text-sm">
                        <div class="rounded-lg border border-slate-200 p-3"><div class="text-lg font-semibold text-slate-950">{{ number_format($selectedRecipients) }}</div><div class="text-xs text-slate-500">Recipients</div></div>
                        <div class="rounded-lg border border-slate-200 p-3"><div class="text-lg font-semibold text-slate-950">{{ number_format($selectedDelivered) }}</div><div class="text-xs text-slate-500">Delivered</div></div>
                        <div class="rounded-lg border border-slate-200 p-3"><div class="text-lg font-semibold text-slate-950">{{ number_format($selectedOpened + $selectedClicked) }}</div><div class="text-xs text-slate-500">Opened / Clicked</div></div>
                        <div class="rounded-lg border border-slate-200 p-3"><div class="text-lg font-semibold text-slate-950">{{ number_format($selectedResponses) }}</div><div class="text-xs text-slate-500">Responses</div></div>
                    </div>
                </section>

                <section class="dashboard-card p-4">
                    <h2 class="text-base font-semibold text-slate-950">Delivery Funnel</h2>
                    <div class="mt-4 space-y-2">
                        @foreach($funnel as $row)
                            <div class="grid grid-cols-[1fr_92px] items-center gap-3 text-xs">
                                <div class="h-4 overflow-hidden rounded bg-slate-100">
                                    <div class="h-full {{ $row['color'] }}" style="width: {{ $row['width'] }}%"></div>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-slate-500">{{ $row['label'] }}</span>
                                    <span class="text-slate-900">{{ number_format($row['value']) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="dashboard-card p-4">
                    <div class="flex items-center gap-2">
                        <span class="grid size-8 place-items-center rounded-full bg-violet-50 text-violet-600 ring-1 ring-violet-100">
                            <i data-lucide="pie-chart" class="size-4"></i>
                        </span>
                        <h2 class="text-base font-semibold text-slate-950">Channel Mix</h2>
                    </div>
                    <div class="mt-3 grid items-center gap-4 sm:grid-cols-[140px_1fr] xl:grid-cols-1 2xl:grid-cols-[140px_1fr]">
                        <div class="relative mx-auto h-36 w-36">
                            <canvas data-chart="doughnut" data-labels='@json(collect($channelMix)->pluck("label"))' data-values='@json(collect($channelMix)->pluck("value"))' data-colors='@json(collect($channelMix)->pluck("color"))'></canvas>
                            <div class="pointer-events-none absolute inset-0 grid place-items-center text-center">
                                <div>
                                    <div class="text-xl font-semibold text-slate-950">{{ number_format($actualChannelTotal) }}</div>
                                    <div class="text-xs text-slate-500">Total</div>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            @foreach($channelMix as $row)
                                <div class="grid grid-cols-[1fr_auto] items-center gap-3 text-sm">
                                    <span class="inline-flex items-center gap-2 text-slate-600">
                                        <span class="grid size-7 place-items-center rounded-full" style="background-color: {{ $row['color'] }}18; color: {{ $row['color'] }}">
                                            <i data-lucide="{{ $channelIconByLabel[$row['label']] ?? 'radio' }}" class="size-4"></i>
                                        </span>
                                        {{ $row['label'] }}
                                    </span>
                                    <span class="text-slate-700">{{ round(($row['value'] / $channelTotal) * 100, 1) }}% ({{ number_format($row['value']) }})</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="dashboard-card p-4">
                    <h2 class="text-base font-semibold text-slate-950">Engagement Trend</h2>
                    <div class="mt-3 h-32"><canvas data-chart="multi-line" data-labels='@json($trendSeries["labels"])' data-datasets='@json($trendSeries["datasets"])'></canvas></div>
                </section>

                <section class="dashboard-card p-4">
                    <div class="flex items-center gap-2">
                        <span class="grid size-8 place-items-center rounded-full bg-rose-50 text-rose-600 ring-1 ring-rose-100">
                            <i data-lucide="triangle-alert" class="size-4"></i>
                        </span>
                        <h2 class="text-base font-semibold text-slate-950">Failed Recipient Reasons</h2>
                    </div>
                    <div class="mt-4 space-y-2">
                        @forelse($failedReasons as $reason)
                            @php($reasonStyle = $reasonMeta[$reason->reason] ?? ['icon' => 'circle-alert', 'tone' => 'bg-rose-50 text-rose-600 ring-rose-100'])
                            <div class="grid grid-cols-[auto_1fr_auto] items-center gap-3 rounded-lg border border-slate-100 px-3 py-2 text-sm">
                                <span class="grid size-8 place-items-center rounded-full ring-1 {{ $reasonStyle['tone'] }}">
                                    <i data-lucide="{{ $reasonStyle['icon'] }}" class="size-4"></i>
                                </span>
                                <span class="truncate text-slate-600">{{ $reason->reason }}</span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ number_format($reason->total) }}</span>
                            </div>
                        @empty
                            <div class="rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-3 text-sm text-emerald-700">
                                <span class="inline-flex items-center gap-2"><i data-lucide="shield-check" class="size-4"></i>No failed recipients in the current delivery window.</span>
                            </div>
                        @endforelse
                    </div>
                </section>
            </aside>
        </div>

        <footer class="flex flex-col gap-2 py-2 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between">
            <span>Copyright 2024 Kingdom Life Global Church. All rights reserved.</span>
            <span class="flex items-center gap-8">
                <span>Version 2.4.0</span>
                <a href="#" class="hover:text-violet-600">Privacy Policy</a>
                <a href="#" class="hover:text-violet-600">Terms of Service</a>
            </span>
        </footer>
    </div>
</x-app-layout>
