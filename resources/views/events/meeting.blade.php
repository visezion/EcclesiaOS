<x-app-layout title="Meeting" :breadcrumbs="$breadcrumbs">
    @php
        $links = $session->meeting_links ?? [];
        $enabledMethods = $session->attendanceSession?->methods ?? [];
        $selectedProviders = array_keys($links);
    @endphp
    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-lg bg-violet-100 text-violet-600"><i data-lucide="video" class="size-7"></i></div>
                <div>
                    <h1 class="text-2xl font-semibold text-slate-950">{{ $session->title }}</h1>
                    <p class="text-sm text-slate-500">{{ $session->event->program?->name }} | {{ $session->event->title }}</p>
                </div>
            </div>
            <a href="{{ route('event-sessions.attendance', $session) }}" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white">
                <i data-lucide="clipboard-check" class="size-4"></i>Attendance Session
            </a>
        </div>

        @if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('event-sessions.meeting.update', $session) }}" class="grid gap-4 xl:grid-cols-[1fr_420px]">
            @csrf
            @method('PUT')
            <main class="space-y-4">
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
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

                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
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
                <button class="rounded-lg bg-violet-600 px-5 py-2.5 text-sm text-white">Save Meeting</button>
            </main>

            <aside class="space-y-4">
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-4 text-base text-slate-950">Meeting Schedule</h2>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-500">Date</dt><dd>{{ $session->session_date->format('M d, Y') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Start</dt><dd>{{ Str::of($session->starts_at)->substr(0,5) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">End</dt><dd>{{ $session->ends_at ? Str::of($session->ends_at)->substr(0,5) : '-' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Time Zone</dt><dd>{{ $session->timezone }}</dd></div>
                    </dl>
                </section>
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-4 text-base text-slate-950">Online Auto Attendance</h2>
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
        </form>
    </div>
</x-app-layout>
