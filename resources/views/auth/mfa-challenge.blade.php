<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>MFA Verification - {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-sidebar font-sans text-slate-900 antialiased">
        <main class="grid min-h-screen place-items-center px-4 py-8">
            <section x-data="{ loading: false }" class="relative w-full max-w-md rounded-xl border border-white/10 bg-white p-8 shadow-2xl">
                <div x-cloak x-show="loading" x-transition.opacity class="absolute inset-0 z-10 grid place-items-center rounded-xl bg-white/95 p-8 text-center">
                    <div>
                        <div class="mx-auto grid size-14 place-items-center rounded-full bg-violet-50 text-violet-600 ring-1 ring-violet-100">
                            <i data-lucide="loader-circle" class="size-7 animate-spin"></i>
                        </div>
                        <h2 class="mt-5 text-lg font-semibold text-slate-950">Kindly wait while we get your dashboard set up...</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500">We are confirming your secure sign-in.</p>
                    </div>
                </div>

                <div class="mb-8 text-center">
                    <div class="mx-auto grid size-14 place-items-center rounded-xl bg-violet-600 text-white shadow-lg">
                        <i data-lucide="shield-check" class="size-8"></i>
                    </div>
                    <h1 class="mt-5 text-xl font-semibold text-slate-950">Multi-factor verification</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Enter the 6-digit code from your authenticator app, or use one recovery code.</p>
                </div>

                @if(session('status'))
                    <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('login.mfa.verify') }}" class="space-y-5" @submit="loading = true">
                    @csrf
                    <div>
                        <label for="code" class="text-sm font-semibold text-slate-700">Verification code</label>
                        <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus placeholder="123456 or ABC12-DEFG3" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-center text-lg font-semibold tracking-wide outline-none focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
                        @error('code')
                            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <button class="flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-3 text-sm font-semibold text-white hover:bg-violet-700 focus-visible:ring-2 focus-visible:ring-violet-300">
                        <i data-lucide="unlock-keyhole" class="size-4"></i>
                        Verify and continue
                    </button>
                    <a href="{{ route('login') }}" class="block text-center text-sm font-semibold text-slate-600 hover:text-violet-700">Use a different account</a>
                </form>
            </section>
        </main>
    </body>
</html>
