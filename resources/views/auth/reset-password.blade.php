<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Reset Password - {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-sidebar font-sans text-slate-900 antialiased">
        <main class="grid min-h-screen place-items-center px-4 py-10">
            <section class="w-full max-w-md rounded-lg border border-white/10 bg-white p-8 shadow-2xl">
                <div class="mb-6 grid size-12 place-items-center rounded-2xl bg-violet-100 text-violet-600">
                    <i data-lucide="shield-check" class="size-6"></i>
                </div>
                <h1 class="text-xl font-bold">Create a new password</h1>
                <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-4">
                    @csrf
                    <input type="hidden" name="token" value="{{ $request->route('token') }}">
                    <div>
                        <label class="text-sm font-bold text-slate-700" for="email">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
                        @error('email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm font-bold text-slate-700" for="password">Password</label>
                        <input id="password" name="password" type="password" required class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
                        @error('password') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm font-bold text-slate-700" for="password_confirmation">Confirm password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
                    </div>
                    <button class="w-full rounded-lg bg-violet-600 px-4 py-3 text-sm font-bold text-white hover:bg-violet-700">Reset Password</button>
                </form>
            </section>
        </main>
    </body>
</html>
