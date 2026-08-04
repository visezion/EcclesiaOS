@props([
    'title' => \App\Support\Branding::current()->systemName(),
    'breadcrumbs' => [],
    'chromeless' => false,
    'hideTopbar' => false,
    'mainClass' => 'px-4 py-5 sm:px-6 lg:px-7',
])

@php
    $branding = \App\Support\Branding::current();
    $title = \App\Support\OrganizationTerminology::translate($title, $terminology);
    $settings = $branding->settings;
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
    $accountSettings = auth()->user()?->account_settings ?? [];
    $requestedThemeMode = data_get($accountSettings, 'preferences.theme_mode') ?: ($settings['theme_mode'] ?? 'light');
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
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $themeMode }}" style="font-size: {{ $branding->interfaceZoom() }}%;">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title }} - {{ $branding->systemName() }}</title>
        @if ($branding->logo())
            <link rel="icon" href="{{ $branding->logo() }}">
            <link rel="apple-touch-icon" href="{{ $branding->logo() }}">
        @endif
        @if ($branding->assetPath(data_get($settings, 'favicon')))
            <link rel="shortcut icon" href="{{ $branding->assetPath(data_get($settings, 'favicon')) }}">
        @endif
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900|lato:400,700|nunito-sans:400,500,600,700|roboto:400,500,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-900" style="{{ $cssStyle }}">
        @if($chromeless)
            {{ $slot }}
        @else
            <div x-data="{ sidebarOpen: false, mobileSearchOpen: false }" class="app-shell min-h-screen">
                <x-sidebar />
                <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-30 bg-slate-950/50 lg:hidden" x-on:click="sidebarOpen = false"></div>
                <div class="lg:pl-72">
                    @unless($hideTopbar)
                        <x-topbar />
                    @endunless
                    <main class="{{ $mainClass }}">
                        @if (session('impersonator_id'))
                            <div class="mb-4 flex flex-col gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 sm:flex-row sm:items-center sm:justify-between">
                                <span>You are impersonating {{ auth()->user()?->name }}.</span>
                                <form method="POST" action="{{ route('users.impersonation.stop') }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                                        <i data-lucide="arrow-left" class="size-4"></i>
                                        Return to Admin
                                    </button>
                                </form>
                            </div>
                        @endif

                        @if ($breadcrumbs)
                            <x-breadcrumbs :items="$breadcrumbs" />
                        @endif

                        {{ $slot }}
                    </main>
                </div>
            </div>
        @endif
    </body>
</html>
