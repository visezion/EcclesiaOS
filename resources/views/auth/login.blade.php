<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Login - {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-sidebar font-sans text-slate-900 antialiased">
        <main class="grid min-h-screen place-items-center px-4 py-10">
            <section x-data="{ loading: false }" class="relative w-full max-w-md overflow-hidden rounded-lg border border-white/10 bg-white p-8 shadow-2xl">
                <div x-cloak x-show="loading" x-transition.opacity class="absolute inset-0 z-10 grid place-items-center bg-white/95 p-8 text-center">
                    <div>
                        <div class="mx-auto grid size-14 place-items-center rounded-full bg-violet-50 text-violet-600 ring-1 ring-violet-100">
                            <i data-lucide="loader-circle" class="size-7 animate-spin"></i>
                        </div>
                        <h2 class="mt-5 text-lg font-semibold text-slate-950">Kindly wait while we get your dashboard set up...</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500">We are checking your account and preparing your dashboard.</p>
                    </div>
                </div>

                <div class="mb-8 flex items-center gap-3">
                    <div class="grid size-12 place-items-center rounded-xl bg-violet-600 text-white shadow-lg">
                        <i data-lucide="cross" class="size-7"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold text-slate-950">{{ config('app.name') }}</h1>
                        <p class="text-sm text-slate-500">{{ config('church.subtitle') }}</p>
                    </div>
                </div>

                @if(session('status'))
                    <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="space-y-5" @submit="loading = true">
                    @csrf
                    <div>
                        <label for="email" class="text-sm font-semibold text-slate-700">Email address</label>
                        <input id="email" name="email" type="email" value="{{ old('email', 'admin@kingdomhub.test') }}" required autofocus autocomplete="email" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm outline-none focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
                        @error('email')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="password" class="text-sm font-semibold text-slate-700">Password</label>
                        <input id="password" name="password" type="password" required autocomplete="current-password" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm outline-none focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
                        @error('password')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" class="rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                        Remember this device
                    </label>
                    <a href="{{ route('password.request') }}" class="block text-center text-sm font-semibold text-slate-600 hover:text-violet-700">Forgot password?</a>
                    <button class="flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-3 text-sm font-semibold text-white hover:bg-violet-700 focus-visible:ring-2 focus-visible:ring-violet-300">
                        <i data-lucide="log-in" class="size-4"></i>
                        Login
                    </button>
                </form>
            </section>
        </main>
    </body>
</html>
