@php
    $churchName = config('church.name', config('app.name'));
    $subtitle = config('church.subtitle', 'Enterprise Church Management System');
    $contactEmail = config('church.contact_email', 'support@example.org');
    $sidebarImage = config('church.sidebar_background', 'images/sidebar-church.png');
    $socialProviders = collect($socialProviders ?? []);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Login - {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#f6f8fc] font-sans text-slate-900 antialiased">
        <main class="min-h-screen lg:grid lg:grid-cols-[minmax(360px,0.48fr)_minmax(620px,1fr)]">
            <aside class="relative hidden min-h-screen overflow-hidden bg-[#061633] text-white lg:flex">
                <div class="absolute inset-0 bg-sidebar"></div>
                <div class="absolute inset-x-0 bottom-0 h-[46%] bg-church-silhouette opacity-95" style="--sidebar-background-image: url('{{ asset($sidebarImage) }}')"></div>
                <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(6,22,51,0.12)_0%,rgba(6,22,51,0.62)_60%,rgba(6,22,51,0.94)_100%)]"></div>

                <div class="relative z-10 flex min-h-screen w-full flex-col justify-between p-10 xl:p-12">
                    <div>
                        <div class="flex items-center gap-4">
                            <div class="grid size-14 place-items-center rounded-xl bg-white/12 text-white shadow-lg ring-1 ring-white/20 backdrop-blur">
                                <i data-lucide="cross" class="size-9"></i>
                            </div>
                            <div>
                                <h1 class="max-w-[16rem] text-2xl font-semibold leading-tight text-white">{{ $churchName }}</h1>
                                <p class="mt-1 text-sm font-medium text-violet-100">{{ $subtitle }}</p>
                            </div>
                        </div>

                        <div class="mt-24 max-w-md">
                            <p class="text-sm font-semibold uppercase text-violet-100">Secure access portal</p>
                            <h2 class="mt-4 text-4xl font-semibold leading-tight text-white">Welcome back.</h2>
                            <p class="mt-4 text-lg leading-8 text-violet-50">Sign in to manage people, campuses, giving, care, events, meetings, and daily operations from one protected portal.</p>
                        </div>

                        <div class="mt-10 grid gap-4">
                            <div class="grid grid-cols-[48px_minmax(0,1fr)] gap-4">
                                <span class="grid size-12 place-items-center rounded-xl bg-white/12 text-white ring-1 ring-white/15"><i data-lucide="shield-check" class="size-6"></i></span>
                                <div>
                                    <h3 class="text-sm font-semibold text-white">Protected by MFA</h3>
                                    <p class="mt-1 text-sm leading-6 text-violet-100">Multi-factor authentication keeps leadership accounts and church data protected.</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-[48px_minmax(0,1fr)] gap-4">
                                <span class="grid size-12 place-items-center rounded-xl bg-white/12 text-white ring-1 ring-white/15"><i data-lucide="users-round" class="size-6"></i></span>
                                <div>
                                    <h3 class="text-sm font-semibold text-white">Role-based access</h3>
                                    <p class="mt-1 text-sm leading-6 text-violet-100">Permissions are scoped for pastors, staff, ministry teams, volunteers, and administrators.</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-[48px_minmax(0,1fr)] gap-4">
                                <span class="grid size-12 place-items-center rounded-xl bg-white/12 text-white ring-1 ring-white/15"><i data-lucide="building-2" class="size-6"></i></span>
                                <div>
                                    <h3 class="text-sm font-semibold text-white">Multi-campus ready</h3>
                                    <p class="mt-1 text-sm leading-6 text-violet-100">Work across campuses while keeping each team focused on the information they should see.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-[44px_minmax(0,1fr)] gap-3 rounded-xl bg-white/10 p-4 ring-1 ring-white/15 backdrop-blur">
                        <span class="grid size-11 place-items-center rounded-lg bg-white/12"><i data-lucide="headphones" class="size-5"></i></span>
                        <div>
                            <p class="text-sm font-semibold text-white">Need help signing in?</p>
                            <p class="mt-1 text-sm leading-6 text-violet-100">Contact your system administrator or email {{ $contactEmail }}.</p>
                        </div>
                    </div>
                </div>
            </aside>

            <section class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-8 sm:px-6 lg:px-10">
                <div class="absolute inset-0 bg-[linear-gradient(135deg,#ffffff_0%,#f7f9fd_48%,#eef3fb_100%)]"></div>
                <div class="absolute right-0 top-0 hidden h-full w-[34%] border-l border-slate-200/60 bg-white/35 lg:block"></div>
                <div class="absolute right-10 top-8 z-10 hidden items-center gap-2 text-sm font-semibold text-slate-600 lg:flex">
                    <i data-lucide="globe-2" class="size-4"></i>
                    English
                    <i data-lucide="chevron-down" class="size-4"></i>
                </div>

                <div class="relative z-10 grid w-full max-w-6xl gap-7 lg:grid-cols-[minmax(0,650px)_280px] lg:items-center">
                    <section x-data="{ loading: false, showPassword: false }" class="relative overflow-hidden rounded-xl border border-slate-200 bg-white shadow-[0_24px_80px_rgba(15,23,42,0.12)]">
                        <div x-cloak x-show="loading" x-transition.opacity class="absolute inset-0 z-20 grid place-items-center bg-white/95 p-8 text-center backdrop-blur">
                            <div>
                                <div class="mx-auto grid size-14 place-items-center rounded-full bg-violet-50 text-violet-600 ring-1 ring-violet-100">
                                    <i data-lucide="loader-circle" class="size-7 animate-spin"></i>
                                </div>
                                <h2 class="mt-5 text-lg font-semibold text-slate-950">Kindly wait while we get your dashboard set up...</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-500">We are checking your account and preparing your secure dashboard.</p>
                            </div>
                        </div>

                        <div class="border-b border-slate-100 px-6 py-5 sm:hidden">
                            <div class="flex items-center gap-3">
                                <div class="grid size-11 place-items-center rounded-xl bg-violet-600 text-white shadow-lg">
                                    <i data-lucide="cross" class="size-7"></i>
                                </div>
                                <div>
                                    <h1 class="text-base font-semibold text-slate-950">{{ $churchName }}</h1>
                                    <p class="text-xs font-medium text-slate-500">{{ $subtitle }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 sm:p-10">
                            <div class="max-w-xl">
                                <div class="grid size-11 place-items-center rounded-xl bg-violet-50 text-violet-600 ring-1 ring-violet-100">
                                    <i data-lucide="lock-keyhole" class="size-6"></i>
                                </div>
                                <h2 class="mt-6 text-3xl font-semibold text-slate-950">Sign in to your account</h2>
                                <p class="mt-2 text-base text-slate-500">Access your church management system.</p>
                            </div>

                            @if(session('status'))
                                <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
                            @endif

                            <form method="POST" action="{{ route('login.store') }}" class="mt-7 space-y-5" @submit="loading = true">
                                @csrf

                                <div>
                                    <label for="email" class="text-sm font-semibold text-slate-700">Email address</label>
                                    <div class="mt-2 grid h-12 grid-cols-[40px_minmax(0,1fr)] items-center rounded-lg border border-slate-200 bg-white px-3 focus-within:border-violet-400 focus-within:ring-4 focus-within:ring-violet-100">
                                        <i data-lucide="mail" class="size-5 text-slate-400"></i>
                                        <input id="email" name="email" type="email" value="{{ old('email', 'admin@kingdomhub.test') }}" required autofocus autocomplete="email" class="h-full border-0 bg-transparent px-1 text-sm font-medium outline-none focus:ring-0">
                                    </div>
                                    @error('email')
                                        <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="password" class="text-sm font-semibold text-slate-700">Password</label>
                                    <div class="mt-2 grid h-12 grid-cols-[40px_minmax(0,1fr)_36px] items-center rounded-lg border border-slate-200 bg-white px-3 focus-within:border-violet-400 focus-within:ring-4 focus-within:ring-violet-100">
                                        <i data-lucide="lock" class="size-5 text-slate-400"></i>
                                        <input id="password" name="password" :type="showPassword ? 'text' : 'password'" required autocomplete="current-password" class="h-full border-0 bg-transparent px-1 text-sm font-medium outline-none focus:ring-0">
                                        <button type="button" @click="showPassword = ! showPassword" class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-50 hover:text-violet-600" aria-label="Toggle password visibility">
                                            <i x-show="! showPassword" data-lucide="eye" class="size-4"></i>
                                            <i x-cloak x-show="showPassword" data-lucide="eye-off" class="size-4"></i>
                                        </button>
                                    </div>
                                    @error('password')
                                        <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-600">
                                        <input type="checkbox" name="remember" class="rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                                        Remember this device
                                    </label>
                                    <a href="{{ route('password.request') }}" class="text-sm font-semibold text-violet-600 hover:text-violet-700">Forgot password?</a>
                                </div>

                                <button class="flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white shadow-lg shadow-violet-600/20 hover:bg-violet-700 focus-visible:ring-2 focus-visible:ring-violet-300">
                                    Sign in
                                    <i data-lucide="arrow-right" class="size-4"></i>
                                </button>

                                @if($socialProviders->isNotEmpty())
                                    @php
                                        $socialProviderCount = $socialProviders->count();
                                    @endphp

                                    <div class="grid grid-cols-[minmax(1.5rem,1fr)_auto_minmax(1.5rem,1fr)] items-center gap-2 sm:gap-3">
                                        <span class="h-px bg-slate-200"></span>
                                        <span class="max-w-full whitespace-nowrap text-center text-xs font-semibold text-slate-400">
                                            or continue with
                                        </span>
                                        <span class="h-px bg-slate-200"></span>
                                    </div>

                                    <div class="grid gap-2 {{ $socialProviderCount === 1 ? '' : 'sm:grid-cols-2' }}">
                                        @foreach($socialProviders as $provider)
                                            <a href="{{ route('social.redirect', $provider['key']) }}" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:border-slate-300 hover:bg-slate-50">
                                                @if(filled($provider['logo'] ?? null))
                                                    <img src="{{ asset($provider['logo']) }}" alt="" class="size-4 shrink-0">
                                                @else
                                                    <i data-lucide="{{ $provider['icon'] }}" class="size-4" style="color: {{ $provider['color'] }}"></i>
                                                @endif
                                                {{ $provider['label'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </form>
                        </div>

                        <div class="grid gap-3 border-t border-slate-100 bg-slate-50/80 px-6 py-4 sm:grid-cols-3 sm:px-10">
                            <div class="grid grid-cols-[34px_minmax(0,1fr)] gap-3">
                                <span class="grid size-8 place-items-center rounded-lg bg-emerald-50 text-emerald-600"><i data-lucide="shield-check" class="size-4"></i></span>
                                <div><p class="text-xs font-semibold text-slate-900">Protected by MFA</p><p class="text-xs text-slate-500">Secure sign-in</p></div>
                            </div>
                            <div class="grid grid-cols-[34px_minmax(0,1fr)] gap-3">
                                <span class="grid size-8 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="users-round" class="size-4"></i></span>
                                <div><p class="text-xs font-semibold text-slate-900">Role-based access</p><p class="text-xs text-slate-500">Scoped permissions</p></div>
                            </div>
                            <div class="grid grid-cols-[34px_minmax(0,1fr)] gap-3">
                                <span class="grid size-8 place-items-center rounded-lg bg-blue-50 text-blue-600"><i data-lucide="building-2" class="size-4"></i></span>
                                <div><p class="text-xs font-semibold text-slate-900">Multi-campus</p><p class="text-xs text-slate-500">Unified management</p></div>
                            </div>
                        </div>
                    </section>

                    <aside class="hidden lg:block">
                        <div class="rounded-xl border border-slate-200 bg-white/86 p-6 shadow-[0_20px_60px_rgba(15,23,42,0.10)] backdrop-blur">
                            <div class="grid size-12 place-items-center rounded-xl bg-violet-50 text-violet-600 ring-1 ring-violet-100">
                                <i data-lucide="shield-check" class="size-6"></i>
                            </div>
                            <h2 class="mt-6 text-lg font-semibold text-slate-950">Two-factor authentication</h2>
                            <p class="mt-3 text-sm leading-7 text-slate-600">Accounts with MFA enabled are verified after password confirmation before the dashboard opens.</p>
                            <div class="mt-8 grid grid-cols-2 gap-4 text-slate-200">
                                <div class="grid aspect-[3/4] place-items-center rounded-xl border border-slate-100 bg-slate-50"><i data-lucide="smartphone" class="size-14"></i></div>
                                <div class="grid aspect-[3/4] place-items-center rounded-xl border border-slate-100 bg-slate-50"><i data-lucide="badge-check" class="size-16"></i></div>
                            </div>
                            <a href="{{ route('password.request') }}" class="mt-7 inline-flex items-center gap-2 text-sm font-semibold text-violet-600 hover:text-violet-700">
                                Recover account access
                                <i data-lucide="arrow-right" class="size-4"></i>
                            </a>
                        </div>
                    </aside>

                    <div class="text-center text-xs font-medium text-slate-500 lg:col-span-2">
                        <span class="inline-flex items-center gap-2"><i data-lucide="lock" class="size-4"></i>Your data is encrypted and secure</span>
                        <span class="mx-3 text-slate-300">|</span>
                        <span>&copy; {{ now()->year }} {{ $churchName }}</span>
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>
