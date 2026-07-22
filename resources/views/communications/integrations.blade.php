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
        $dataTables = [
            ['table' => 'notification_templates', 'note' => 'Stores message templates', 'icon' => 'file-text'],
            ['table' => 'notifications', 'note' => 'Master notification records', 'icon' => 'bell'],
            ['table' => 'message_batches', 'note' => 'Batch groups for delivery', 'icon' => 'copy-plus'],
            ['table' => 'notification_recipients', 'note' => 'Recipients and targeting data', 'icon' => 'users'],
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

        <section class="grid gap-4 xl:grid-cols-[230px_1fr_560px]">
            <aside class="rounded-lg border border-slate-200 bg-white shadow-sm">
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
                    <article id="{{ Str::slug($meta['label']) }}" class="rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="grid gap-4 border-b border-slate-100 p-4 xl:grid-cols-[250px_1fr_1fr_1fr_auto] xl:items-start">
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
                            <label class="text-xs text-slate-500">Provider
                                <select name="providers[{{ $setting->channel }}][provider]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                                    @foreach($catalog as $provider)
                                        <option value="{{ $provider['value'] }}" @selected($setting->provider === $provider['value'])>{{ $provider['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-xs text-slate-500">Sender Identity
                                <input name="providers[{{ $setting->channel }}][sender_identity]" value="{{ $setting->sender_identity }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            </label>
                            <label class="text-xs text-slate-500">Webhook Secret
                                <input name="providers[{{ $setting->channel }}][webhook_secret]" type="password" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="{{ $setting->webhook_secret_hash ? 'Verified secret configured' : 'Set webhook secret' }}">
                            </label>
                            <div class="flex items-center gap-3">
                                <button type="submit" form="test-{{ $setting->channel }}" class="rounded-lg border border-violet-200 px-4 py-2.5 text-sm text-violet-700 hover:bg-violet-50">Test Connection</button>
                                <label class="relative inline-flex cursor-pointer items-center">
                                    <input type="checkbox" name="providers[{{ $setting->channel }}][enabled]" value="1" @checked($setting->enabled) class="peer sr-only">
                                    <span class="h-6 w-11 rounded-full bg-slate-200 transition peer-checked:bg-emerald-500"></span>
                                    <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                                </label>
                            </div>
                        </div>

                        <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4">
                            <label class="text-xs text-slate-500">API / Base URL
                                <input name="providers[{{ $setting->channel }}][endpoint_url]" value="{{ $config['endpoint_url'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="{{ $setting->channel === 'sms' ? 'https://zender.example.com' : 'https://api.provider.com' }}">
                            </label>
                            <label class="text-xs text-slate-500">API Token
                                <input name="providers[{{ $setting->channel }}][api_key]" type="password" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="{{ filled($config['api_key_last_four'] ?? null) ? 'Saved token ending '.$config['api_key_last_four'] : 'Paste API token' }}">
                            </label>
                            <label class="text-xs text-slate-500">Account / Project ID
                                <input name="providers[{{ $setting->channel }}][account_id]" value="{{ $config['account_id'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            </label>
                            <label class="text-xs text-slate-500">Device ID / App ID
                                <input name="providers[{{ $setting->channel }}][device_id]" value="{{ $config['device_id'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            </label>
                            <label class="text-xs text-slate-500">Sender Number
                                <input name="providers[{{ $setting->channel }}][sender_number]" value="{{ $config['sender_number'] ?? '' }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                            </label>
                            <label class="text-xs text-slate-500">Webhook URL
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

            <aside class="space-y-4">
                <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 class="text-base font-semibold text-slate-950">Notification Architecture</h2>
                    <div class="mt-4 grid grid-cols-[1fr_auto_1fr_auto_130px] items-center gap-3 text-sm">
                        <div class="rounded-lg border border-violet-200 bg-violet-50 p-3">
                            <div class="mb-2 font-medium text-violet-800">Domain Events</div>
                            @foreach($architectureEvents as $event)
                                <div class="border-t border-violet-100 py-1 text-xs text-violet-700">{{ $event }}</div>
                            @endforeach
                        </div>
                        <i data-lucide="arrow-right" class="size-5 text-slate-400"></i>
                        <div class="rounded-lg border border-violet-200 bg-violet-50 p-3">
                            <div class="mb-2 font-medium text-violet-800">Queued Listeners</div>
                            @foreach($architectureListeners as $listener)
                                <div class="border-t border-violet-100 py-1 text-xs text-violet-700">{{ $listener }}</div>
                            @endforeach
                        </div>
                        <i data-lucide="arrow-right" class="size-5 text-slate-400"></i>
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                            <div class="mb-2 font-medium text-emerald-800">Channels</div>
                            @foreach($channels as $channel)
                                <div class="flex items-center gap-2 border-t border-emerald-100 py-1 text-xs text-emerald-700"><i data-lucide="{{ $channel['icon'] }}" class="size-3"></i>{{ $channel['label'] }}</div>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 class="text-base font-semibold text-slate-950">Communication Data Model <span class="text-slate-500">(Table References)</span></h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        @foreach($dataTables as $table)
                            <div class="rounded-lg border border-slate-200 p-3">
                                <div class="flex items-center gap-2 text-xs font-medium text-violet-700"><i data-lucide="{{ $table['icon'] }}" class="size-4"></i>{{ $table['table'] }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $table['note'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </aside>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.1fr_1.1fr_1fr_1fr_1fr]">
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
