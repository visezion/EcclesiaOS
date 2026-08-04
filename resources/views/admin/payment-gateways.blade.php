<x-app-layout title="Payment Gateways" :breadcrumbs="$breadcrumbs">
    @php
        $providerMeta = [
            'stripe' => [
                'name' => 'Stripe',
                'region' => 'Global',
                'currency' => strtoupper($church->currency),
                'copy' => 'Cards and Stripe-hosted checkout',
                'color' => '#635bff',
                'logo' => 'images/payment-providers/stripe.svg',
                'icon' => 'credit-card',
                'keys_url' => 'https://dashboard.stripe.com/test/apikeys',
                'keys_label' => 'Open Stripe test keys',
            ],
            'paystack' => [
                'name' => 'Paystack',
                'region' => 'Nigeria',
                'currency' => 'NGN',
                'copy' => 'Cards, bank transfer, USSD and local methods',
                'color' => '#08a88a',
                'logo' => 'images/payment-providers/paystack.svg',
                'icon' => 'landmark',
                'keys_url' => 'https://dashboard.paystack.com/#/settings/developer',
                'keys_label' => 'Open Paystack developer settings',
            ],
            'paypal' => [
                'name' => 'PayPal',
                'region' => 'United States',
                'currency' => 'USD',
                'copy' => 'PayPal wallet and eligible cards',
                'color' => '#0070ba',
                'logo' => 'images/payment-providers/paypal.svg',
                'icon' => 'wallet-cards',
                'keys_url' => 'https://developer.paypal.com/dashboard/applications/sandbox',
                'keys_label' => 'Open PayPal sandbox apps',
            ],
        ];
        $configuredCount = collect($gateways)->filter(fn (array $gateway): bool => $gateway['enabled'] && filled($gateway['secret_key'] ?: $gateway['client_secret']))->count();
        $enabledCount = collect($gateways)->where('enabled', true)->count();
        $testedCount = collect($gateways)->where('last_test_status', 'success')->count();
        $activeProvider = old('provider_key', 'stripe');
    @endphp

    <div class="space-y-5" x-data="{ activeProvider: @js($activeProvider) }">
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="grid gap-4 p-5 xl:grid-cols-[minmax(0,1fr)_minmax(380px,520px)] xl:items-start">
                <div class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex min-w-0 gap-4">
                            <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-600 ring-1 ring-violet-100">
                                <i data-lucide="credit-card" class="size-6"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-violet-600">Giving & Finance</p>
                                <h1 class="mt-1 text-2xl font-semibold text-slate-950">Payment Gateways</h1>
                                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Configure the secure payment providers available to donors giving online to {{ $church->name }}.</p>
                            </div>
                        </div>

                        <span class="inline-flex w-fit shrink-0 items-center gap-2 rounded-lg {{ $configuredCount > 0 ? 'bg-emerald-50 text-emerald-700 ring-emerald-100' : 'bg-amber-50 text-amber-700 ring-amber-100' }} px-3 py-2 text-xs font-semibold ring-1">
                            <i data-lucide="{{ $configuredCount > 0 ? 'badge-check' : 'triangle-alert' }}" class="size-4"></i>
                            {{ $configuredCount > 0 ? $configuredCount.' ready for giving' : 'Setup required' }}
                        </span>
                    </div>

                    <div class="mt-5 flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs leading-5 text-slate-500">Only enabled and fully configured gateways appear on the public giving page.</p>
                        <a href="{{ route('giving.create') }}" target="_blank" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            <i data-lucide="external-link" class="size-4"></i>
                            View Giving Page
                        </a>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3 xl:grid-cols-1 2xl:grid-cols-3">
                    @foreach([
                        ['label' => 'Configured', 'value' => $configuredCount, 'hint' => 'ready to receive', 'icon' => 'badge-check', 'class' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
                        ['label' => 'Enabled', 'value' => $enabledCount, 'hint' => 'offered to donors', 'icon' => 'toggle-right', 'class' => 'bg-violet-50 text-violet-600 ring-violet-100'],
                        ['label' => 'Connections', 'value' => $testedCount, 'hint' => 'successfully tested', 'icon' => 'plug-zap', 'class' => 'bg-blue-50 text-blue-600 ring-blue-100'],
                    ] as $metric)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <span class="grid size-9 place-items-center rounded-lg ring-1 {{ $metric['class'] }}"><i data-lucide="{{ $metric['icon'] }}" class="size-4"></i></span>
                                <span class="text-xl font-semibold text-slate-950">{{ $metric['value'] }}</span>
                            </div>
                            <p class="mt-3 text-sm font-semibold text-slate-900">{{ $metric['label'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $metric['hint'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        @if(session('status'))
            <div class="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">
                <i data-lucide="check-circle-2" class="mt-0.5 size-4 shrink-0"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif
        @if($errors->any())
            <div class="flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-700">
                <i data-lucide="circle-alert" class="mt-0.5 size-4 shrink-0"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
            <section class="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 pt-4 sm:px-5">
                    <div class="flex gap-1 overflow-x-auto">
                        @foreach($gateways as $provider => $gateway)
                            @php($configured = $gateway['enabled'] && filled($gateway['secret_key'] ?: $gateway['client_secret']))
                            <button type="button" @click="activeProvider = '{{ $provider }}'" class="relative flex min-w-max items-center gap-2 rounded-t-lg px-4 py-3 text-sm font-semibold transition" :class="activeProvider === '{{ $provider }}' ? 'bg-violet-50 text-violet-700' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'">
                                <img src="{{ asset($providerMeta[$provider]['logo']) }}" alt="" class="size-4 object-contain">
                                {{ $providerMeta[$provider]['name'] }}
                                <span class="size-1.5 rounded-full {{ $configured ? 'bg-emerald-500' : 'bg-slate-300' }}" title="{{ $configured ? 'Configured' : 'Not configured' }}"></span>
                                <span x-show="activeProvider === '{{ $provider }}'" class="absolute inset-x-0 bottom-0 h-0.5 bg-violet-600"></span>
                            </button>
                        @endforeach
                    </div>
                </div>

                @foreach($gateways as $provider => $gateway)
                    @php($provider = (string) $provider)
                    @php($meta = $providerMeta[$provider])
                    @php($configured = $gateway['enabled'] && filled($gateway['secret_key'] ?: $gateway['client_secret']))
                    <form x-cloak x-show="activeProvider === '{{ $provider }}'" method="POST" action="{{ route('payment-gateways.update-provider', $provider) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="provider_key" value="{{ $provider }}">
                        <input type="hidden" name="currency" value="{{ $meta['currency'] }}">

                        <div class="space-y-6 p-5">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="grid size-11 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white shadow-sm">
                                        <img src="{{ asset($meta['logo']) }}" alt="{{ $meta['name'] }} logo" class="size-6 object-contain">
                                    </span>
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h2 class="text-lg font-semibold text-slate-950">{{ $meta['name'] }}</h2>
                                            <span class="rounded-md {{ $configured ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }} px-2 py-1 text-[11px] font-semibold">{{ $configured ? 'Configured' : 'Not configured' }}</span>
                                        </div>
                                        <p class="mt-1 text-sm text-slate-500">{{ $meta['region'] }} · {{ $meta['currency'] }} · {{ $meta['copy'] }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 text-xs text-slate-500">
                                    <i data-lucide="shield-check" class="size-4 text-emerald-600"></i>
                                    Encrypted credentials
                                </div>
                            </div>

                            <div class="grid gap-4 lg:grid-cols-2">
                                <div class="rounded-xl border border-slate-200 p-4">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <h3 class="text-sm font-semibold text-slate-900">Gateway status</h3>
                                            <p class="mt-1 text-xs leading-5 text-slate-500">Allow donors to select {{ $meta['name'] }} during checkout.</p>
                                        </div>
                                        <label class="relative inline-flex cursor-pointer items-center">
                                            <input type="hidden" name="enabled" value="0">
                                            <input name="enabled" type="checkbox" value="1" @checked(old('provider_key') === $provider ? old('enabled') : $gateway['enabled']) class="peer sr-only">
                                            <span class="h-6 w-11 rounded-full bg-slate-200 transition peer-checked:bg-violet-600 after:absolute after:left-1 after:top-1 after:size-4 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-5"></span>
                                        </label>
                                    </div>
                                </div>

                                <fieldset class="rounded-xl border border-slate-200 p-4" x-data="{ mode: @js(old('provider_key') === $provider ? old('mode', $gateway['mode']) : $gateway['mode']) }">
                                    <legend class="px-1 text-sm font-semibold text-slate-900">Operating mode</legend>
                                    <div class="grid grid-cols-2 gap-2">
                                        @foreach(['test' => 'Test / Sandbox', 'live' => 'Live'] as $value => $label)
                                            <label class="cursor-pointer rounded-lg border px-3 py-2.5 text-center text-xs font-semibold transition" :class="mode === '{{ $value }}' ? 'border-violet-300 bg-violet-50 text-violet-700' : 'border-slate-200 text-slate-600'">
                                                <input name="mode" type="radio" value="{{ $value }}" x-model="mode" class="sr-only">
                                                {{ $label }}
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            </div>

                            <div>
                                <div class="mb-3">
                                    <h3 class="text-sm font-semibold text-slate-900">API credentials</h3>
                                    <p class="mt-1 text-xs text-slate-500">Leave a saved credential blank to keep its current value.</p>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    @if($provider !== 'paypal')
                                        <label class="space-y-1.5 text-sm">
                                            <span class="font-semibold text-slate-700">Publishable key</span>
                                            <input name="publishable_key" type="password" autocomplete="off" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:ring-violet-400" placeholder="{{ $gateway['publishable_last_four'] ? 'Saved key ending '.$gateway['publishable_last_four'].' — leave blank to keep' : 'pk_test_...' }}">
                                        </label>
                                        <label class="space-y-1.5 text-sm">
                                            <span class="font-semibold text-slate-700">Secret key</span>
                                            <input name="secret_key" type="password" autocomplete="new-password" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:ring-violet-400" placeholder="{{ $gateway['secret_last_four'] ? 'Encrypted key ending '.$gateway['secret_last_four'].' — leave blank to keep' : 'sk_test_...' }}">
                                        </label>
                                    @else
                                        <label class="space-y-1.5 text-sm">
                                            <span class="font-semibold text-slate-700">Client ID</span>
                                            <input name="client_id" type="password" autocomplete="off" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:ring-violet-400" placeholder="{{ $gateway['client_id_last_four'] ? 'Saved ID ending '.$gateway['client_id_last_four'].' — leave blank to keep' : 'PayPal client ID' }}">
                                        </label>
                                        <label class="space-y-1.5 text-sm">
                                            <span class="font-semibold text-slate-700">Client secret</span>
                                            <input name="client_secret" type="password" autocomplete="new-password" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:ring-violet-400" placeholder="{{ $gateway['client_secret_last_four'] ? 'Encrypted secret ending '.$gateway['client_secret_last_four'].' — leave blank to keep' : 'PayPal client secret' }}">
                                        </label>
                                        <label class="space-y-1.5 text-sm sm:col-span-2">
                                            <span class="font-semibold text-slate-700">Webhook ID</span>
                                            <input name="webhook_id" autocomplete="off" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:ring-violet-400" placeholder="{{ $gateway['webhook_id_last_four'] ? 'Saved ID ending '.$gateway['webhook_id_last_four'].' — leave blank to keep' : 'PayPal webhook ID' }}">
                                        </label>
                                    @endif
                                    @if($provider === 'stripe')
                                        <label class="space-y-1.5 text-sm sm:col-span-2">
                                            <span class="font-semibold text-slate-700">Webhook signing secret</span>
                                            <input name="webhook_secret" type="password" autocomplete="new-password" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:ring-violet-400" placeholder="{{ $gateway['webhook_last_four'] ? 'Encrypted secret ending '.$gateway['webhook_last_four'].' — leave blank to keep' : 'whsec_...' }}">
                                        </label>
                                    @endif
                                </div>
                            </div>

                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                                    <i data-lucide="webhook" class="size-4 text-violet-600"></i>
                                    Webhook endpoint
                                </div>
                                <div class="mt-3 flex min-w-0 flex-col gap-2 sm:flex-row">
                                    <input value="{{ $webhookUrls[$provider] }}" readonly class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 font-mono text-xs text-slate-700">
                                    <button type="button" data-copy-value="{{ $webhookUrls[$provider] }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" onclick="navigator.clipboard?.writeText(this.dataset.copyValue)">
                                        <i data-lucide="copy" class="size-3.5"></i>
                                        Copy
                                    </button>
                                </div>
                                <p class="mt-2 text-xs leading-5 text-slate-500">
                                    @if($provider === 'stripe') Subscribe to checkout session completed and asynchronous payment events.
                                    @elseif($provider === 'paystack') Add this endpoint in Paystack Webhooks. Every successful charge is verified before it enters Finance.
                                    @else Subscribe to PAYMENT.CAPTURE.COMPLETED and save the matching Webhook ID above.
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <span class="text-xs text-slate-500">Changes affect new checkout sessions only.</span>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <button form="test-{{ $provider }}" type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                    <i data-lucide="plug-zap" class="size-4"></i>
                                    Test Connection
                                </button>
                                <button class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                                    <i data-lucide="save" class="size-4"></i>
                                    Save {{ $meta['name'] }}
                                </button>
                            </div>
                        </div>
                    </form>
                    <form id="test-{{ $provider }}" method="POST" action="{{ route('payment-gateways.test-provider', $provider) }}" class="hidden">@csrf</form>
                @endforeach
            </section>

            <aside class="space-y-5">
                @foreach($gateways as $provider => $gateway)
                    @php($meta = $providerMeta[$provider])
                    <div x-cloak x-show="activeProvider === '{{ $provider }}'" class="space-y-5">
                        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Connection health</p>
                                    <h2 class="mt-1 font-semibold text-slate-950">{{ $meta['name'] }}</h2>
                                </div>
                                <span class="grid size-10 place-items-center rounded-lg {{ $gateway['last_test_status'] === 'success' ? 'bg-emerald-50 text-emerald-600' : ($gateway['last_test_status'] === 'failed' ? 'bg-rose-50 text-rose-600' : 'bg-slate-100 text-slate-500') }}">
                                    <i data-lucide="{{ $gateway['last_test_status'] === 'success' ? 'circle-check' : ($gateway['last_test_status'] === 'failed' ? 'circle-x' : 'activity') }}" class="size-5"></i>
                                </span>
                            </div>
                            <div class="mt-4 rounded-lg bg-slate-50 p-3">
                                <p class="text-sm font-semibold text-slate-800">{{ $gateway['last_test_status'] ? Str::headline($gateway['last_test_status']) : 'Not tested yet' }}</p>
                                @if($gateway['last_test_message'])<p class="mt-1 text-xs leading-5 text-slate-500">{{ $gateway['last_test_message'] }}</p>@endif
                                @if($gateway['last_tested_at'])<p class="mt-2 text-[11px] text-slate-400">{{ \Illuminate\Support\Carbon::parse($gateway['last_tested_at'])->format('M d, Y · h:i A') }}</p>@endif
                            </div>
                        </section>

                        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center gap-2">
                                <img src="{{ asset($meta['logo']) }}" alt="" class="size-4 object-contain">
                                <h2 class="font-semibold text-slate-950">Get credentials</h2>
                            </div>
                            <ol class="mt-4 space-y-3 text-xs leading-5 text-slate-600">
                                <li class="flex gap-3"><span class="grid size-5 shrink-0 place-items-center rounded-md bg-violet-50 font-semibold text-violet-700">1</span><span>Open the provider’s developer dashboard.</span></li>
                                <li class="flex gap-3"><span class="grid size-5 shrink-0 place-items-center rounded-md bg-violet-50 font-semibold text-violet-700">2</span><span>Use test or sandbox credentials first.</span></li>
                                <li class="flex gap-3"><span class="grid size-5 shrink-0 place-items-center rounded-md bg-violet-50 font-semibold text-violet-700">3</span><span>Add the webhook URL shown on this page.</span></li>
                                <li class="flex gap-3"><span class="grid size-5 shrink-0 place-items-center rounded-md bg-violet-50 font-semibold text-violet-700">4</span><span>Save and run the connection test.</span></li>
                            </ol>
                            <a href="{{ $meta['keys_url'] }}" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-violet-700 hover:text-violet-800">
                                {{ $meta['keys_label'] }}
                                <i data-lucide="external-link" class="size-4"></i>
                            </a>
                        </section>

                        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                                <i data-lucide="shield-check" class="size-4 text-emerald-600"></i>
                                Safe payment recording
                            </div>
                            <p class="mt-2 text-xs leading-5 text-slate-500">EcclesiaOS verifies status, amount, currency and provider date before creating a Finance record. Duplicate callbacks do not create duplicate donations.</p>
                        </section>
                    </div>
                @endforeach
            </aside>
        </div>
    </div>
</x-app-layout>
