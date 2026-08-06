<x-app-layout title="Account Settings" :breadcrumbs="$breadcrumbs">
    @php
        $preferences = $settings['preferences'];
        $notifications = $settings['notifications'];
        $security = $settings['security'];
        $securityScore = 55
            + ($user->mfa_enabled ? 18 : 0)
            + (($security['login_notifications'] ?? false) ? 10 : 0)
            + (($security['trusted_device_alerts'] ?? false) ? 8 : 0)
            + (filled($user->recovery_email) ? 9 : 0);
        $securityScore = min(100, $securityScore);
        $mfaConfirmed = (bool) data_get($security, 'mfa_confirmed');
        $initialTab = in_array(old('section'), ['preferences', 'notifications', 'security'], true) ? old('section') : 'preferences';
    @endphp

    <div
        x-data="{ tab: @js($initialTab), allowedTabs: ['preferences', 'notifications', 'security'] }"
        x-init="
            const syncTabFromHash = () => {
                const requestedTab = window.location.hash.replace('#', '');
                if (allowedTabs.includes(requestedTab)) tab = requestedTab;
            };
            syncTabFromHash();
            window.addEventListener('hashchange', syncTabFromHash);
        "
        class="space-y-5"
    >
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-lg bg-violet-100 text-violet-600">
                    <i data-lucide="user-round-cog" class="size-7"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-semibold text-slate-950">Account Settings</h1>
                    <p class="text-sm text-slate-500">Manage personal preferences, notifications, sign-in security, MFA, and account defaults.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('profile.edit') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                    <i data-lucide="user-round" class="size-4"></i>
                    Profile
                </a>
                <form method="POST" action="{{ route('account.settings.test-notification') }}">
                    @csrf
                    <button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white hover:bg-violet-700">
                        <i data-lucide="bell-ring" class="size-4"></i>
                        Send Test Notification
                    </button>
                </form>
            </div>
        </div>

        @if(session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ $errors->first() }}</div>
        @endif

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="dashboard-card">
                <div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-lg bg-violet-50 text-violet-600 ring-1 ring-violet-100"><i data-lucide="palette" class="size-5"></i></span><div><div class="text-xs text-slate-500">Theme</div><div class="mt-1 text-xl text-slate-950">{{ Str::headline($preferences['theme_mode']) }}</div><div class="text-xs text-slate-500">personal display mode</div></div></div>
            </article>
            <article class="dashboard-card">
                <div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-lg bg-blue-50 text-blue-600 ring-1 ring-blue-100"><i data-lucide="bell" class="size-5"></i></span><div><div class="text-xs text-slate-500">Unread Alerts</div><div class="mt-1 text-xl text-slate-950">{{ number_format($unreadNotifications) }}</div><div class="text-xs text-slate-500">{{ Str::headline($notifications['notification_frequency']) }}</div></div></div>
            </article>
            <article class="dashboard-card">
                <div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-lg bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100"><i data-lucide="shield-check" class="size-5"></i></span><div><div class="text-xs text-slate-500">MFA</div><div class="mt-1 text-xl text-slate-950">{{ $user->mfa_enabled ? 'Enabled' : 'Disabled' }}</div><div class="text-xs text-slate-500">{{ Str::headline($security['mfa_method']) }}</div></div></div>
            </article>
            <article class="dashboard-card">
                <div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-lg bg-orange-50 text-orange-600 ring-1 ring-orange-100"><i data-lucide="gauge" class="size-5"></i></span><div><div class="text-xs text-slate-500">Security Score</div><div class="mt-1 text-xl text-slate-950">{{ $securityScore }}/100</div><div class="text-xs text-slate-500">{{ $securityScore >= 90 ? 'excellent' : 'improve MFA/recovery' }}</div></div></div>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[280px_minmax(0,1fr)]">
            <aside class="space-y-3">
                <nav aria-label="Account settings sections" role="tablist" class="grid grid-cols-3 gap-2 xl:block xl:space-y-2">
                    @foreach([
                        ['preferences', 'Preferences', 'sliders-horizontal'],
                        ['notifications', 'Notifications', 'bell'],
                        ['security', 'Security & MFA', 'lock'],
                    ] as [$tabId, $tabLabel, $tabIcon])
                        <a
                            id="{{ $tabId }}-tab"
                            href="#{{ $tabId }}"
                            role="tab"
                            :aria-selected="tab === '{{ $tabId }}'"
                            aria-controls="{{ $tabId }}"
                            @click="tab = '{{ $tabId }}'"
                            class="flex min-w-0 flex-col items-center justify-center gap-2 rounded-lg border px-3 py-3 text-center text-xs font-semibold transition xl:flex-row xl:justify-start xl:px-4 xl:text-left xl:text-sm"
                            :class="tab === '{{ $tabId }}' ? 'border-violet-200 bg-violet-50 text-violet-700 shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                        >
                            <i data-lucide="{{ $tabIcon }}" class="size-4 shrink-0" :class="tab === '{{ $tabId }}' ? 'text-violet-600' : 'text-slate-400'"></i>
                            <span class="truncate">{{ $tabLabel }}</span>
                            <i x-show="tab === '{{ $tabId }}'" data-lucide="chevron-right" class="ml-auto hidden size-4 xl:block"></i>
                        </a>
                    @endforeach
                </nav>
                <section class="dashboard-card">
                    <h2 class="text-base font-semibold text-slate-950">Account</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="text-xs text-slate-500">Name</dt><dd class="text-slate-950">{{ $user->name }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Email</dt><dd class="break-all text-slate-950">{{ $user->email }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Role</dt><dd class="text-slate-950">{{ $user->roles->pluck('name')->join(', ') ?: 'No role' }}</dd></div>
                    </dl>
                </section>
            </aside>

            <main class="space-y-4">
                <form id="preferences" x-show="tab === 'preferences'" x-cloak role="tabpanel" aria-labelledby="preferences-tab" method="POST" action="{{ route('account.settings.update') }}" class="dashboard-card">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="section" value="preferences">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <div><h2 class="text-base font-semibold text-slate-950">Account Preferences</h2><p class="text-sm text-slate-500">Set personal display, language, date, and landing-page defaults.</p></div>
                        <button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white"><i data-lucide="save" class="size-4"></i>Save Preferences</button>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="text-sm text-slate-600">Timezone<select name="timezone" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">@foreach(['America/Chicago' => 'Central Time', 'America/New_York' => 'Eastern Time', 'UTC' => 'UTC', 'Asia/Nicosia' => 'Asia/Nicosia'] as $value => $label)<option value="{{ $value }}" @selected(old('timezone', $user->timezone ?? config('app.timezone')) === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label class="text-sm text-slate-600">Language<select name="language" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">@foreach(['en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 'pt' => 'Portuguese'] as $value => $label)<option value="{{ $value }}" @selected(old('language', $preferences['language']) === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label class="text-sm text-slate-600">Date Format<select name="date_format" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">@foreach(['M d, Y' => 'May 25, 2024', 'Y-m-d' => '2024-05-25', 'd M Y' => '25 May 2024', 'm/d/Y' => '05/25/2024'] as $value => $label)<option value="{{ $value }}" @selected(old('date_format', $preferences['date_format']) === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label class="text-sm text-slate-600">Theme Mode<select name="theme_mode" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">@foreach(['light' => 'Light', 'dark' => 'Dark', 'system' => 'System'] as $value => $label)<option value="{{ $value }}" @selected(old('theme_mode', $preferences['theme_mode']) === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label class="text-sm text-slate-600">Default Landing Page<select name="default_landing_page" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">@foreach(['dashboard' => 'Dashboard', 'members.index' => 'Members', 'programs.index' => 'Programs', 'calendar.index' => 'Calendar', 'profile.edit' => 'Profile'] as $value => $label)<option value="{{ $value }}" @selected(old('default_landing_page', $preferences['default_landing_page']) === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-4 py-3 text-sm text-slate-700">Compact data tables<input type="checkbox" name="compact_tables" value="1" @checked(old('compact_tables', $preferences['compact_tables'])) class="rounded border-slate-300 text-violet-600"></label>
                    </div>
                </form>

                <form id="notifications" x-show="tab === 'notifications'" x-cloak role="tabpanel" aria-labelledby="notifications-tab" method="POST" action="{{ route('account.settings.update') }}" class="dashboard-card">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="section" value="notifications">
                    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div><h2 class="text-base font-semibold text-slate-950">Notification Preferences</h2><p class="text-sm text-slate-500">Control how EcclesiaOS contacts you and which activity reaches your account.</p></div>
                        <button class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="save" class="size-4"></i>Save Notifications</button>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-[.9fr_1.35fr]">
                        <div class="space-y-4">
                            <section class="rounded-lg border border-slate-200 p-4">
                                <div class="mb-3"><h3 class="text-sm font-semibold text-slate-950">Delivery channels</h3><p class="mt-1 text-xs text-slate-500">Only configured channels with valid contact details can deliver.</p></div>
                                <div class="space-y-2 text-sm">
                                    @foreach([
                                        'in_app_notifications' => ['In-app', 'Alerts in EcclesiaOS', 'bell', 'bg-violet-50 text-violet-600'],
                                        'email_notifications' => ['Email', $user->email ?: 'No email address', 'mail', 'bg-blue-50 text-blue-600'],
                                        'sms_notifications' => ['SMS', $user->phone ?: 'No phone number', 'phone', 'bg-emerald-50 text-emerald-600'],
                                        'whatsapp_notifications' => ['WhatsApp', $user->phone ?: 'No phone number', 'message-circle', 'bg-green-50 text-green-600'],
                                        'push_notifications' => ['Browser push', 'This registered browser', 'monitor', 'bg-amber-50 text-amber-600'],
                                    ] as $field => [$label, $help, $icon, $tone])
                                        <label class="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50/70 px-3 py-2.5">
                                            <span class="grid size-9 shrink-0 place-items-center rounded-lg {{ $tone }}"><i data-lucide="{{ $icon }}" class="size-4"></i></span>
                                            <span class="min-w-0 flex-1"><span class="block font-semibold text-slate-700">{{ $label }}</span><span class="block truncate text-[10px] text-slate-400">{{ $help }}</span></span>
                                            <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $notifications[$field])) class="rounded border-slate-300 text-violet-600">
                                        </label>
                                    @endforeach
                                </div>
                            </section>

                            <section class="rounded-lg border border-slate-200 p-4">
                                <h3 class="text-sm font-semibold text-slate-950">Timing and priority</h3>
                                <label class="mt-3 block text-xs font-medium text-slate-600">Delivery frequency<select name="notification_frequency" class="mt-1.5 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm">@foreach(['instant' => 'Instant', 'daily_digest' => 'Daily digest', 'weekly_summary' => 'Weekly summary', 'priority_only' => 'Critical alerts only'] as $value => $label)<option value="{{ $value }}" @selected(old('notification_frequency', $notifications['notification_frequency']) === $value)>{{ $label }}</option>@endforeach</select></label>
                                <label class="mt-3 flex items-start gap-3 rounded-lg border border-rose-100 bg-rose-50/60 p-3"><input type="checkbox" name="critical_alerts" value="1" @checked(old('critical_alerts', $notifications['critical_alerts'])) class="mt-0.5 rounded border-rose-300 text-rose-600"><span><span class="block text-xs font-bold text-rose-800">Critical alerts</span><span class="mt-1 block text-[10px] leading-4 text-rose-600">Allow urgent operational alerts to bypass digest timing and quiet hours.</span></span></label>
                            </section>
                        </div>

                        <section class="rounded-lg border border-slate-200 p-4">
                            <div class="mb-3"><h3 class="text-sm font-semibold text-slate-950">Notification topics</h3><p class="mt-1 text-xs text-slate-500">Turn off topics you do not need. Your permissions still control which records you can open.</p></div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach([
                                    'notify_approvals' => ['Approval tasks', 'Requests waiting for your decision', 'badge-check', 'bg-violet-50 text-violet-600'],
                                    'notify_financial_assistance' => ['Financial assistance', 'Changes, approval and disbursement', 'hand-coins', 'bg-emerald-50 text-emerald-600'],
                                    'notify_events' => ['Events and programs', 'Schedules, updates and cancellations', 'calendar-days', 'bg-blue-50 text-blue-600'],
                                    'notify_attendance' => ['Attendance', 'Sessions, check-ins and exceptions', 'clipboard-check', 'bg-cyan-50 text-cyan-600'],
                                    'notify_members' => ['Member care', 'Member and pastoral-care updates', 'heart-handshake', 'bg-rose-50 text-rose-600'],
                                    'notify_volunteers' => ['Volunteers', 'Assignments and team updates', 'users', 'bg-orange-50 text-orange-600'],
                                    'notify_registration' => ['Registrations', 'Confirmations and follow-up', 'list-checks', 'bg-indigo-50 text-indigo-600'],
                                    'notify_reports' => ['Reports and analytics', 'Scheduled reports and insights', 'chart-no-axes-combined', 'bg-fuchsia-50 text-fuchsia-600'],
                                    'notify_security' => ['Security and system', 'Sign-ins, account and system alerts', 'shield-check', 'bg-slate-100 text-slate-600'],
                                ] as $field => [$label, $help, $icon, $tone])
                                    <label class="flex min-h-20 items-center gap-3 rounded-lg border border-slate-200 p-3 transition hover:bg-slate-50">
                                        <span class="grid size-9 shrink-0 place-items-center rounded-lg {{ $tone }}"><i data-lucide="{{ $icon }}" class="size-4"></i></span>
                                        <span class="min-w-0 flex-1"><span class="block text-xs font-bold text-slate-700">{{ $label }}</span><span class="mt-1 block text-[10px] leading-4 text-slate-400">{{ $help }}</span></span>
                                        <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $notifications[$field])) class="rounded border-slate-300 text-violet-600">
                                    </label>
                                @endforeach
                            </div>
                        </section>
                    </div>
                    <div class="mt-4 flex items-center gap-2 rounded-lg border border-blue-100 bg-blue-50 px-3 py-2.5 text-[10px] text-blue-700"><i data-lucide="info" class="size-4 shrink-0"></i>These settings synchronize directly with the Communications delivery engine and apply to future notifications.</div>
                </form>

                <form id="security" x-show="tab === 'security'" x-cloak role="tabpanel" aria-labelledby="security-tab" method="POST" action="{{ route('account.settings.update') }}" class="dashboard-card">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="section" value="security">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <div><h2 class="text-base font-semibold text-slate-950">Security & MFA</h2><p class="text-sm text-slate-500">Control multi-factor authentication, recovery, and sign-in alert behavior.</p></div>
                        <button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white"><i data-lucide="save" class="size-4"></i>Save Security</button>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-lg border border-slate-200 px-4 py-3 text-sm text-slate-700">
                            <label class="flex items-center justify-between gap-3">Enable multi-factor authentication<input type="checkbox" name="mfa_enabled" value="1" @checked(old('mfa_enabled', $user->mfa_enabled)) class="rounded border-slate-300 text-violet-600"></label>
                            <p class="mt-2 text-xs leading-5 text-slate-500">{{ $mfaConfirmed ? 'Authenticator MFA is confirmed and will be required on sign-in.' : 'Use the setup screen to scan a QR code and confirm MFA before it is enforced.' }}</p>
                            <a href="{{ route('account.mfa.setup') }}" class="mt-3 inline-flex items-center gap-2 rounded-lg border border-violet-200 px-3 py-2 text-xs font-semibold text-violet-700 hover:bg-violet-50"><i data-lucide="qr-code" class="size-4"></i>{{ $mfaConfirmed ? 'Manage scan setup' : 'Set up with scan code' }}</a>
                        </div>
                        <label class="text-sm text-slate-600">MFA Method<select name="mfa_method" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">@foreach(['authenticator' => 'Authenticator App', 'email' => 'Email Code', 'sms' => 'SMS Code'] as $value => $label)<option value="{{ $value }}" @selected(old('mfa_method', $security['mfa_method']) === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label class="text-sm text-slate-600">Recovery Email<input name="recovery_email" type="email" value="{{ old('recovery_email', $user->recovery_email) }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="recovery@example.org"></label>
                        <label class="text-sm text-slate-600">Session Timeout<select name="session_timeout_minutes" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">@foreach([15 => '15 minutes', 30 => '30 minutes', 60 => '1 hour', 120 => '2 hours', 480 => '8 hours'] as $value => $label)<option value="{{ $value }}" @selected((int) old('session_timeout_minutes', $security['session_timeout_minutes']) === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-4 py-3 text-sm text-slate-700">Login notifications<input type="checkbox" name="login_notifications" value="1" @checked(old('login_notifications', $security['login_notifications'])) class="rounded border-slate-300 text-violet-600"></label>
                        <label class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-4 py-3 text-sm text-slate-700">Trusted-device alerts<input type="checkbox" name="trusted_device_alerts" value="1" @checked(old('trusted_device_alerts', $security['trusted_device_alerts'])) class="rounded border-slate-300 text-violet-600"></label>
                    </div>
                </form>
            </main>
        </section>
    </div>
</x-app-layout>
