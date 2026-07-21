<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Forgot Password - {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-sidebar font-sans text-slate-900 antialiased">
        <main class="grid min-h-screen place-items-center px-4 py-10">
            <section class="w-full max-w-md rounded-lg border border-white/10 bg-white p-8 shadow-2xl">
                <div class="mb-6 grid size-12 place-items-center rounded-2xl bg-violet-100 text-violet-600">
                    <i data-lucide="mail" class="size-6"></i>
                </div>
                <h1 class="text-xl font-bold">Reset your password</h1>
                <p class="mt-2 text-sm text-slate-500">Enter your account email and Laravel will send a reset link through the configured mail channel.</p>
                @if (session('status'))
                    <div class="mt-4 rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>
                @endif
                <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <label class="text-sm font-bold text-slate-700" for="email">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
                        @error('email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <button class="w-full rounded-lg bg-violet-600 px-4 py-3 text-sm font-bold text-white hover:bg-violet-700">Send Reset Link</button>
                    <a href="{{ route('login') }}" class="block text-center text-sm font-semibold text-slate-600 hover:text-violet-700">Return to login</a>
                </form>
            </section>
        </main>
    </body>
</html>
