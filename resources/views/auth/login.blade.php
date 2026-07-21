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
            <div class="w-full max-w-md rounded-lg border border-white/10 bg-white p-8 shadow-2xl">
                <div class="mb-8 flex items-center gap-3">
                    <div class="grid size-12 place-items-center rounded-2xl bg-gradient-to-br from-violet-500 to-purple-700 text-white shadow-lg">
                        <i data-lucide="cross" class="size-8"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold">{{ config('app.name') }}</h1>
                        <p class="text-sm text-slate-500">{{ config('church.subtitle') }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                    @csrf
                    <div>
                        <label for="email" class="text-sm font-bold text-slate-700">Email address</label>
                        <input id="email" name="email" type="email" value="{{ old('email', 'admin@kingdomhub.test') }}" required autofocus class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm outline-none focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
                        @error('email')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="password" class="text-sm font-bold text-slate-700">Password</label>
                        <input id="password" name="password" type="password" required class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm outline-none focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
                        @error('password')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" class="rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                        Remember this device
                    </label>
                    <button class="flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-3 text-sm font-bold text-white hover:bg-violet-700 focus-visible:ring-2 focus-visible:ring-violet-300">
                        <i data-lucide="log-in" class="size-4"></i> Sign in
                    </button>
                    <a href="{{ route('password.request') }}" class="block text-center text-sm font-semibold text-slate-600 hover:text-violet-700">Forgot password?</a>
                </form>
            </div>
        </main>
    </body>
</html>
