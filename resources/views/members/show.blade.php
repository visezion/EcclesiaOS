<x-app-layout title="Member Profile" :breadcrumbs="$breadcrumbs">
    @php
        $fullName = trim($member->first_name.' '.$member->last_name);
        $profileDetails = $member->memberProfile;
        $familyMembers = $member->family?->members ?? collect();
        $otherFamilyMembers = $familyMembers->where('id', '!=', $member->id)->values();
        $spouse = $otherFamilyMembers->first();
        $children = $otherFamilyMembers->slice(1);
        $primaryVolunteer = $member->volunteers->first();
        $primaryMinistry = $primaryVolunteer?->ministry?->name ?? $profile['ministry'];
        $volunteerRoles = $member->volunteers->where('status', 'active')->count();
        $attendanceLast90 = $member->attendanceRecords->filter(fn ($record) => $record->service_date && $record->service_date->gte(now()->subDays(90)));
        $attendanceScore = min(100, (int) round(($attendanceLast90->count() / 12) * 100));
        $donationsLastSixMonths = $member->donations->filter(fn ($donation) => $donation->received_at && $donation->received_at->gte(now()->subMonths(6)));
        $givingConsistency = min(100, (int) round(($donationsLastSixMonths->count() / 6) * 100));
        $volunteerHours = (int) $profile['volunteerHours'];
        $profileAddress = collect([$profile['addressLine'], $profile['city'], $profile['state'], $profile['postalCode'], $profile['country']])->filter()->join(', ');
        $profileAddress = $profileAddress !== '' ? $profileAddress : ($member->family?->address ?? 'No address on file');
        $preferences = $profile['communicationPreferences'];
        $journey = $profile['spiritualJourney'];
        $skills = collect($profile['skills']);
        $profileFields = collect([
            $member->first_name,
            $member->last_name,
            $member->email,
            $member->phone,
            $member->status,
            $member->joined_at,
            $member->church_id,
            $member->campus_id,
            $member->family_id,
            $primaryMinistry,
            $profile['dateOfBirth'],
            $profile['gender'] !== 'Not specified' ? $profile['gender'] : null,
            $profile['marital'] !== 'Not specified' ? $profile['marital'] : null,
            $profile['occupation'],
            $profileAddress,
            $profile['emergencyContactName'],
            $profile['careLevel'],
        ]);
        $profileCompletion = (int) round(($profileFields->filter(fn ($value) => filled($value))->count() / $profileFields->count()) * 100);
        $memberType = $member->status === 'new' ? 'New Member' : 'Regular Member';
        $profileStatus = Str::headline($member->status ?: 'active');
        $statusClass = $member->status === 'inactive' ? 'bg-rose-50 text-rose-700 ring-rose-200' : ($member->status === 'new' ? 'bg-violet-50 text-violet-700 ring-violet-200' : 'bg-emerald-50 text-emerald-700 ring-emerald-200');
        $openCareTasks = $member->careTasks->whereNotIn('status', ['resolved', 'completed'])->values();
        $recentCareTasks = $member->careTasks->sortByDesc('updated_at')->take(3);
        $recentPrayers = $member->prayerRequests->sortByDesc('created_at')->take(3);
        $recentInteractions = collect()
            ->merge($member->attendanceRecords->map(fn ($record) => [
                'label' => 'Checked in for service',
                'meta' => $member->campus?->name ?? 'Campus',
                'date' => $record->service_date,
                'icon' => 'calendar-check',
                'color' => 'text-emerald-600 bg-emerald-50',
            ]))
            ->merge($member->donations->map(fn ($donation) => [
                'label' => 'Giving recorded',
                'meta' => Number::currency((float) $donation->amount, $donation->currency ?? $member->church?->currency ?? 'USD'),
                'date' => $donation->received_at,
                'icon' => 'hand-coins',
                'color' => 'text-orange-600 bg-orange-50',
            ]))
            ->merge($member->careTasks->map(fn ($task) => [
                'label' => Str::headline($task->type).' follow-up',
                'meta' => $task->next_action ?: Str::headline($task->status),
                'date' => $task->updated_at,
                'icon' => 'heart-handshake',
                'color' => 'text-violet-600 bg-violet-50',
            ]))
            ->sortByDesc('date')
            ->take(5)
            ->values();
        $timelineItems = $recentInteractions->take(8);
        $attendanceBars = $attendanceHistory->reverse()->values();
        $maxAttendanceBar = max(1, $attendanceBars->count());
        $totalGiven = (float) $givingTotal;
        $latestAttendance = $member->attendanceRecords->sortByDesc('service_date')->first();
        $latestGift = $member->donations->sortByDesc('received_at')->first();
        $latestCareTask = $member->careTasks->sortByDesc('updated_at')->first();
        $latestPrayer = $member->prayerRequests->sortByDesc('created_at')->first();
        $avatarInitials = Str::upper(Str::substr($member->first_name, 0, 1).Str::substr($member->last_name, 0, 1));
        $ring = fn (int $value, string $color) => "background: conic-gradient({$color} 0 {$value}%, #e9eef7 {$value}% 100%)";
    @endphp

    <div
        x-data="{ editOpen: {{ request()->boolean('edit') ? 'true' : 'false' }}, careOpen: false, ministryOpen: false, actionMenu: false }"
        class="space-y-5"
    >
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex min-w-0 items-center gap-4">
                    <a href="{{ route('members.index') }}" class="grid size-10 shrink-0 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50" title="Back to members">
                        <i data-lucide="arrow-left" class="size-4"></i>
                    </a>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl font-semibold tracking-normal text-slate-950">Member Profile</h1>
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs ring-1 {{ $statusClass }}"><i data-lucide="badge-check" class="size-3.5"></i>{{ $profileStatus }}</span>
                        </div>
                        <p class="mt-1 truncate text-sm text-slate-500">{{ $fullName }} | {{ $profile['code'] }} | {{ $member->campus?->name ?? 'Unassigned' }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="mailto:{{ $member->email }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:border-violet-200 hover:text-violet-700">
                        <i data-lucide="message-square-text" class="size-4"></i>
                        Send Message
                    </a>
                    <form method="POST" action="{{ route('members.check-in', $member) }}">
                        @csrf
                        <button class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:border-violet-200 hover:text-violet-700">
                            <i data-lucide="calendar-check" class="size-4"></i>
                            Check In
                        </button>
                    </form>
                    <button type="button" @click="ministryOpen = true" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:border-violet-200 hover:text-violet-700">
                        <i data-lucide="users-round" class="size-4"></i>
                        Assign Ministry
                    </button>
                    <div class="relative">
                        <button type="button" @click="actionMenu = ! actionMenu" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-700">
                            <i data-lucide="pencil" class="size-4"></i>
                            Edit Member
                            <i data-lucide="chevron-down" class="size-4"></i>
                        </button>
                        <div x-cloak x-show="actionMenu" @click.outside="actionMenu = false" class="absolute right-0 z-20 mt-2 w-56 rounded-lg border border-slate-200 bg-white p-1 text-sm shadow-xl">
                            <button type="button" @click="editOpen = true; actionMenu = false" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-slate-700 hover:bg-violet-50 hover:text-violet-700"><i data-lucide="user-pen" class="size-4"></i>Edit profile details</button>
                            <button type="button" @click="careOpen = true; actionMenu = false" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-slate-700 hover:bg-violet-50 hover:text-violet-700"><i data-lucide="heart-handshake" class="size-4"></i>Create care task</button>
                            <form method="POST" action="{{ route('members.check-in', $member) }}">
                                @csrf
                                <button class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-slate-700 hover:bg-violet-50 hover:text-violet-700"><i data-lucide="badge-check" class="size-4"></i>Record check-in</button>
                            </form>
                            <form method="POST" action="{{ route('members.destroy', $member) }}" onsubmit="return confirm('Delete this member and remove the profile from active reports?')">
                                @csrf
                                @method('DELETE')
                                <button class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-rose-600 hover:bg-rose-50"><i data-lucide="trash-2" class="size-4"></i>Delete member</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ $errors->first() }}</div>
        @endif

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.9fr)_repeat(4,minmax(160px,1fr))]">
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm xl:col-span-2">
                <div class="h-1.5 bg-gradient-to-r from-violet-600 via-sky-500 to-emerald-500"></div>
                <div class="p-5">
                    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(280px,.9fr)]">
                        <div class="flex items-center gap-5">
                            <div class="relative grid size-32 shrink-0 place-items-center rounded-full bg-gradient-to-br from-violet-100 via-sky-100 to-emerald-100 text-3xl font-semibold text-violet-700 ring-8 ring-slate-50">
                                {{ $avatarInitials }}
                                <span class="absolute bottom-3 right-3 size-5 rounded-full border-4 border-white bg-emerald-500"></span>
                            </div>
                            <div class="min-w-0">
                                <h1 class="text-3xl font-semibold tracking-normal text-slate-950">{{ $fullName }}</h1>
                                <p class="mt-1 text-sm text-slate-500">Member ID: {{ $profile['code'] }}</p>
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs ring-1 {{ $statusClass }}"><i data-lucide="badge-check" class="size-3.5"></i>{{ $profileStatus }}</span>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs text-emerald-700 ring-1 ring-emerald-200"><i data-lucide="leaf" class="size-3.5"></i>{{ $profile['givingStatus'] }}</span>
                                </div>
                                <div class="mt-4 grid gap-2 text-sm text-slate-600 sm:grid-cols-2">
                                    <span class="inline-flex items-center gap-2"><i data-lucide="calendar-days" class="size-4 text-slate-400"></i>Joined: {{ $profile['joined'] }}</span>
                                    <span class="inline-flex items-center gap-2"><i data-lucide="clock-3" class="size-4 text-slate-400"></i>Member Since: {{ $member->joined_at?->format('M Y') ?? 'N/A' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-3 border-slate-200 text-sm lg:border-l lg:pl-6">
                            <div class="grid grid-cols-[36px_1fr] items-center gap-3">
                                <span class="grid size-9 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="church" class="size-4"></i></span>
                                <span><span class="block text-xs text-slate-500">Campus</span>{{ $member->campus?->name ?? 'Unassigned' }}</span>
                            </div>
                            <div class="grid grid-cols-[36px_1fr] items-center gap-3">
                                <span class="grid size-9 place-items-center rounded-lg bg-sky-50 text-sky-600"><i data-lucide="user-round-check" class="size-4"></i></span>
                                <span><span class="block text-xs text-slate-500">Member Type</span>{{ $memberType }}</span>
                            </div>
                            <div class="grid grid-cols-[36px_1fr] items-center gap-3">
                                <span class="grid size-9 place-items-center rounded-lg bg-rose-50 text-rose-600"><i data-lucide="heart" class="size-4"></i></span>
                                <span><span class="block text-xs text-slate-500">Marital Status</span>{{ $profile['marital'] }}</span>
                            </div>
                            <div class="grid grid-cols-[36px_1fr] items-center gap-3">
                                <span class="grid size-9 place-items-center rounded-lg bg-slate-50 text-slate-600"><i data-lucide="users" class="size-4"></i></span>
                                <span><span class="block text-xs text-slate-500">Family</span>{{ $member->family?->name ?? 'No household' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 text-center shadow-sm">
                <p class="text-xs text-slate-500">Attendance Score</p>
                <div class="mx-auto mt-3 grid size-20 place-items-center rounded-full" style="{{ $ring($attendanceScore, '#10b981') }}">
                    <div class="grid size-14 place-items-center rounded-full bg-white text-lg text-slate-950">{{ $attendanceScore }}%</div>
                </div>
                <p class="mt-2 text-sm text-emerald-600">{{ $attendanceScore >= 70 ? 'Consistent' : 'Needs care' }}</p>
                <p class="text-xs text-slate-500">Last 90 days</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 text-center shadow-sm">
                <p class="text-xs text-slate-500">Giving Consistency</p>
                <div class="mx-auto mt-3 grid size-20 place-items-center rounded-full" style="{{ $ring($givingConsistency, '#059669') }}">
                    <div class="grid size-14 place-items-center rounded-full bg-white text-lg text-slate-950">{{ $givingConsistency }}%</div>
                </div>
                <p class="mt-2 text-sm text-emerald-600">{{ $givingConsistency >= 50 ? 'Faithful' : 'Developing' }}</p>
                <p class="text-xs text-slate-500">Last 6 months</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 text-center shadow-sm">
                <p class="text-xs text-slate-500">Volunteer Hours</p>
                <div class="mt-7 text-4xl text-slate-950">{{ $volunteerHours }}</div>
                <p class="mt-2 text-sm text-slate-500">Hours</p>
                <p class="text-xs text-slate-500">This year</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 text-center shadow-sm">
                <p class="text-xs text-slate-500">Profile Completeness</p>
                <div class="mx-auto mt-3 grid size-20 place-items-center rounded-full" style="{{ $ring($profileCompletion, '#6c4dff') }}">
                    <div class="grid size-14 place-items-center rounded-full bg-white text-lg text-slate-950">{{ $profileCompletion }}%</div>
                </div>
                <p class="mt-2 text-sm text-slate-700">{{ $profileCompletion >= 85 ? 'Excellent' : 'In progress' }}</p>
                <p class="text-xs text-slate-500">{{ $profileCompletion >= 85 ? 'Almost complete' : 'Review missing fields' }}</p>
            </div>
        </section>

        <nav class="flex gap-7 overflow-x-auto border-b border-slate-200 text-sm text-slate-500">
            @foreach([
                ['Overview', 'phone'],
                ['Family', 'users-round'],
                ['Attendance', 'calendar-days'],
                ['Giving', 'hand-coins'],
                ['Ministry Involvement', 'flame'],
                ['Notes', 'square-pen'],
                ['Documents', 'file-text'],
                ['Activity Timeline', 'history'],
            ] as [$item, $icon])
                <a href="#{{ Str::slug($item) }}" class="{{ $loop->first ? 'border-violet-600 text-violet-600' : 'border-transparent hover:border-violet-300 hover:text-violet-600' }} inline-flex shrink-0 items-center gap-2 border-b-2 px-1 pb-3">
                    <i data-lucide="{{ $icon }}" class="size-4"></i>{{ $item }}
                </a>
            @endforeach
        </nav>

        <section id="overview" class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-5">
            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-base text-slate-950"><i data-lucide="user-round" class="size-4 text-violet-600"></i>Personal Information</h2>
                    <button type="button" @click="editOpen = true" class="text-sm text-violet-600">Edit</button>
                </div>
                <dl class="space-y-3 text-sm">
                    @foreach([
                        ['Full Name', $fullName],
                        ['Preferred Name', $profile['preferredName']],
                        ['Date of Birth', $profile['dateOfBirth'] ?: 'Not recorded'],
                        ['Age', $profile['age'] ? $profile['age'].' years' : 'Not recorded'],
                        ['Gender', $profile['gender']],
                        ['Occupation', $profile['occupation'] ?: 'Not recorded'],
                        ['Employer', $profile['employer'] ?: 'Not recorded'],
                        ['Place of Birth', $profile['placeOfBirth'] ?: 'Not recorded'],
                        ['Nationality', $profile['nationality'] ?: 'Not recorded'],
                        ['Member Status', $profileStatus],
                        ['Membership Type', $memberType],
                    ] as [$label, $value])
                        <div class="grid grid-cols-[135px_1fr] gap-3">
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="text-slate-900">{{ filled($value) ? $value : 'N/A' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-base text-slate-950"><i data-lucide="phone" class="size-4 text-violet-600"></i>Contact Information</h2>
                    <button type="button" @click="editOpen = true" class="text-sm text-violet-600">Edit</button>
                </div>
                <div class="space-y-3 text-sm text-slate-700">
                    <div class="grid grid-cols-[28px_1fr] gap-3"><i data-lucide="mail" class="size-4 text-violet-600"></i><span>{{ $member->email ?: 'No email on file' }}</span></div>
                    <div class="grid grid-cols-[28px_1fr] gap-3"><i data-lucide="mail-plus" class="size-4 text-violet-600"></i><span>{{ $profile['alternateEmail'] ?: 'No alternate email' }}</span></div>
                    <div class="grid grid-cols-[28px_1fr] gap-3"><i data-lucide="smartphone" class="size-4 text-violet-600"></i><span>{{ $member->phone ?: 'No mobile phone on file' }}</span></div>
                    <div class="grid grid-cols-[28px_1fr] gap-3"><i data-lucide="phone" class="size-4 text-violet-600"></i><span>{{ $profile['homePhone'] ?: 'No home phone on file' }}</span></div>
                    <div class="grid grid-cols-[28px_1fr] gap-3"><i data-lucide="map-pin" class="size-4 text-violet-600"></i><span>{{ $profileAddress }}</span></div>
                </div>
            </article>

            <article id="family" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-base text-slate-950"><i data-lucide="users-round" class="size-4 text-violet-600"></i>Family & Household</h2>
                    <a href="{{ route('families.index', $member->family ? ['selected' => $member->family->opaqueId()] : []) }}" class="text-sm text-violet-600">View Family Details</a>
                </div>
                <dl class="space-y-3 text-sm">
                    <div class="grid grid-cols-[110px_1fr] gap-3"><dt class="text-slate-500">Spouse</dt><dd>{{ $spouse ? trim($spouse->first_name.' '.$spouse->last_name) : 'Not listed' }}</dd></div>
                    <div class="grid grid-cols-[110px_1fr] gap-3"><dt class="text-slate-500">Children</dt><dd>{{ $children->pluck('first_name')->join(', ') ?: 'None listed' }}</dd></div>
                    <div class="grid grid-cols-[110px_1fr] gap-3"><dt class="text-slate-500">Household Size</dt><dd>{{ $familyMembers->count() ?: 'N/A' }}</dd></div>
                    <div class="grid grid-cols-[110px_1fr] gap-3"><dt class="text-slate-500">Family Status</dt><dd>{{ $member->family ? 'Active Family' : 'Unassigned' }}</dd></div>
                </dl>
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-base text-slate-950"><i data-lucide="church" class="size-4 text-violet-600"></i>Church & Campus Assignment</h2>
                    <button type="button" @click="editOpen = true" class="text-sm text-violet-600">Edit</button>
                </div>
                <dl class="space-y-3 text-sm">
                    <div class="grid grid-cols-[130px_1fr] gap-3"><dt class="text-slate-500">Church</dt><dd>{{ $member->church?->name ?? 'N/A' }}</dd></div>
                    <div class="grid grid-cols-[130px_1fr] gap-3"><dt class="text-slate-500">Campus</dt><dd>{{ $member->campus?->name ?? 'Unassigned' }}</dd></div>
                    <div class="grid grid-cols-[130px_1fr] gap-3"><dt class="text-slate-500">Ministry</dt><dd>{{ $primaryMinistry }}</dd></div>
                    <div class="grid grid-cols-[130px_1fr] gap-3"><dt class="text-slate-500">Sunday Service</dt><dd>{{ $latestAttendance?->service_date?->format('M d, Y') ?? 'No attendance yet' }}</dd></div>
                </dl>
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-base text-slate-950"><i data-lucide="sparkles" class="size-4 text-violet-600"></i>Spiritual Journey</h2>
                    <button type="button" @click="editOpen = true" class="text-sm text-violet-600">Edit</button>
                </div>
                <div class="space-y-3 text-sm">
                    @foreach([
                        ['Salvation Date', filled($journey['salvation_date'] ?? null) ? \Illuminate\Support\Carbon::parse($journey['salvation_date'])->format('M d, Y') : 'Not recorded'],
                        ['Baptism', filled($journey['baptism_date'] ?? null) ? \Illuminate\Support\Carbon::parse($journey['baptism_date'])->format('M d, Y') : 'Not recorded'],
                        ['Discipleship Class', $journey['discipleship_class'] ?? 'Not recorded'],
                        ['Membership Class', $journey['membership_class'] ?? 'Not recorded'],
                    ] as [$label, $value])
                        <div class="grid grid-cols-[24px_1fr_100px] items-center gap-3">
                            <i data-lucide="circle-check" class="size-4 text-emerald-600"></i>
                            <span class="text-slate-500">{{ $label }}</span>
                            <span class="text-right text-slate-900">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
            </article>

            <article id="ministry-involvement" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-base text-slate-950"><i data-lucide="hand-heart" class="size-4 text-violet-600"></i>Ministry Involvement</h2>
                    <button type="button" @click="ministryOpen = true" class="text-sm text-violet-600">Manage</button>
                </div>
                <dl class="space-y-3 text-sm">
                    <div class="grid grid-cols-[130px_1fr] gap-3"><dt class="text-slate-500">Primary Ministry</dt><dd>{{ $primaryMinistry }}</dd></div>
                    <div class="grid grid-cols-[130px_1fr] gap-3"><dt class="text-slate-500">Role</dt><dd>{{ $primaryVolunteer?->role ? Str::headline($primaryVolunteer->role) : 'Team Member' }}</dd></div>
                    <div class="grid grid-cols-[130px_1fr] gap-3"><dt class="text-slate-500">Status</dt><dd>{{ $primaryVolunteer?->status ? Str::headline($primaryVolunteer->status) : 'Not assigned' }}</dd></div>
                    <div class="grid grid-cols-[130px_1fr] gap-3"><dt class="text-slate-500">Assignments</dt><dd>{{ $member->volunteers->count() }}</dd></div>
                </dl>
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-base text-slate-950"><i data-lucide="star" class="size-4 text-violet-600"></i>Skills & Talents</h2>
                    <button type="button" @click="editOpen = true" class="text-sm text-violet-600">Edit</button>
                </div>
                <div class="flex flex-wrap gap-2">
                    @forelse($skills as $skill)
                        <span class="rounded-full bg-violet-50 px-3 py-1 text-xs text-violet-700 ring-1 ring-violet-100">{{ $skill }}</span>
                    @empty
                        <span class="text-sm text-slate-500">No skills recorded.</span>
                    @endforelse
                </div>
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-base text-slate-950"><i data-lucide="messages-square" class="size-4 text-violet-600"></i>Communication Preferences</h2>
                    <button type="button" @click="editOpen = true" class="text-sm text-violet-600">Edit</button>
                </div>
                <dl class="space-y-3 text-sm">
                    <div class="grid grid-cols-[150px_1fr] gap-3"><dt class="text-slate-500">Preferred Contact</dt><dd>{{ Str::headline($preferences['preferred_contact'] ?? 'email') }}</dd></div>
                    <div class="grid grid-cols-[150px_1fr] gap-3"><dt class="text-slate-500">Email Notifications</dt><dd>{{ ($preferences['email_notifications'] ?? false) ? 'Enabled' : 'Disabled' }}</dd></div>
                    <div class="grid grid-cols-[150px_1fr] gap-3"><dt class="text-slate-500">SMS Notifications</dt><dd>{{ ($preferences['sms_notifications'] ?? false) ? 'Enabled' : 'Disabled' }}</dd></div>
                    <div class="grid grid-cols-[150px_1fr] gap-3"><dt class="text-slate-500">Mailing List</dt><dd>{{ ($preferences['mailing_mail'] ?? false) ? 'Enabled' : 'Disabled' }}</dd></div>
                    <div class="grid grid-cols-[150px_1fr] gap-3"><dt class="text-slate-500">Language</dt><dd>{{ config('app.locale') }}</dd></div>
                </dl>
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-base text-slate-950"><i data-lucide="briefcase-medical" class="size-4 text-violet-600"></i>Emergency Contact</h2>
                    <button type="button" @click="editOpen = true" class="text-sm text-violet-600">Edit</button>
                </div>
                <dl class="space-y-3 text-sm">
                    <div class="grid grid-cols-[120px_1fr] gap-3"><dt class="text-slate-500">Contact Name</dt><dd>{{ $profile['emergencyContactName'] ?: ($spouse ? trim($spouse->first_name.' '.$spouse->last_name) : 'Not recorded') }}</dd></div>
                    <div class="grid grid-cols-[120px_1fr] gap-3"><dt class="text-slate-500">Relationship</dt><dd>{{ $profile['emergencyContactRelationship'] ?: ($spouse ? 'Family' : 'N/A') }}</dd></div>
                    <div class="grid grid-cols-[120px_1fr] gap-3"><dt class="text-slate-500">Phone</dt><dd>{{ $profile['emergencyContactPhone'] ?: ($spouse?->phone ?? 'Not recorded') }}</dd></div>
                    <div class="grid grid-cols-[120px_1fr] gap-3"><dt class="text-slate-500">Alternate</dt><dd>{{ $profile['emergencyContactAltPhone'] ?: 'Not recorded' }}</dd></div>
                </dl>
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-base text-slate-950"><i data-lucide="shield-check" class="size-4 text-violet-600"></i>Pastoral Care Status</h2>
                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs text-emerald-700 ring-1 ring-emerald-100">{{ Str::headline($profile['careLevel']) }}</span>
                </div>
                <dl class="space-y-3 text-sm">
                    <div class="grid grid-cols-[130px_1fr] gap-3"><dt class="text-slate-500">Care Level</dt><dd>{{ Str::headline($profile['careLevel']) }}</dd></div>
                    <div class="grid grid-cols-[130px_1fr] gap-3"><dt class="text-slate-500">Pastor</dt><dd>{{ $latestCareTask?->assignedUser?->name ?? 'Unassigned' }}</dd></div>
                    <div class="grid grid-cols-[130px_1fr] gap-3"><dt class="text-slate-500">Last Contact</dt><dd>{{ $latestCareTask?->updated_at?->format('M d, Y') ?? 'No care contact' }}</dd></div>
                    <div class="grid grid-cols-[130px_1fr] gap-3"><dt class="text-slate-500">Care Notes</dt><dd>{{ $profile['careNotes'] ? Str::limit($profile['careNotes'], 55) : ($latestCareTask?->notes ? Str::limit($latestCareTask->notes, 55) : 'No notes recorded') }}</dd></div>
                </dl>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-4">
            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-base text-slate-950"><i data-lucide="list-checks" class="size-4 text-violet-600"></i>Follow-up Tasks</h2>
                    <button type="button" @click="careOpen = true" class="text-sm text-violet-600">Create Task</button>
                </div>
                <div class="space-y-3">
                    @forelse($recentCareTasks as $task)
                        <div class="grid grid-cols-[18px_1fr_auto] items-start gap-3 text-sm">
                            <span class="mt-1 size-3 rounded-sm border border-violet-300"></span>
                            <span>
                                <span class="block text-slate-900">{{ $task->next_action ?: Str::headline($task->type) }}</span>
                                <span class="text-xs text-slate-500">Due {{ $task->due_at?->format('M d, Y') ?? 'not scheduled' }}</span>
                            </span>
                            <span class="rounded-full bg-orange-50 px-2 py-1 text-xs text-orange-700 ring-1 ring-orange-100">{{ Str::headline($task->status) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No follow-up tasks.</p>
                    @endforelse
                </div>
                <a href="{{ route('members.follow-up', ['keyword' => $fullName]) }}" class="mt-4 inline-block text-sm text-violet-600">View All Tasks ({{ $member->careTasks->count() }})</a>
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-base text-slate-950"><i data-lucide="message-circle-heart" class="size-4 text-violet-600"></i>Prayer Requests</h2>
                    <a href="{{ route('prayer-requests.index') }}" class="text-sm text-violet-600">Add Prayer Request</a>
                </div>
                <div class="space-y-3">
                    @forelse($recentPrayers as $request)
                        <div class="grid grid-cols-[18px_1fr_auto] items-start gap-3 text-sm">
                            <span class="mt-1 size-3 rounded-sm border border-slate-300"></span>
                            <span>
                                <span class="block text-slate-900">{{ $request->title }}</span>
                                <span class="text-xs text-slate-500">Requested {{ $request->created_at->format('M d, Y') }}</span>
                            </span>
                            <span class="rounded-full {{ $request->status === 'answered' ? 'bg-emerald-50 text-emerald-700 ring-emerald-100' : 'bg-violet-50 text-violet-700 ring-violet-100' }} px-2 py-1 text-xs ring-1">{{ Str::headline($request->status) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No prayer requests.</p>
                    @endforelse
                </div>
                <a href="{{ route('prayer-requests.index') }}" class="mt-4 inline-block text-sm text-violet-600">View All Requests ({{ $member->prayerRequests->count() }})</a>
            </article>

            <article id="giving" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-base text-slate-950"><i data-lucide="hand-coins" class="size-4 text-violet-600"></i>Giving Summary</h2>
                    <a href="{{ route('finance.index', ['keyword' => $member->email]) }}" class="text-sm text-violet-600">View Giving History</a>
                </div>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p class="text-slate-500">Total Given</p>
                        <p class="mt-1 text-2xl text-slate-950">{{ Number::currency($totalGiven, $member->church?->currency ?? 'USD') }}</p>
                    </div>
                    <div>
                        <p class="text-slate-500">Consistency</p>
                        <p class="mt-1 text-2xl text-emerald-600">{{ $givingConsistency }}%</p>
                    </div>
                </div>
                <div class="mt-4 space-y-2 text-sm">
                    <div class="flex items-center justify-between"><span class="text-slate-500">Last Gift</span><span>{{ $latestGift?->received_at?->format('M d, Y') ?? 'None' }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-slate-500">Gift Count</span><span>{{ $member->donations->count() }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-slate-500">Status</span><span>{{ $profile['givingStatus'] }}</span></div>
                </div>
            </article>

            <article id="attendance" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-base text-slate-950"><i data-lucide="bar-chart-3" class="size-4 text-violet-600"></i>Attendance History</h2>
                    <a href="{{ route('attendance.index', ['keyword' => $member->email]) }}" class="text-sm text-violet-600">View Full History</a>
                </div>
                <div class="flex h-28 items-end gap-2 border-b border-slate-100 pb-2">
                    @forelse($attendanceBars as $index => $record)
                        <div class="flex flex-1 flex-col items-center gap-2">
                            <div class="w-full rounded-t bg-violet-500" style="height: {{ 35 + (($index + 1) / $maxAttendanceBar) * 55 }}%"></div>
                            <span class="text-[10px] text-slate-400">{{ $record->service_date?->format('M j') }}</span>
                        </div>
                    @empty
                        <div class="grid h-full w-full place-items-center text-sm text-slate-500">No attendance history.</div>
                    @endforelse
                </div>
                <div class="mt-3 grid grid-cols-3 gap-3 text-center text-sm">
                    <span><span class="block text-lg text-slate-950">{{ $attendanceScore }}%</span><span class="text-xs text-slate-500">Average</span></span>
                    <span><span class="block text-lg text-slate-950">{{ $attendanceLast90->count() }}</span><span class="text-xs text-slate-500">Services</span></span>
                    <span><span class="block text-lg text-slate-950">{{ $latestAttendance?->service_date?->format('M d') ?? 'N/A' }}</span><span class="text-xs text-slate-500">Last</span></span>
                </div>
            </article>
        </section>

        <section id="activity-timeline" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-base text-slate-950"><i data-lucide="history" class="size-4 text-violet-600"></i>Activity Timeline</h2>
                <a href="{{ route('audit-logs.index', ['keyword' => $member->email ?: $member->first_name]) }}" class="text-sm text-violet-600">View Full Timeline</a>
            </div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                @forelse($timelineItems as $item)
                    <div class="relative rounded-lg border border-slate-100 p-3 text-sm">
                        <span class="grid size-9 place-items-center rounded-lg {{ $item['color'] }}"><i data-lucide="{{ $item['icon'] }}" class="size-4"></i></span>
                        <p class="mt-3 text-slate-950">{{ $item['label'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $item['meta'] }}</p>
                        <p class="mt-2 text-xs text-slate-400">{{ $item['date']?->format('M d, Y h:i A') ?? 'No date' }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No activity for this member yet.</p>
                @endforelse
            </div>
        </section>

        <div x-cloak x-show="editOpen || careOpen || ministryOpen" class="fixed inset-0 z-40 bg-slate-950/40" @click="editOpen = false; careOpen = false; ministryOpen = false"></div>

        <aside x-cloak x-show="editOpen" x-transition class="fixed inset-y-0 right-0 z-50 w-full max-w-5xl overflow-y-auto bg-slate-50 shadow-2xl">
            <div class="sticky top-0 z-10 border-b border-slate-200 bg-white/95 px-6 py-4 backdrop-blur">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 items-center gap-4">
                        <div class="grid size-12 shrink-0 place-items-center rounded-full bg-gradient-to-br from-violet-100 to-sky-100 text-sm font-semibold text-violet-700 ring-4 ring-slate-50">{{ $avatarInitials }}</div>
                        <div class="min-w-0">
                            <h2 class="text-xl font-semibold tracking-normal text-slate-950">Edit Member Profile</h2>
                            <p class="mt-1 truncate text-sm text-slate-500">{{ $fullName }} | {{ $profile['code'] }} | {{ $member->campus?->name ?? 'Unassigned' }}</p>
                        </div>
                    </div>
                    <button type="button" @click="editOpen = false" class="grid size-10 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50">
                        <i data-lucide="x" class="size-5"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <div class="mb-5 grid gap-3 md:grid-cols-3">
                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs text-slate-500">Current Status</p>
                        <p class="mt-2 text-lg font-semibold text-slate-950">{{ $profileStatus }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $memberType }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs text-slate-500">Care Level</p>
                        <p class="mt-2 text-lg font-semibold text-slate-950">{{ Str::headline($profile['careLevel']) }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $openCareTasks->count() }} open care tasks</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs text-slate-500">Profile Completion</p>
                        <p class="mt-2 text-lg font-semibold text-slate-950">{{ $profileCompletion }}%</p>
                        <p class="mt-1 text-xs text-slate-500">Connected to live member data</p>
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    @include('members.partials.form', ['action' => route('members.update', $member), 'method' => 'PUT', 'member' => $profile])
                </div>
            </div>
        </aside>

        <aside x-cloak x-show="careOpen" class="fixed inset-y-0 right-0 z-50 w-full max-w-md overflow-y-auto bg-white p-6 shadow-2xl">
            <div class="mb-5 flex items-center justify-between">
                <h2 class="text-lg text-slate-950">Create Care Task</h2>
                <button type="button" @click="careOpen = false" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"><i data-lucide="x" class="size-5"></i></button>
            </div>
            <form method="POST" action="{{ route('care-tasks.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="member_id" value="{{ $member->opaqueId() }}">
                <div>
                    <label class="text-sm text-slate-600">Care Type</label>
                    <select name="type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option>Counseling</option>
                        <option>Visitation</option>
                        <option>Prayer Request</option>
                        <option>Membership</option>
                        <option>Family Care</option>
                        <option>Hospital Visit</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Assigned To</label>
                    <select name="assigned_user_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="">Unassigned</option>
                        @foreach($users as $user)
                            <option value="{{ $user->opaqueId() }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-sm text-slate-600">Priority</label>
                        <select name="priority" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">Status</label>
                        <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <option value="pending">Pending</option>
                            <option value="assigned">Assigned</option>
                            <option value="in-progress">In Progress</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Due Date</label>
                    <input type="datetime-local" name="due_at" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-sm text-slate-600">Next Action</label>
                    <input name="next_action" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Schedule follow-up call">
                </div>
                <div>
                    <label class="text-sm text-slate-600">Notes</label>
                    <textarea name="notes" rows="4" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Pastoral care notes"></textarea>
                </div>
                <button class="w-full rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white hover:bg-violet-700">Create Task</button>
            </form>
        </aside>

        <aside x-cloak x-show="ministryOpen" class="fixed inset-y-0 right-0 z-50 w-full max-w-md overflow-y-auto bg-white p-6 shadow-2xl">
            <div class="mb-5 flex items-center justify-between">
                <h2 class="text-lg text-slate-950">Assign Ministry</h2>
                <button type="button" @click="ministryOpen = false" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"><i data-lucide="x" class="size-5"></i></button>
            </div>
            <form method="POST" action="{{ route('members.assign-ministry', $member) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="text-sm text-slate-600">Ministry</label>
                    <select name="ministry_id" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        @foreach($ministries as $ministry)
                            <option value="{{ $ministry->id }}" @selected($primaryVolunteer?->ministry_id === $ministry->id)>{{ $ministry->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="w-full rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white hover:bg-violet-700">Assign Ministry</button>
            </form>
        </aside>
    </div>
</x-app-layout>
