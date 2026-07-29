@php
    $branding = \App\Support\Branding::current();
    $systemName = $branding->systemName();
    $churchName = $branding->churchName();
    $subtitle = $branding->subtitle();
    $sidebarImage = $branding->sidebarBackground() ?? asset('images/sidebar-church.png');
    $logoUrl = $branding->logo();
    $settings = $branding->settings;
    $faviconUrl = $branding->assetPath(data_get($settings, 'favicon'));
    $fontStacks = [
        'Inter' => 'Inter, ui-sans-serif, system-ui, sans-serif',
        'Roboto' => 'Roboto, ui-sans-serif, system-ui, sans-serif',
        'Lato' => 'Lato, ui-sans-serif, system-ui, sans-serif',
        'Nunito Sans' => '"Nunito Sans", ui-sans-serif, system-ui, sans-serif',
        'System UI' => 'ui-sans-serif, system-ui, sans-serif',
    ];
    $fontSizes = [
        'compact' => '0.8125rem',
        'default' => '0.875rem',
        'comfortable' => '0.9375rem',
    ];
    $requestedThemeMode = $settings['theme_mode'] ?? 'light';
    $themeMode = in_array($requestedThemeMode, ['light', 'dark', 'system'], true) ? $requestedThemeMode : 'light';
    $cssVariables = [
        '--brand-primary' => $settings['primary_color'] ?? '#6C4DFF',
        '--brand-secondary' => $settings['secondary_color'] ?? '#A855F7',
        '--page-bg' => $settings['page_background'] ?? '#F6F8FC',
        '--card-radius' => ((int) ($settings['card_radius'] ?? 8)).'px',
        '--font-app' => $fontStacks[$settings['font_family'] ?? 'Inter'] ?? $fontStacks['Inter'],
        '--app-font-size' => $fontSizes[$settings['font_scale'] ?? 'default'] ?? $fontSizes['default'],
        '--sidebar-start' => $settings['sidebar_start_color'] ?? '#061633',
        '--sidebar-mid' => $settings['sidebar_middle_color'] ?? '#082851',
        '--sidebar-end' => $settings['sidebar_end_color'] ?? '#061633',
        '--sidebar-text' => $settings['sidebar_text_color'] ?? '#E2E8F0',
        '--sidebar-profile-bg' => $settings['sidebar_profile_color'] ?? '#020617',
    ];
    $cssStyle = collect($cssVariables)->map(fn ($value, $key): string => $key.': '.e($value))->implode('; ');
    $socialProviders = collect($socialProviders ?? []);
    $microsoftProvider = $socialProviders->firstWhere('key', 'microsoft');
    $secondaryProviders = $socialProviders->reject(fn (array $provider): bool => ($provider['key'] ?? null) === 'microsoft')->values();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $themeMode }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Login - {{ $systemName }}</title>
        @if ($faviconUrl)
            <link rel="icon" href="{{ $faviconUrl }}">
            <link rel="shortcut icon" href="{{ $faviconUrl }}">
        @elseif ($logoUrl)
            <link rel="icon" href="{{ $logoUrl }}">
        @endif
        @if ($logoUrl)
            <link rel="apple-touch-icon" href="{{ $logoUrl }}">
        @endif
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900|lato:400,700|nunito-sans:400,500,600,700|roboto:400,500,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            .login-page {
                background: var(--page-bg);
                font-family: var(--font-app);
                font-size: var(--app-font-size);
            }

            .login-sidebar {
                background:
                    radial-gradient(circle at 72% 48%, color-mix(in srgb, var(--brand-secondary) 30%, transparent), transparent 28%),
                    linear-gradient(145deg, var(--sidebar-start) 0%, var(--sidebar-mid) 48%, var(--sidebar-end) 100%);
                color: var(--sidebar-text);
            }

            .login-sidebar .text-white {
                color: var(--sidebar-text) !important;
            }

            .login-sidebar .text-white\/70,
            .login-sidebar .text-white\/78,
            .login-sidebar .text-white\/68 {
                color: color-mix(in srgb, var(--sidebar-text) 72%, transparent) !important;
            }

            .login-main-panel {
                background: linear-gradient(
                    135deg,
                    color-mix(in srgb, var(--page-bg) 38%, white) 0%,
                    var(--page-bg) 58%,
                    color-mix(in srgb, var(--page-bg) 88%, #dbe5f4) 100%
                );
            }

            .login-accent-text {
                color: var(--brand-secondary) !important;
            }

            .login-accent-line {
                background: var(--brand-secondary);
            }

            .login-display {
                font-family: Georgia, "Times New Roman", serif;
            }

            .login-network-ring {
                border-color: color-mix(in srgb, var(--brand-primary) 42%, transparent);
                box-shadow: 0 0 65px color-mix(in srgb, var(--brand-primary) 32%, transparent);
            }

            .login-network-connector {
                stroke: color-mix(in srgb, var(--brand-secondary) 70%, #9ec5ff);
                stroke-dasharray: 3 5;
                stroke-linecap: round;
                stroke-width: 1.5;
            }

            .login-network-core {
                border-color: var(--brand-secondary);
                background: color-mix(in srgb, var(--brand-primary) 72%, transparent);
                box-shadow: 0 0 36px color-mix(in srgb, var(--brand-secondary) 80%, transparent);
            }

            .login-network-node {
                border-color: color-mix(in srgb, var(--brand-secondary) 52%, transparent);
                background: color-mix(in srgb, var(--sidebar-profile-bg) 84%, transparent);
                box-shadow: 0 0 28px color-mix(in srgb, var(--brand-primary) 32%, transparent);
            }

            .login-field:focus-within {
                border-color: var(--brand-primary);
                box-shadow: 0 0 0 4px color-mix(in srgb, var(--brand-primary) 14%, transparent);
            }

            .login-submit {
                background: linear-gradient(90deg, var(--brand-primary), var(--brand-secondary));
                box-shadow: 0 10px 24px color-mix(in srgb, var(--brand-primary) 22%, transparent);
            }

            .login-submit:hover {
                filter: brightness(.94);
            }

            html[data-theme="dark"] .login-main-panel {
                background: linear-gradient(135deg, #111827 0%, var(--page-bg) 56%, #0b1220 100%);
            }

            @media (prefers-color-scheme: dark) {
                html[data-theme="system"] .login-main-panel {
                    background: linear-gradient(135deg, #111827 0%, var(--page-bg) 56%, #0b1220 100%);
                }
            }
        </style>
    </head>
    <body class="login-page min-h-screen text-slate-900 antialiased" style="{{ $cssStyle }}">
        <main class="min-h-screen xl:grid xl:grid-cols-2">
            <aside class="login-sidebar relative hidden min-h-screen overflow-hidden xl:flex">
                <div class="absolute inset-x-0 bottom-0 h-[28%] bg-church-silhouette opacity-70" style="--sidebar-background-image: url('{{ $sidebarImage }}')"></div>
                <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(3,10,28,0.12)_0%,rgba(3,10,28,0.46)_58%,rgba(3,10,28,0.86)_100%)]"></div>
                <div class="pointer-events-none absolute inset-0 opacity-35" style="background-image: radial-gradient(circle at 68% 50%, rgba(139, 92, 246, .28) 0 1px, transparent 2px), linear-gradient(120deg, transparent 0 45%, rgba(125, 92, 255, .28) 46%, transparent 47%); background-size: 52px 52px, 100% 100%;"></div>

                <div class="login-sidebar-copy relative z-10 flex min-h-screen w-full flex-col justify-between p-10 2xl:p-14">
                    <div>
                        <div class="flex items-center gap-4">
                            <div class="grid size-16 place-items-center overflow-hidden rounded-xl bg-transparent">
                                @if ($logoUrl)
                                    <img src="{{ $logoUrl }}" alt="{{ $churchName }} logo" class="size-full object-contain">
                                @else
                                    <i data-lucide="shield-check" class="size-12 text-white"></i>
                                @endif
                            </div>
                            <div>
                                <div class="text-4xl font-black leading-none tracking-normal text-white">{{ $systemName }}</div>
                                <div class="mt-2 text-base text-white/70">{{ $subtitle }}</div>
                            </div>
                        </div>

                        <div class="mt-14 max-w-[500px] 2xl:mt-16">
                            <h1 class="login-display text-[38px] font-black leading-[1.12] text-white 2xl:text-[40px]">
                                All your ministry.
                                <span class="login-accent-text block italic">One unified platform.</span>
                            </h1>
                            <div class="login-accent-line mt-6 h-1 w-16 rounded-full"></div>
                            <p class="mt-6 max-w-[300px] text-lg leading-7 text-white/78">Manage members, services, reports, communications, and leadership workflows in one secure platform.</p>
                        </div>

                        <div class="mt-10 grid max-w-[300px] gap-5">
                            @foreach ([
                                ['icon' => 'landmark', 'title' => 'Multi-campus operations', 'copy' => 'Oversee all campuses and ministries from a single, unified system.', 'tone' => 'text-violet-300'],
                                ['icon' => 'bar-chart-3', 'title' => 'Attendance & reporting', 'copy' => 'Track attendance, engagement and generate insightful reports.', 'tone' => 'text-blue-300'],
                                ['icon' => 'shield-check', 'title' => 'Secure approvals', 'copy' => 'Role-based workflows and approvals keep every process accountable.', 'tone' => 'text-teal-300'],
                                ['icon' => 'users', 'title' => 'Pastor & leadership insights', 'copy' => 'Real-time dashboards to guide decisions and drive impact.', 'tone' => 'text-amber-300'],
                            ] as $item)
                                <div class="grid grid-cols-[48px_minmax(0,1fr)] gap-4">
                                    <span class="grid size-12 place-items-center rounded-xl border border-white/15 bg-white/10 {{ $item['tone'] }}">
                                        <i data-lucide="{{ $item['icon'] }}" class="size-6"></i>
                                    </span>
                                    <div>
                                        <h2 class="text-sm font-bold text-white">{{ $item['title'] }}</h2>
                                        <p class="mt-1 text-xs leading-5 text-white/68">{{ $item['copy'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="pointer-events-none absolute right-[18px] top-[29%] hidden size-[448px] 2xl:block" aria-hidden="true">
                    <svg class="absolute inset-0 size-full" viewBox="0 0 448 448" fill="none">
                        <line class="login-network-connector" x1="224" y1="224" x2="224" y2="46"></line>
                        <line class="login-network-connector" x1="224" y1="224" x2="402" y2="224"></line>
                        <line class="login-network-connector" x1="224" y1="224" x2="367" y2="402"></line>
                        <line class="login-network-connector" x1="224" y1="224" x2="224" y2="402"></line>
                        <line class="login-network-connector" x1="224" y1="224" x2="46" y2="313"></line>
                        <line class="login-network-connector" x1="224" y1="224" x2="46" y2="224"></line>
                    </svg>
                    <div class="login-network-ring absolute inset-[4.75rem] rounded-full border"></div>
                    <div class="absolute inset-[7.25rem] rounded-full border border-blue-300/20"></div>
                    <div class="login-network-core absolute left-1/2 top-1/2 grid size-28 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full border-2">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="" class="size-14 object-contain brightness-0 invert">
                        @else
                            <i data-lucide="shield-check" class="size-14 text-white"></i>
                        @endif
                    </div>
                    @foreach ([
                        ['icon' => 'users', 'label' => 'Members', 'position' => 'left-1/2 top-0 -translate-x-1/2'],
                        ['icon' => 'calendar-check', 'label' => 'Attendance', 'position' => 'right-0 top-1/2 -translate-y-1/2'],
                        ['icon' => 'message-square', 'label' => 'Communications', 'position' => 'bottom-0 right-[8%]'],
                        ['icon' => 'bar-chart-3', 'label' => 'Reports', 'position' => 'bottom-0 left-1/2 -translate-x-1/2'],
                        ['icon' => 'heart-handshake', 'label' => 'Giving', 'position' => 'bottom-[20%] left-0'],
                        ['icon' => 'user-round-check', 'label' => 'Leadership', 'position' => 'left-0 top-1/2 -translate-y-1/2'],
                    ] as $node)
                        <div class="login-network-node absolute {{ $node['position'] }} flex h-[76px] w-[88px] flex-col items-center justify-center gap-2 rounded-2xl border backdrop-blur-sm">
                            <i data-lucide="{{ $node['icon'] }}" class="login-accent-text size-6 shrink-0"></i>
                            <span class="max-w-full px-1 text-center text-[10px] font-bold leading-none text-white">{{ $node['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </aside>

            <section class="relative min-h-screen overflow-hidden px-4 py-6 sm:px-8 xl:px-10">
                <div class="login-main-panel absolute inset-0"></div>

                <header class="relative z-10 mx-auto flex w-full max-w-[744px] items-center justify-between py-2">
                    <div class="flex items-center gap-3 xl:hidden">
                        <div class="grid size-11 place-items-center overflow-hidden rounded-lg bg-transparent">
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="{{ $churchName }} logo" class="size-full object-contain">
                            @else
                                <i data-lucide="shield-check" class="size-8 text-violet-600"></i>
                            @endif
                        </div>
                        <div>
                            <div class="text-lg font-black text-slate-950">{{ $systemName }}</div>
                            <div class="text-xs text-slate-500">{{ $subtitle }}</div>
                        </div>
                    </div>
                    <div class="ml-auto flex items-center gap-5 text-slate-900">
                        <button type="button" class="grid size-9 place-items-center rounded-full hover:bg-slate-100" title="Theme">
                            <i data-lucide="moon" class="size-5"></i>
                        </button>
                        <button type="button" class="grid size-9 place-items-center rounded-full hover:bg-slate-100" title="Help">
                            <i data-lucide="circle-help" class="size-5"></i>
                        </button>
                        <span class="hidden h-7 w-px bg-slate-200 sm:block"></span>
                        <button type="button" class="inline-flex items-center gap-2 text-sm font-bold">
                            EN
                            <i data-lucide="chevron-down" class="size-4"></i>
                        </button>
                    </div>
                </header>

                <div class="relative z-10 mx-auto grid min-h-[calc(100vh-6.5rem)] w-full max-w-[744px] items-center gap-[24px] py-8 lg:grid-cols-[470px_250px]">
                    <section x-data="{ loading: false, showPassword: false }" class="relative overflow-hidden rounded-lg border border-slate-200 bg-white p-7 shadow-[0_24px_80px_rgba(15,23,42,0.12)] sm:p-[32px] lg:self-start lg:mt-[38px]">
                        <div x-cloak x-show="loading" x-transition.opacity class="absolute inset-0 z-20 grid place-items-center bg-white/95 p-8 text-center backdrop-blur">
                            <div>
                                <div class="mx-auto grid size-14 place-items-center rounded-full bg-violet-50 text-violet-600 ring-1 ring-violet-100">
                                    <i data-lucide="loader-circle" class="size-7 animate-spin"></i>
                                </div>
                                <h2 class="mt-5 text-lg font-semibold text-slate-950">Please wait...</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-500">We are checking your account.</p>
                            </div>
                        </div>

                        <div class="mx-auto w-full">
                            <h1 class="login-display text-center text-[2.45rem] font-black leading-tight text-slate-950">Welcome back</h1>
                            <p class="mt-1 text-center text-sm text-slate-500">Sign in to continue to <span class="font-semibold text-violet-600">{{ $systemName }}</span></p>

                            @if(session('status'))
                                <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
                            @endif

                            <form method="POST" action="{{ route('login.store') }}" class="mt-7 space-y-4" @submit="loading = true">
                                @csrf

                                <label class="block">
                                    <span class="text-sm font-bold text-slate-800">Email Address</span>
                                    <span class="login-field mt-2 grid h-12 grid-cols-[40px_minmax(0,1fr)] items-center rounded-lg border border-slate-300 bg-white px-3">
                                        <i data-lucide="mail" class="size-5 text-slate-400"></i>
                                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="you@example.com" class="h-full border-0 bg-transparent px-1 text-sm font-medium outline-none placeholder:text-slate-400 focus:ring-0">
                                    </span>
                                    @error('email')
                                        <span class="mt-2 block text-sm text-rose-600">{{ $message }}</span>
                                    @enderror
                                </label>

                                <label class="block">
                                    <span class="text-sm font-bold text-slate-800">Password</span>
                                    <span class="login-field mt-2 grid h-12 grid-cols-[40px_minmax(0,1fr)_36px] items-center rounded-lg border border-slate-300 bg-white px-3">
                                        <i data-lucide="lock-keyhole" class="size-5 text-slate-400"></i>
                                        <input id="password" name="password" :type="showPassword ? 'text' : 'password'" required autocomplete="current-password" placeholder="Enter your password" class="h-full border-0 bg-transparent px-1 text-sm font-medium outline-none placeholder:text-slate-400 focus:ring-0">
                                        <button type="button" @click="showPassword = ! showPassword" class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-50 hover:text-violet-600" aria-label="Toggle password visibility">
                                            <i x-show="! showPassword" data-lucide="eye-off" class="size-4"></i>
                                            <i x-cloak x-show="showPassword" data-lucide="eye" class="size-4"></i>
                                        </button>
                                    </span>
                                    @error('password')
                                        <span class="mt-2 block text-sm text-rose-600">{{ $message }}</span>
                                    @enderror
                                </label>

                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-600">
                                        <input type="checkbox" name="remember" class="rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                                        Remember me
                                    </label>
                                    <a href="{{ route('password.request') }}" class="text-sm font-semibold text-violet-600 hover:text-violet-700">Forgot password?</a>
                                </div>

                                <button class="login-submit flex h-12 w-full items-center justify-center gap-2 rounded-lg px-4 text-base font-bold text-white focus-visible:ring-2 focus-visible:ring-violet-300">
                                    <i data-lucide="lock" class="size-4"></i>
                                    Sign In
                                </button>

                                @if($microsoftProvider)
                                    <a href="{{ route('social.redirect', $microsoftProvider['key']) }}" class="inline-flex h-12 w-full items-center justify-center gap-3 rounded-lg border border-slate-300 bg-white px-4 text-base font-bold text-slate-800 hover:border-slate-400 hover:bg-slate-50">
                                        @if(filled($microsoftProvider['logo'] ?? null))
                                            <img src="{{ asset($microsoftProvider['logo']) }}" alt="" class="size-5 shrink-0">
                                        @else
                                            <i data-lucide="{{ $microsoftProvider['icon'] }}" class="size-5" style="color: {{ $microsoftProvider['color'] }}"></i>
                                        @endif
                                        Sign in with {{ $microsoftProvider['label'] }}
                                    </a>
                                @endif

                                @if($secondaryProviders->isNotEmpty())
                                    <div class="grid grid-cols-[minmax(1.5rem,1fr)_auto_minmax(1.5rem,1fr)] items-center gap-3">
                                        <span class="h-px bg-slate-200"></span>
                                        <span class="whitespace-nowrap text-center text-sm font-medium text-slate-400">or continue with</span>
                                        <span class="h-px bg-slate-200"></span>
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2">
                                        @foreach($secondaryProviders as $provider)
                                            <a href="{{ route('social.redirect', $provider['key']) }}" class="inline-flex h-12 items-center justify-center gap-3 rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-700 hover:border-slate-300 hover:bg-slate-50">
                                                @if(filled($provider['logo'] ?? null))
                                                    <img src="{{ asset($provider['logo']) }}" alt="" class="size-5 shrink-0">
                                                @else
                                                    <i data-lucide="{{ $provider['icon'] }}" class="size-5" style="color: {{ $provider['color'] }}"></i>
                                                @endif
                                                {{ $provider['label'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="grid grid-cols-[40px_minmax(0,1fr)] gap-3 rounded-lg bg-slate-100 px-4 py-3 text-sm text-slate-500">
                                    <span class="grid size-9 place-items-center rounded-lg bg-slate-200 text-slate-600">
                                        <i data-lucide="shield-check" class="size-5"></i>
                                    </span>
                                    <p class="leading-6">For your security, this system uses 2-factor authentication. You may be prompted for a verification code after sign in.</p>
                                </div>
                            </form>
                        </div>
                    </section>

                    <aside class="hidden min-h-[492px] rounded-lg border border-slate-200 bg-white p-[20px] shadow-[0_20px_60px_rgba(15,23,42,0.10)] lg:flex lg:translate-y-[32px] lg:flex-col">
                        <h2 class="login-display text-xl font-black text-slate-950">System status</h2>
                        <div class="mt-4 flex items-center gap-2 text-sm font-semibold text-emerald-600">
                            <span class="size-2 rounded-full bg-emerald-500"></span>
                            All systems operational
                        </div>
                        <div class="mt-6 flex flex-1 flex-col justify-between border-t border-slate-200 pt-5">
                            @foreach ([
                                ['icon' => 'shield-check', 'title' => 'Secure Access', 'copy' => 'Bank-level encryption and multi-factor authentication.', 'tone' => 'bg-violet-50 text-violet-600'],
                                ['icon' => 'users-round', 'title' => 'Role-based permissions', 'copy' => 'Access is tailored to your role and ministry responsibilities.', 'tone' => 'bg-blue-50 text-blue-600'],
                                ['icon' => 'clipboard-list', 'title' => 'Audit logging enabled', 'copy' => 'All activities are recorded for transparency and compliance.', 'tone' => 'bg-teal-50 text-teal-600'],
                                ['icon' => 'cloud-check', 'title' => '24/7 cloud availability', 'copy' => 'Reliable, scalable, and built for ministry, always on.', 'tone' => 'bg-amber-50 text-amber-600'],
                            ] as $item)
                                <div class="grid grid-cols-[48px_minmax(0,1fr)] gap-3 border-b border-slate-100 pb-4 last:border-b-0 last:pb-0">
                                    <span class="grid size-12 place-items-center rounded-lg {{ $item['tone'] }}">
                                        <i data-lucide="{{ $item['icon'] }}" class="size-6"></i>
                                    </span>
                                    <div>
                                        <h3 class="text-sm font-bold text-slate-900">{{ $item['title'] }}</h3>
                                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ $item['copy'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </aside>

                    <div class="text-center text-sm font-medium text-slate-500 lg:col-span-2">
                        <span class="inline-flex items-center gap-2"><i data-lucide="shield-check" class="size-5 text-slate-700"></i>Protected by enterprise-grade security</span>
                        <span class="mt-1 block text-xs">Your data is safe with us.</span>
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>
