<x-app-layout title="Member Registration" :chromeless="true">
    @php
        $branding = \App\Support\Branding::current();
        $completed = session('registration_complete');
        $selectedInterests = collect(old('interests', []));
        $fieldClass = 'mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-violet-400 focus:ring-4 focus:ring-violet-100';
        $systemName = $branding->systemName();
        $logoUrl = $branding->logo();
        $downloadUrl = config('church.download_url');
        $documentationUrl = config('church.documentation_url');
        $primaryHref = auth()->check()
            ? (auth()->user()->hasAnyRole(['Member']) && ! auth()->user()->hasPermission('view dashboard') ? route('bible.index') : route('dashboard'))
            : route('login');
        $primaryLabel = auth()->check() ? 'Open App' : 'Get Started';
    @endphp

    <div x-data="{ mobileMenu: false }" class="relative min-h-screen overflow-x-clip bg-slate-50">
        <div class="pointer-events-none absolute -left-24 top-24 size-80 rounded-full bg-violet-200/45 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-32 bottom-10 size-96 rounded-full bg-sky-200/40 blur-3xl"></div>

        <header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
            <nav class="mx-auto flex h-[70px] max-w-[1440px] items-center justify-between gap-5 px-5 sm:px-8 lg:px-[5.5rem]">
                <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3" aria-label="{{ $systemName }} home">
                    <span class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-lg">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $branding->churchName() }} logo" class="size-full object-contain">
                        @else
                            <span class="grid size-full place-items-center bg-[var(--brand-primary)] text-white"><i data-lucide="cross" class="size-6"></i></span>
                        @endif
                    </span>
                    <span class="truncate text-[1.6rem] font-black tracking-tight text-slate-950">{{ $systemName }}</span>
                </a>

                <div class="hidden items-center gap-9 text-sm font-semibold text-slate-700 lg:flex">
                    <a href="{{ route('members.self-register') }}" class="text-violet-600">Member Registration</a>
                    <a href="{{ route('features') }}" class="hover:text-violet-600">Features</a>
                    <a href="{{ route('home').'#solutions' }}" class="hover:text-violet-600">Solutions</a>
                    <a href="{{ route('home').'#resources' }}" class="hover:text-violet-600">Resources</a>
                    <a href="{{ $downloadUrl }}" class="hover:text-violet-600">Download</a>
                    <a href="{{ $documentationUrl }}" target="_blank" rel="noopener noreferrer" class="hover:text-violet-600">Documentation</a>
                    <a href="{{ route('home').'#about' }}" class="hover:text-violet-600">About Us</a>
                </div>

                <div class="hidden items-center gap-3 sm:flex">
                    <span class="grid size-9 place-items-center text-slate-700"><i data-lucide="moon" class="size-5"></i></span>
                    @guest
                        <a href="{{ route('login') }}" class="rounded-lg border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-800 hover:bg-slate-50">Login</a>
                    @endguest
                    <a href="{{ $primaryHref }}" class="rounded-lg bg-[var(--brand-primary)] px-5 py-3 text-sm font-bold text-white shadow-lg transition hover:brightness-95">{{ $primaryLabel }}</a>
                </div>

                <button type="button" @click="mobileMenu = ! mobileMenu" class="grid size-10 place-items-center rounded-lg border border-slate-200 bg-white text-slate-800 sm:hidden" aria-label="Toggle navigation">
                    <i data-lucide="menu" class="size-5"></i>
                </button>
            </nav>
            <div x-cloak x-show="mobileMenu" x-transition class="border-t border-slate-200 bg-white p-5 sm:hidden">
                <div class="grid gap-2 text-sm font-semibold text-slate-700">
                    <a href="{{ route('members.self-register') }}" @click="mobileMenu = false" class="rounded-lg bg-violet-50 px-3 py-2 text-violet-700">Member Registration</a>
                    <a href="{{ route('features') }}" class="rounded-lg px-3 py-2">Features</a>
                    <a href="{{ route('home').'#solutions' }}" class="rounded-lg px-3 py-2">Solutions</a>
                    <a href="{{ route('home').'#resources' }}" class="rounded-lg px-3 py-2">Resources</a>
                    <a href="{{ $downloadUrl }}" class="rounded-lg px-3 py-2">Download</a>
                    <a href="{{ $documentationUrl }}" target="_blank" rel="noopener noreferrer" class="rounded-lg px-3 py-2">Documentation</a>
                    <a href="{{ route('home').'#about' }}" class="rounded-lg px-3 py-2">About Us</a>
                    @guest
                        <a href="{{ route('login') }}" class="rounded-lg border border-slate-200 px-3 py-2">Login</a>
                    @endguest
                    <a href="{{ $primaryHref }}" class="rounded-lg bg-[var(--brand-primary)] px-3 py-3 text-center text-white">{{ $primaryLabel }}</a>
                </div>
            </div>
        </header>

        <main class="relative z-10 mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 sm:py-10 lg:grid-cols-[minmax(280px,0.68fr)_minmax(0,1.32fr)] lg:px-8">
            <aside class="relative overflow-hidden rounded-3xl bg-[#17102f] p-6 text-white shadow-2xl shadow-violet-950/20 sm:p-8 lg:sticky lg:top-8 lg:h-fit">
                <div class="absolute -right-16 -top-16 size-56 rounded-full bg-violet-500/25 blur-2xl"></div>
                <div class="absolute -bottom-20 -left-16 size-64 rounded-full bg-sky-500/20 blur-3xl"></div>
                <div class="relative">
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-bold text-violet-100">
                        <i data-lucide="sparkles" class="size-3.5"></i>
                        You belong here
                    </span>
                    <h1 class="mt-6 text-3xl font-black leading-tight text-white sm:text-4xl">Welcome to our church family.</h1>
                    <p class="mt-4 text-sm leading-7 text-violet-100/75">Whether today is your first visit or you have been with us for years, this simple form helps us serve you better.</p>

                    <div class="mt-8 grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                        @foreach ([
                            ['user-plus', 'New here?', 'Create your member profile in a few minutes.'],
                            ['user-check', 'Already a member?', 'Reconnect securely without creating a duplicate record.'],
                            ['shield-check', 'Private & protected', 'Your details are only used for church care and communication.'],
                        ] as [$icon, $title, $copy])
                            <div class="flex gap-3 rounded-2xl border border-white/10 bg-white/[0.07] p-4">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white/10 text-violet-200"><i data-lucide="{{ $icon }}" class="size-5"></i></span>
                                <span><strong class="block text-sm">{{ $title }}</strong><span class="mt-1 block text-xs leading-5 text-violet-100/65">{{ $copy }}</span></span>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-8 border-t border-white/10 pt-6">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-violet-200/70">Need help?</p>
                        <p class="mt-2 text-sm leading-6 text-violet-100/70">A welcome-team member can complete this form with you at any time.</p>
                    </div>
                </div>
            </aside>

            <section class="min-w-0">
                @if ($completed)
                    <div class="overflow-hidden rounded-3xl border border-emerald-200 bg-white shadow-xl shadow-slate-200/60">
                        <div class="bg-emerald-50 px-6 py-10 text-center sm:px-10 sm:py-14">
                            <span class="mx-auto grid size-20 place-items-center rounded-full bg-emerald-500 text-white shadow-xl shadow-emerald-200">
                                <i data-lucide="check-circle-2" class="size-10"></i>
                            </span>
                            <p class="mt-6 text-xs font-black uppercase tracking-[0.2em] text-emerald-700">Registration complete</p>
                            <h2 class="mt-3 text-3xl font-black text-slate-950">Thank you, {{ $completed['first_name'] }}!</h2>
                            <p class="mx-auto mt-3 max-w-lg text-sm leading-7 text-slate-600">
                                Your information was received securely. Our welcome team now has what they need to help you feel connected.
                            </p>
                        </div>
                        <div class="grid gap-4 p-6 sm:grid-cols-2 sm:p-8">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Confirmation reference</div>
                                <div class="mt-2 font-mono text-lg font-black tracking-wider text-slate-950">{{ $completed['reference'] }}</div>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Today’s check-in</div>
                                <div class="mt-2 flex items-center gap-2 font-bold {{ $completed['checked_in'] ? 'text-emerald-700' : 'text-slate-700' }}">
                                    <i data-lucide="{{ $completed['checked_in'] ? 'badge-check' : 'calendar-days' }}" class="size-5"></i>
                                    {{ $completed['checked_in'] ? 'You are checked in' : 'Not requested' }}
                                </div>
                            </div>
                            @if ($completed['account_created'] ?? false)
                                <div class="rounded-2xl border border-violet-200 bg-violet-50 p-4 sm:col-span-2">
                                    <div class="flex items-start gap-3">
                                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-violet-600 text-white"><i data-lucide="key-round" class="size-5"></i></span>
                                        <div>
                                            <div class="font-black text-slate-950">Your member login is ready</div>
                                            <p class="mt-1 text-sm leading-6 text-slate-600">Sign in with <strong>{{ $completed['email'] }}</strong> and the password you created.</p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <a href="{{ route('members.self-register') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50">
                                Register another person
                            </a>
                            <a href="{{ ($completed['account_created'] ?? false) ? route('login') : route('home') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-violet-200 hover:bg-violet-700">
                                {{ ($completed['account_created'] ?? false) ? 'Sign in to my account' : 'Finish' }} <i data-lucide="arrow-right" class="size-4"></i>
                            </a>
                        </div>
                    </div>
                @else
                    <form
                        method="POST"
                        action="{{ route('members.self-register.store') }}"
                        x-data="{
                            registrationType: @js(old('registration_type', 'new')),
                            createAccount: @js((string) old('create_account', '1') === '1'),
                            showPassword: false
                        }"
                        class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60"
                    >
                        @csrf
                        <input name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                        <div class="border-b border-slate-100 px-5 py-6 sm:px-8">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-violet-600">Let’s get connected</p>
                            <h2 class="mt-2 text-2xl font-black text-slate-950 sm:text-3xl">Tell us a little about yourself</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-500">Required fields are marked with an asterisk. Most people finish in under four minutes.</p>
                        </div>

                        <div class="space-y-7 p-5 sm:p-8">
                            @if ($errors->any())
                                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                                    <div class="flex items-start gap-3">
                                        <i data-lucide="circle-alert" class="mt-0.5 size-5 shrink-0"></i>
                                        <div><strong class="block">Please check the form.</strong><span class="mt-1 block">{{ $errors->first() }}</span></div>
                                    </div>
                                </div>
                            @endif

                            <fieldset>
                                <legend class="text-sm font-black text-slate-950">Which best describes you?</legend>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <label class="relative cursor-pointer">
                                        <input type="radio" name="registration_type" value="new" x-model="registrationType" class="peer sr-only">
                                        <span class="flex h-full items-start gap-3 rounded-2xl border-2 border-slate-200 p-4 transition peer-checked:border-violet-500 peer-checked:bg-violet-50">
                                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-violet-100 text-violet-600"><i data-lucide="user-plus" class="size-5"></i></span>
                                            <span><strong class="block text-sm text-slate-950">I’m new here</strong><span class="mt-1 block text-xs leading-5 text-slate-500">Create my church member profile.</span></span>
                                        </span>
                                    </label>
                                    <label class="relative cursor-pointer">
                                        <input type="radio" name="registration_type" value="returning" x-model="registrationType" class="peer sr-only">
                                        <span class="flex h-full items-start gap-3 rounded-2xl border-2 border-slate-200 p-4 transition peer-checked:border-violet-500 peer-checked:bg-violet-50">
                                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-emerald-100 text-emerald-600"><i data-lucide="user-check" class="size-5"></i></span>
                                            <span><strong class="block text-sm text-slate-950">I’m already a member</strong><span class="mt-1 block text-xs leading-5 text-slate-500">Confirm my connection or check in.</span></span>
                                        </span>
                                    </label>
                                </div>
                                <p x-cloak x-show="registrationType === 'returning'" class="mt-3 rounded-xl bg-sky-50 p-3 text-xs leading-5 text-sky-800">
                                    <i data-lucide="shield-check" class="mr-1 inline size-4"></i>
                                    We match your name with your email or phone. We never display member-directory information here.
                                </p>
                            </fieldset>

                            <section>
                                <div class="mb-4 flex items-center gap-3">
                                    <span class="grid size-8 place-items-center rounded-lg bg-violet-100 text-sm font-black text-violet-700">1</span>
                                    <div><h3 class="font-black text-slate-950">Your details</h3><p class="text-xs text-slate-500">How our welcome team can identify and contact you.</p></div>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <label class="text-sm font-bold text-slate-700">First name *
                                        <input name="first_name" value="{{ old('first_name') }}" required autocomplete="given-name" class="{{ $fieldClass }}" placeholder="Your first name">
                                    </label>
                                    <label class="text-sm font-bold text-slate-700">Last name *
                                        <input name="last_name" value="{{ old('last_name') }}" required autocomplete="family-name" class="{{ $fieldClass }}" placeholder="Your last name">
                                    </label>
                                    <label x-show="registrationType === 'new'" class="text-sm font-bold text-slate-700">What should we call you?
                                        <input name="preferred_name" value="{{ old('preferred_name') }}" autocomplete="nickname" class="{{ $fieldClass }}" placeholder="Preferred name (optional)">
                                    </label>
                                    <label class="text-sm font-bold text-slate-700">Email address <span x-show="createAccount">*</span>
                                        <span class="relative block">
                                            <i data-lucide="mail" class="pointer-events-none absolute left-4 top-[18px] size-4 text-slate-400"></i>
                                            <input name="email" type="email" value="{{ old('email') }}" autocomplete="email" :required="createAccount" class="{{ $fieldClass }} pl-11" placeholder="you@example.com">
                                        </span>
                                    </label>
                                    <label class="text-sm font-bold text-slate-700">Phone number
                                        <span class="relative block">
                                            <i data-lucide="phone" class="pointer-events-none absolute left-4 top-[18px] size-4 text-slate-400"></i>
                                            <input name="phone" value="{{ old('phone') }}" autocomplete="tel" class="{{ $fieldClass }} pl-11" placeholder="+1 555 000 0000">
                                        </span>
                                    </label>
                                    <label class="text-sm font-bold text-slate-700">Church location
                                        <select name="campus_id" class="{{ $fieldClass }}">
                                            <option value="">Choose a location</option>
                                            @foreach ($campuses as $campus)
                                                <option value="{{ $campus->id }}" @selected((string) old('campus_id') === (string) $campus->id)>{{ $campus->name }}{{ $campus->city ? ' · '.$campus->city : '' }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>
                                <p class="mt-3 text-xs text-slate-500">Please provide at least an email address or phone number.</p>
                            </section>

                            <section class="rounded-2xl border border-violet-200 bg-violet-50/60 p-4 sm:p-5">
                                <input type="hidden" name="create_account" value="0">
                                <label class="flex cursor-pointer items-start gap-3">
                                    <input name="create_account" type="checkbox" value="1" x-model="createAccount" class="mt-1 size-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                                    <span>
                                        <strong class="flex items-center gap-2 text-sm text-slate-950"><i data-lucide="key-round" class="size-4 text-violet-600"></i>Create my member login</strong>
                                        <span class="mt-1 block text-xs leading-5 text-slate-600">Use the Bible, messages, and other member services with the safe default Member role.</span>
                                    </span>
                                </label>

                                <div x-cloak x-show="createAccount" x-transition.opacity class="mt-4 grid gap-4 border-t border-violet-200 pt-4 sm:grid-cols-2">
                                    <label class="text-sm font-bold text-slate-700">Password *
                                        <span class="relative block">
                                            <input name="password" :type="showPassword ? 'text' : 'password'" :required="createAccount" autocomplete="new-password" class="{{ $fieldClass }} pr-11" placeholder="At least 8 characters">
                                            <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-[14px] grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-white hover:text-slate-700" :aria-label="showPassword ? 'Hide password' : 'Show password'">
                                                <i data-lucide="eye" class="size-4"></i>
                                            </button>
                                        </span>
                                    </label>
                                    <label class="text-sm font-bold text-slate-700">Confirm password *
                                        <input name="password_confirmation" :type="showPassword ? 'text' : 'password'" :required="createAccount" autocomplete="new-password" class="{{ $fieldClass }}" placeholder="Enter it again">
                                    </label>
                                    <p class="text-xs leading-5 text-slate-500 sm:col-span-2">Use at least 8 characters with uppercase, lowercase, and a number. Your password is securely encrypted.</p>
                                </div>
                            </section>

                            <section x-cloak x-show="registrationType === 'new'" x-transition.opacity>
                                <div class="mb-4 flex items-center gap-3">
                                    <span class="grid size-8 place-items-center rounded-lg bg-violet-100 text-sm font-black text-violet-700">2</span>
                                    <div><h3 class="font-black text-slate-950">A little more about you</h3><p class="text-xs text-slate-500">Optional details that help us care well.</p></div>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <label class="text-sm font-bold text-slate-700">Date of birth
                                        <input name="date_of_birth" type="date" value="{{ old('date_of_birth') }}" autocomplete="bday" class="{{ $fieldClass }}">
                                    </label>
                                    <label class="text-sm font-bold text-slate-700">Gender
                                        <select name="gender" class="{{ $fieldClass }}">
                                            <option value="">Prefer not to specify</option>
                                            @foreach (['Male', 'Female', 'Prefer not to say'] as $gender)
                                                <option value="{{ $gender }}" @selected(old('gender') === $gender)>{{ $gender }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="text-sm font-bold text-slate-700 sm:col-span-2">Address
                                        <input name="address_line" value="{{ old('address_line') }}" autocomplete="street-address" class="{{ $fieldClass }}" placeholder="Street address">
                                    </label>
                                    <label class="text-sm font-bold text-slate-700">City
                                        <input name="city" value="{{ old('city') }}" autocomplete="address-level2" class="{{ $fieldClass }}" placeholder="City">
                                    </label>
                                    <label class="text-sm font-bold text-slate-700">Emergency contact name
                                        <input name="emergency_contact_name" value="{{ old('emergency_contact_name') }}" class="{{ $fieldClass }}" placeholder="Full name">
                                    </label>
                                    <label class="text-sm font-bold text-slate-700 sm:col-span-2">Emergency contact phone
                                        <input name="emergency_contact_phone" value="{{ old('emergency_contact_phone') }}" class="{{ $fieldClass }}" placeholder="Phone number">
                                    </label>
                                </div>
                            </section>

                            <section>
                                <div class="mb-4 flex items-center gap-3">
                                    <span class="grid size-8 place-items-center rounded-lg bg-violet-100 text-sm font-black text-violet-700" x-text="registrationType === 'new' ? '3' : '2'"></span>
                                    <div><h3 class="font-black text-slate-950">How would you like to connect?</h3><p class="text-xs text-slate-500">Select anything you would like to learn more about.</p></div>
                                </div>
                                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($interests as $value => $label)
                                        <label class="cursor-pointer">
                                            <input name="interests[]" type="checkbox" value="{{ $value }}" @checked($selectedInterests->contains($value)) class="peer sr-only">
                                            <span class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-3 text-xs font-bold text-slate-600 transition peer-checked:border-violet-400 peer-checked:bg-violet-50 peer-checked:text-violet-700">
                                                <span class="grid size-5 place-items-center rounded-md border border-slate-300 peer-checked:border-violet-500"><i data-lucide="check" class="size-3"></i></span>
                                                {{ $label }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                    <label class="text-sm font-bold text-slate-700">How did you hear about us?
                                        <select name="how_heard" class="{{ $fieldClass }}">
                                            <option value="">Choose an option</option>
                                            @foreach ($howHeardOptions as $value => $label)
                                                <option value="{{ $value }}" @selected(old('how_heard') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="text-sm font-bold text-slate-700">Best way to contact you *
                                        <select name="preferred_contact" required class="{{ $fieldClass }}">
                                            <option value="email" @selected(old('preferred_contact', 'email') === 'email')>Email</option>
                                            <option value="phone" @selected(old('preferred_contact') === 'phone')>Phone or text</option>
                                        </select>
                                    </label>
                                </div>
                                <label class="mt-4 block text-sm font-bold text-slate-700">Is there anything you would like help with?
                                    <textarea name="support_note" rows="3" class="{{ $fieldClass }}" placeholder="Prayer, pastoral care, a question, or a detail for our welcome team (optional)">{{ old('support_note') }}</textarea>
                                    <span class="mt-1.5 block text-xs font-normal text-slate-500">This note is private and visible only to authorized church staff.</span>
                                </label>
                            </section>

                            <section class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <label class="flex cursor-pointer items-start gap-3">
                                    <input name="check_in_today" type="checkbox" value="1" @checked(old('check_in_today')) class="mt-0.5 size-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                                    <span><strong class="block text-sm text-slate-800">I’m attending today</strong><span class="mt-1 block text-xs leading-5 text-slate-500">Optionally mark me present at the selected church location.</span></span>
                                </label>
                                <label class="flex cursor-pointer items-start gap-3 border-t border-slate-200 pt-3">
                                    <input name="communications_consent" type="checkbox" value="1" @checked(old('communications_consent')) class="mt-0.5 size-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                                    <span><strong class="block text-sm text-slate-800">Keep me connected</strong><span class="mt-1 block text-xs leading-5 text-slate-500">I agree to receive useful church communication using my preferred contact method.</span></span>
                                </label>
                                <label class="flex cursor-pointer items-start gap-3 border-t border-slate-200 pt-3">
                                    <input name="privacy_consent" type="checkbox" value="1" required @checked(old('privacy_consent')) class="mt-0.5 size-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                                    <span><strong class="block text-sm text-slate-800">Privacy consent *</strong><span class="mt-1 block text-xs leading-5 text-slate-500">I confirm these details are mine and may be securely used for membership, pastoral care, and church administration.</span></span>
                                </label>
                            </section>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/70 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                            <span class="inline-flex items-center gap-2 text-xs font-medium text-slate-500"><i data-lucide="shield-check" class="size-4 text-emerald-600"></i>Securely submitted to {{ $church->name }}</span>
                            <button class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-violet-600 px-6 py-3.5 text-sm font-black text-white shadow-lg shadow-violet-200 transition hover:bg-violet-700 focus:ring-4 focus:ring-violet-200 sm:w-auto">
                                Complete registration <i data-lucide="arrow-right" class="size-4"></i>
                            </button>
                        </div>
                    </form>
                @endif

                <footer class="px-4 py-6 text-center text-xs leading-5 text-slate-500">
                    &copy; {{ now()->year }} {{ $branding->churchName() }}. Your information is handled with care and is never shown in this public form.
                </footer>
            </section>
        </main>
    </div>
</x-app-layout>
