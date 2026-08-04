<x-app-layout title="Online Giving" :chromeless="true">
    @php
        $branding = \App\Support\Branding::current();
        $field = 'mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-violet-400 focus:ring-4 focus:ring-violet-100';
    @endphp
    <div class="min-h-screen bg-slate-50">
        <header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/95 backdrop-blur-xl">
            <nav class="mx-auto flex h-[70px] max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3">
                    <span class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-lg bg-[var(--brand-primary)] text-white">
                        @if($branding->logo())<img src="{{ $branding->logo() }}" alt="" class="size-full object-contain">@else<i data-lucide="cross" class="size-6"></i>@endif
                    </span>
                    <span class="truncate text-xl font-black text-slate-950">{{ $branding->systemName() }}</span>
                </a>
                <div class="flex items-center gap-2">
                    <a href="{{ route('members.self-register') }}" class="hidden rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 sm:inline-flex">Member Registration</a>
                    <a href="{{ route('login') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700">Sign in</a>
                </div>
            </nav>
        </header>

        <main class="mx-auto grid max-w-6xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[0.85fr_1.15fr] lg:px-8 lg:py-12">
            <section class="relative overflow-hidden rounded-3xl bg-[#111c38] p-7 text-white shadow-2xl sm:p-9">
                <div class="absolute -right-20 -top-20 size-64 rounded-full bg-violet-500/25 blur-3xl"></div>
                <div class="relative">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold text-violet-100"><i data-lucide="heart" class="size-4"></i>Secure online giving</span>
                    <h1 class="mt-6 text-4xl font-black leading-tight text-white">Generosity that makes a difference.</h1>
                    <p class="mt-4 text-sm leading-7 text-slate-300">Support the ministry of {{ $church->name }} through a secure, one-time online gift.</p>
                    <div class="mt-8 space-y-3">
                        @foreach([['shield-check','Protected checkout','Payment details are entered securely on Stripe.'],['receipt','Accurate records','Only verified successful payments enter the finance ledger.'],['calendar-check','Correct payment date','The recorded date comes from the payment gateway.']] as [$icon,$title,$copy])
                            <div class="flex gap-3 rounded-2xl border border-white/10 bg-white/[0.06] p-4">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white/10 text-violet-200"><i data-lucide="{{ $icon }}" class="size-5"></i></span>
                                <span><strong class="block text-sm">{{ $title }}</strong><span class="mt-1 block text-xs leading-5 text-slate-300">{{ $copy }}</span></span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            @php($firstProvider = collect($providers)->first(fn ($item) => $item['configured']))
            @php($defaultProvider = old('provider', collect($providers)->search($firstProvider) ?: 'stripe'))
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xl shadow-slate-200/60 sm:p-8" x-data="{ amount: @js(old('amount', '50')), provider: @js($defaultProvider), providers: @js($providers) }">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-violet-600">Make a gift</p>
                <h2 class="mt-2 text-3xl font-black text-slate-950">Choose your amount</h2>
                <p class="mt-2 text-sm text-slate-500">Currency: <strong x-text="providers[provider]?.currency || @js(strtoupper($church->currency))"></strong></p>

                @if($errors->any())
                    <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{{ $errors->first() }}</div>
                @endif
                @unless($gatewayConfigured)
                    <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Online giving is being configured. Please check back shortly.</div>
                @endunless

                <form method="POST" action="{{ route('giving.checkout') }}" class="mt-6 space-y-5">
                    @csrf
                    <fieldset>
                        <legend class="text-sm font-bold text-slate-700">Payment method *</legend>
                        <div class="mt-2 grid gap-2 sm:grid-cols-3">
                            @foreach($providers as $key => $option)
                                <label class="relative rounded-xl border-2 p-3 transition" :class="provider === '{{ $key }}' ? 'border-violet-500 bg-violet-50' : 'border-slate-200 {{ $option['configured'] ? 'cursor-pointer' : 'cursor-not-allowed opacity-50' }}">
                                    <input name="provider" type="radio" value="{{ $key }}" x-model="provider" @disabled(!$option['configured']) class="sr-only">
                                    <strong class="block text-sm text-slate-900">{{ $option['label'] }}</strong>
                                    <span class="mt-1 block text-[11px] text-slate-500">{{ $option['region'] }} · {{ $option['currency'] }}</span>
                                    @unless($option['configured'])<span class="mt-1 block text-[10px] font-bold text-amber-700">Not configured</span>@endunless
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-5">
                        @foreach([25,50,100,250,500] as $preset)
                            <button type="button" @click="amount = '{{ $preset }}'" :class="amount == '{{ $preset }}' ? 'border-violet-500 bg-violet-50 text-violet-700' : 'border-slate-200 text-slate-600'" class="rounded-xl border px-2 py-3 text-sm font-black"><span x-text="providers[provider]?.currency"></span> {{ $preset }}</button>
                        @endforeach
                    </div>
                    <label class="block text-sm font-bold text-slate-700">Gift amount *
                        <input name="amount" x-model="amount" type="number" min="1" max="999999.99" step="0.01" required class="{{ $field }}" placeholder="50.00">
                    </label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="text-sm font-bold text-slate-700">Your name *
                            <input name="donor_name" value="{{ old('donor_name', auth()->user()?->name) }}" required autocomplete="name" class="{{ $field }}">
                        </label>
                        <label class="text-sm font-bold text-slate-700">Email address *
                            <input name="donor_email" value="{{ old('donor_email', auth()->user()?->email) }}" type="email" required autocomplete="email" class="{{ $field }}">
                        </label>
                        <label class="text-sm font-bold text-slate-700">Giving fund
                            <select name="fund_id" class="{{ $field }}"><option value="">General giving</option>@foreach($funds as $fund)<option value="{{ $fund->id }}" @selected((string)old('fund_id')===(string)$fund->id)>{{ $fund->name }}</option>@endforeach</select>
                        </label>
                        <label class="text-sm font-bold text-slate-700">Church location
                            <select name="campus_id" class="{{ $field }}"><option value="">Main church</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected((string)old('campus_id')===(string)$campus->id)>{{ $campus->name }}</option>@endforeach</select>
                        </label>
                    </div>
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <input name="anonymous" type="checkbox" value="1" @checked(old('anonymous')) class="mt-0.5 rounded border-slate-300 text-violet-600">
                        <span><strong class="block text-sm text-slate-800">Show this gift as anonymous</strong><span class="mt-1 block text-xs text-slate-500">Your email is still used by the payment provider for the transaction.</span></span>
                    </label>
                    <button @disabled(!$gatewayConfigured) class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-violet-600 px-6 py-4 text-sm font-black text-white shadow-lg shadow-violet-200 disabled:cursor-not-allowed disabled:opacity-50">
                        Continue to secure payment <i data-lucide="lock-keyhole" class="size-4"></i>
                    </button>
                    <p class="text-center text-xs leading-5 text-slate-500">You will be redirected to the selected provider’s secure checkout. EcclesiaOS never receives or stores your card number.</p>
                </form>
            </section>
        </main>
    </div>
</x-app-layout>
