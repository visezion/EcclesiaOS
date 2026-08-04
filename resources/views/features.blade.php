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
    $categoryForRoute = static function (string $route): string {
        return match (true) {
            str_starts_with($route, 'finance') || str_starts_with($route, 'bookstore') => 'finance',
            str_starts_with($route, 'communications') => 'communication',
            str_starts_with($route, 'users') || str_starts_with($route, 'roles') || str_starts_with($route, 'settings') || str_starts_with($route, 'audit-logs') || str_starts_with($route, 'campuses') || str_starts_with($route, 'assets') || str_starts_with($route, 'facilities') || str_starts_with($route, 'modules') || str_starts_with($route, 'auth-settings') || str_starts_with($route, 'developer-hub') => 'administration',
            str_starts_with($route, 'reports') || str_starts_with($route, 'leadership-reports') || str_starts_with($route, 'feedback') => 'reports',
            str_starts_with($route, 'meeting-integrations') || str_starts_with($route, 'meetings') => 'integration',
            str_starts_with($route, 'ministries') || str_starts_with($route, 'prayer-requests') || str_starts_with($route, 'volunteers') || str_starts_with($route, 'sermons') || str_starts_with($route, 'children-youth') || str_starts_with($route, 'counselling') || str_starts_with($route, 'staff') => 'ministry',
            default => 'core',
        };
    };
    $categoryMeta = collect([
        ['key' => 'core', 'label' => 'Core', 'icon' => 'users'],
        ['key' => 'ministry', 'label' => 'Ministry', 'icon' => 'church'],
        ['key' => 'finance', 'label' => 'Finance', 'icon' => 'wallet'],
        ['key' => 'communication', 'label' => 'Communication', 'icon' => 'message-square'],
        ['key' => 'administration', 'label' => 'Administration', 'icon' => 'shield-check'],
        ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart-no-axes-column'],
        ['key' => 'integration', 'label' => 'Integration', 'icon' => 'radio-tower'],
    ])->keyBy('key');
    $features = \App\Support\ModuleRegistry::modules()
        ->sortBy(fn (array $module): string => $categoryForRoute((string) $module['route']).' '.$module['label'])
        ->values()
        ->map(function (array $module, int $index) use ($categoryForRoute, $categoryMeta): array {
            $route = (string) $module['route'];
            $category = $categoryForRoute($route);
            $planned = collect($module['planned'] ?? [])->take(4)->implode(', ');
            $description = filled($module['description'] ?? null)
                ? (string) $module['description']
                : ($planned !== '' ? $planned.'.' : 'Core platform tools and ministry workflows.');
            $hue = (252 + ($index * 137)) % 360;

            return [
                'category' => $category,
                'category_label' => $categoryMeta->get($category)['label'] ?? str($category)->headline()->toString(),
                'icon' => $module['icon'] ?? 'blocks',
                'title' => $module['label'],
                'copy' => $description,
                'color_style' => "--module-accent: hsl({$hue} 72% 43%); --module-soft: hsl({$hue} 88% 95%)",
                'route' => $route,
            ];
        })
        ->values();
    $categories = collect([
        ['key' => 'all', 'label' => 'All Features', 'icon' => 'sparkles', 'count' => $features->count()],
    ])->concat(
        $categoryMeta
            ->map(function (array $category) use ($features): array {
                return [
                    ...$category,
                    'count' => $features->where('category', $category['key'])->count(),
                ];
            })
            ->filter(fn (array $category): bool => $category['count'] > 0)
            ->values()
    );
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $themeMode }}" style="font-size: {{ $branding->interfaceZoom() }}%;">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Features - {{ $systemName }}</title>
        <meta name="description" content="Explore the church management features available in {{ $systemName }}.">
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
            .features-page {
                background: var(--page-bg);
                color: #0b1437;
                font-family: var(--font-app);
                font-size: var(--app-font-size);
            }

            .features-shell {
                margin-inline: auto;
                max-width: 1480px;
                padding-inline: clamp(1.25rem, 4.5vw, 5rem);
            }

            .features-primary {
                background: var(--brand-primary);
                box-shadow: 0 12px 28px color-mix(in srgb, var(--brand-primary) 22%, transparent);
            }

            .features-accent {
                color: var(--brand-primary);
            }

            .features-soft {
                background: color-mix(in srgb, var(--brand-primary) 9%, white);
                color: var(--brand-primary);
            }

            .features-image {
                border: 1px solid color-mix(in srgb, var(--brand-primary) 12%, #dfe6f2);
                border-radius: var(--card-radius);
                box-shadow:
                    0 30px 78px rgb(30 38 74 / 0.16),
                    0 12px 30px color-mix(in srgb, var(--brand-primary) 12%, transparent);
            }

            .features-category {
                border-radius: var(--card-radius);
                color: #53627c;
            }

            .features-category-active {
                background: color-mix(in srgb, var(--brand-primary) 10%, white);
                color: var(--brand-primary);
            }

            .features-nav-link {
                position: relative;
                display: inline-flex;
                height: 2.75rem;
                align-items: center;
                background: transparent;
                transition: color 160ms ease;
            }

            .features-nav-link:hover,
            .features-nav-link-active {
                color: var(--brand-primary);
            }

            .features-nav-link-active::after {
                position: absolute;
                right: 0;
                bottom: 0;
                left: 0;
                height: 2px;
                border-radius: 999px;
                background: var(--brand-primary);
                content: "";
            }

            .feature-card {
                border-radius: var(--card-radius);
                background: rgb(255 255 255 / 0.86);
                box-shadow: 0 15px 38px rgb(30 38 74 / 0.08);
            }

            .feature-module-icon {
                background: var(--module-soft);
                color: var(--module-accent);
            }

            .features-metrics {
                border-radius: var(--card-radius);
                background: rgb(255 255 255 / 0.52);
                box-shadow: 0 14px 38px rgb(30 38 74 / 0.07);
                backdrop-filter: blur(12px);
            }

            html[data-theme="dark"] .features-page {
                color: #e8edf8;
            }

            html[data-theme="dark"] .feature-card,
            html[data-theme="dark"] .features-metrics {
                background: rgb(17 24 39 / 0.78);
            }

            @media (prefers-color-scheme: dark) {
                html[data-theme="system"] .features-page {
                    color: #e8edf8;
                }

                html[data-theme="system"] .feature-card,
                html[data-theme="system"] .features-metrics {
                    background: rgb(17 24 39 / 0.78);
                }
            }
        </style>
    </head>
    <body
        class="features-page min-h-screen antialiased"
        style="{{ $cssStyle }}"
        x-data="{ mobileMenu: false, featureFilter: 'all' }"
    >
        <header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
            <nav class="features-shell flex h-[74px] items-center justify-between gap-5">
                <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3" aria-label="{{ $systemName }} home">
                    <span class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-lg">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $churchName }} logo" class="size-full object-contain">
                        @else
                            <span class="features-primary grid size-full place-items-center text-white"><i data-lucide="cross" class="size-6"></i></span>
                        @endif
                    </span>
                    <span class="truncate text-[1.6rem] font-black tracking-tight text-slate-950">{{ $systemName }}</span>
                </a>

                <div class="hidden h-full items-center gap-8 text-sm font-semibold text-slate-700 lg:flex">
                    <a href="{{ route('home') }}" class="features-nav-link">Home</a>
                    <a href="{{ route('features') }}" class="features-nav-link features-nav-link-active" aria-current="page">Features</a>
                    <a href="{{ route('home') }}#solutions" class="features-nav-link">Solutions</a>
                    <a href="{{ route('home') }}#resources" class="features-nav-link">Resources</a>
                    <a href="{{ route('home') }}#pricing" class="features-nav-link">Pricing</a>
                    <a href="{{ route('home') }}#about" class="features-nav-link">About Us</a>
                </div>

                <div class="hidden items-center gap-3 sm:flex">
                    <span class="grid size-9 place-items-center text-slate-700"><i data-lucide="moon" class="size-5"></i></span>
                    @guest
                        <a href="{{ route('login') }}" class="rounded-lg border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-800">Login</a>
                    @endguest
                    <a href="{{ $primaryHref }}" class="features-primary rounded-lg px-5 py-3 text-sm font-bold text-white">{{ $primaryLabel }}</a>
                </div>

                <button type="button" @click="mobileMenu = ! mobileMenu" class="grid size-10 place-items-center rounded-lg border border-slate-200 bg-white text-slate-800 sm:hidden" aria-label="Toggle navigation">
                    <i data-lucide="menu" class="size-5"></i>
                </button>
            </nav>
            <div x-cloak x-show="mobileMenu" x-transition class="border-t border-slate-200 bg-white p-5 sm:hidden">
                <div class="grid gap-2 text-sm font-semibold text-slate-700">
                    <a href="{{ route('home') }}" class="rounded-lg px-3 py-2">Home</a>
                    <a href="{{ route('features') }}" class="features-category-active rounded-lg px-3 py-2">Features</a>
                    <a href="{{ route('home') }}#solutions" class="rounded-lg px-3 py-2">Solutions</a>
                    <a href="{{ route('login') }}" class="rounded-lg border border-slate-200 px-3 py-2">Login</a>
                    <a href="{{ $primaryHref }}" class="features-primary rounded-lg px-3 py-3 text-center text-white">{{ $primaryLabel }}</a>
                </div>
            </div>
        </header>

        <main>
            <section class="features-shell grid min-h-[390px] items-center gap-12 py-12 lg:grid-cols-[0.72fr_1.28fr]">
                <div>
                    <div class="features-soft inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold">
                        <i data-lucide="sparkles" class="size-4"></i>
                        Everything you need to manage your church.
                    </div>
                    <h1 class="mt-6 text-[clamp(2.6rem,3.2vw,4rem)] font-black leading-[1.12] tracking-[-0.04em] text-slate-950">
                        Powerful Features.<br>
                        <span class="features-accent">Kingdom Impact.</span>
                    </h1>
                    <p class="mt-5 max-w-lg text-lg leading-8 text-slate-600">{{ $systemName }} brings all your church operations into one unified platform designed to save time, increase engagement, and help your ministry thrive.</p>
                    <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ $primaryHref }}" class="features-primary inline-flex items-center justify-center gap-3 rounded-lg px-7 py-4 text-base font-bold text-white">
                            {{ auth()->check() ? 'Open Dashboard' : 'Book a Demo' }}
                            <i data-lucide="{{ auth()->check() ? 'layout-dashboard' : 'calendar-check' }}" class="size-5"></i>
                        </a>
                        <a href="#feature-catalog" class="features-accent inline-flex items-center justify-center gap-3 rounded-lg px-6 py-4 text-base font-bold">
                            View Features
                            <i data-lucide="play" class="size-5"></i>
                        </a>
                    </div>
                </div>

                <div class="features-image h-[350px] overflow-hidden">
                    <picture class="block size-full">
                        <source srcset="{{ asset('images/landing-dashboard.webp') }}" type="image/webp">
                        <img
                            src="{{ asset('images/landing-dashboard.png') }}"
                            alt="{{ $systemName }} dashboard overview"
                            class="size-full object-cover object-top"
                            width="1440"
                            height="900"
                            fetchpriority="high"
                        >
                    </picture>
                </div>
            </section>

            <section id="feature-catalog" class="features-shell scroll-mt-24 border-t border-slate-200 py-10">
                <div class="text-center">
                    <h2 class="text-3xl font-black text-slate-950">Features Built for Every Ministry Need</h2>
                    <p class="mt-3 text-base text-slate-500">Explore the powerful tools that help churches operate effectively and fulfill their mission.</p>
                </div>

                <div class="mt-8 grid items-start gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
                    <aside class="feature-card grid gap-1 p-3 lg:sticky lg:top-24">
                        @foreach ($categories as $category)
                            <button
                                type="button"
                                @click="featureFilter = '{{ $category['key'] }}'"
                                :class="featureFilter === '{{ $category['key'] }}' ? 'features-category-active' : ''"
                                class="features-category flex items-center gap-3 px-4 py-3 text-left text-sm font-semibold"
                            >
                                <i data-lucide="{{ $category['icon'] }}" class="size-4"></i>
                                <span class="min-w-0 flex-1">{{ $category['label'] }}</span>
                                <span class="rounded-full bg-white/70 px-2 py-0.5 text-xs">{{ $category['count'] }}</span>
                            </button>
                        @endforeach
                    </aside>

                    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($features as $feature)
                            <article
                                x-show="featureFilter === 'all' || featureFilter === '{{ $feature['category'] }}'"
                                x-transition.opacity
                                class="feature-card min-h-[178px] p-6"
                                style="{{ $feature['color_style'] }}"
                            >
                                <div class="flex items-start gap-5">
                                    <span class="feature-module-icon grid size-14 shrink-0 place-items-center rounded-full">
                                        <i data-lucide="{{ $feature['icon'] }}" class="size-7"></i>
                                    </span>
                                    <div>
                                        <h3 class="mt-3 text-base font-bold text-slate-950">{{ $feature['title'] }}</h3>
                                        <p class="mt-2 text-sm leading-6 text-slate-500">{{ $feature['copy'] }}</p>
                                        <a href="{{ $primaryHref }}" class="features-accent mt-4 inline-flex items-center gap-2 text-sm font-bold">
                                            Learn more
                                            <i data-lucide="arrow-right" class="size-4"></i>
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="features-shell pb-16 pt-2">
                <div class="features-metrics grid gap-6 p-7 sm:grid-cols-2 lg:grid-cols-[repeat(4,1fr)_1.45fr]">
                    @foreach ([
                        ['users-round', '2,000+', 'Churches Trust Us'],
                        ['user-check', '500K+', 'Active Members'],
                        ['globe-2', '50+', 'Countries'],
                        ['shield-check', '99.9%', 'Uptime & Reliability'],
                    ] as [$icon, $value, $label])
                        <div class="flex items-center justify-center gap-4 lg:border-r lg:border-slate-200">
                            <span class="features-soft grid size-12 shrink-0 place-items-center rounded-full"><i data-lucide="{{ $icon }}" class="size-6"></i></span>
                            <div>
                                <div class="features-accent text-2xl font-black">{{ $value }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $label }}</div>
                            </div>
                        </div>
                    @endforeach
                    <div class="text-center lg:text-left">
                        <div class="text-sm font-bold text-slate-950">Ready to experience these features?</div>
                        <p class="mt-2 text-xs text-slate-500">Join churches already thriving with {{ $systemName }}.</p>
                        <a href="{{ $primaryHref }}" class="features-primary mt-4 inline-flex items-center gap-2 rounded-lg px-5 py-3 text-sm font-bold text-white">
                            Get Started Today
                            <i data-lucide="arrow-right" class="size-4"></i>
                        </a>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200 bg-white/70">
            <div class="features-shell flex flex-col gap-3 py-6 text-center text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:text-left">
                <span>© {{ now()->year }} {{ $churchName }}. All rights reserved.</span>
                <span>{{ $subtitle }}</span>
            </div>
        </footer>
    </body>
</html>
