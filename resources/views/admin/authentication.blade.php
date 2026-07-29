<x-app-layout title="Authentication" :breadcrumbs="$breadcrumbs">
    @php
        $branding = \App\Support\Branding::current();
        $loginReady = $providers->where('enabled', true)->where('configured', true)->count();
        $attentionNeeded = $providers->filter(fn (array $provider): bool => $provider['enabled'] && ! $provider['configured'])->count();
        $readyProviders = $providers->filter(fn (array $provider): bool => $provider['enabled'] && $provider['configured'])->values();
        $metrics = [
            [
                'label' => 'Login Buttons',
                'value' => $loginReady,
                'hint' => 'enabled and ready',
                'icon' => 'log-in',
                'class' => 'bg-violet-50 text-violet-600 ring-violet-100',
            ],
            [
                'label' => 'Enabled',
                'value' => $stats['enabled'],
                'hint' => 'turned on by admin',
                'icon' => 'toggle-right',
                'class' => 'bg-emerald-50 text-emerald-600 ring-emerald-100',
            ],
            [
                'label' => 'Configured',
                'value' => $stats['configured'],
                'hint' => 'credentials saved',
                'icon' => 'key-round',
                'class' => 'bg-blue-50 text-blue-600 ring-blue-100',
            ],
            [
                'label' => 'Social Logins',
                'value' => $stats['recent_social_logins'],
                'hint' => 'last 30 days',
                'icon' => 'activity',
                'class' => 'bg-orange-50 text-orange-600 ring-orange-100',
            ],
        ];
    @endphp

    <div class="space-y-5">
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="grid gap-4 p-5 xl:grid-cols-[minmax(0,1fr)_minmax(380px,520px)] xl:items-start">
                <div class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex min-w-0 gap-4">
                            <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-600 ring-1 ring-violet-100">
                                <i data-lucide="shield-check" class="size-6"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-violet-600">Administration</p>
                                <h1 class="mt-1 text-2xl font-semibold text-slate-950">Authentication</h1>
                                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Control the social sign-in buttons that appear on the login page. Providers become visible only after they are enabled and have saved credentials.</p>
                            </div>
                        </div>

                        <span class="inline-flex w-fit shrink-0 items-center gap-2 rounded-lg {{ $attentionNeeded > 0 ? 'bg-amber-50 text-amber-700 ring-amber-100' : 'bg-emerald-50 text-emerald-700 ring-emerald-100' }} px-3 py-2 text-xs font-semibold ring-1">
                            <i data-lucide="{{ $attentionNeeded > 0 ? 'triangle-alert' : 'badge-check' }}" class="size-4"></i>
                            {{ $attentionNeeded > 0 ? $attentionNeeded.' needs setup' : 'Login ready' }}
                        </span>
                    </div>

                    <div class="mt-5 grid gap-4 border-t border-slate-100 pt-4 lg:grid-cols-[1fr_auto] lg:items-end">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Visible on login</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @forelse ($readyProviders as $provider)
                                    <span class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-800">
                                        @if(filled($provider['logo'] ?? null))
                                            <img src="{{ $branding->assetPath($provider['logo']) }}" alt="" class="size-4">
                                        @else
                                            <i data-lucide="{{ $provider['icon'] }}" class="size-4" style="color: {{ $provider['color'] }}"></i>
                                        @endif
                                        {{ $provider['label'] }}
                                    </span>
                                @empty
                                    <span class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-600">
                                        <i data-lucide="eye-off" class="size-4"></i>
                                        No social buttons visible
                                    </span>
                                @endforelse
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 lg:justify-end">
                            <a href="{{ route('login') }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                <i data-lucide="external-link" class="size-4"></i>
                                View Login
                            </a>
                            <button form="authentication-provider-form" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                                <i data-lucide="save" class="size-4"></i>
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($metrics as $metric)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <span class="grid size-10 place-items-center rounded-lg ring-1 {{ $metric['class'] }}">
                                    <i data-lucide="{{ $metric['icon'] }}" class="size-5"></i>
                                </span>
                                <span class="text-xl font-semibold text-slate-950">{{ number_format($metric['value']) }}</span>
                            </div>
                            <p class="mt-3 text-sm font-semibold text-slate-900">{{ $metric['label'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $metric['hint'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        @if (session('status'))
            <div class="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-700">
                <i data-lucide="check-circle-2" class="mt-0.5 size-4 shrink-0"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-700">
                <i data-lucide="circle-alert" class="mt-0.5 size-4 shrink-0"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <form id="authentication-provider-form" method="POST" action="{{ route('auth-settings.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <section class="dashboard-card">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">Social Sign-In Providers</h2>
                            <p class="mt-1 text-sm text-slate-500">Enable only providers that have valid OAuth credentials and the callback URL registered with the provider.</p>
                        </div>
                        @if ($attentionNeeded > 0)
                            <span class="inline-flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 ring-1 ring-amber-100">
                                <i data-lucide="triangle-alert" class="size-4"></i>
                                {{ $attentionNeeded }} needs credentials
                            </span>
                        @else
                            <span class="inline-flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">
                                <i data-lucide="badge-check" class="size-4"></i>
                                Ready settings
                            </span>
                        @endif
                    </div>
                </section>

                @foreach ($providers as $index => $provider)
                    @php
                        $isEnabled = (bool) old("providers.$index.enabled", $provider['enabled']);
                        $isConfigured = (bool) $provider['configured'];
                        $isLoginReady = $isEnabled && $isConfigured;
                        $status = $isLoginReady ? 'Visible on login' : ($isEnabled ? 'Credentials needed' : 'Hidden from login');
                        $statusClass = $isLoginReady
                            ? 'bg-emerald-50 text-emerald-700 ring-emerald-100'
                            : ($isEnabled ? 'bg-amber-50 text-amber-700 ring-amber-100' : 'bg-slate-100 text-slate-600 ring-slate-200');
                    @endphp

                    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="grid gap-4 border-b border-slate-100 p-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="grid size-12 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white shadow-sm">
                                    @if(filled($provider['logo'] ?? null))
                                        <img src="{{ $branding->assetPath($provider['logo']) }}" alt="" class="size-6">
                                    @else
                                        <i data-lucide="{{ $provider['icon'] }}" class="size-5" style="color: {{ $provider['color'] }}"></i>
                                    @endif
                                </span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-base font-semibold text-slate-950">{{ $provider['label'] }}</h3>
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClass }}">
                                            <span class="size-1.5 rounded-full {{ $isLoginReady ? 'bg-emerald-500' : ($isEnabled ? 'bg-amber-500' : 'bg-slate-400') }}"></span>
                                            {{ $status }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">{{ $isConfigured ? 'Client credentials are saved.' : 'Add a client ID and secret before enabling this provider for users.' }}</p>
                                </div>
                            </div>

                            <label class="inline-flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700 lg:min-w-[190px]">
                                <span>Enable sign-in</span>
                                <input type="hidden" name="providers[{{ $index }}][provider]" value="{{ $provider['key'] }}">
                                <input type="checkbox" name="providers[{{ $index }}][enabled]" value="1" @checked($isEnabled) class="rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                            </label>
                        </div>

                        <div class="grid gap-4 p-4 lg:grid-cols-2">
                            <label class="space-y-1.5 text-sm font-medium text-slate-700">
                                <span>Client ID</span>
                                <input name="providers[{{ $index }}][client_id]" value="{{ old("providers.$index.client_id", $provider['client_id']) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:ring-violet-100" placeholder="{{ $provider['label'] }} OAuth client ID">
                            </label>

                            <label class="space-y-1.5 text-sm font-medium text-slate-700">
                                <span>Client Secret</span>
                                <input name="providers[{{ $index }}][client_secret]" type="password" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:ring-violet-100" placeholder="{{ $provider['has_secret'] ? 'Saved. Leave blank to keep current secret.' : 'OAuth client secret' }}">
                            </label>

                            <label class="space-y-1.5 text-sm font-medium text-slate-700 lg:col-span-2">
                                <span>Redirect / Callback URL</span>
                                <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                                    <input readonly value="{{ $provider['callback_url'] }}" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-600">
                                    <a href="{{ $provider['callback_url'] }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                                        <i data-lucide="link" class="size-4"></i>
                                        Open
                                    </a>
                                </div>
                            </label>

                            <div class="flex flex-col gap-3 rounded-lg bg-slate-50 p-3 text-xs leading-5 text-slate-500 lg:col-span-2 sm:flex-row sm:items-center sm:justify-between">
                                <span class="inline-flex items-center gap-2">
                                    <i data-lucide="{{ $isConfigured ? 'lock-keyhole' : 'key-round' }}" class="size-4 text-slate-400"></i>
                                    {{ $provider['has_secret'] ? 'A secret is stored securely. Leave the secret field blank to keep it.' : 'No secret is currently saved for this provider.' }}
                                </span>

                                @if ($provider['has_secret'])
                                    <label class="inline-flex items-center gap-2 font-semibold text-rose-600">
                                        <input type="checkbox" name="providers[{{ $index }}][clear_secret]" value="1" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                                        Clear saved secret
                                    </label>
                                @endif
                            </div>
                        </div>
                    </section>
                @endforeach
            </form>

            <aside class="space-y-4">
                <section class="dashboard-card">
                    <div class="flex items-start gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-violet-50 text-violet-600 ring-1 ring-violet-100">
                            <i data-lucide="list-checks" class="size-5"></i>
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-slate-950">Readiness Rules</h2>
                            <p class="mt-1 text-xs leading-5 text-slate-500">These rules control what users see on the login page.</p>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3 text-sm">
                        <div class="flex gap-2 rounded-lg bg-slate-50 p-3 text-slate-600">
                            <i data-lucide="check" class="mt-0.5 size-4 shrink-0 text-emerald-600"></i>
                            <span>Provider must be enabled.</span>
                        </div>
                        <div class="flex gap-2 rounded-lg bg-slate-50 p-3 text-slate-600">
                            <i data-lucide="check" class="mt-0.5 size-4 shrink-0 text-emerald-600"></i>
                            <span>Client ID and secret must be saved.</span>
                        </div>
                        <div class="flex gap-2 rounded-lg bg-slate-50 p-3 text-slate-600">
                            <i data-lucide="check" class="mt-0.5 size-4 shrink-0 text-emerald-600"></i>
                            <span>The user must already exist in EcclesiaOS.</span>
                        </div>
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="flex items-start gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-amber-50 text-amber-600 ring-1 ring-amber-100">
                            <i data-lucide="info" class="size-5"></i>
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-slate-950">Provider Notes</h2>
                            <div class="mt-2 space-y-3 text-sm leading-6 text-slate-600">
                                <p>Add the callback URL in each provider developer console before turning sign-in on.</p>
                                <p>X may not return email addresses. First-time X sign-in works best after manually linking the account or after a prior social login with email.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 p-4">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-950">Recent Activity</h2>
                            <p class="mt-1 text-xs text-slate-500">Authentication changes and social logins.</p>
                        </div>
                        <span class="grid size-9 place-items-center rounded-lg bg-slate-50 text-slate-500">
                            <i data-lucide="history" class="size-4"></i>
                        </span>
                    </div>

                    <div class="divide-y divide-slate-100">
                        @forelse ($recentActivity as $activity)
                            <div class="p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ Str::headline($activity->action) }}</p>
                                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ $activity->description }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-500">{{ $activity->created_at?->diffForHumans() }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="p-6">
                                <x-empty-state icon="lock" title="No auth activity" message="Social sign-in and authentication settings activity will appear here." />
                            </div>
                        @endforelse
                    </div>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
