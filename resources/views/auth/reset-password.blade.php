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
        '--font-app' => $fontStacks[$settings['font_family'] ?? 'Inter'] ?? $fontStacks['Inter'],
        '--app-font-size' => $fontSizes[$settings['font_scale'] ?? 'default'] ?? $fontSizes['default'],
    ];
    $cssStyle = collect($cssVariables)->map(fn ($value, $key): string => $key.': '.e($value))->implode('; ');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $themeMode }}" style="font-size: {{ $branding->interfaceZoom() }}%;">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Reset Password · {{ $systemName }}</title>
        <meta name="description" content="Reset your {{ $churchName }} account password securely.">
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
            .auth-shell { box-shadow: 0 30px 90px rgba(15, 23, 42, 0.12); }
            .auth-card { box-shadow: 0 24px 80px rgba(15, 23, 42, 0.12); }
            .brand-bar { background: linear-gradient(90deg, var(--brand-primary), var(--brand-secondary)); }
            .focus-ring:focus {
                outline: none;
                border-color: var(--brand-primary);
                box-shadow: 0 0 0 4px color-mix(in srgb, var(--brand-primary) 16%, transparent);
            }
        </style>
    </head>
    <body class="min-h-screen text-slate-900 antialiased" style="{{ $cssStyle }}">
        <main class="mx-auto flex min-h-screen w-full max-w-6xl flex-col items-center justify-center gap-8 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
            <div class="auth-shell grid w-full max-w-5xl overflow-hidden rounded-[28px] border border-slate-200 bg-white/90 backdrop-blur xl:grid-cols-[1.05fr_.95fr]">
                <section class="relative hidden overflow-hidden bg-slate-950 p-10 text-white xl:flex xl:flex-col xl:justify-between xl:p-12" @if ($sidebarBackgroundUrl) style="background-image: url('{{ $sidebarBackgroundUrl }}'); background-position: center; background-size: cover;" @endif>
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
                            <div class="mb-6 mt-16 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white/75"><i data-lucide="shield-check" class="size-3.5 text-emerald-300"></i>Secure account recovery</div>
                            <h1 class="text-4xl font-black leading-[1.05] text-white 2xl:text-5xl">Back to serving your church.</h1>
                            <p class="mt-5 max-w-md text-base leading-7 text-white/75">Create a new password and return to the people, ministry, and work that matter most.</p>
                            <p class="mt-5 max-w-md text-sm leading-6 text-white/55">Your reset link is private, time-limited, and protected by {{ $systemName }}.</p>
                        </div>
                    </div>
                    <div class="relative z-10 grid gap-3 text-sm text-white/72">
                        <div class="flex items-center gap-2"><span class="size-2 rounded-full bg-emerald-400"></span>Private recovery link</div>
                        <div class="flex items-center gap-2"><span class="size-2 rounded-full bg-sky-400"></span>Secure password update</div>
                        <div class="flex items-center gap-2"><span class="size-2 rounded-full bg-violet-400"></span>Church-branded experience</div>
                    </div>
                </section>

                <section class="flex items-center justify-center p-5 sm:p-10 lg:p-12">
                    <div class="auth-card w-full max-w-[390px] rounded-[24px] border border-slate-200 bg-white p-6 sm:p-8">
                        <div class="flex items-center gap-3 xl:hidden">
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="{{ $churchName }} logo" class="size-12 rounded-xl object-contain">
                            @else
                                <div class="grid size-12 place-items-center rounded-xl bg-slate-950 text-white"><i data-lucide="shield-check" class="size-7"></i></div>
                            @endif
                            <div class="space-y-1"><div class="text-xl font-black leading-tight text-slate-950">{{ $systemName }}</div><div class="text-sm leading-5 text-slate-500">{{ $subtitle }}</div></div>
                        </div>
                        <div class="mt-6">
                            <div class="brand-bar h-1.5 w-16 rounded-full"></div>
                            <h2 class="mt-5 text-3xl font-black tracking-tight text-slate-950">Create a new password</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-500">Choose a strong password to keep your {{ $churchName }} account secure.</p>
                        </div>
                        @if ($errors->any())
                            <div class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">Please review the highlighted fields and try again.</div>
                        @endif
                        <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-4">
                            @csrf
                            <input type="hidden" name="token" value="{{ $request->route('token') }}">
                            <label class="block">
                                <span class="text-sm font-semibold text-slate-700">Email</span>
                                <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autocomplete="email" class="focus-ring mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400">
                                @error('email')<span class="mt-2 block text-sm text-rose-600">{{ $message }}</span>@enderror
                            </label>
                            <label class="block">
                                <span class="text-sm font-semibold text-slate-700">New password</span>
                                <input id="password" name="password" type="password" required autocomplete="new-password" class="focus-ring mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900">
                                @error('password')<span class="mt-2 block text-sm text-rose-600">{{ $message }}</span>@enderror
                            </label>
                            <label class="block">
                                <span class="text-sm font-semibold text-slate-700">Confirm new password</span>
                                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="focus-ring mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900">
                            </label>
                            <button class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-slate-950 px-4 text-sm font-bold text-white transition hover:bg-slate-800"><i data-lucide="key-round" class="size-4"></i>Update password</button>
                        </form>
                        <a href="{{ route('login') }}" class="mt-5 inline-flex w-full items-center justify-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-violet-700"><i data-lucide="arrow-left" class="size-4"></i>Return to sign in</a>
                    </div>
                </section>
            </div>
            <x-brand-footer class="w-full max-w-5xl border-slate-200/80 px-2" />
        </main>
    </body>
</html>
