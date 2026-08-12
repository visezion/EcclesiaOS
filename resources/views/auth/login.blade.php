@php
    $branding = \App\Support\Branding::current();
    $systemName = $branding->systemName();
    $churchName = $branding->churchName();
    $subtitle = $branding->subtitle();
    $logoUrl = $branding->logo();
    $faviconUrl = $branding->assetPath(data_get($branding->settings, 'favicon'));
    $settings = $branding->settings;
    $sidebarBackgroundUrl = $branding->sidebarBackground();
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
        '--brand-primary' => $settings['primary_color'] ?? '#1D4ED8',
        '--brand-secondary' => $settings['secondary_color'] ?? '#7C3AED',
        '--page-bg' => $settings['page_background'] ?? '#F5F7FB',
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
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $themeMode }}" style="font-size: {{ $branding->interfaceZoom() }}%;">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Login - {{ $systemName }}</title>
        @if ($faviconUrl)
            <link rel="icon" href="{{ $faviconUrl }}">
            <link rel="shortcut icon" href="{{ $faviconUrl }}">
        @endif
        @if ($logoUrl)
            <link rel="apple-touch-icon" href="{{ $logoUrl }}">
        @endif
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            body {
                background:
                    radial-gradient(circle at top left, color-mix(in srgb, var(--brand-primary) 14%, transparent), transparent 26%),
                    radial-gradient(circle at bottom right, color-mix(in srgb, var(--brand-secondary) 12%, transparent), transparent 32%),
                    var(--page-bg);
                font-family: var(--font-app);
                font-size: var(--app-font-size);
            }
            .login-card {
                box-shadow: 0 24px 80px rgba(15, 23, 42, 0.12);
            }
            .login-shell {
                box-shadow: 0 30px 90px rgba(15, 23, 42, 0.12);
            }
            .login-grid {
                grid-template-columns: 1fr;
            }
            @media (min-width: 1280px) {
                .login-grid {
                    grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
                    min-height: 680px;
                }
            }
            .brand-bar {
                background: linear-gradient(90deg, var(--brand-primary), var(--brand-secondary));
            }
            .focus-ring:focus {
                outline: none;
                border-color: var(--brand-primary);
                box-shadow: 0 0 0 4px color-mix(in srgb, var(--brand-primary) 16%, transparent);
            }
        </style>
    </head>
    <body class="min-h-screen text-slate-900 antialiased" style="{{ $cssStyle }}">
        <main class="mx-auto flex min-h-screen w-full max-w-6xl flex-col items-center justify-center gap-8 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
            <div class="login-shell login-grid grid w-full max-w-5xl overflow-hidden rounded-[28px] border border-slate-200 bg-white/90 backdrop-blur">
                <section
                    class="relative hidden overflow-hidden bg-slate-950 p-10 text-white xl:flex xl:flex-col xl:justify-between xl:p-12"
                    @if ($sidebarBackgroundUrl)
                        style="background-image: url('{{ $sidebarBackgroundUrl }}'); background-position: center; background-size: cover;"
                    @endif
                >
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(124,58,237,.24),transparent_26%),linear-gradient(145deg,#0f172a_0%,#13233f_48%,#06101f_100%)]"></div>
                    <div class="relative z-10">
                        <div class="flex items-center gap-4">
                            <div class="grid size-14 place-items-center rounded-2xl bg-white/10 ring-1 ring-white/10">
                                @if ($logoUrl)
                                    <img src="{{ $logoUrl }}" alt="{{ $churchName }} logo" class="size-full rounded-2xl object-contain">
                                @else
                                    <i data-lucide="shield-check" class="size-8"></i>
                                @endif
                            </div>
                            <div class="space-y-1.5">
                                <div class="text-2xl font-black leading-tight tracking-tight">{{ $systemName }}</div>
                                <div class="text-sm leading-5 text-white/70">{{ $subtitle }}</div>
                            </div>
                        </div>

                        <div class="mt-14 max-w-lg">
                            <div class="mb-6 mt-16 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white/75"><i data-lucide="shield-check" class="size-3.5 text-emerald-300"></i>Secure church management</div>
                            <h1 class="text-4xl font-black leading-[1.05] text-white 2xl:text-5xl">A simple login for your team.</h1>
                            <p class="mt-5 max-w-md text-base leading-7 text-white/75">Sign in to manage members, communications, services, reports, and church operations from one place.</p>
                            <p class="mt-5 max-w-md text-sm leading-6 text-white/55">{{ config('church.product_vision') }}</p>
                        </div>
                    </div>

                    <div class="relative z-10 grid gap-3 text-sm text-white/72">
                        <div class="flex items-center gap-2">
                            <span class="size-2 rounded-full bg-emerald-400"></span>
                            Branded login
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="size-2 rounded-full bg-sky-400"></span>
                            Role-based access
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="size-2 rounded-full bg-violet-400"></span>
                            Multi-factor ready
                        </div>
                    </div>
                </section>

                <section class="flex items-center justify-center p-5 sm:p-10 lg:p-12">
                    <div class="login-card w-full max-w-[390px] rounded-[24px] border border-slate-200 bg-white p-6 sm:p-8">
                        <div class="flex items-center gap-3 xl:hidden">
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="{{ $churchName }} logo" class="size-12 rounded-xl object-contain">
                            @else
                                <div class="grid size-12 place-items-center rounded-xl bg-slate-950 text-white">
                                    <i data-lucide="shield-check" class="size-7"></i>
                                </div>
                            @endif
                            <div class="space-y-1">
                                <div class="text-xl font-black leading-tight text-slate-950">{{ $systemName }}</div>
                                <div class="text-sm leading-5 text-slate-500">{{ $subtitle }}</div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <div class="brand-bar h-1.5 w-16 rounded-full"></div>
                            <h2 class="mt-5 text-3xl font-black tracking-tight text-slate-950">Sign in</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-500">Use your member, staff, or administrator account to continue.</p>
                        </div>

                        @if(session('status'))
                            <div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                                {{ session('status') }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
                            @csrf

                            <label class="block">
                                <span class="text-sm font-semibold text-slate-700">Email</span>
                                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="admin@example.org" class="focus-ring mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400">
                                @error('email')
                                    <span class="mt-2 block text-sm text-rose-600">{{ $message }}</span>
                                @enderror
                            </label>

                            <label class="block">
                                <span class="text-sm font-semibold text-slate-700">Password</span>
                                <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Your password" class="focus-ring mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400">
                                @error('password')
                                    <span class="mt-2 block text-sm text-rose-600">{{ $message }}</span>
                                @enderror
                            </label>

                            <div class="flex items-center justify-between gap-3 text-sm">
                                <label class="inline-flex items-center gap-2 text-slate-600">
                                    <input type="checkbox" name="remember" class="rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                                    Remember me
                                </label>
                                <a href="{{ route('password.request') }}" class="font-semibold text-violet-700 hover:text-violet-800">Forgot password?</a>
                            </div>

                            <button class="inline-flex h-12 w-full items-center justify-center rounded-2xl bg-slate-950 px-4 text-sm font-bold text-white transition hover:bg-slate-800">
                                Sign in
                            </button>
                        </form>

                        <div class="mt-5 rounded-2xl border border-violet-100 bg-violet-50/70 px-4 py-3 text-center text-sm text-slate-600">
                            New or returning member?
                            <a href="{{ route('members.self-register') }}" class="font-bold text-violet-700 hover:text-violet-800">
                                Register or check in
                            </a>
                        </div>

                        @if ($microsoftProvider || $secondaryProviders->isNotEmpty())
                            <div class="my-6 flex items-center gap-3 text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">
                                <span class="h-px flex-1 bg-slate-200"></span>
                                <span>Or continue with</span>
                                <span class="h-px flex-1 bg-slate-200"></span>
                            </div>
                        @endif

                        @if ($microsoftProvider)
                            <a href="{{ route('social.redirect', $microsoftProvider['key']) }}" class="inline-flex h-12 w-full items-center justify-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                @if(filled($microsoftProvider['logo'] ?? null))
                                    <img src="{{ asset($microsoftProvider['logo']) }}" alt="" class="size-5 shrink-0">
                                @else
                                    <i data-lucide="{{ $microsoftProvider['icon'] }}" class="size-5" style="color: {{ $microsoftProvider['color'] }}"></i>
                                @endif
                                Sign in with {{ $microsoftProvider['label'] }}
                            </a>
                        @endif

                        @if ($secondaryProviders->isNotEmpty())
                            <div class="mt-3 grid gap-3 @if($secondaryProviders->count() > 1) sm:grid-cols-2 @endif">
                                @foreach ($secondaryProviders as $provider)
                                    <a href="{{ route('social.redirect', $provider['key']) }}" class="inline-flex h-12 items-center justify-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
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

                    </div>
                </section>
            </div>
            <x-brand-footer class="w-full max-w-5xl border-slate-200/80 px-2" />
        </main>
    </body>
</html>
