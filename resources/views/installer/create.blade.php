@php
    $branding = $branding ?? \App\Support\Branding::current();
    $systemName = $branding->systemName();
    $churchName = $branding->churchName();
    $subtitle = 'Initial installation';
    $logoUrl = $branding->logo();
    $settings = $settings ?? $branding->settings;
    $sidebarImage = $branding->sidebarBackground() ?? asset('images/sidebar-church.png');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Install - {{ $systemName }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            .installer-page { font-family: var(--font-app, ui-sans-serif, system-ui, sans-serif); background: linear-gradient(135deg, #09111f 0%, #f6f8fc 45%, #eef2ff 100%); }
        </style>
    </head>
    <body class="installer-page min-h-screen text-slate-900 antialiased">
        <main class="min-h-screen lg:grid lg:grid-cols-[1.05fr_0.95fr]">
            <section class="relative hidden overflow-hidden bg-slate-950 text-white lg:flex lg:flex-col lg:justify-between">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_70%_30%,rgba(168,85,247,.3),transparent_28%),linear-gradient(145deg,rgba(7,13,24,.94),rgba(10,24,54,.88))]"></div>
                <div class="absolute inset-x-0 bottom-0 h-[36%] bg-church-silhouette opacity-75" style="--sidebar-background-image: url('{{ $sidebarImage }}')"></div>
                <div class="relative z-10 p-12 2xl:p-16">
                    <div class="flex items-center gap-4">
                        <div class="grid size-16 place-items-center overflow-hidden rounded-2xl bg-white/10 ring-1 ring-white/10">
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="{{ $churchName }} logo" class="size-full object-contain">
                            @else
                                <i data-lucide="shield-check" class="size-10"></i>
                            @endif
                        </div>
                        <div>
                            <div class="text-3xl font-black">{{ $systemName }}</div>
                            <div class="mt-1 text-sm text-white/70">{{ $subtitle }}</div>
                        </div>
                    </div>

                    <div class="mt-16 max-w-xl">
                        <h1 class="text-5xl font-black leading-[1.05] tracking-tight">Finish setup in one branded step.</h1>
                        <p class="mt-6 text-lg leading-8 text-white/76">Enter your church details, create the first Super Administrator, and the system will prepare the initial workspace for login.</p>
                    </div>

                    <div class="mt-12 grid max-w-xl gap-4 sm:grid-cols-2">
                        @foreach ([
                            ['title' => 'Church profile', 'copy' => 'Name, address, timezone, currency, contact details.'],
                            ['title' => 'Administrator', 'copy' => 'Create the first login with a secure password.'],
                            ['title' => 'Ready for Docker', 'copy' => 'Works after a fresh container deploy.'],
                            ['title' => 'No CLI needed', 'copy' => 'The installer handles the first boot without shell commands.'],
                        ] as $card)
                            <div class="rounded-2xl border border-white/10 bg-white/6 p-5 backdrop-blur">
                                <div class="text-sm font-bold">{{ $card['title'] }}</div>
                                <div class="mt-2 text-sm leading-6 text-white/70">{{ $card['copy'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="relative flex items-center px-4 py-8 sm:px-8 lg:px-12">
                <div class="mx-auto w-full max-w-2xl rounded-[28px] border border-slate-200/80 bg-white/90 p-6 shadow-[0_25px_80px_rgba(15,23,42,.12)] backdrop-blur-xl sm:p-8">
                    <div class="mb-6 flex items-center gap-3 lg:hidden">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $churchName }} logo" class="size-12 rounded-xl object-contain">
                        @else
                            <div class="grid size-12 place-items-center rounded-xl bg-slate-950 text-white"><i data-lucide="shield-check" class="size-7"></i></div>
                        @endif
                        <div>
                            <div class="text-xl font-black text-slate-950">{{ $systemName }}</div>
                            <div class="text-sm text-slate-500">Initial installation</div>
                        </div>
                    </div>

                    <div class="mb-8">
                        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-violet-700">Installation</p>
                        <h2 class="mt-2 text-3xl font-black text-slate-950">Create your church and first administrator</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-500">This runs once on a fresh deployment. After completion, the login screen becomes available for the new admin account.</p>
                    </div>

                    @if ($errors->any())
                        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                            <ul class="space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('install.store') }}" class="space-y-6">
                        @csrf
                        <div class="grid gap-5 md:grid-cols-2">
                            <label class="block text-sm font-semibold text-slate-700">
                                Church Name
                                <input name="church_name" value="{{ old('church_name', $settings['church_name'] ?? $churchName) }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-violet-500 focus:ring-4 focus:ring-violet-100">
                            </label>
                            <label class="block text-sm font-semibold text-slate-700">
                                Church Email
                                <input type="email" name="church_email" value="{{ old('church_email', $settings['church_contact_email'] ?? '') }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-violet-500 focus:ring-4 focus:ring-violet-100">
                            </label>
                            <label class="block text-sm font-semibold text-slate-700 md:col-span-2">
                                Church Address
                                <input name="church_address" value="{{ old('church_address', $settings['church_address'] ?? '') }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-violet-500 focus:ring-4 focus:ring-violet-100">
                            </label>
                            <label class="block text-sm font-semibold text-slate-700">
                                Timezone
                                <select name="church_timezone" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-violet-500 focus:ring-4 focus:ring-violet-100">
                                    @foreach ($timezones as $value => $label)
                                        <option value="{{ $value }}" @selected(old('church_timezone', $settings['church_timezone'] ?? 'UTC') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block text-sm font-semibold text-slate-700">
                                Currency
                                <select name="church_currency" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-violet-500 focus:ring-4 focus:ring-violet-100">
                                    @foreach ($currencies as $currency)
                                        <option value="{{ $currency }}" @selected(old('church_currency', $settings['church_currency'] ?? 'USD') === $currency)>{{ $currency }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block text-sm font-semibold text-slate-700">
                                Contact Phone
                                <input name="church_phone" value="{{ old('church_phone', $settings['church_contact_phone'] ?? '') }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-violet-500 focus:ring-4 focus:ring-violet-100">
                            </label>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <label class="block text-sm font-semibold text-slate-700">
                                Administrator Name
                                <input name="admin_name" value="{{ old('admin_name', 'Church Administrator') }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-violet-500 focus:ring-4 focus:ring-violet-100">
                            </label>
                            <label class="block text-sm font-semibold text-slate-700">
                                Administrator Email
                                <input type="email" name="admin_email" value="{{ old('admin_email', 'admin@kingdomhub.test') }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-violet-500 focus:ring-4 focus:ring-violet-100">
                            </label>
                            <label class="block text-sm font-semibold text-slate-700 md:col-span-2">
                                Administrator Password
                                <input type="password" name="admin_password" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-violet-500 focus:ring-4 focus:ring-violet-100" placeholder="12+ characters, mixed case, number">
                            </label>
                        </div>

                        <button class="inline-flex w-full items-center justify-center rounded-2xl bg-slate-950 px-5 py-4 text-sm font-bold text-white transition hover:bg-slate-800">Complete installation</button>
                    </form>
                </div>
            </section>
        </main>
    </body>
</html>
