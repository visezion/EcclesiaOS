<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('code') - {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
        <main class="grid min-h-screen place-items-center px-4 py-10">
            <section class="dashboard-card w-full max-w-lg text-center">
                <div class="mx-auto grid size-16 place-items-center rounded-2xl bg-violet-100 text-violet-600">
                    <i data-lucide="shield-alert" class="size-8"></i>
                </div>
                <p class="mt-5 text-sm font-bold uppercase tracking-wide text-violet-600">@yield('code')</p>
                <h1 class="mt-2 text-2xl font-bold text-slate-950">@yield('title')</h1>
                <p class="mt-2 text-sm text-slate-500">@yield('message')</p>
                <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="mt-6 inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-violet-700">
                    <i data-lucide="arrow-left" class="size-4"></i> Return safely
                </a>
            </section>
        </main>
    </body>
</html>
