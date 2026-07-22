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
    @endphp

    <div class="space-y-5">
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

        <section class="grid gap-4 xl:grid-cols-[280px_1fr]">
            <aside class="space-y-3">
                <a href="#preferences" class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 hover:bg-violet-50"><i data-lucide="sliders-horizontal" class="size-4 text-violet-600"></i>Preferences</a>
                <a href="#notifications" class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 hover:bg-violet-50"><i data-lucide="bell" class="size-4 text-violet-600"></i>Notifications</a>
                <a href="#security" class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 hover:bg-violet-50"><i data-lucide="lock" class="size-4 text-violet-600"></i>Security & MFA</a>
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
                <form id="preferences" method="POST" action="{{ route('account.settings.update') }}" class="dashboard-card scroll-mt-24">
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

                <form id="notifications" method="POST" action="{{ route('account.settings.update') }}" class="dashboard-card scroll-mt-24">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="section" value="notifications">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <div><h2 class="text-base font-semibold text-slate-950">Notification Preferences</h2><p class="text-sm text-slate-500">Choose delivery channels and the account activity you want to hear about.</p></div>
                        <button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white"><i data-lucide="save" class="size-4"></i>Save Notifications</button>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <section class="rounded-lg border border-slate-200 p-4">
                            <h3 class="mb-3 text-sm font-semibold text-slate-950">Delivery Channels</h3>
                            <div class="space-y-3 text-sm">
                                @foreach(['email_notifications' => ['Email notifications', 'mail'], 'sms_notifications' => ['SMS notifications', 'phone'], 'in_app_notifications' => ['In-app notifications', 'bell'], 'push_notifications' => ['Browser push alerts', 'monitor']] as $field => [$label, $icon])
                                    <label class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-slate-700"><span class="inline-flex items-center gap-2"><i data-lucide="{{ $icon }}" class="size-4 text-violet-600"></i>{{ $label }}</span><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $notifications[$field])) class="rounded border-slate-300 text-violet-600"></label>
                                @endforeach
                            </div>
                        </section>
                        <section class="rounded-lg border border-slate-200 p-4">
                            <h3 class="mb-3 text-sm font-semibold text-slate-950">Topics</h3>
                            <label class="mb-3 block text-sm text-slate-600">Frequency<select name="notification_frequency" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">@foreach(['instant' => 'Instant', 'daily_digest' => 'Daily digest', 'weekly_summary' => 'Weekly summary', 'priority_only' => 'Priority only'] as $value => $label)<option value="{{ $value }}" @selected(old('notification_frequency', $notifications['notification_frequency']) === $value)>{{ $label }}</option>@endforeach</select></label>
                            <div class="space-y-3 text-sm">
                                @foreach(['notify_security' => 'Security activity', 'notify_members' => 'Member updates', 'notify_events' => 'Events and programs', 'notify_reports' => 'Reports and analytics'] as $field => $label)
                                    <label class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-slate-700">{{ $label }}<input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $notifications[$field])) class="rounded border-slate-300 text-violet-600"></label>
                                @endforeach
                            </div>
                        </section>
                    </div>
                </form>

                <form id="security" method="POST" action="{{ route('account.settings.update') }}" class="dashboard-card scroll-mt-24">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="section" value="security">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <div><h2 class="text-base font-semibold text-slate-950">Security & MFA</h2><p class="text-sm text-slate-500">Control multi-factor authentication, recovery, and sign-in alert behavior.</p></div>
                        <button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white"><i data-lucide="save" class="size-4"></i>Save Security</button>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-4 py-3 text-sm text-slate-700">Enable multi-factor authentication<input type="checkbox" name="mfa_enabled" value="1" @checked(old('mfa_enabled', $user->mfa_enabled)) class="rounded border-slate-300 text-violet-600"></label>
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
