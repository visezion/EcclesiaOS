@php
    $branding = \App\Support\Branding::current();
    $settings = $branding->settings;
    $systemName = $branding->systemName();
    $churchName = $branding->churchName();
    $subtitle = $branding->subtitle();
    $logoUrl = $branding->logo();
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
        '--page-bg' => $settings['page_background'] ?? '#F6F8FC',
        '--card-radius' => ((int) ($settings['card_radius'] ?? 8)).'px',
        '--font-app' => $fontStacks[$settings['font_family'] ?? 'Inter'] ?? $fontStacks['Inter'],
        '--app-font-size' => $fontSizes[$settings['font_scale'] ?? 'default'] ?? $fontSizes['default'],
    ];
    $cssStyle = collect($cssVariables)->map(fn ($value, $key): string => $key.': '.e($value))->implode('; ');
    $primaryHref = auth()->check() ? route('dashboard') : route('login');
    $primaryLabel = auth()->check() ? 'Open Dashboard' : 'Get Started';
    $downloadUrl = config('church.download_url');
    $documentationUrl = config('church.documentation_url');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $themeMode }}" style="font-size: {{ $branding->interfaceZoom() }}%;">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $systemName }} - {{ $subtitle }}</title>
        <meta name="description" content="{{ $systemName }} helps churches manage members, ministries, events, finances, and communications in one place.">
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
            .landing-page {
                background: var(--page-bg);
                color: #0b1437;
                font-family: var(--font-app);
                font-size: var(--app-font-size);
            }

            .landing-shell {
                margin-inline: auto;
                max-width: 1440px;
                padding-inline: clamp(1.25rem, 5vw, 5.5rem);
            }

            .landing-card {
                border: 1px solid color-mix(in srgb, var(--brand-primary) 10%, #dfe6f2);
                border-radius: var(--card-radius);
                background: color-mix(in srgb, white 94%, var(--page-bg));
                box-shadow: 0 24px 70px rgb(37 46 83 / 0.08);
            }

            .landing-primary {
                background: var(--brand-primary);
                box-shadow: 0 12px 26px color-mix(in srgb, var(--brand-primary) 24%, transparent);
            }

            .landing-primary:hover {
                filter: brightness(.94);
                transform: translateY(-1px);
            }

            .landing-accent-text {
                color: var(--brand-primary);
            }

            .landing-icon {
                background: color-mix(in srgb, var(--brand-primary) 9%, white);
                color: var(--brand-primary);
            }

            .landing-feature-card {
                border: 0;
                border-radius: var(--card-radius);
                background: rgb(255 255 255 / 0.88);
                box-shadow:
                    0 16px 38px rgb(30 38 74 / 0.09),
                    0 4px 12px rgb(30 38 74 / 0.04);
            }

            .landing-metrics-strip {
                border: 0;
                border-radius: var(--card-radius);
                background: rgb(255 255 255 / 0.52);
                box-shadow: 0 14px 36px rgb(30 38 74 / 0.07);
                backdrop-filter: blur(12px);
            }

            .landing-dashboard {
                box-shadow:
                    0 34px 85px rgb(30 38 74 / 0.18),
                    0 14px 34px color-mix(in srgb, var(--brand-primary) 14%, transparent);
            }

            html[data-theme="dark"] .landing-page {
                color: #e8edf8;
            }

            html[data-theme="dark"] .landing-card {
                background: color-mix(in srgb, #111827 94%, var(--brand-primary));
            }

            html[data-theme="dark"] .landing-feature-card,
            html[data-theme="dark"] .landing-metrics-strip {
                background: rgb(17 24 39 / 0.72);
            }

            @media (prefers-color-scheme: dark) {
                html[data-theme="system"] .landing-page {
                    color: #e8edf8;
                }

                html[data-theme="system"] .landing-card {
                    background: color-mix(in srgb, #111827 94%, var(--brand-primary));
                }

                html[data-theme="system"] .landing-feature-card,
                html[data-theme="system"] .landing-metrics-strip {
                    background: rgb(17 24 39 / 0.72);
                }
            }

        </style>
    </head>
    <body class="landing-page min-h-screen antialiased" style="{{ $cssStyle }}" x-data="{ mobileMenu: false }">
        <header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
            <nav class="landing-shell flex h-[70px] items-center justify-between gap-5">
                <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3" aria-label="{{ $systemName }} home">
                    <span class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-lg">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $churchName }} logo" class="size-full object-contain">
                        @else
                            <span class="landing-primary grid size-full place-items-center text-white"><i data-lucide="cross" class="size-6"></i></span>
                        @endif
                    </span>
                    <span class="truncate text-[1.6rem] font-black tracking-tight text-slate-950">{{ $systemName }}</span>
                </a>

                <div class="hidden items-center gap-9 text-sm font-semibold text-slate-700 lg:flex">
                    <a href="{{ route('giving.create') }}" class="hover:text-violet-600">Give</a>
                    <a href="{{ route('members.self-register') }}" class="hover:text-violet-600">Member Registration</a>
                    <a href="{{ route('features') }}" class="hover:text-violet-600">Features</a>
                    <a href="{{ $downloadUrl }}" class="hover:text-violet-600">Download</a>
                    <a href="{{ $documentationUrl }}" target="_blank" rel="noopener noreferrer" class="hover:text-violet-600">Documentation</a>
                </div>

                <div class="hidden items-center gap-3 sm:flex">
                    <span class="grid size-9 place-items-center text-slate-700"><i data-lucide="moon" class="size-5"></i></span>
                    @guest
                        <a href="{{ route('login') }}" class="rounded-lg border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-800 hover:bg-slate-50">Login</a>
                    @endguest
                    <a href="{{ $primaryHref }}" class="landing-primary rounded-lg px-5 py-3 text-sm font-bold text-white transition">{{ $primaryLabel }}</a>
                </div>

                <button type="button" @click="mobileMenu = ! mobileMenu" class="grid size-10 place-items-center rounded-lg border border-slate-200 bg-white text-slate-800 sm:hidden" aria-label="Toggle navigation">
                    <i data-lucide="menu" class="size-5"></i>
                </button>
            </nav>
            <div x-cloak x-show="mobileMenu" x-transition class="border-t border-slate-200 bg-white p-5 sm:hidden">
                <div class="grid gap-2 text-sm font-semibold text-slate-700">
                    <a href="{{ route('giving.create') }}" class="rounded-lg px-3 py-2">Give</a>
                    <a href="{{ route('members.self-register') }}" class="rounded-lg px-3 py-2">Member Registration</a>
                    <a href="{{ route('features') }}" class="rounded-lg px-3 py-2">Features</a>
                    <a href="{{ $downloadUrl }}" class="rounded-lg px-3 py-2">Download</a>
                    <a href="{{ $documentationUrl }}" target="_blank" rel="noopener noreferrer" class="rounded-lg px-3 py-2">Documentation</a>
                    <a href="{{ route('login') }}" class="rounded-lg border border-slate-200 px-3 py-2">Login</a>
                    <a href="{{ $primaryHref }}" class="landing-primary rounded-lg px-3 py-3 text-center text-white">{{ $primaryLabel }}</a>
                </div>
            </div>
        </header>

        <main>
            <section class="landing-shell relative grid min-h-[520px] items-center gap-12 py-16 lg:grid-cols-[0.9fr_1.1fr] lg:py-10">
                <div class="relative z-10">
                    <div class="inline-flex items-center gap-2 rounded-full bg-violet-50 px-4 py-2 text-sm font-semibold text-violet-700">
                        <i data-lucide="sparkles" class="size-4"></i>
                        Built for Churches. Designed for Impact.
                    </div>
                    <h1 class="mt-7 text-[clamp(2.5rem,2.85vw,3.75rem)] font-black leading-[1.17] tracking-[-0.04em] text-slate-950 lg:whitespace-nowrap">
                        Empowering Churches.<br>
                        Uniting Ministries.<br>
                        <span class="landing-accent-text">Transforming Lives.</span>
                    </h1>
                    <p class="mt-6 max-w-xl text-lg leading-8 text-slate-600">
                        {{ $systemName }} is an all-in-one church management system that helps you manage people, ministries, events, finances, and communications, so you can focus on what matters most.
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ $primaryHref }}" class="landing-primary inline-flex items-center justify-center gap-3 rounded-lg px-7 py-4 text-base font-bold text-white transition">
                            {{ auth()->check() ? 'Open Dashboard' : 'Book a Demo' }}
                            <i data-lucide="{{ auth()->check() ? 'layout-dashboard' : 'calendar-check' }}" class="size-5"></i>
                        </a>
                        <a href="{{ route('features') }}" class="inline-flex items-center justify-center gap-3 rounded-lg border border-violet-300 bg-white px-7 py-4 text-base font-bold text-violet-700 hover:bg-violet-50">
                            Explore Features
                            <i data-lucide="arrow-right" class="size-5"></i>
                        </a>
                    </div>
                    <div class="mt-8 flex items-center gap-4">
                        <div class="flex -space-x-2">
                            @foreach (['AJ', 'MO', 'SK'] as $initials)
                                <span class="grid size-9 place-items-center rounded-full border-2 border-white bg-slate-800 text-xs font-bold text-white">{{ $initials }}</span>
                            @endforeach
                        </div>
                        <div>
                            <div class="text-amber-500">★★★★★</div>
                            <div class="text-xs text-slate-500">Trusted by growing churches worldwide</div>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <div class="landing-dashboard landing-card overflow-hidden">
                        <picture>
                            <source srcset="{{ asset('images/landing-dashboard.webp') }}" type="image/webp">
                            <img
                                src="{{ asset('images/landing-dashboard.png') }}"
                                alt="{{ $systemName }} dashboard showing members, attendance, giving, assets, reports, and upcoming events"
                                class="block h-auto w-full"
                                width="1440"
                                height="900"
                                fetchpriority="high"
                            >
                        </picture>
                    </div>
                </div>
            </section>

            <section id="features" class="landing-shell scroll-mt-24 py-12">
                <div class="flex items-center justify-center gap-4">
                    <span class="h-px w-8 bg-violet-500"></span>
                    <h2 class="text-center text-sm font-bold text-slate-900">Everything your church needs to thrive</h2>
                    <span class="h-px w-8 bg-violet-500"></span>
                </div>
                <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    @foreach ([
                        ['users', 'Members & Families', 'Manage members, families, profiles, and spiritual growth in one place.', 'landing-icon'],
                        ['calendar-days', 'Programs & Attendance', 'Organize events, track attendance, and measure ministry impact.', 'landing-icon'],
                        ['heart', 'Giving & Finance', 'Handle donations, tithes, budgets, and financial reports with ease.', 'bg-rose-50 text-rose-600'],
                        ['message-square', 'Communications', 'Send emails, SMS, push notifications, and engage your church.', 'landing-icon'],
                        ['chart-no-axes-column', 'Reports & Analytics', 'Get insights that help you make better decisions faster.', 'bg-emerald-50 text-emerald-600'],
                        ['blocks', 'And Much More', 'Ministries, sermons, prayer, bookstore, HR, assets, and more.', 'landing-icon'],
                    ] as [$icon, $title, $copy, $tone])
                        <article class="landing-feature-card flex min-h-[190px] flex-col items-center p-6 text-center transition hover:-translate-y-1 hover:shadow-xl">
                            <span class="{{ $tone }} grid size-12 place-items-center rounded-xl"><i data-lucide="{{ $icon }}" class="size-6"></i></span>
                            <h3 class="mt-4 text-base font-bold text-slate-950">{{ $title }}</h3>
                            <p class="mt-2 text-base leading-6 text-slate-500">{{ $copy }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            <section id="solutions" class="landing-shell scroll-mt-24 pb-14 pt-2">
                <div class="landing-metrics-strip grid gap-6 p-6 sm:grid-cols-2 lg:grid-cols-5">
                    @foreach ([
                        ['church', '2,000+', 'Churches Trust Us'],
                        ['users-round', '500K+', 'Active Members'],
                        ['blocks', '50+', 'Powerful Modules'],
                        ['clock-3', '24/7', 'Support & Updates'],
                        ['heart', 'Built with ❤️', "for God's Kingdom"],
                    ] as [$icon, $value, $label])
                        <div class="flex items-center justify-center gap-4 lg:border-r lg:border-slate-200 lg:last:border-r-0">
                            <span class="landing-icon grid size-12 shrink-0 place-items-center rounded-xl"><i data-lucide="{{ $icon }}" class="size-6"></i></span>
                            <div>
                                <div class="text-xl font-black text-violet-600">{{ $value }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $label }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

           
        </main>

        <footer id="resources" class="border-t border-slate-200 bg-white/70">
            <div class="landing-shell flex flex-col gap-3 py-6 text-center text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:text-left">
                <span>&copy; {{ now()->year }} {{ config('church.product_name', 'EcclesiaOS') }} &middot; v{{ ltrim((string) config('updater.current_version', '0.0.0'), 'vV') }}. All rights reserved.</span>
                <span><strong>{{ config('church.product_name', 'EcclesiaOS') }}</strong> · {{ config('church.product_vision', 'Equipping churches to connect people, steward ministry, and serve with clarity.') }}</span>
            </div>
        </footer>
    </body>
</html>
