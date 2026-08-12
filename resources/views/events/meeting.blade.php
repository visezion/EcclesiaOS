<x-app-layout title="Meeting" :breadcrumbs="$breadcrumbs">
    @php
        $links = $session->meeting_links ?? [];
        $enabledMethods = $session->attendanceSession?->methods ?? [];
        $selectedProviders = array_keys($links);
    @endphp
    <div x-data="{ agendaOpen: {{ $errors->any() ? 'true' : 'false' }} }" class="space-y-6">
        <section class="responsive-page-header">
            <div class="responsive-page-title">
                <div>
                    <div class="mb-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        <span>{{ $session->event->program?->name ?? 'Standalone event' }}</span><span class="text-slate-300">/</span><span>{{ $session->event->title }}</span><span class="text-slate-300">/</span><span>Meeting</span>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="hidden size-14 shrink-0 place-items-center rounded-2xl bg-violet-50 text-violet-600 ring-1 ring-violet-100 sm:grid"><i data-lucide="video" class="size-7"></i></div>
                        <div>
                            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl">{{ $session->title }}</h1>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-500">
                                <span class="inline-flex items-center gap-1.5"><i data-lucide="calendar-days" class="size-3.5 text-violet-600"></i>{{ $session->session_date->format('M d, Y') }}</span>
                                <span class="inline-flex items-center gap-2 rounded-lg bg-black/15 px-3 py-2"><i data-lucide="clock-3" class="size-4 text-violet-200"></i>{{ Str::of($session->starts_at)->substr(0,5) }}{{ $session->ends_at ? ' – '.Str::of($session->ends_at)->substr(0,5) : '' }}</span>
                                @if($session->venue)<span class="inline-flex items-center gap-1.5"><i data-lucide="map-pin" class="size-3.5 text-violet-600"></i>{{ $session->venue }}</span>@endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="responsive-page-actions">
                    @if($session->status === 'draft')
                        <form method="POST" action="{{ route('event-sessions.submit-approval', $session) }}">
                            @csrf
                            <button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="send" class="size-4"></i>Submit for approval</button>
                        </form>
                    @endif
                    <a href="{{ route('event-sessions.attendance', $session) }}" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700"><i data-lucide="clipboard-check" class="size-4"></i>Attendance</a>
                    @if($session->event->program)
                        <a href="{{ route('event-sessions.index', [$session->event->program, $session->event]) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-violet-700 hover:bg-violet-50"><i data-lucide="arrow-left" class="size-4"></i>Sessions</a>
                    @else
                        <a href="{{ route('events.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-violet-700 hover:bg-violet-50"><i data-lucide="arrow-left" class="size-4"></i>Events</a>
                    @endif
                </div>
            </div>
        </section>

        @if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <main class="space-y-4">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex items-center gap-2"><span class="grid size-9 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="list-checks" class="size-4"></i></span><h2 class="text-base font-semibold text-slate-950">Meeting Agenda</h2></div>
                            <p class="text-sm text-slate-500">Add the running order for this meeting and notify each responsible person.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">{{ $agendaSections->count() }} items</span>
                            <button type="button" @click="agendaOpen = !agendaOpen" class="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-3 py-2 text-xs font-medium text-white hover:bg-violet-700"><i data-lucide="plus" class="size-3.5"></i>Add item</button>
                        </div>
                    </div>

                    <div class="relative mt-5 space-y-3 before:absolute before:bottom-5 before:left-[1.2rem] before:top-5 before:w-px before:bg-violet-100">
                        @forelse($agendaSections as $section)
                            <article class="relative rounded-xl border border-slate-200 bg-white p-4 pl-14 shadow-sm transition hover:border-violet-200 hover:shadow-md">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <span class="absolute left-3 top-4 grid size-8 shrink-0 place-items-center rounded-full bg-violet-600 text-sm font-semibold text-white ring-4 ring-violet-50">{{ $section->position }}</span>
                                        <div class="min-w-0">
                                            <h3 class="font-medium text-slate-950">{{ $section->title }}</h3>
                                            <p class="mt-1 text-xs text-slate-500">{{ Str::headline($section->section_type) }} · {{ $section->planned_start_time ? Str::of($section->planned_start_time)->substr(0, 5) : 'Time not set' }}{{ $section->planned_duration_minutes ? ' · '.$section->planned_duration_minutes.' min' : '' }}</p>
                                            @if($section->description)<p class="mt-2 text-sm text-slate-600">{{ $section->description }}</p>@endif
                                            @if($section->resource_reference)<p class="mt-2 text-xs text-violet-700"><i data-lucide="book-open" class="mr-1 inline size-3.5"></i>{{ $section->resource_reference }}</p>@endif
                                            @if($section->attachment_path)<a href="{{ Storage::disk('public')->url($section->attachment_path) }}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1 text-xs text-violet-700 hover:text-violet-900"><i data-lucide="paperclip" class="size-3.5"></i>{{ $section->attachment_name ?: 'Open attachment' }}</a>@endif
                                        </div>
                                    </div>
                                    <div class="text-right text-xs text-slate-500">
                                        @forelse($section->assignments as $assignment)
                                            <div class="font-medium text-slate-700">{{ $assignment->user?->name ?? trim(($assignment->member?->first_name ?? '').' '.($assignment->member?->last_name ?? '')) }}</div>
                                            <div>{{ $assignment->role_title }}</div>
                                        @empty
                                            <span>Unassigned</span>
                                        @endforelse
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-5 text-sm text-slate-500">No agenda items yet. Add the first item below.</div>
                        @endforelse
                    </div>

                    <form x-cloak x-show="agendaOpen" method="POST" action="{{ route('event-sessions.agenda.store', $session) }}" enctype="multipart/form-data" class="mt-5 space-y-4 rounded-xl border border-violet-100 bg-violet-50/40 p-4">
                        @csrf
                        <div class="flex items-center justify-between gap-3 border-b border-violet-100 pb-3"><div><h3 class="text-sm font-semibold text-slate-900">New agenda item</h3><p class="text-xs text-slate-500">Add only the details you need.</p></div><button type="button" @click="agendaOpen = false" class="text-xs font-medium text-slate-500 hover:text-slate-800">Cancel</button></div>
                        <div class="grid gap-3 md:grid-cols-4">
                            <label class="text-sm text-slate-600">Title
                                <input name="title" value="{{ old('title') }}" required placeholder="Opening prayer" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            </label>
                            <label class="text-sm text-slate-600">Type
                                <select name="section_type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    @foreach(['custom'=>'Custom','worship'=>'Worship','prayer'=>'Prayer','sermon'=>'Sermon','offering'=>'Offering','announcement'=>'Announcement','media'=>'Media','hospitality'=>'Hospitality'] as $key => $label)
                                        <option value="{{ $key }}" @selected(old('section_type', 'custom') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-sm text-slate-600">Start time
                                <input name="planned_start_time" value="{{ old('planned_start_time') }}" type="time" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            </label>
                            <label class="text-sm text-slate-600">Duration (min)
                                <input name="planned_duration_minutes" value="{{ old('planned_duration_minutes') }}" type="number" min="1" max="720" placeholder="10" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            </label>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="text-sm text-slate-600">Notes
                                <textarea name="description" rows="2" placeholder="What should happen in this part?" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">{{ old('description') }}</textarea>
                            </label>
                            <label class="text-sm text-slate-600">Bible reference or resource link
                                <input name="resource_reference" value="{{ old('resource_reference') }}" placeholder="John 3:16 or https://..." class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            </label>
                        </div>
                        <div class="grid gap-3 md:grid-cols-4">
                            <label class="text-sm text-slate-600">Responsible type
                                <select name="assignee_type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <option value="">Nobody yet</option>
                                    <option value="user" @selected(old('assignee_type') === 'user')>Staff user</option>
                                    <option value="member" @selected(old('assignee_type') === 'member')>Member</option>
                                </select>
                            </label>
                            <label class="text-sm text-slate-600">Staff user
                                <select name="user_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <option value="">Select user</option>
                                    @foreach($assignableUsers as $user)<option value="{{ $user->id }}" @selected((string) old('user_id') === (string) $user->id)>{{ $user->name }}</option>@endforeach
                                </select>
                            </label>
                            <label class="text-sm text-slate-600">Member
                                <select name="member_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <option value="">Select member</option>
                                    @foreach($assignableMembers as $member)<option value="{{ $member->id }}" @selected((string) old('member_id') === (string) $member->id)>{{ $member->first_name }} {{ $member->last_name }}</option>@endforeach
                                </select>
                            </label>
                            <label class="text-sm text-slate-600">Role
                                <input name="role_title" value="{{ old('role_title') }}" placeholder="Worship leader" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            </label>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 sm:items-end">
                            <label class="text-sm text-slate-600">Optional file
                                <input name="attachment" type="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png" class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs">
                                <span class="mt-1 block text-xs text-slate-400">Maximum 10 MB.</span>
                            </label>
                            <button class="inline-flex items-center justify-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-violet-700"><i data-lucide="plus" class="size-4"></i>Add agenda item</button>
                        </div>
                    </form>
                </section>

                <form method="POST" action="{{ route('event-sessions.meeting.update', $session) }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="mb-4 text-base text-slate-950">Meeting Information</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="text-sm text-slate-600">Meeting Type
                            <select name="meeting_type" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                @foreach(['physical'=>'Physical','online'=>'Online','hybrid'=>'Hybrid'] as $key=>$label)
                                    <option value="{{ $key }}" @selected($session->meeting_type === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm text-slate-600">Capacity
                            <input name="capacity" type="number" min="0" value="{{ $session->capacity }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        </label>
                        <label class="text-sm text-slate-600">Venue
                            <input name="venue" value="{{ $session->venue }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        </label>
                        <label class="text-sm text-slate-600">Address
                            <input name="address" value="{{ $session->address }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        </label>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-base text-slate-950">Built-in Online Rooms</h2>
                            <p class="text-sm text-slate-500">All online methods open inside EcclesiaOS and mark attendance automatically for signed-in members.</p>
                        </div>
                        <a href="{{ route('meeting-integrations.index') }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-violet-700">Adapter Setup</a>
                    </div>
                    <div class="space-y-3">
                        @forelse($enabledMeetingProviders as $provider => $meta)
                            @php
                                $label = $meta['label'];
                                $icon = $meta['icon'];
                                $selected = in_array($provider, $selectedProviders, true);
                                $room = old("meeting_links.{$provider}.room", $links[$provider]['room'] ?? 'kingdomlife-'.$provider.'-'.$session->id);
                            @endphp
                            <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <label class="flex items-center gap-2 text-sm text-slate-800">
                                        <input type="checkbox" name="meeting_links[{{ $provider }}][enabled]" value="1" @checked(old("meeting_links.{$provider}.enabled", $selected)) class="rounded border-slate-300 text-violet-600">
                                        <i data-lucide="{{ $icon }}" class="size-4 text-violet-600"></i>{{ $label }}
                                    </label>
                                    @if($selected && in_array($provider, $enabledMethods, true))
                                        <a href="{{ route('meetings.rooms.show', [$session, $provider]) }}" class="rounded-lg bg-white px-3 py-1.5 text-sm text-violet-700 ring-1 ring-slate-200 hover:bg-violet-50">Open Room</a>
                                    @else
                                        <span class="rounded-lg bg-white px-3 py-1.5 text-sm text-slate-400 ring-1 ring-slate-200">{{ $selected ? 'Save attendance' : 'Not selected' }}</span>
                                    @endif
                                </div>
                                <div class="grid gap-3 md:grid-cols-[1fr_150px]">
                                    <input name="meeting_links[{{ $provider }}][room]" value="{{ $room }}" placeholder="Room ID" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <input name="meeting_links[{{ $provider }}][access_code]" value="{{ old("meeting_links.{$provider}.access_code", $links[$provider]['access_code'] ?? '') }}" placeholder="Access Code" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </div>
                                <code class="mt-2 block break-all rounded bg-white px-3 py-2 text-xs text-slate-500">{{ route('meetings.rooms.show', [$session, $provider]) }}</code>
                            </div>
                        @empty
                            <div class="rounded-lg bg-amber-50 p-3 text-sm text-amber-700">No built-in meeting methods are enabled. Enable them in Meeting Method Setup first.</div>
                        @endforelse
                    </div>
                </section>
                <button class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-3 text-sm font-medium text-white shadow-sm hover:bg-violet-700"><i data-lucide="save" class="size-4"></i>Save meeting settings</button>
                </form>
            </main>

            <aside class="space-y-4">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center gap-2"><span class="grid size-9 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="calendar-clock" class="size-4"></i></span><h2 class="text-base font-semibold text-slate-950">Schedule</h2></div>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-500">Date</dt><dd>{{ $session->session_date->format('M d, Y') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Start</dt><dd>{{ Str::of($session->starts_at)->substr(0,5) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">End</dt><dd>{{ $session->ends_at ? Str::of($session->ends_at)->substr(0,5) : '-' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Time Zone</dt><dd>{{ $session->timezone }}</dd></div>
                    </dl>
                </section>
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center gap-2"><span class="grid size-9 place-items-center rounded-lg bg-emerald-50 text-emerald-600"><i data-lucide="radio-tower" class="size-4"></i></span><h2 class="text-base font-semibold text-slate-950">Quick join</h2></div>
                    <div class="space-y-2">
                        @forelse($enabledMeetingProviders as $provider => $meta)
                            @if(in_array($provider, $selectedProviders, true) && in_array($provider, $enabledMethods, true))
                                <a href="{{ route('meetings.rooms.show', [$session, $provider]) }}" class="flex w-full items-center justify-between rounded-lg border border-slate-200 px-3 py-2 text-sm text-violet-700 hover:bg-violet-50">
                                    <span class="inline-flex items-center gap-2"><i data-lucide="{{ $meta['icon'] }}" class="size-4"></i>Open {{ $meta['label'] }}</span>
                                    <i data-lucide="arrow-right" class="size-4"></i>
                                </a>
                            @endif
                        @empty
                            <p class="text-sm text-slate-500">No enabled online methods.</p>
                        @endforelse
                        @if(collect($enabledMeetingProviders)->keys()->intersect($selectedProviders)->intersect($enabledMethods)->isEmpty())
                            <p class="text-sm text-slate-500">No online room was selected for this meeting.</p>
                        @endif
                    </div>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
