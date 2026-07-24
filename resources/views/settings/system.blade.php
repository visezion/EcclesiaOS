<x-app-layout title="System Settings" :breadcrumbs="$breadcrumbs">
    @php
        $assetUrl = function (?string $path, ?string $fallback = null): ?string {
            $path = $path ?: $fallback;

            if (! $path) {
                return null;
            }

            return \Illuminate\Support\Str::startsWith($path, ['http://', 'https://', '/'])
                ? $path
                : (\Illuminate\Support\Str::startsWith($path, 'branding/') ? asset('storage/'.$path) : asset($path));
        };
        $sidebarBackgroundUrl = $assetUrl($settings['sidebar_background'] ?? null, config('church.sidebar_background'));
        $logoUrl = $assetUrl($settings['logo'] ?? null);
        $faviconUrl = $assetUrl($settings['favicon'] ?? null);
        $sectionLinks = [
            ['id' => 'general', 'label' => 'General', 'icon' => 'settings'],
            ['id' => 'organization', 'label' => 'Organization Profile', 'icon' => 'building-2'],
            ['id' => 'campus', 'label' => $terminology['campus_plural'].' & Branches', 'icon' => 'network'],
            ['id' => 'users', 'label' => 'Users & Access', 'icon' => 'users'],
            ['id' => 'roles', 'label' => 'Roles & Permissions', 'icon' => 'shield-check'],
            ['id' => 'security', 'label' => 'Security', 'icon' => 'shield-alert'],
            ['id' => 'authentication', 'label' => 'Authentication', 'icon' => 'lock'],
            ['id' => 'notifications', 'label' => 'Notifications', 'icon' => 'bell'],
            ['id' => 'communications', 'label' => 'Communications', 'icon' => 'mail'],
            ['id' => 'finance', 'label' => 'Finance Defaults', 'icon' => 'wallet'],
            ['id' => 'assets', 'label' => 'Asset & Inventory Defaults', 'icon' => 'package-check'],
            ['id' => 'bookstore', 'label' => 'Book Store Settings', 'icon' => 'book-open'],
            ['id' => 'workflow', 'label' => 'Workflow & Approvals', 'icon' => 'git-branch'],
            ['id' => 'integrations', 'label' => 'Integrations & API', 'icon' => 'link'],
            ['id' => 'backup', 'label' => 'Backup & Recovery', 'icon' => 'cloud-check'],
            ['id' => 'audit', 'label' => 'Audit & Compliance', 'icon' => 'clipboard-list'],
            ['id' => 'localization', 'label' => 'Localization', 'icon' => 'globe-2'],
            ['id' => 'branding', 'label' => 'Branding', 'icon' => 'palette'],
            ['id' => 'advanced', 'label' => 'Advanced / System', 'icon' => 'wrench'],
        ];
        $toneClasses = [
            'violet' => 'bg-violet-50 text-violet-600',
            'emerald' => 'bg-emerald-50 text-emerald-600',
            'blue' => 'bg-blue-50 text-blue-600',
            'orange' => 'bg-orange-50 text-orange-600',
            'rose' => 'bg-rose-50 text-rose-600',
        ];
    @endphp

    <div
        x-data="{
            more: false,
            hiddenSections: ['users', 'security', 'notifications', 'workflow', 'integrations', 'backup', 'audit', 'localization', 'advanced'],
            openSection(id) {
                if (this.hiddenSections.includes(id)) {
                    this.more = true;
                }

                this.$nextTick(() => {
                    const section = document.getElementById(id);
                    section?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    section?.querySelector('input, select, textarea, a, button')?.focus({ preventScroll: true });

                    if (section) {
                        history.replaceState(null, '', '#' + id);
                    }
                });
            },
            edit(target) {
                const section = document.querySelector(target);
                section?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                section?.querySelector('input, select, textarea')?.focus({ preventScroll: true });
            }
        }"
        class="space-y-5"
    >
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-950">System Settings</h1>
                <p class="mt-1 text-sm text-slate-500">Configure global settings and preferences for your church management system.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button form="system-settings-form" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                    <i data-lucide="save" class="size-4"></i>
                    Save Changes
                </button>
                <form method="POST" action="{{ route('settings.system.reset') }}">
                    @csrf
                    @method('PUT')
                    <button class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <i data-lucide="rotate-ccw" class="size-4"></i>
                        Reset to Default
                    </button>
                </form>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
        @endif

        @if (session('error') || $errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">
                {{ session('error') ?? $errors->first() }}
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            @foreach ($stats as $stat)
                <section class="dashboard-card flex min-h-[112px] items-center gap-4">
                    <span class="grid size-12 shrink-0 place-items-center rounded-full {{ $toneClasses[$stat['tone']] ?? $toneClasses['violet'] }}">
                        <i data-lucide="{{ $stat['icon'] }}" class="size-6"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="text-xs font-medium text-slate-500">{{ $stat['label'] }}</div>
                        <div class="mt-1 truncate text-xl font-semibold text-slate-950">{{ $stat['value'] }}</div>
                        <div class="mt-1 truncate text-xs font-medium {{ $stat['tone'] === 'rose' ? 'text-rose-600' : ($stat['tone'] === 'emerald' ? 'text-emerald-600' : 'text-slate-500') }}">{{ $stat['sub'] }}</div>
                    </div>
                </section>
            @endforeach
        </div>

        <div class="grid gap-4 xl:grid-cols-[260px_1fr_360px]">
            <aside class="dashboard-card h-fit p-0">
                <div class="border-b border-slate-100 px-4 py-3 text-sm font-semibold text-slate-950">Settings</div>
                <nav class="space-y-1 p-3 text-sm">
                    @foreach ($sectionLinks as $link)
                        <a href="#{{ $link['id'] }}" @click.prevent="openSection('{{ $link['id'] }}')" class="{{ $loop->first ? 'bg-violet-50 text-violet-600' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950' }} flex items-center gap-3 rounded-lg px-3 py-2 font-medium">
                            <i data-lucide="{{ $link['icon'] }}" class="size-4"></i>
                            <span class="truncate">{{ $link['label'] }}</span>
                        </a>
                    @endforeach
                </nav>
            </aside>

            <main class="min-w-0">
                <form id="system-settings-form" method="POST" action="{{ route('settings.system.update') }}" enctype="multipart/form-data" class="dashboard-card space-y-4">
                    @csrf
                    @method('PUT')

                    <div id="general" class="border-b border-slate-100 pb-4">
                        <h2 class="text-lg font-semibold text-slate-950">General Settings</h2>
                        <p class="mt-1 text-sm text-slate-500">Configure core system preferences and defaults.</p>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-3">
                        <section id="organization" class="rounded-lg border border-slate-200 p-4">
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-950"><i data-lucide="building-2" class="size-4 text-violet-600"></i> Organization Details</h3>
                                <button type="button" @click="edit('#organization')" class="text-xs font-medium text-violet-600">Edit</button>
                            </div>
                            <div class="grid gap-3">
                                <label class="space-y-1 text-xs font-medium text-slate-500">System Name<input name="system_name" value="{{ old('system_name', $settings['system_name']) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900"></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">Church Name<input name="church_name" value="{{ old('church_name', $settings['church_name']) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900"></label>
                                <div class="grid gap-3 md:grid-cols-2">
                                    <label class="space-y-1 text-xs font-medium text-slate-500">Primary Email<input name="primary_email" type="email" value="{{ old('primary_email', $settings['primary_email']) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900"></label>
                                    <label class="space-y-1 text-xs font-medium text-slate-500">Support Email<input name="support_email" type="email" value="{{ old('support_email', $settings['support_email']) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900"></label>
                                </div>
                                <div class="grid gap-3 md:grid-cols-2">
                                    <label class="space-y-1 text-xs font-medium text-slate-500">Phone Number<input name="phone" value="{{ old('phone', $settings['phone']) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900"></label>
                                    <label class="space-y-1 text-xs font-medium text-slate-500">Address<input name="address" value="{{ old('address', $settings['address']) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900"></label>
                                </div>
                                <div class="grid gap-3 md:grid-cols-2">
                                    <label class="space-y-1 text-xs font-medium text-slate-500">Timezone<select name="timezone" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['America/Chicago' => '(UTC-06:00) Central Time', 'America/New_York' => '(UTC-05:00) Eastern Time', 'UTC' => 'UTC', 'Asia/Nicosia' => 'Asia/Nicosia'] as $value => $label)<option value="{{ $value }}" @selected(old('timezone', $settings['timezone']) === $value)>{{ $label }}</option>@endforeach</select></label>
                                    <label class="space-y-1 text-xs font-medium text-slate-500">Date Format<select name="date_format" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['M d, Y' => now()->format('M d, Y'), 'Y-m-d' => now()->format('Y-m-d'), 'd/m/Y' => now()->format('d/m/Y')] as $value => $label)<option value="{{ $value }}" @selected(old('date_format', $settings['date_format']) === $value)>{{ $label }}</option>@endforeach</select></label>
                                </div>
                                <div class="grid gap-3 md:grid-cols-2">
                                    <label class="space-y-1 text-xs font-medium text-slate-500">Currency<select name="currency" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['USD' => 'USD ($)', 'EUR' => 'EUR (€)', 'GBP' => 'GBP (£)', 'NGN' => 'NGN (₦)'] as $value => $label)<option value="{{ $value }}" @selected(old('currency', $settings['currency']) === $value)>{{ $label }}</option>@endforeach</select></label>
                                    <label class="space-y-1 text-xs font-medium text-slate-500">Language<select name="language" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['English (US)', 'English (UK)', 'French', 'Spanish'] as $value)<option value="{{ $value }}" @selected(old('language', $settings['language']) === $value)>{{ $value }}</option>@endforeach</select></label>
                                </div>
                            </div>
                        </section>

                        <section id="branding" class="rounded-lg border border-slate-200 p-4">
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-950"><i data-lucide="palette" class="size-4 text-violet-600"></i> Branding & Appearance</h3>
                                <button type="button" @click="edit('#branding')" class="text-xs font-medium text-violet-600">Edit</button>
                            </div>
                            <div class="space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <div class="mb-1 text-xs font-medium text-slate-500">Logo</div>
                                        <div class="flex items-center gap-3">
                                            @if ($logoUrl)
                                                <img src="{{ $logoUrl }}" alt="Logo preview" class="size-10 rounded-lg object-cover">
                                            @else
                                                <span class="grid size-10 place-items-center rounded-lg bg-violet-600 text-white"><i data-lucide="cross" class="size-6"></i></span>
                                            @endif
                                            <label class="cursor-pointer rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-violet-600 hover:bg-violet-50">Change<input name="logo" type="file" accept="image/png" class="sr-only"></label>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="mb-1 text-xs font-medium text-slate-500">Favicon</div>
                                        <div class="flex items-center gap-3">
                                            @if ($faviconUrl)
                                                <img src="{{ $faviconUrl }}" alt="Favicon preview" class="size-10 rounded-lg object-cover">
                                            @else
                                                <span class="grid size-10 place-items-center rounded-lg bg-violet-600 text-white"><i data-lucide="cross" class="size-6"></i></span>
                                            @endif
                                            <label class="cursor-pointer rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-violet-600 hover:bg-violet-50">Change<input name="favicon" type="file" accept="image/png" class="sr-only"></label>
                                        </div>
                                    </div>
                                </div>
                                <div class="grid gap-3 md:grid-cols-2">
                                    <label class="space-y-1 text-xs font-medium text-slate-500">Primary Color<input name="primary_color" type="color" value="{{ old('primary_color', $settings['primary_color']) }}" class="h-10 w-full rounded-lg border border-slate-200 px-2 py-1"></label>
                                    <label class="space-y-1 text-xs font-medium text-slate-500">Theme Mode<select name="theme_mode" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['light' => 'Light', 'dark' => 'Dark', 'system' => 'System'] as $value => $label)<option value="{{ $value }}" @selected(old('theme_mode', $settings['theme_mode']) === $value)>{{ $label }}</option>@endforeach</select></label>
                                </div>
                                <div class="grid gap-3 md:grid-cols-2">
                                    <label class="space-y-1 text-xs font-medium text-slate-500">Font Style<select name="font_family" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['Inter', 'Roboto', 'Lato', 'Nunito Sans', 'System UI'] as $value)<option value="{{ $value }}" @selected(old('font_family', $settings['font_family']) === $value)>{{ $value }}</option>@endforeach</select></label>
                                    <label class="space-y-1 text-xs font-medium text-slate-500">Email Branding<select name="email_template_branding" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['Use Custom Branding', 'Use Default Branding', 'Minimal Branding'] as $value)<option value="{{ $value }}" @selected(old('email_template_branding', $settings['email_template_branding']) === $value)>{{ $value }}</option>@endforeach</select></label>
                                </div>
                                <label class="space-y-1 text-xs font-medium text-slate-500">
                                    Sidebar Background PNG
                                    <input name="sidebar_background" type="file" accept="image/png" class="block w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-violet-50 file:px-3 file:py-1 file:text-xs file:font-medium file:text-violet-700">
                                </label>
                                <details class="rounded-lg border border-slate-200 bg-slate-50/60">
                                    <summary class="cursor-pointer px-3 py-2 text-xs font-medium text-slate-600">Advanced branding</summary>
                                    <div class="grid gap-3 border-t border-slate-200 bg-white p-3">
                                        <div class="grid gap-3 md:grid-cols-2">
                                            <label class="space-y-1 text-xs font-medium text-slate-500">Secondary Color<input name="secondary_color" type="color" value="{{ old('secondary_color', $settings['secondary_color']) }}" class="h-9 w-full rounded-lg border border-slate-200 px-2 py-1"></label>
                                            <label class="space-y-1 text-xs font-medium text-slate-500">Page Background<input name="page_background" type="color" value="{{ old('page_background', $settings['page_background']) }}" class="h-9 w-full rounded-lg border border-slate-200 px-2 py-1"></label>
                                        </div>
                                        <div class="grid gap-3 md:grid-cols-2">
                                            <label class="space-y-1 text-xs font-medium text-slate-500">Font Size<select name="font_scale" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['compact' => 'Compact', 'default' => 'Default', 'comfortable' => 'Comfortable'] as $value => $label)<option value="{{ $value }}" @selected(old('font_scale', $settings['font_scale']) === $value)>{{ $label }}</option>@endforeach</select></label>
                                            <label class="space-y-1 text-xs font-medium text-slate-500">Card Radius<select name="card_radius" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach ([4, 6, 8, 12, 16, 20] as $value)<option value="{{ $value }}" @selected((int) old('card_radius', $settings['card_radius']) === $value)>{{ $value }} px</option>@endforeach</select></label>
                                        </div>
                                        <div class="grid gap-3 md:grid-cols-2">
                                            <label class="space-y-1 text-xs font-medium text-slate-500">Sidebar Text<input name="sidebar_text_color" type="color" value="{{ old('sidebar_text_color', $settings['sidebar_text_color']) }}" class="h-9 w-full rounded-lg border border-slate-200 px-2 py-1"></label>
                                            <label class="space-y-1 text-xs font-medium text-slate-500">Profile Panel<input name="sidebar_profile_color" type="color" value="{{ old('sidebar_profile_color', $settings['sidebar_profile_color']) }}" class="h-9 w-full rounded-lg border border-slate-200 px-2 py-1"></label>
                                        </div>
                                        <div class="grid gap-3 md:grid-cols-3">
                                            <label class="space-y-1 text-xs font-medium text-slate-500">Sidebar Top<input name="sidebar_start_color" type="color" value="{{ old('sidebar_start_color', $settings['sidebar_start_color']) }}" class="h-9 w-full rounded-lg border border-slate-200 px-2 py-1"></label>
                                            <label class="space-y-1 text-xs font-medium text-slate-500">Middle<input name="sidebar_middle_color" type="color" value="{{ old('sidebar_middle_color', $settings['sidebar_middle_color']) }}" class="h-9 w-full rounded-lg border border-slate-200 px-2 py-1"></label>
                                            <label class="space-y-1 text-xs font-medium text-slate-500">Bottom<input name="sidebar_end_color" type="color" value="{{ old('sidebar_end_color', $settings['sidebar_end_color']) }}" class="h-9 w-full rounded-lg border border-slate-200 px-2 py-1"></label>
                                        </div>
                                        <div class="grid gap-3 md:grid-cols-[1fr_120px]">
                                            <div class="rounded-lg border border-slate-200 p-3" style="background: {{ $settings['page_background'] }};">
                                                <div class="rounded-lg border border-white/60 p-3 text-sm shadow-sm" style="background: white; border-radius: {{ (int) $settings['card_radius'] }}px; font-family: {{ $settings['font_family'] === 'Nunito Sans' ? "'Nunito Sans'" : $settings['font_family'] }}, ui-sans-serif, system-ui, sans-serif;">
                                                    <span class="inline-flex rounded-md px-2 py-1 text-white" style="background: {{ $settings['primary_color'] }};">Brand preview</span>
                                                </div>
                                            </div>
                                            <div class="overflow-hidden rounded-lg border border-slate-200 bg-sidebar">
                                                <div class="h-full min-h-20 bg-church-silhouette" style="--sidebar-background-image: url('{{ $sidebarBackgroundUrl }}');"></div>
                                            </div>
                                        </div>
                                    </div>
                                </details>
                            </div>
                        </section>

                        <section id="authentication" class="rounded-lg border border-slate-200 p-4">
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-950"><i data-lucide="lock" class="size-4 text-violet-600"></i> Authentication & Security</h3>
                                <button type="button" @click="edit('#authentication')" class="text-xs font-medium text-violet-600">Edit</button>
                            </div>
                            <div class="space-y-3">
                                <label class="flex items-center justify-between gap-3 text-xs font-medium text-slate-500">Multi-Factor Authentication<input name="mfa_required" type="checkbox" value="1" @checked(old('mfa_required', $settings['mfa_required'])) class="rounded border-slate-300 text-violet-600"></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">Password Policy<select name="password_policy" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['Strong (Recommended)', 'Standard', 'Custom'] as $value)<option value="{{ $value }}" @selected(old('password_policy', $settings['password_policy']) === $value)>{{ $value }}</option>@endforeach</select></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">Session Timeout<input name="session_timeout" type="number" min="5" max="1440" value="{{ old('session_timeout', $settings['session_timeout']) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900"></label>
                                <label class="flex items-center justify-between gap-3 text-xs font-medium text-slate-500">Login Notifications<input name="login_notifications" type="checkbox" value="1" @checked(old('login_notifications', $settings['login_notifications'])) class="rounded border-slate-300 text-violet-600"></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">SSO Provider<select name="sso_provider" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['Google Workspace', 'Microsoft Entra ID', 'None'] as $value)<option value="{{ $value }}" @selected(old('sso_provider', $settings['sso_provider']) === $value)>{{ $value }}</option>@endforeach</select></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">IP Restriction<select name="ip_restriction" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['Disabled', 'Allowlist Only', 'Blocklist Enabled'] as $value)<option value="{{ $value }}" @selected(old('ip_restriction', $settings['ip_restriction']) === $value)>{{ $value }}</option>@endforeach</select></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">Device Trust<select name="device_trust" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['Trusted Devices Only', 'Any Device', 'Managed Devices'] as $value)<option value="{{ $value }}" @selected(old('device_trust', $settings['device_trust']) === $value)>{{ $value }}</option>@endforeach</select></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">Account Lockout Policy<select name="account_lockout_policy" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['5 attempts, 15 min lock', '3 attempts, 30 min lock', 'Disabled'] as $value)<option value="{{ $value }}" @selected(old('account_lockout_policy', $settings['account_lockout_policy']) === $value)>{{ $value }}</option>@endforeach</select></label>
                            </div>
                        </section>

                        <section id="roles" class="rounded-lg border border-slate-200 p-4">
                            <div class="mb-4 flex items-center justify-between gap-3"><h3 class="flex items-center gap-2 text-sm font-semibold text-slate-950"><i data-lucide="shield-check" class="size-4 text-violet-600"></i> Roles & Authorization</h3><button type="button" @click="edit('#roles')" class="text-xs font-medium text-violet-600">Edit</button></div>
                            <div class="grid gap-3">
                                <label class="space-y-1 text-xs font-medium text-slate-500">Default User Role<select name="default_user_role" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach ($roles as $role)<option value="{{ $role->name }}" @selected(old('default_user_role', $settings['default_user_role']) === $role->name)>{{ $role->name }}</option>@endforeach</select></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">Approval Requirements<select name="approval_requirements" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['Manager Approval', 'Administrator Approval', 'No Approval'] as $value)<option value="{{ $value }}" @selected(old('approval_requirements', $settings['approval_requirements']) === $value)>{{ $value }}</option>@endforeach</select></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">Policy Enforcement<select name="policy_enforcement" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['Strict', 'Balanced', 'Permissive'] as $value)<option value="{{ $value }}" @selected(old('policy_enforcement', $settings['policy_enforcement']) === $value)>{{ $value }}</option>@endforeach</select></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">Data Access Scope<select name="data_access_scope" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['Role-Based Access', 'Campus Scoped', 'Global Access'] as $value)<option value="{{ $value }}" @selected(old('data_access_scope', $settings['data_access_scope']) === $value)>{{ $value }}</option>@endforeach</select></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">Branch Visibility Rules<select name="branch_visibility_rules" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['By Assignment', 'All Branches', 'Home Branch Only'] as $value)<option value="{{ $value }}" @selected(old('branch_visibility_rules', $settings['branch_visibility_rules']) === $value)>{{ $value }}</option>@endforeach</select></label>
                            </div>
                        </section>

                        <section id="campus" class="rounded-lg border border-slate-200 p-4">
                            <div class="mb-4 flex items-center justify-between gap-3"><h3 class="flex items-center gap-2 text-sm font-semibold text-slate-950"><i data-lucide="network" class="size-4 text-violet-600"></i> Church & {{ $terminology['campus_singular'] }} Settings</h3><button type="button" @click="edit('#campus')" class="text-xs font-medium text-violet-600">Edit</button></div>
                            <div class="grid gap-3">
                                <label class="space-y-1 text-xs font-medium text-slate-500">Headquarters Church<select name="headquarters_church_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach ($churches as $item)<option value="{{ $item->id }}" @selected((int) old('headquarters_church_id', $settings['headquarters_church_id']) === $item->id)>{{ $item->name }}</option>@endforeach</select></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">Default {{ $terminology['campus_singular'] }}<select name="default_campus_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900"><option value="">No default {{ Str::lower($terminology['campus_singular']) }}</option>@foreach ($campuses as $campus)<option value="{{ $campus->id }}" @selected((int) old('default_campus_id', $settings['default_campus_id']) === $campus->id)>{{ $campus->name }}</option>@endforeach</select></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">Multi-{{ $terminology['campus_singular'] }} Access<select name="multi_campus_access" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['Role-Based Access', 'Single Campus', 'All Campuses'] as $value)<option value="{{ $value }}" @selected(old('multi_campus_access', $settings['multi_campus_access']) === $value)>{{ str_replace(['Campus', 'Campuses'], [$terminology['campus_singular'], $terminology['campus_plural']], $value) }}</option>@endforeach</select></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">Branch Code Prefix<input name="branch_code_prefix" value="{{ old('branch_code_prefix', $settings['branch_code_prefix']) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900"></label>
                                <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-3">
                                    <div class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Admin Terminology</div>
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <label class="space-y-1 text-xs font-medium text-slate-500">{{ $terminology['campus_singular'] }} Singular Label<input name="campus_singular_label" value="{{ old('campus_singular_label', $settings['campus_singular_label']) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900" placeholder="Branch"></label>
                                        <label class="space-y-1 text-xs font-medium text-slate-500">{{ $terminology['campus_plural'] }} Plural Label<input name="campus_plural_label" value="{{ old('campus_plural_label', $settings['campus_plural_label']) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900" placeholder="Branches"></label>
                                        <label class="space-y-1 text-xs font-medium text-slate-500">{{ $terminology['ministry_singular'] }} Singular Label<input name="ministry_singular_label" value="{{ old('ministry_singular_label', $settings['ministry_singular_label']) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900" placeholder="Department"></label>
                                        <label class="space-y-1 text-xs font-medium text-slate-500">{{ $terminology['ministry_plural'] }} Plural Label<input name="ministry_plural_label" value="{{ old('ministry_plural_label', $settings['ministry_plural_label']) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900" placeholder="Departments"></label>
                                    </div>
                                    <p class="mt-2 text-xs text-slate-500">Changes display names only. Database fields and routes remain campus and ministry for compatibility.</p>
                                </div>
                            </div>
                        </section>

                        <section id="communications" class="rounded-lg border border-slate-200 p-4">
                            <div class="mb-4 flex items-center justify-between gap-3"><h3 class="flex items-center gap-2 text-sm font-semibold text-slate-950"><i data-lucide="message-square" class="size-4 text-violet-600"></i> Communications Settings</h3><button type="button" @click="edit('#communications')" class="text-xs font-medium text-violet-600">Edit</button></div>
                            <div class="grid gap-3">
                                <label class="space-y-1 text-xs font-medium text-slate-500">SMTP / Email Server<input name="smtp_server" value="{{ old('smtp_server', $settings['smtp_server']) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900"></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">SMS Provider<input name="sms_provider" value="{{ old('sms_provider', $settings['sms_provider']) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900"></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">WhatsApp Integration<input name="whatsapp_integration" value="{{ old('whatsapp_integration', $settings['whatsapp_integration']) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900"></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">Notification Preferences<select name="notification_preferences" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['Standard (Recommended)', 'Quiet', 'High Priority Only'] as $value)<option value="{{ $value }}" @selected(old('notification_preferences', $settings['notification_preferences']) === $value)>{{ $value }}</option>@endforeach</select></label>
                                <button form="test-smtp-form" class="w-fit rounded-lg border border-violet-200 px-4 py-2 text-sm font-medium text-violet-600 hover:bg-violet-50">Test Connection</button>
                            </div>
                        </section>

                        <section id="finance" class="rounded-lg border border-slate-200 p-4">
                            <div class="mb-4 flex items-center justify-between gap-3"><h3 class="flex items-center gap-2 text-sm font-semibold text-slate-950"><i data-lucide="wallet" class="size-4 text-violet-600"></i> Finance Defaults</h3><button type="button" @click="edit('#finance')" class="text-xs font-medium text-violet-600">Edit</button></div>
                            <div class="grid gap-3">
                                <label class="space-y-1 text-xs font-medium text-slate-500">Receipt Numbering<select name="receipt_numbering" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['Auto Increment', 'Date Prefix', 'Manual'] as $value)<option value="{{ $value }}" @selected(old('receipt_numbering', $settings['receipt_numbering']) === $value)>{{ $value }}</option>@endforeach</select></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">Giving Categories<select name="giving_categories" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['10 Categories', '20 Categories', 'Custom Categories'] as $value)<option value="{{ $value }}" @selected(old('giving_categories', $settings['giving_categories']) === $value)>{{ $value }}</option>@endforeach</select></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">Tax Handling<select name="tax_handling" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['Tax Exempt', 'Taxable', 'Mixed'] as $value)<option value="{{ $value }}" @selected(old('tax_handling', $settings['tax_handling']) === $value)>{{ $value }}</option>@endforeach</select></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">Fiscal Year Start<select name="fiscal_year_start" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['January', 'April', 'July', 'October'] as $value)<option value="{{ $value }}" @selected(old('fiscal_year_start', $settings['fiscal_year_start']) === $value)>{{ $value }}</option>@endforeach</select></label>
                            </div>
                        </section>

                        <section id="assets" class="rounded-lg border border-slate-200 p-4">
                            <div class="mb-4 flex items-center justify-between gap-3"><h3 class="flex items-center gap-2 text-sm font-semibold text-slate-950"><i data-lucide="package-check" class="size-4 text-violet-600"></i> Asset & Inventory Defaults</h3><button type="button" @click="edit('#assets')" class="text-xs font-medium text-violet-600">Edit</button></div>
                            <div class="grid gap-3">
                                <label class="space-y-1 text-xs font-medium text-slate-500">Depreciation Method<select name="depreciation_method" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['Straight Line', 'Declining Balance', 'Manual'] as $value)<option value="{{ $value }}" @selected(old('depreciation_method', $settings['depreciation_method']) === $value)>{{ $value }}</option>@endforeach</select></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">Maintenance Alerts<select name="maintenance_alerts" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['30 Days Before', '14 Days Before', '7 Days Before'] as $value)<option value="{{ $value }}" @selected(old('maintenance_alerts', $settings['maintenance_alerts']) === $value)>{{ $value }}</option>@endforeach</select></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">Asset Categories<select name="asset_categories" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['12 Categories', '20 Categories', 'Custom Categories'] as $value)<option value="{{ $value }}" @selected(old('asset_categories', $settings['asset_categories']) === $value)>{{ $value }}</option>@endforeach</select></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">Stock Threshold Alert<select name="stock_threshold_alert" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['10 Items', '20 Items', 'Custom Threshold'] as $value)<option value="{{ $value }}" @selected(old('stock_threshold_alert', $settings['stock_threshold_alert']) === $value)>{{ $value }}</option>@endforeach</select></label>
                            </div>
                        </section>

                        <section id="bookstore" class="rounded-lg border border-slate-200 p-4">
                            <div class="mb-4 flex items-center justify-between gap-3"><h3 class="flex items-center gap-2 text-sm font-semibold text-slate-950"><i data-lucide="book-open" class="size-4 text-violet-600"></i> Book Store Settings</h3><button type="button" @click="edit('#bookstore')" class="text-xs font-medium text-violet-600">Edit</button></div>
                            <div class="grid gap-3">
                                <label class="flex items-center justify-between gap-3 text-xs font-medium text-slate-500">Low Stock Alerts<input name="low_stock_alerts" type="checkbox" value="1" @checked(old('low_stock_alerts', $settings['low_stock_alerts'])) class="rounded border-slate-300 text-violet-600"></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">SKU Format<input name="sku_format" value="{{ old('sku_format', $settings['sku_format']) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900"></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">Order Approval Workflow<select name="order_approval_workflow" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['Manager Approval', 'Finance Approval', 'No Approval'] as $value)<option value="{{ $value }}" @selected(old('order_approval_workflow', $settings['order_approval_workflow']) === $value)>{{ $value }}</option>@endforeach</select></label>
                                <label class="space-y-1 text-xs font-medium text-slate-500">Payment Methods<input name="payment_methods" value="{{ old('payment_methods', $settings['payment_methods']) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900"></label>
                            </div>
                        </section>
                    </div>

                    <div x-show="more" x-cloak class="grid gap-4 lg:grid-cols-3">
                        <section id="users" class="rounded-lg border border-slate-200 p-4"><h3 class="mb-3 text-sm font-semibold text-slate-950">Users & Access</h3><p class="text-sm text-slate-500">{{ number_format($roles->sum(fn ($role) => $role->users()->count())) }} role assignments across {{ number_format($roles->count()) }} roles.</p><a href="{{ route('users.index') }}" class="mt-3 inline-flex text-sm font-medium text-violet-600">Open User Management</a></section>
                        <section id="security" class="rounded-lg border border-slate-200 p-4"><h3 class="mb-3 text-sm font-semibold text-slate-950">Security</h3><p class="text-sm text-slate-500">Security score is calculated from MFA, password, login notification, IP, and device trust settings.</p><a href="{{ route('audit-logs.index', ['tab' => 'authentication']) }}" class="mt-3 inline-flex text-sm font-medium text-violet-600">Review Authentication Events</a></section>
                        <section id="notifications" class="rounded-lg border border-slate-200 p-4"><h3 class="mb-3 text-sm font-semibold text-slate-950">Notifications</h3><p class="text-sm text-slate-500">Notification preferences are saved with communication defaults and applied to future modules.</p><a href="{{ route('communications.index') }}" class="mt-3 inline-flex text-sm font-medium text-violet-600">Open Communications</a></section>
                        <section id="workflow" class="rounded-lg border border-slate-200 p-4"><h3 class="mb-3 text-sm font-semibold text-slate-950">Workflow & Approvals</h3><p class="text-sm text-slate-500">Default workflow: {{ $settings['approval_requirements'] }}.</p><a href="{{ route('workflows.index') }}" class="mt-3 inline-flex text-sm font-medium text-violet-600">Open Workflows</a></section>
                        <section id="integrations" class="rounded-lg border border-slate-200 p-4"><h3 class="mb-3 text-sm font-semibold text-slate-950">Integrations & API</h3><p class="text-sm text-slate-500">Connected providers: {{ collect([$settings['smtp_server'], $settings['sms_provider'], $settings['whatsapp_integration']])->filter()->count() }}.</p><button form="test-storage-form" class="mt-3 text-sm font-medium text-violet-600">Test Storage</button></section>
                        <section id="backup" class="rounded-lg border border-slate-200 p-4"><h3 class="mb-3 text-sm font-semibold text-slate-950">Backup & Recovery</h3><label class="space-y-1 text-xs font-medium text-slate-500">Backup Frequency<select name="backup_frequency" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">@foreach (['Every 6 hours', 'Daily', 'Weekly'] as $value)<option value="{{ $value }}" @selected(old('backup_frequency', $settings['backup_frequency']) === $value)>{{ $value }}</option>@endforeach</select></label><label class="mt-3 block space-y-1 text-xs font-medium text-slate-500">Backup Retention<input name="backup_retention" value="{{ old('backup_retention', $settings['backup_retention']) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900"></label></section>
                        <section id="audit" class="rounded-lg border border-slate-200 p-4"><h3 class="mb-3 text-sm font-semibold text-slate-950">Audit & Compliance</h3><label class="space-y-1 text-xs font-medium text-slate-500">Audit Retention<input name="audit_retention" value="{{ old('audit_retention', $settings['audit_retention']) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900"></label><a href="{{ route('audit-logs.index') }}" class="mt-3 inline-flex text-sm font-medium text-violet-600">Open Audit Logs</a></section>
                        <section id="localization" class="rounded-lg border border-slate-200 p-4"><h3 class="mb-3 text-sm font-semibold text-slate-950">Localization</h3><label class="space-y-1 text-xs font-medium text-slate-500">Region<input name="localization_region" value="{{ old('localization_region', $settings['localization_region']) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900"></label></section>
                        <section id="advanced" class="rounded-lg border border-slate-200 p-4"><h3 class="mb-3 text-sm font-semibold text-slate-950">Advanced / System</h3><p class="text-sm text-slate-500">Environment: {{ app()->environment() }}. PHP {{ PHP_VERSION }}.</p><button form="test-backup-form" class="mt-3 text-sm font-medium text-violet-600">Test Backup</button></section>
                    </div>

                    <button type="button" @click="more = ! more" class="mx-auto flex items-center gap-2 px-4 py-1 text-sm font-medium text-violet-600">
                        <span x-text="more ? 'Hide More Settings Sections' : 'Show More Settings Sections'">Show More Settings Sections</span>
                        <i data-lucide="chevron-up" class="size-4" x-bind:class="more ? '' : 'rotate-180'"></i>
                    </button>
                </form>
            </main>

            <aside class="space-y-4">
                <section class="dashboard-card">
                    <div class="mb-4 flex items-center justify-between gap-3"><h2 class="text-base font-semibold text-slate-950">System Health</h2><span class="text-xs font-medium text-emerald-600">All Systems Operational</span></div>
                    <div class="space-y-3">
                        @foreach ($health as $item)
                            <div class="flex items-center justify-between gap-3 text-sm"><span class="flex items-center gap-2 text-slate-600"><span class="size-2 rounded-full {{ $item['status'] === 'healthy' ? 'bg-emerald-500' : 'bg-orange-500' }}"></span>{{ $item['label'] }}</span><span class="font-medium {{ $item['status'] === 'healthy' ? 'text-emerald-600' : 'text-orange-600' }}">{{ $item['value'] }}</span></div>
                        @endforeach
                    </div>
                    <a href="#advanced" @click.prevent="openSection('advanced')" class="mt-4 inline-flex text-sm font-medium text-violet-600">View System Status</a>
                </section>

                <section class="dashboard-card">
                    <h2 class="mb-4 text-base font-semibold text-slate-950">Compliance & Security</h2>
                    <div class="space-y-3">
                        @foreach ($compliance as $item)
                            <div class="flex items-center justify-between gap-3 text-sm"><span class="text-slate-600">{{ $item['label'] }}</span><span class="font-medium {{ $item['status'] === 'healthy' ? 'text-emerald-600' : 'text-orange-600' }}">{{ $item['value'] }}</span></div>
                        @endforeach
                    </div>
                    <a href="{{ route('audit-logs.index', ['tab' => 'policies']) }}" class="mt-4 inline-flex text-sm font-medium text-violet-600">View Compliance Report</a>
                </section>

                <section class="dashboard-card">
                    <h2 class="mb-4 text-base font-semibold text-slate-950">System Information</h2>
                    <div class="space-y-3">
                        @foreach ($systemInfo as $item)
                            <div class="flex items-center justify-between gap-3 text-sm"><span class="text-slate-600">{{ $item['label'] }}</span><span class="text-right font-medium text-slate-700">{{ $item['value'] }}</span></div>
                        @endforeach
                    </div>
                    <a href="#general" @click.prevent="openSection('general')" class="mt-4 inline-flex text-sm font-medium text-violet-600">View System Details</a>
                </section>

                <section class="dashboard-card">
                    <div class="mb-4 flex items-center justify-between gap-3"><h2 class="text-base font-semibold text-slate-950">Recent Configuration Activity</h2><a href="{{ route('audit-logs.index', ['keyword' => 'Settings']) }}" class="text-xs font-medium text-violet-600">View All</a></div>
                    <div class="space-y-4">
                        @forelse ($recentActivity as $log)
                            <div class="flex gap-3 text-sm">
                                <span class="mt-1 size-2 rounded-full bg-violet-500"></span>
                                <div class="min-w-0">
                                    <div class="truncate font-medium text-slate-900">{{ $log->description }}</div>
                                    <div class="text-xs text-slate-500">{{ $log->user?->name ?? 'System' }} • {{ $log->created_at->format('M d, Y h:i A') }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-lg border border-slate-100 bg-slate-50 p-3 text-sm text-slate-500">No configuration activity yet.</div>
                        @endforelse
                    </div>
                </section>
            </aside>
        </div>

        @foreach (['smtp', 'sms', 'whatsapp', 'backup', 'storage'] as $service)
            <form id="test-{{ $service }}-form" method="POST" action="{{ route('settings.system.test-connection') }}" class="hidden">
                @csrf
                <input type="hidden" name="service" value="{{ $service }}">
            </form>
        @endforeach
    </div>
</x-app-layout>
