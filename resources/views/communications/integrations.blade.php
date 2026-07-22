<x-app-layout title="Communication Integrations" :breadcrumbs="$breadcrumbs">
    @php
        $cards = [
            ['label' => 'Connected Channels', 'value' => $stats['connected'].' / '.count($channels), 'icon' => 'link', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Active Providers', 'value' => $stats['providers'], 'icon' => 'shield-check', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Providers Tested', 'value' => $stats['healthy'], 'icon' => 'check-circle-2', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Templates Synced', 'value' => $stats['templates'], 'icon' => 'file-search', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Webhook Secrets', 'value' => $stats['webhooks'], 'icon' => 'webhook', 'tone' => 'bg-teal-50 text-teal-600 ring-teal-100'],
            ['label' => 'Provider Failures Today', 'value' => $stats['failures'], 'icon' => 'triangle-alert', 'tone' => 'bg-rose-50 text-rose-600 ring-rose-100'],
        ];
    @endphp
    <div class="space-y-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between"><div><h1 class="text-2xl font-semibold text-slate-950">Channel Integrations & Communication Settings</h1><p class="text-sm text-slate-500">Configure communication channels, provider queues, retry policy, and webhook security.</p></div><button form="integration-form" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white"><i data-lucide="save" class="size-4"></i>Save Changes</button></div>
        @include('communications.partials.flash')
        @include('communications.partials.subnav')
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">@foreach($cards as $card)<article class="dashboard-card"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-lg ring-1 {{ $card['tone'] }}"><i data-lucide="{{ $card['icon'] }}" class="size-5"></i></span><div><div class="text-xs text-slate-500">{{ $card['label'] }}</div><div class="mt-1 text-2xl text-slate-950">{{ is_numeric($card['value']) ? number_format($card['value']) : $card['value'] }}</div></div></div></article>@endforeach</section>
        <section class="grid gap-4 xl:grid-cols-[1fr_420px]">
            <form id="integration-form" method="POST" action="{{ route('communications.integrations.update') }}" class="space-y-3">@csrf @method('PUT')
                @foreach($settings as $setting)
                    @php($meta = $channels[$setting->channel])
                    <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="grid gap-4 xl:grid-cols-[240px_1fr_1fr_130px_120px] xl:items-center">
                            <div class="flex items-center gap-3"><span class="grid size-12 place-items-center rounded-lg ring-1 {{ $meta['tone'] }}"><i data-lucide="{{ $meta['icon'] }}" class="size-6"></i></span><div><div class="font-semibold text-slate-950">{{ $meta['label'] }} Provider</div><div class="text-xs text-slate-500">{{ $setting->enabled ? 'Connected' : 'Disabled' }}</div></div></div>
                            <label class="text-sm text-slate-600">Provider<input name="providers[{{ $setting->channel }}][provider]" value="{{ $setting->provider }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5"></label>
                            <label class="text-sm text-slate-600">Sender Identity<input name="providers[{{ $setting->channel }}][sender_identity]" value="{{ $setting->sender_identity }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5"></label>
                            <label class="text-sm text-slate-600">Rate / Min<input name="providers[{{ $setting->channel }}][rate_limit_per_minute]" type="number" min="1" value="{{ $setting->rate_limit_per_minute }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5"></label>
                            <label class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2.5 text-sm">Enabled<input type="checkbox" name="providers[{{ $setting->channel }}][enabled]" value="1" @checked($setting->enabled) class="rounded border-slate-300 text-violet-600"></label>
                        </div>
                        <div class="mt-3 grid gap-3 md:grid-cols-[1fr_220px_auto] md:items-end">
                            <label class="text-sm text-slate-600">Webhook Secret<input name="providers[{{ $setting->channel }}][webhook_secret]" type="password" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5" placeholder="{{ $setting->webhook_secret_hash ? 'Secret already configured' : 'Set webhook secret' }}"></label>
                            <label class="text-sm text-slate-600">Retry Policy<select name="providers[{{ $setting->channel }}][retry_policy]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5">@foreach(['exponential','linear','manual'] as $policy)<option value="{{ $policy }}" @selected($setting->retry_policy === $policy)>{{ Str::headline($policy) }}</option>@endforeach</select></label>
                            <button type="submit" form="test-{{ $setting->channel }}" class="rounded-lg border border-violet-200 px-4 py-2.5 text-sm text-violet-700">Test Connection</button>
                        </div>
                    </article>
                @endforeach
            </form>
            @foreach($settings as $setting)
                <form id="test-{{ $setting->channel }}" method="POST" action="{{ route('communications.integrations.test', $setting->channel) }}" class="hidden">@csrf</form>
            @endforeach
            <aside class="space-y-4">
                <section class="dashboard-card"><h2 class="text-base font-semibold text-slate-950">Notification Architecture</h2><div class="mt-4 grid gap-3 text-sm"><div class="rounded-lg border border-violet-100 bg-violet-50 p-3 text-violet-700">Domain Events</div><div class="rounded-lg border border-blue-100 bg-blue-50 p-3 text-blue-700">Queued Listeners</div><div class="rounded-lg border border-emerald-100 bg-emerald-50 p-3 text-emerald-700">Channel Providers</div><div class="rounded-lg border border-slate-100 bg-slate-50 p-3 text-slate-700">Delivery Attempts & Audit Logs</div></div></section>
                <section class="dashboard-card"><h2 class="text-base font-semibold text-slate-950">Communication Data Model</h2><div class="mt-4 grid gap-2 text-sm">@foreach(['communication_templates','communication_campaigns','communication_recipients','communication_deliveries','communication_provider_settings','user_notification_preferences'] as $table)<div class="rounded-lg border border-slate-200 px-3 py-2"><i data-lucide="database" class="mr-2 inline size-4 text-violet-600"></i>{{ $table }}</div>@endforeach</div></section>
                <section class="dashboard-card"><h2 class="text-base font-semibold text-slate-950">Provider Health</h2><div class="mt-4 space-y-2 text-sm">@foreach($providerHealth as $provider)<div class="flex justify-between"><span>{{ $provider['provider'] }}</span><span class="{{ $provider['enabled'] ? 'text-emerald-600' : 'text-rose-600' }}">{{ $provider['enabled'] ? $provider['rate'].'%' : 'Disabled' }}</span></div>@endforeach</div></section>
            </aside>
        </section>
    </div>
</x-app-layout>
