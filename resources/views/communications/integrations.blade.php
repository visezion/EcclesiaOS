<x-app-layout title="Communication Integrations" :breadcrumbs="$breadcrumbs">
    @php
        $statCards = [
            ['label' => 'Connected Channels', 'value' => $stats['connected'].' / '.count($channels), 'note' => $stats['connected'] === count($channels) ? '100% connected' : 'configure remaining channels', 'icon' => 'link', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Active Providers', 'value' => $stats['providers'], 'note' => $stats['healthy'].' operational', 'icon' => 'shield-check', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Queue Workers Healthy', 'value' => collect($queueHealth)->where('status', 'Healthy')->count().' / '.collect($queueHealth)->count(), 'note' => 'live queue assignments', 'icon' => 'shield-check', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Templates Synced', 'value' => $stats['templates'], 'note' => 'synced from message templates', 'icon' => 'file-text', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Webhook Endpoints Verified', 'value' => $stats['webhooks'].' / '.count($channels), 'note' => 'signed webhook secrets', 'icon' => 'webhook', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Provider Failures Today', 'value' => $providerFailures['today'], 'note' => $providerFailures['today'] > 0 ? 'requires review' : 'no failures', 'icon' => 'triangle-alert', 'tone' => 'bg-rose-50 text-rose-600 ring-rose-100'],
        ];
        $settingNav = [
            ['label' => 'General', 'note' => 'Global communication defaults', 'icon' => 'settings'],
            ['label' => 'Zender', 'note' => 'WhatsApp and SMS gateway credentials', 'icon' => 'radio-tower'],
            ['label' => 'In-App', 'note' => 'In-app messaging settings', 'icon' => 'message-square'],
            ['label' => 'Email', 'note' => 'Email provider configuration', 'icon' => 'mail'],
            ['label' => 'SMS', 'note' => 'Zender / SMS gateway setup', 'icon' => 'message-square-text'],
            ['label' => 'WhatsApp', 'note' => 'WhatsApp provider configuration', 'icon' => 'messages-square'],
            ['label' => 'Push', 'note' => 'Push notification settings', 'icon' => 'bell'],
            ['label' => 'Templates', 'note' => 'Template management and sync', 'icon' => 'file-text'],
            ['label' => 'Automation', 'note' => 'Rules, triggers and workflows', 'icon' => 'git-branch'],
            ['label' => 'Queue & Retry', 'note' => 'Queues, workers and retry policy', 'icon' => 'sliders-horizontal'],
            ['label' => 'Security', 'note' => 'Secrets, encryption and compliance', 'icon' => 'shield-check'],
            ['label' => 'Audit', 'note' => 'Activity logs and change history', 'icon' => 'history'],
        ];
        $providerFormConfig = [
            'in_app' => [
                'System Channel' => ['fields' => [], 'sender' => 'Channel name'],
            ],
            'email' => [
                'SendGrid' => ['fields' => ['endpoint_url' => ['label' => 'SendGrid API endpoint', 'placeholder' => 'https://api.sendgrid.com/v3/mail/send'], 'api_key' => ['label' => 'SendGrid API key', 'placeholder' => 'Paste SendGrid API key', 'required' => true], 'account_id' => ['label' => 'Sender domain / account ID'], 'device_id' => ['label' => 'Template / app ID'], 'sender_number' => ['label' => 'From email address', 'required' => true], 'webhook_url' => ['label' => 'Event webhook URL']], 'sender' => 'From name'],
                'SMTP / Mailer' => ['fields' => ['endpoint_url' => ['label' => 'SMTP host', 'placeholder' => 'smtp.example.com', 'required' => true], 'api_key' => ['label' => 'SMTP password', 'placeholder' => 'Paste SMTP password', 'required' => true], 'account_id' => ['label' => 'SMTP username', 'required' => true], 'device_id' => ['label' => 'SMTP port', 'placeholder' => '587'], 'sender_number' => ['label' => 'From email address', 'required' => true], 'webhook_url' => ['label' => 'Webhook URL']], 'sender' => 'From name'],
                'Mailgun' => ['fields' => ['endpoint_url' => ['label' => 'Mailgun API URL', 'placeholder' => 'https://api.mailgun.net/v3'], 'api_key' => ['label' => 'Mailgun API key', 'placeholder' => 'Paste Mailgun API key', 'required' => true], 'account_id' => ['label' => 'Mailgun domain', 'placeholder' => 'mg.example.org', 'required' => true], 'device_id' => ['label' => 'Region / app ID'], 'sender_number' => ['label' => 'From email address', 'required' => true], 'webhook_url' => ['label' => 'Webhook URL']], 'sender' => 'From name'],
            ],
            'sms' => [
                'Zender SMS Gateway' => ['fields' => ['endpoint_url' => ['label' => 'Zender site URL', 'placeholder' => 'https://zender.example.com', 'required' => true], 'api_key' => ['label' => 'Zender API key', 'placeholder' => 'Paste Zender API key', 'required' => true], 'account_id' => ['label' => 'Gateway account ID'], 'device_id' => ['label' => 'Device unique ID'], 'sender_number' => ['label' => 'Sender number'], 'webhook_url' => ['label' => 'Delivery webhook URL']], 'sender' => 'Sender name'],
                'Twilio' => ['fields' => ['endpoint_url' => ['label' => 'Twilio API base URL', 'placeholder' => 'https://api.twilio.com'], 'api_key' => ['label' => 'Twilio Auth token', 'placeholder' => 'Paste Twilio Auth token', 'required' => true], 'account_id' => ['label' => 'Twilio Account SID', 'required' => true], 'device_id' => ['label' => 'Messaging Service SID'], 'sender_number' => ['label' => 'From phone number', 'required' => true], 'webhook_url' => ['label' => 'Status callback URL']], 'sender' => 'Sender name'],
                'Custom SMS Gateway' => ['fields' => ['endpoint_url' => ['label' => 'Gateway API URL', 'placeholder' => 'https://sms.example.com', 'required' => true], 'api_key' => ['label' => 'Gateway API key', 'placeholder' => 'Paste gateway API key', 'required' => true], 'account_id' => ['label' => 'Account / gateway ID'], 'device_id' => ['label' => 'Device / app ID'], 'sender_number' => ['label' => 'Sender number', 'required' => true], 'webhook_url' => ['label' => 'Delivery webhook URL']], 'sender' => 'Sender name'],
            ],
            'whatsapp' => [
                'Meta WhatsApp' => ['fields' => ['endpoint_url' => ['label' => 'Graph API URL', 'placeholder' => 'https://graph.facebook.com/v20.0'], 'api_key' => ['label' => 'Access token', 'placeholder' => 'Paste Meta access token', 'required' => true], 'account_id' => ['label' => 'Phone number ID', 'required' => true], 'device_id' => ['label' => 'WhatsApp Business Account ID', 'required' => true], 'sender_number' => ['label' => 'WhatsApp number'], 'webhook_url' => ['label' => 'Webhook callback URL']], 'sender' => 'Business display name'],
                'Zender WhatsApp Gateway' => ['fields' => ['endpoint_url' => ['label' => 'Zender site URL', 'placeholder' => 'https://zender.example.com', 'required' => true], 'api_key' => ['label' => 'Zender API key', 'placeholder' => 'Paste Zender API key', 'required' => true], 'account_id' => ['label' => 'WhatsApp account ID', 'required' => true], 'device_id' => ['label' => 'Device unique ID'], 'sender_number' => ['label' => 'WhatsApp number'], 'webhook_url' => ['label' => 'Delivery webhook URL']], 'sender' => 'Business display name'],
                'Twilio WhatsApp' => ['fields' => ['endpoint_url' => ['label' => 'Twilio API base URL', 'placeholder' => 'https://api.twilio.com'], 'api_key' => ['label' => 'Twilio Auth token', 'placeholder' => 'Paste Twilio Auth token', 'required' => true], 'account_id' => ['label' => 'Twilio Account SID', 'required' => true], 'device_id' => ['label' => 'Messaging Service SID'], 'sender_number' => ['label' => 'WhatsApp sender', 'required' => true], 'webhook_url' => ['label' => 'Status callback URL']], 'sender' => 'Business display name'],
            ],
            'push' => [
                'Firebase Cloud Messaging' => ['fields' => ['endpoint_url' => ['label' => 'FCM endpoint URL', 'placeholder' => 'https://fcm.googleapis.com'], 'api_key' => ['label' => 'Firebase server key / token', 'placeholder' => 'Paste Firebase credential', 'required' => true], 'account_id' => ['label' => 'Firebase project ID', 'required' => true], 'device_id' => ['label' => 'Firebase app ID'], 'sender_number' => ['label' => 'App display name'], 'webhook_url' => ['label' => 'Delivery webhook URL']], 'sender' => 'App name'],
                'Web Push' => ['fields' => ['endpoint_url' => ['label' => 'Push service URL', 'placeholder' => 'https://updates.push.services.mozilla.com'], 'api_key' => ['label' => 'VAPID private key', 'placeholder' => 'Paste VAPID private key', 'required' => true], 'account_id' => ['label' => 'VAPID public key', 'required' => true], 'device_id' => ['label' => 'Subject / app ID'], 'sender_number' => ['label' => 'App display name'], 'webhook_url' => ['label' => 'Delivery webhook URL']], 'sender' => 'App name'],
            ],
        ];
        $dataTables = [
            ['table' => 'notification_templates', 'note' => 'Stores message templates', 'icon' => 'file-text'],
            ['table' => 'notifications', 'note' => 'Master notification records', 'icon' => 'bell'],
            ['table' => 'message_batches', 'note' => 'Batch groups for delivery', 'icon' => 'copy-plus'],
            ['table' => 'notification_recipients', 'note' => 'Recipients and targeting data', 'icon' => 'users'],
            ['table' => 'communication_whatsapp_groups', 'note' => 'Synced Zender WhatsApp groups', 'icon' => 'messages-square'],
            ['table' => 'communication_logs', 'note' => 'Inbound / outbound logs', 'icon' => 'list-checks'],
            ['table' => 'delivery_attempts', 'note' => 'Per-attempt delivery details', 'icon' => 'send'],
            ['table' => 'communication_settings', 'note' => 'Configured channels and providers', 'icon' => 'settings'],
            ['table' => 'scheduled_messages', 'note' => 'Scheduled and recurring messages', 'icon' => 'calendar-clock'],
            ['table' => 'user_notification_preferences', 'note' => 'User channel preferences', 'icon' => 'user-round-cog'],
        ];
        $architectureEvents = ['EventSessionCreated', 'EventSessionUpdated', 'EventSessionCancelled', 'AttendanceSessionOpened', 'AttendanceRecorded', 'VolunteerAssigned', 'RegistrationConfirmed'];
        $architectureListeners = ['SendEventNotification', 'SendAttendanceConfirmation', 'SendVolunteerAssignment', 'SendCancellationNotice'];
    @endphp

    <div class="space-y-4">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-950">Channel Integrations & Communication Settings</h1>
                <p class="text-sm text-slate-500">Configure communication channels, manage providers, queues, templates, and notification architecture to ensure reliable multi-channel delivery.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button form="integration-form" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white shadow-sm hover:bg-violet-700">
                    <i data-lucide="save" class="size-4"></i> Save Changes
                </button>
                <a href="{{ route('communications.integrations') }}" class="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-white px-4 py-2.5 text-sm text-violet-700 shadow-sm hover:bg-violet-50">
                    <i data-lucide="rotate-cw" class="size-4"></i> Reset
                </a>
            </div>
        </div>

        @include('communications.partials.flash')

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
            @foreach($statCards as $card)
                <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center gap-3">
                        <span class="grid size-12 shrink-0 place-items-center rounded-full ring-1 {{ $card['tone'] }}">
                            <i data-lucide="{{ $card['icon'] }}" class="size-5"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="truncate text-xs text-slate-500">{{ $card['label'] }}</div>
                            <div class="mt-1 text-2xl text-slate-950">{{ is_numeric($card['value']) ? number_format($card['value']) : $card['value'] }}</div>
                            <div class="mt-1 truncate text-xs {{ str_contains($card['note'], 'review') ? 'text-rose-600' : 'text-emerald-600' }}">{{ $card['note'] }}</div>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="grid gap-4 xl:grid-cols-[220px_minmax(0,1fr)]">
            <aside class="h-fit rounded-lg border border-slate-200 bg-white shadow-sm xl:sticky xl:top-4">
                <div class="border-b border-slate-100 p-4">
                    <div class="flex items-center gap-2 text-sm font-semibold text-violet-700"><i data-lucide="settings" class="size-4"></i> Settings</div>
                </div>
                <nav class="p-2">
                    @foreach($settingNav as $index => $item)
                        <a href="#{{ Str::slug($item['label']) }}" class="flex gap-3 rounded-lg px-3 py-2.5 text-sm {{ $index === 0 ? 'bg-violet-50 text-violet-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950' }}">
                            <i data-lucide="{{ $item['icon'] }}" class="mt-0.5 size-4 shrink-0"></i>
                            <span><span class="block">{{ $item['label'] }}</span><span class="block text-xs text-slate-400">{{ $item['note'] }}</span></span>
                        </a>
                    @endforeach
                </nav>
            </aside>

            <form id="integration-form" method="POST" action="{{ route('communications.integrations.update') }}" class="space-y-3">
                @csrf
                @method('PUT')

                <article id="zender" class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="grid gap-4 border-b border-slate-100 p-4 xl:grid-cols-[minmax(220px,280px)_1fr_auto] xl:items-start">
                        <div class="flex items-start gap-3">
                            <span class="grid size-14 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100">
                                <i data-lucide="radio-tower" class="size-7"></i>
                            </span>
                            <div>
                                <div class="text-sm font-semibold text-slate-950">Zender Setup</div>
                                <p class="mt-1 text-sm text-slate-500">Connect Zender to send SMS, WhatsApp messages, and WhatsApp group updates.</p>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="text-xs text-slate-500">Default Send Type
                                <select name="zender[service]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                                    <option value="whatsapp" @selected(($zenderSettings['service'] ?? 'whatsapp') === 'whatsapp')>WhatsApp</option>
                                    <option value="sms" @selected(($zenderSettings['service'] ?? 'whatsapp') === 'sms')>SMS</option>
                                </select>
                            </label>
                            <label class="text-xs text-slate-500">Status
                                <span class="mt-1 flex min-h-[42px] items-center gap-3 rounded-lg border border-slate-200 px-3">
                                    <input type="checkbox" name="zender[enabled]" value="1" @checked($zenderSettings['enabled'] ?? false) class="size-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    <span class="text-sm text-slate-700">Enable Zender sending</span>
                                </span>
                            </label>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button form="integration-form" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm text-white shadow-sm hover:bg-emerald-700">
                                <i data-lucide="save" class="size-4"></i> Save Zender
                            </button>
                            <button type="submit" form="sync-zender-groups-form" class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-white px-4 py-2.5 text-sm text-emerald-700 shadow-sm hover:bg-emerald-50">
                                <i data-lucide="refresh-cw" class="size-4"></i> Sync Groups
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-3">
                        <label class="text-xs text-slate-500">Zender URL
                            <input name="zender[site_url]" value="{{ $zenderSettings['site_url'] ?? 'https://zender.vicezion.com' }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="https://zender.vicezion.com">
                        </label>
                        <label class="text-xs text-slate-500">API Key
                            <input name="zender[api_key]" type="password" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="{{ filled($zenderSettings['api_key_last_four'] ?? null) ? 'Saved key ending '.$zenderSettings['api_key_last_four'] : 'Paste API key from Zender' }}">
                        </label>
                        <label class="text-xs text-slate-500">WhatsApp Unique ID
                            <input name="zender[whatsapp_account_id]" value="{{ $zenderSettings['whatsapp_account_id'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Example: wa_01HF8Q9ZENDER">
                        </label>
                    </div>
                    <div class="border-t border-slate-100 p-4">
                        <details class="rounded-lg border border-slate-200 bg-slate-50/70 p-3">
                            <summary class="cursor-pointer text-sm font-medium text-slate-800">SMS settings</summary>
                            <div class="mt-3 grid gap-4 md:grid-cols-3">
                                <label class="text-xs text-slate-500">Android Device ID
                                    <input name="zender[device_unique_id]" value="{{ $zenderSettings['device_unique_id'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm" placeholder="android-main-campus">
                                </label>
                                <label class="text-xs text-slate-500">Gateway ID
                                    <input name="zender[gateway_unique_id]" value="{{ $zenderSettings['gateway_unique_id'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm" placeholder="gateway-partner-01">
                                </label>
                                <label class="text-xs text-slate-500">SIM
                                    <select name="zender[sim_slot]" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm">
                                        <option value="">Select SIM</option>
                                        <option value="1" @selected(($zenderSettings['sim_slot'] ?? '') === '1')>SIM 1</option>
                                        <option value="2" @selected(($zenderSettings['sim_slot'] ?? '') === '2')>SIM 2</option>
                                    </select>
                                </label>
                            </div>
                        </details>
                    </div>
                </article>

                <article id="zender-whatsapp-groups" class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="grid gap-3 border-b border-slate-100 p-4 xl:grid-cols-[1fr_auto] xl:items-center">
                        <div>
                            <h2 class="flex items-center gap-2 text-base font-semibold text-slate-950"><i data-lucide="users-round" class="size-5 text-emerald-600"></i>Zender WhatsApp Groups</h2>
                            <p class="mt-1 text-sm text-slate-500">Use groups for church, campus, or ministry announcements.</p>
                        </div>
                        <button type="submit" form="sync-zender-groups-form" class="inline-flex items-center justify-center gap-2 rounded-lg border border-emerald-200 px-4 py-2.5 text-sm text-emerald-700 hover:bg-emerald-50">
                            <i data-lucide="refresh-cw" class="size-4"></i>
                            Fetch Latest Groups
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[820px] text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Group</th>
                                    <th class="px-4 py-3">Scope</th>
                                    <th class="px-4 py-3">{{ $terminology['campus_singular'] }}</th>
                                    <th class="px-4 py-3">{{ $terminology['ministry_singular'] }}</th>
                                    <th class="px-4 py-3">Synced</th>
                                    <th class="px-4 py-3 text-right">Enabled</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($zenderGroups as $group)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-slate-900">{{ $group->name }}</div>
                                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                                <span>{{ $group->provider_group_id }}</span>
                                                @if($group->participant_count)
                                                    <span class="rounded bg-slate-100 px-2 py-0.5">{{ number_format($group->participant_count) }} members</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <select name="zender_groups[{{ $group->id }}][target_scope]" class="w-full min-w-[130px] rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                                @foreach(['unassigned' => 'Unassigned', 'church' => 'All Church', 'campus' => $terminology['campus_singular'], 'ministry' => $terminology['ministry_singular'], 'ignore' => 'Ignore'] as $value => $label)
                                                    <option value="{{ $value }}" @selected($group->target_scope === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-4 py-3">
                                            <select name="zender_groups[{{ $group->id }}][campus_id]" class="w-full min-w-[150px] rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                                <option value="">No campus</option>
                                                @foreach($campuses as $campus)
                                                    <option value="{{ $campus->id }}" @selected($group->campus_id === $campus->id)>{{ $campus->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-4 py-3">
                                            <select name="zender_groups[{{ $group->id }}][ministry_id]" class="w-full min-w-[170px] rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                                <option value="">No ministry</option>
                                                @foreach($ministryOptions as $ministry)
                                                    <option value="{{ $ministry->id }}" @selected($group->ministry_id === $ministry->id)>{{ $ministry->name }}{{ $ministry->campus?->name ? ' - '.$ministry->campus->name : '' }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">{{ $group->synced_at?->diffForHumans() ?? 'Not synced' }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <label class="inline-flex items-center justify-end gap-2">
                                                <input type="checkbox" name="zender_groups[{{ $group->id }}][enabled]" value="1" @checked($group->enabled) class="size-4 rounded border-slate-300 text-emerald-600">
                                                <span class="text-xs text-slate-500">Use</span>
                                            </label>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-5 py-10 text-center">
                                            <x-empty-state icon="messages-square" title="No WhatsApp groups yet" message="Fetch groups from Zender or add one manually." />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-100 bg-slate-50/60 p-4">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-950"><i data-lucide="plus" class="size-4 text-emerald-600"></i>Add Group Manually</h3>
                        <p class="mt-1 text-xs text-slate-500">Paste the group address from Zender. It usually ends in <span class="font-mono">@g.us</span>.</p>
                        <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-[1fr_1fr_150px_160px_180px_auto]">
                            <label class="text-xs text-slate-500">Group Name
                                <input form="manual-zender-group-form" name="name" required class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Example: Main Church Announcements">
                            </label>
                            <label class="text-xs text-slate-500">Group Address
                                <input form="manual-zender-group-form" name="provider_group_id" required class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="120363000000000000@g.us">
                            </label>
                            <label class="text-xs text-slate-500">Send To
                                <select form="manual-zender-group-form" name="target_scope" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                    <option value="church">All Church</option>
                                    <option value="campus">{{ $terminology['campus_singular'] }}</option>
                                    <option value="ministry">{{ $terminology['ministry_singular'] }}</option>
                                    <option value="unassigned">Unassigned</option>
                                </select>
                            </label>
                            <label class="text-xs text-slate-500">Campus
                                <select form="manual-zender-group-form" name="campus_id" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                    <option value="">No campus</option>
                                    @foreach($campuses as $campus)
                                        <option value="{{ $campus->id }}">{{ $campus->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-xs text-slate-500">{{ $terminology['ministry_singular'] }}
                                <select form="manual-zender-group-form" name="ministry_id" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                    <option value="">No {{ Str::lower($terminology['ministry_singular']) }}</option>
                                    @foreach($ministryOptions as $ministry)
                                        <option value="{{ $ministry->id }}">{{ $ministry->name }}{{ $ministry->campus?->name ? ' - '.$ministry->campus->name : '' }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <button type="submit" form="manual-zender-group-form" class="self-end inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700">
                                <i data-lucide="plus" class="size-4"></i>Add
                            </button>
                        </div>
                    </div>
                </article>

                @foreach($settings as $setting)
                    @php
                        $meta = $channels[$setting->channel];
                        $config = $setting->settings ?? [];
                        $catalog = $providerCatalog[$setting->channel] ?? [];
                        $queue = $config['queue'] ?? $setting->channel.'_queue';
                        $workers = $config['workers'] ?? ($setting->channel === 'in_app' ? 4 : 8);
                        $dailyLimit = $config['daily_limit'] ?? 100000;
                        $providerLink = $config['provider_url'] ?? null;
                    @endphp
                    <article id="{{ Str::slug($meta['label']) }}" data-provider-card data-channel="{{ $setting->channel }}" data-has-api-key="{{ filled($config['api_key_last_four'] ?? null) ? 'true' : 'false' }}" class="rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="grid gap-4 border-b border-slate-100 p-4 lg:grid-cols-[minmax(220px,280px)_1fr_auto] lg:items-start">
                            <div class="flex items-start gap-3">
                                <span class="grid size-14 shrink-0 place-items-center rounded-lg ring-1 {{ $meta['tone'] }}">
                                    <i data-lucide="{{ $meta['icon'] }}" class="size-7"></i>
                                </span>
                                <div>
                                    <div class="text-sm font-semibold text-slate-950">{{ $meta['label'] }} Provider</div>
                                    <div class="text-sm text-slate-700">{{ $setting->provider }}</div>
                                    <span class="mt-2 inline-flex rounded-md px-2 py-1 text-xs {{ $setting->enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $setting->enabled ? 'Connected' : 'Disabled' }}</span>
                                    @if($providerLink)
                                        <a href="{{ $providerLink }}" target="_blank" rel="noreferrer" class="mt-2 flex items-center gap-1 text-xs text-violet-600">Provider page <i data-lucide="external-link" class="size-3"></i></a>
                                    @endif
                                </div>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <label class="text-xs text-slate-500">Provider
                                    <select data-provider-selector name="providers[{{ $setting->channel }}][provider]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                                        @foreach($catalog as $provider)
                                            <option value="{{ $provider['value'] }}" @selected($setting->provider === $provider['value'])>{{ $provider['label'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="text-xs text-slate-500"><span data-provider-sender-label>Sender</span>
                                    <input name="providers[{{ $setting->channel }}][sender_identity]" value="{{ $setting->sender_identity }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                                </label>
                                <label class="text-xs text-slate-500">Webhook Secret
                                    <input name="providers[{{ $setting->channel }}][webhook_secret]" type="password" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="{{ $setting->webhook_secret_hash ? 'Secret saved' : 'Set secret' }}">
                                </label>
                            </div>
                            <div class="flex items-center gap-3">
                                <button type="submit" form="test-{{ $setting->channel }}" class="rounded-lg border border-violet-200 px-4 py-2.5 text-sm text-violet-700 hover:bg-violet-50">Test Connection</button>
                                <label class="relative inline-flex cursor-pointer items-center">
                                    <input type="hidden" name="providers[{{ $setting->channel }}][enabled]" value="0">
                                    <input type="checkbox" name="providers[{{ $setting->channel }}][enabled]" value="1" @checked($setting->enabled) class="peer sr-only">
                                    <span class="h-6 w-11 rounded-full bg-slate-200 transition peer-checked:bg-emerald-500"></span>
                                    <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                                </label>
                            </div>
                        </div>

                        <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                            <label data-provider-field="endpoint_url" class="text-xs text-slate-500"><span data-provider-field-label>API / Base URL</span>
                                <input name="providers[{{ $setting->channel }}][endpoint_url]" value="{{ $config['endpoint_url'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="{{ $setting->channel === 'sms' ? 'https://zender.example.com' : 'https://api.provider.com' }}">
                            </label>
                            <label data-provider-field="api_key" class="text-xs text-slate-500"><span data-provider-field-label>API Token</span>
                                <input name="providers[{{ $setting->channel }}][api_key]" type="password" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="{{ filled($config['api_key_last_four'] ?? null) ? 'Saved token ending '.$config['api_key_last_four'] : 'Paste API token' }}">
                            </label>
                            <label data-provider-field="account_id" class="text-xs text-slate-500"><span data-provider-field-label>Account / Project ID</span>
                                <input name="providers[{{ $setting->channel }}][account_id]" value="{{ $config['account_id'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            </label>
                            <label data-provider-field="device_id" class="text-xs text-slate-500"><span data-provider-field-label>Device ID / App ID</span>
                                <input name="providers[{{ $setting->channel }}][device_id]" value="{{ $config['device_id'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            </label>
                            <label data-provider-field="sender_number" class="text-xs text-slate-500"><span data-provider-field-label>Sender Number</span>
                                <input name="providers[{{ $setting->channel }}][sender_number]" value="{{ $config['sender_number'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            </label>
                            <label data-provider-field="webhook_url" class="text-xs text-slate-500"><span data-provider-field-label>Webhook URL</span>
                                <input name="providers[{{ $setting->channel }}][webhook_url]" value="{{ $config['webhook_url'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="{{ route('communications.delivery-logs') }}">
                            </label>
                            <label class="text-xs text-slate-500">Rate Limit
                                <input name="providers[{{ $setting->channel }}][rate_limit_per_minute]" type="number" min="1" max="100000" value="{{ $setting->rate_limit_per_minute }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            </label>
                            <label class="text-xs text-slate-500">Retry Policy
                                <select name="providers[{{ $setting->channel }}][retry_policy]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                                    @foreach(['exponential','linear','manual'] as $policy)
                                        <option value="{{ $policy }}" @selected($setting->retry_policy === $policy)>{{ Str::headline($policy) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-xs text-slate-500">Queue Assignment
                                <input name="providers[{{ $setting->channel }}][queue]" value="{{ $queue }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            </label>
                            <label class="text-xs text-slate-500">Workers
                                <input name="providers[{{ $setting->channel }}][workers]" type="number" min="1" max="100" value="{{ $workers }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            </label>
                            <label class="text-xs text-slate-500">Daily Limit
                                <input name="providers[{{ $setting->channel }}][daily_limit]" type="number" min="1" value="{{ $dailyLimit }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            </label>
                            <label class="text-xs text-slate-500">Region
                                <input name="providers[{{ $setting->channel }}][region]" value="{{ $config['region'] ?? 'US Central' }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            </label>
                        </div>
                    </article>
                @endforeach
            </form>

            @foreach($settings as $setting)
                <form id="test-{{ $setting->channel }}" method="POST" action="{{ route('communications.integrations.test', $setting->channel) }}" class="hidden">@csrf</form>
            @endforeach
            <form id="sync-zender-groups-form" method="POST" action="{{ route('communications.integrations.zender-groups.sync') }}" class="hidden">@csrf</form>
            <form id="manual-zender-group-form" method="POST" action="{{ route('communications.integrations.zender-groups.store') }}" class="hidden">@csrf</form>

        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-base font-semibold text-slate-950">Notification Flow</h2>
                <div class="mt-4 grid gap-3 text-sm lg:grid-cols-[1fr_auto_1fr_auto_150px] lg:items-start">
                    <div class="rounded-lg border border-violet-200 bg-violet-50 p-3">
                        <div class="mb-2 font-medium text-violet-800">Events</div>
                        @foreach($architectureEvents as $event)
                            <div class="border-t border-violet-100 py-1 text-xs text-violet-700">{{ $event }}</div>
                        @endforeach
                    </div>
                    <i data-lucide="arrow-right" class="hidden size-5 text-slate-400 lg:mt-12 lg:block"></i>
                    <div class="rounded-lg border border-violet-200 bg-violet-50 p-3">
                        <div class="mb-2 font-medium text-violet-800">Listeners</div>
                        @foreach($architectureListeners as $listener)
                            <div class="border-t border-violet-100 py-1 text-xs text-violet-700">{{ $listener }}</div>
                        @endforeach
                    </div>
                    <i data-lucide="arrow-right" class="hidden size-5 text-slate-400 lg:mt-12 lg:block"></i>
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                        <div class="mb-2 font-medium text-emerald-800">Channels</div>
                        @foreach($channels as $channel)
                            <div class="flex items-center gap-2 border-t border-emerald-100 py-1 text-xs text-emerald-700"><i data-lucide="{{ $channel['icon'] }}" class="size-3"></i>{{ $channel['label'] }}</div>
                        @endforeach
                    </div>
                </div>
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-base font-semibold text-slate-950">Communication Tables</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($dataTables as $table)
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="flex items-center gap-2 text-xs font-medium text-violet-700"><i data-lucide="{{ $table['icon'] }}" class="size-4"></i>{{ $table['table'] }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $table['note'] }}</div>
                        </div>
                    @endforeach
                </div>
            </article>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-950">System Health</h2>
                <div class="mt-4 grid gap-2 text-xs sm:grid-cols-2">
                    @foreach(['Email Service', 'SMS Service', 'WhatsApp Service', 'Push Service', 'Database', 'Queue System'] as $service)
                        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"><span class="inline-flex items-center gap-2"><i data-lucide="check-circle-2" class="size-4 text-violet-600"></i>{{ $service }}</span><span class="text-emerald-600">Operational</span></div>
                    @endforeach
                </div>
                <div class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-center text-xs text-emerald-700">Overall Status: All Systems Operational</div>
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-950">Queue Monitoring</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-500"><tr><th class="py-2">Queue</th><th>Workers</th><th>Processed</th><th>Failed</th><th>Latency</th><th>Status</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($queueHealth as $queue)
                                <tr><td class="py-2 text-slate-900">{{ $queue['queue'] }}</td><td>{{ $queue['workers'] }}</td><td>{{ number_format($queue['processed']) }}</td><td>{{ number_format($queue['failed']) }}</td><td>{{ $queue['latency'] }} ms</td><td><span class="rounded-full px-2 py-1 {{ $queue['status'] === 'Healthy' ? 'bg-emerald-50 text-emerald-700' : 'bg-orange-50 text-orange-700' }}">{{ $queue['status'] }}</span></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <a href="{{ route('communications.delivery-logs') }}" class="mt-3 block text-center text-sm text-violet-600">View All Queues -></a>
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-950">Retry Policy Settings</h2>
                <div class="mt-4 space-y-3 text-xs">
                    <div class="flex justify-between"><span class="text-slate-500">Default Max Attempts</span><span class="text-slate-950">3</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Backoff Strategy</span><span class="text-slate-950">Exponential</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Initial Delay</span><span class="text-slate-950">1 second</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Max Delay</span><span class="text-slate-950">300 seconds</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Jitter</span><span class="text-emerald-600">On</span></div>
                </div>
                <button form="integration-form" class="mt-4 w-full rounded-lg border border-violet-200 px-3 py-2 text-sm text-violet-700">Apply to All Channels</button>
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-950">Security & Compliance</h2>
                <div class="mt-4 space-y-3 text-xs">
                    @foreach(['Encryption at Rest' => 'AES-256', 'Encryption in Transit' => 'TLS 1.2+', 'Secrets Management' => 'Encrypted', 'PII Handling' => 'Masked', 'Data Retention' => '365 days', 'Compliance' => 'SOC 2 / GDPR Ready'] as $label => $value)
                        <div class="flex items-center justify-between gap-3"><span class="inline-flex items-center gap-2 text-slate-500"><i data-lucide="shield-check" class="size-4 text-violet-600"></i>{{ $label }}</span><span class="text-right text-slate-900">{{ $value }}</span></div>
                    @endforeach
                </div>
                <a href="{{ route('audit-logs.index') }}" class="mt-4 block text-center text-sm text-violet-600">View Compliance Details -></a>
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-950">Provider Failures <span class="text-slate-500">(Today)</span></h2>
                <div class="mt-5 text-5xl text-slate-950">{{ number_format($providerFailures['today']) }}</div>
                <p class="mt-2 text-sm text-emerald-600">{{ $providerFailures['today'] === 0 ? 'No failures' : 'Needs review' }}</p>
                <dl class="mt-5 space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Last 7 Days</dt><dd>{{ number_format($providerFailures['last_7_days']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Last 30 Days</dt><dd>{{ number_format($providerFailures['last_30_days']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">MTTR</dt><dd>{{ $providerFailures['mttr'] }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Failure Rate</dt><dd>{{ $providerFailures['failure_rate'] }}%</dd></div>
                </dl>
                <a href="{{ route('communications.delivery-logs', ['status' => 'failed']) }}" class="mt-4 block text-center text-sm text-violet-600">View Failure Logs -></a>
            </article>
        </section>
    </div>
</x-app-layout>

<script>
    (() => {
        const providerConfig = @js($providerFormConfig);

        const updateProviderFields = (card) => {
            const channel = card.dataset.channel;
            const selector = card.querySelector('[data-provider-selector]');
            const enabled = card.querySelector('input[type="checkbox"][name$="[enabled]"]')?.checked ?? false;
            const config = providerConfig[channel]?.[selector?.value] ?? {fields: [], sender: 'Sender'};
            const fields = config.fields ?? {};

            card.querySelector('[data-provider-sender-label]').textContent = config.sender ?? 'Sender';
            card.querySelectorAll('[data-provider-field]').forEach((wrapper) => {
                const key = wrapper.dataset.providerField;
                const field = fields[key] ?? null;
                const input = wrapper.querySelector('input');
                const label = wrapper.querySelector('[data-provider-field-label]');
                const visible = field !== null;

                wrapper.hidden = !visible;
                input.disabled = !visible;
                const savedCredential = key === 'api_key' && card.dataset.hasApiKey === 'true';
                input.required = visible && Boolean(field.required) && enabled && !savedCredential;
                if (visible) {
                    label.textContent = field.label ?? key;
                    input.placeholder = field.placeholder ?? '';
                }
            });
        };

        document.querySelectorAll('[data-provider-card]').forEach((card) => {
            card.querySelector('[data-provider-selector]')?.addEventListener('change', () => {
                card.dataset.hasApiKey = 'false';
                updateProviderFields(card);
            });
            card.querySelector('input[type="checkbox"][name$="[enabled]"]')?.addEventListener('change', () => updateProviderFields(card));
            updateProviderFields(card);
        });
    })();
</script>
