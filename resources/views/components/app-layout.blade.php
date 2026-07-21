@props(['title' => config('app.name'), 'breadcrumbs' => []])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title }} - {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-900">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen bg-slate-50">
            <x-sidebar />
            <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-30 bg-slate-950/50 lg:hidden" x-on:click="sidebarOpen = false"></div>
            <div class="lg:pl-72">
                <x-topbar />
                <main class="px-4 py-5 sm:px-6 lg:px-7">
                    @if ($breadcrumbs)
                        <x-breadcrumbs :items="$breadcrumbs" />
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
