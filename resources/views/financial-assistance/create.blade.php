<x-app-layout title="New Financial Assistance Request" :breadcrumbs="$breadcrumbs">
    @php
        $categoryIcons = [
            'emergency_relief' => 'life-buoy',
            'medical' => 'heart-pulse',
            'education' => 'graduation-cap',
            'housing' => 'home',
            'food' => 'receipt',
            'ministry_project' => 'landmark',
            'community_outreach' => 'users',
            'travel_transport' => 'route',
            'funeral_bereavement' => 'heart',
            'missions_evangelism' => 'megaphone',
            'event_program_support' => 'calendar-days',
            'volunteer_support' => 'heart-handshake',
            'operational_support' => 'building-2',
            'facility_repairs' => 'wrench',
            'equipment_technology' => 'monitor',
            'utilities_rent' => 'receipt',
            'other' => 'ellipsis',
        ];
        $categoryGroups = [
            'People & Families' => [
                'description' => 'Care for members, families, and people facing a specific need.',
                'icon' => 'users-round',
                'categories' => ['emergency_relief', 'medical', 'food', 'housing', 'education', 'travel_transport', 'funeral_bereavement'],
            ],
            'Ministry & Community' => [
                'description' => 'Support ministry work, outreach, missions, programs, and serving teams.',
                'icon' => 'heart-handshake',
                'categories' => ['ministry_project', 'community_outreach', 'missions_evangelism', 'event_program_support', 'volunteer_support'],
            ],
            'Church & Campus' => [
                'description' => 'Meet operational, facility, equipment, and essential church needs.',
                'icon' => 'church',
                'categories' => ['operational_support', 'facility_repairs', 'equipment_technology', 'utilities_rent', 'other'],
            ],
        ];
        $stepHasError = [
            1 => $errors->has('category'),
            2 => $errors->hasAny(['title', 'beneficiary_type', 'beneficiary_name', 'purpose', 'justification']),
            3 => $errors->hasAny(['amount', 'needed_by', 'target_campus_id', 'urgency', 'preferred_payment_method', 'payee_name']),
            4 => $errors->has('evidence') || $errors->has('evidence.*'),
        ];
        $initialStep = collect($stepHasError)->search(true) ?: 1;
    @endphp

    <div
        x-data="{
            step: {{ $initialStep }},
            category: @js(old('category', '')),
            files: [],
            goTo(nextStep) {
                this.step = Math.min(4, Math.max(1, nextStep));
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }"
        class="space-y-4"
    >
        @if($errors->any())
            <x-alert type="error">
                <div class="font-bold">Please correct the highlighted information.</div>
                <ul class="mt-1 list-inside list-disc text-xs">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </x-alert>
        @endif

        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-black text-slate-950">Financial Assistance</h1>
                    <span class="rounded-full bg-violet-50 px-3 py-1 text-[10px] font-bold text-violet-700 ring-1 ring-violet-100">New request</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-[10px] font-semibold text-slate-600"><i data-lucide="lock-keyhole" class="size-3"></i>Confidential request</span>
                </div>
                <p class="mt-1 text-sm text-slate-500">Create a private, trackable request for church financial support.</p>
            </div>
            <a href="{{ route('financial-assistance.index') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-xs font-bold text-slate-600 hover:bg-slate-50">
                <i data-lucide="arrow-left" class="size-4"></i>All requests
            </a>
        </header>

        <form method="POST" action="{{ route('financial-assistance.store') }}" enctype="multipart/form-data" novalidate class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            @csrf

            <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                <h2 class="text-lg font-black text-slate-950">Request financial assistance</h2>
                <p class="mt-1 max-w-4xl text-sm leading-6 text-slate-500">Explain the need clearly and attach an invoice, quotation, medical bill, budget, receipt, or supporting letter. The request will be routed through campus review and finance authorization.</p>
            </div>

            <div class="grid xl:grid-cols-[170px_minmax(0,1fr)_300px]">
                <nav aria-label="Request steps" class="border-b border-slate-200 bg-slate-50/60 p-4 xl:border-b-0 xl:border-r">
                    <ol class="grid grid-cols-2 gap-2 sm:grid-cols-4 xl:block xl:space-y-0">
                        @foreach([
                            [1, 'Step 1', 'What kind of help is needed?'],
                            [2, 'Step 2', 'Request details'],
                            [3, 'Step 3', 'Amount, destination, and timing'],
                            [4, 'Step 4', 'Evidence and supporting documents'],
                        ] as [$number, $eyebrow, $label])
                            <li class="relative xl:pb-9 last:pb-0">
                                @unless($loop->last)
                                    <span class="absolute left-[15px] top-8 hidden h-[calc(100%-2rem)] w-px bg-slate-200 xl:block"></span>
                                @endunless
                                <button type="button" @click="goTo({{ $number }})" class="relative flex w-full items-start gap-3 rounded-lg p-2 text-left transition hover:bg-white" :class="step === {{ $number }} ? 'bg-white shadow-sm ring-1 ring-slate-200' : ''">
                                    <span class="grid size-8 shrink-0 place-items-center rounded-full border text-xs font-black transition"
                                        :class="step === {{ $number }} ? 'border-violet-600 bg-violet-600 text-white' : (step > {{ $number }} ? 'border-violet-200 bg-violet-50 text-violet-600' : 'border-slate-300 bg-white text-slate-600')">
                                        <i x-show="step > {{ $number }}" data-lucide="check" class="size-3.5"></i>
                                        <span x-show="step <= {{ $number }}">{{ $number }}</span>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-xs font-bold" :class="step === {{ $number }} ? 'text-violet-700' : 'text-slate-600'">{{ $eyebrow }}</span>
                                        <span class="mt-1 hidden text-xs leading-5 text-slate-600 xl:block">{{ $label }}{{ $number === 4 ? ' *' : '' }}</span>
                                        @if($stepHasError[$number])
                                            <span class="mt-1 block text-[10px] font-bold text-rose-600">Needs attention</span>
                                        @endif
                                    </span>
                                </button>
                            </li>
                        @endforeach
                    </ol>
                </nav>

                <main class="min-w-0 p-5 sm:p-6">
                    <section x-show="step === 1" x-cloak>
                        <div class="mb-5">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-violet-600">Step 1 of 4</p>
                            <h3 class="mt-1 text-lg font-black text-slate-950">Who or what needs financial support?</h3>
                            <p class="mt-1 text-xs text-slate-500">Choose a people, ministry, or church need so the request reaches the right reviewers.</p>
                        </div>
                        <div class="space-y-6">
                            @foreach($categoryGroups as $groupName => $group)
                                <section>
                                    <div class="mb-3 flex items-start gap-3">
                                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="{{ $group['icon'] }}" class="size-4"></i></span>
                                        <div>
                                            <h4 class="text-sm font-black text-slate-900">{{ $groupName }}</h4>
                                            <p class="mt-0.5 text-[11px] leading-4 text-slate-500">{{ $group['description'] }}</p>
                                        </div>
                                    </div>
                                    <div class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                                        @foreach($group['categories'] as $key)
                                            <label class="cursor-pointer">
                                                <input type="radio" name="category" value="{{ $key }}" x-model="category" class="peer sr-only" @checked(old('category') === $key)>
                                                <span class="flex min-h-16 items-center gap-3 rounded-lg border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-violet-300 hover:bg-violet-50/30 peer-checked:border-violet-600 peer-checked:bg-violet-50 peer-checked:text-violet-800 peer-checked:ring-1 peer-checked:ring-violet-600">
                                                    <i data-lucide="{{ $categoryIcons[$key] }}" class="size-5 shrink-0 text-violet-600"></i>
                                                    <span class="min-w-0 flex-1">{{ $categories[$key] }}</span>
                                                    <span class="grid size-5 shrink-0 place-items-center rounded-full border transition" :class="category === @js($key) ? 'border-violet-600 bg-violet-600 text-white' : 'border-slate-300 bg-white text-transparent'"><i data-lucide="check" class="size-3"></i></span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    </section>

                    <section x-show="step === 2" x-cloak>
                        <div class="mb-5">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-violet-600">Step 2 of 4</p>
                            <h3 class="mt-1 text-lg font-black text-slate-950">Request details</h3>
                            <p class="mt-1 text-xs text-slate-500">Tell the reviewers who needs assistance, what it will cover, and why it is needed.</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="sm:col-span-2"><span class="text-xs font-bold text-slate-700">Request title *</span><input name="title" value="{{ old('title') }}" required maxlength="180" placeholder="Example: Emergency medical treatment support" class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm"></label>
                            <label><span class="text-xs font-bold text-slate-700">Who or what will receive support? *</span><select name="beneficiary_type" required class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm"><option value="">Select person, ministry, or church</option>@foreach($beneficiaries as $key => $label)<option value="{{ $key }}" @selected(old('beneficiary_type') === $key)>{{ $label }}</option>@endforeach</select></label>
                            <label><span class="text-xs font-bold text-slate-700">Person, ministry, or church name *</span><input name="beneficiary_name" value="{{ old('beneficiary_name') }}" required maxlength="180" placeholder="Enter the person, family, ministry, church, or project" class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm"></label>
                            <label class="sm:col-span-2"><span class="text-xs font-bold text-slate-700">What will the money be used for? *</span><textarea name="purpose" rows="4" required minlength="20" maxlength="10000" placeholder="List the exact expense, service, items, or project the assistance will cover." class="mt-1.5 w-full rounded-lg border-slate-200 text-sm">{{ old('purpose') }}</textarea></label>
                            <label class="sm:col-span-2"><span class="text-xs font-bold text-slate-700">Why is church assistance necessary? *</span><textarea name="justification" rows="4" required minlength="20" maxlength="10000" placeholder="Explain the circumstances, other funding considered, expected impact, and why this request is appropriate." class="mt-1.5 w-full rounded-lg border-slate-200 text-sm">{{ old('justification') }}</textarea></label>
                        </div>
                    </section>

                    <section x-show="step === 3" x-cloak>
                        <div class="mb-5">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-violet-600">Step 3 of 4</p>
                            <h3 class="mt-1 text-lg font-black text-slate-950">Amount, destination, and timing</h3>
                            <p class="mt-1 text-xs text-slate-500">Set the requested amount, receiving campus, urgency, and payment details.</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label><span class="text-xs font-bold text-slate-700">Amount requested *</span><div class="mt-1.5 flex h-10 rounded-lg border border-slate-200 bg-white focus-within:border-violet-400 focus-within:ring-1 focus-within:ring-violet-400"><span class="grid place-items-center border-r border-slate-200 px-3 text-xs font-black text-slate-500">{{ $currency }}</span><input name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" required placeholder="0.00" class="min-w-0 flex-1 border-0 bg-transparent text-sm focus:ring-0"></div></label>
                            <label><span class="text-xs font-bold text-slate-700">Needed by</span><input name="needed_by" type="date" min="{{ now()->toDateString() }}" value="{{ old('needed_by') }}" class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm"></label>
                            <label><span class="text-xs font-bold text-slate-700">Receiving campus or headquarters *</span><select name="target_campus_id" required class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm"><option value="">Select receiving campus</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected((string) old('target_campus_id', auth()->user()->campus_id) === (string) $campus->id)>{{ $campus->name }}{{ Str::contains(Str::lower($campus->type.' '.$campus->slug), ['main', 'headquarter']) ? ' — HQ' : '' }}</option>@endforeach</select>@unless($canRouteAcrossCampuses)<span class="mt-1 block text-[10px] text-slate-400">Requests are routed to your assigned campus.</span>@endunless</label>
                            <label><span class="text-xs font-bold text-slate-700">Urgency *</span><select name="urgency" required class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm">@foreach($urgencies as $key => $label)<option value="{{ $key }}" @selected(old('urgency', 'normal') === $key)>{{ $label }}</option>@endforeach</select></label>
                            <label><span class="text-xs font-bold text-slate-700">Preferred payment method</span><select name="preferred_payment_method" class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm"><option value="">No preference</option>@foreach(['bank_transfer' => 'Bank transfer', 'cash' => 'Cash', 'cheque' => 'Cheque', 'vendor_payment' => 'Pay vendor directly', 'mobile_money' => 'Mobile money', 'other' => 'Other'] as $key => $label)<option value="{{ $key }}" @selected(old('preferred_payment_method') === $key)>{{ $label }}</option>@endforeach</select></label>
                            <label><span class="text-xs font-bold text-slate-700">Payee name</span><input name="payee_name" value="{{ old('payee_name') }}" maxlength="180" placeholder="Who should receive payment?" class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm"></label>
                        </div>
                    </section>

                    <section x-show="step === 4" x-cloak>
                        <div class="mb-5">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-violet-600">Step 4 of 4</p>
                            <h3 class="mt-1 text-lg font-black text-slate-950">Evidence and supporting documents</h3>
                            <p class="mt-1 text-xs text-slate-500">Attach 1–5 files. Accepted: images, PDF, Word, Excel, CSV, or text. Maximum 10 MB each.</p>
                        </div>
                        <label class="flex min-h-52 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-8 text-center transition hover:border-violet-400 hover:bg-violet-50/40">
                            <span class="grid size-12 place-items-center rounded-full bg-violet-50 text-violet-600"><i data-lucide="upload" class="size-6"></i></span>
                            <span class="mt-3 text-sm font-black text-slate-800">Choose evidence files</span>
                            <span class="mt-1 text-xs text-slate-500" x-text="files.length ? files.length + ' file(s) selected' : 'Invoices, quotes, bills, budgets, receipts, or supporting letters'"></span>
                            <input type="file" name="evidence[]" multiple required accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt" class="sr-only" @change="files = Array.from($event.target.files)">
                        </label>
                        <div x-show="files.length" x-cloak class="mt-3 grid gap-2 sm:grid-cols-2">
                            <template x-for="file in files" :key="file.name">
                                <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-xs">
                                    <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-600"><i data-lucide="clipboard-check" class="size-4"></i></span>
                                    <span class="min-w-0 flex-1 truncate font-semibold text-slate-700" x-text="file.name"></span>
                                    <span class="shrink-0 text-[10px] text-slate-400" x-text="(file.size / 1024 / 1024).toFixed(1) + ' MB'"></span>
                                </div>
                            </template>
                        </div>
                    </section>
                </main>

                <aside class="border-t border-slate-200 bg-slate-50/40 p-5 xl:border-l xl:border-t-0">
                    <h3 class="font-black text-slate-950">Approval workflow</h3>
                    <ol class="mt-5">
                        @foreach([
                            ['Submit request', 'Your information and evidence are securely recorded.'],
                            ['Campus review', 'The receiving campus confirms the need and local context.'],
                            ['Finance authorization', 'Finance verifies amount, policy, and available funding.'],
                            ['Disbursement', 'Payment is recorded with a traceable reference.'],
                        ] as [$title, $description])
                            <li class="relative flex gap-3 pb-6 last:pb-0">
                                <span class="grid size-8 shrink-0 place-items-center rounded-full border border-violet-500 bg-white text-xs font-black text-violet-700">{{ $loop->iteration }}</span>
                                @unless($loop->last)<span class="absolute left-[15px] top-8 h-[calc(100%-2rem)] border-l border-dashed border-violet-300"></span>@endunless
                                <div class="pt-0.5"><div class="text-xs font-black text-slate-800">{{ $title }}</div><p class="mt-1 text-[10px] leading-4 text-slate-500">{{ $description }}</p></div>
                            </li>
                        @endforeach
                    </ol>
                    <div class="mt-6 rounded-lg border border-violet-200 bg-violet-50 p-4">
                        <div class="flex gap-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-white text-violet-600"><i data-lucide="shield-check" class="size-5"></i></span>
                            <div><div class="text-xs font-black text-violet-900">Private and auditable</div><p class="mt-1 text-[10px] leading-4 text-violet-700">Evidence is stored privately. Access is limited by church, campus, role, and approval responsibility.</p></div>
                        </div>
                    </div>
                </aside>
            </div>

            <footer class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-white px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div class="text-[10px] text-slate-500">
                    <span x-text="'Step ' + step + ' of 4'"></span>
                    <span class="mx-2 text-slate-300">•</span>
                    <span>Your progress remains on this page until submitted.</span>
                </div>
                <div class="grid gap-2 sm:flex">
                    <a x-show="step === 1" href="{{ route('financial-assistance.index') }}" class="inline-flex h-10 min-w-32 items-center justify-center rounded-lg border border-slate-200 bg-white px-5 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancel</a>
                    <button x-show="step > 1" type="button" @click="goTo(step - 1)" class="inline-flex h-10 min-w-32 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-5 text-xs font-bold text-slate-600 hover:bg-slate-50"><i data-lucide="arrow-left" class="size-3.5"></i>Back</button>
                    <button x-show="step < 4" type="button" @click="goTo(step + 1)" class="inline-flex h-10 min-w-44 items-center justify-center gap-2 rounded-lg bg-violet-600 px-5 text-xs font-bold text-white hover:bg-violet-700">Continue<i data-lucide="arrow-right" class="size-3.5"></i></button>
                    <button x-show="step === 4" type="submit" class="inline-flex h-10 min-w-52 items-center justify-center gap-2 rounded-lg bg-violet-600 px-5 text-xs font-bold text-white hover:bg-violet-700"><i data-lucide="send" class="size-3.5"></i>Submit for approval</button>
                </div>
            </footer>
        </form>
    </div>
</x-app-layout>
