<x-app-layout title="Attendance Methods" :breadcrumbs="$breadcrumbs">
    @php
        $methods = $attendanceSession->methods ?? [];
        $onlineMethods = ['zoom', 'google_meet', 'jitsi', 'livekit'];
        $methodMeta = [
            'qr' => ['QR Code', 'Scan or enter the session QR token.', 'scan-qr-code', 'bg-violet-50 text-violet-600 ring-violet-100'],
            'geolocation' => ['Geolocation', 'Check in using browser location.', 'map-pin', 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            'manual' => ['Manual Check-in', 'Leader records attendance.', 'user-check', 'bg-blue-50 text-blue-600 ring-blue-100'],
            'kiosk' => ['Kiosk', 'Use a venue kiosk check-in station.', 'monitor', 'bg-slate-50 text-slate-600 ring-slate-200'],
            'face' => ['Face Recognition', 'Capture face evidence for verification.', 'scan-face', 'bg-orange-50 text-orange-600 ring-orange-100'],
            'zoom' => ['Zoom Room', 'Open the selected built-in Zoom room.', 'video', 'bg-blue-50 text-blue-600 ring-blue-100'],
            'google_meet' => ['Google Meet Room', 'Open the selected built-in Meet room.', 'calendar-clock', 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            'jitsi' => ['Jitsi Room', 'Join the selected built-in Jitsi room.', 'radio', 'bg-violet-50 text-violet-600 ring-violet-100'],
            'livekit' => ['LiveKit Room', 'Open the selected LiveKit room.', 'radio-tower', 'bg-orange-50 text-orange-600 ring-orange-100'],
        ];
        $availableMethods = collect($methodMeta)->filter(fn ($meta, $method) => in_array($method, $methods, true) && (! in_array($method, $onlineMethods, true) || in_array($method, $selectedOnlineMethods ?? [], true)));
        $physicalCount = $availableMethods->keys()->filter(fn ($method) => ! in_array($method, $onlineMethods, true))->count();
        $onlineCount = $availableMethods->keys()->filter(fn ($method) => in_array($method, $onlineMethods, true))->count();
        $session = $attendanceSession->eventSession;
    @endphp

    <div class="space-y-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-lg bg-violet-100 text-violet-600">
                    <i data-lucide="scan-line" class="size-7"></i>
                </div>
                <div>
                    <div class="mb-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        <a href="{{ route('attendance.index') }}" class="text-violet-600 hover:text-violet-700">Attendance</a>
                        <i data-lucide="chevron-right" class="size-3"></i>
                        <span>Check-in Methods</span>
                    </div>
                    <h1 class="text-2xl font-semibold text-slate-950">Choose Your Check-in Method</h1>
                    <p class="text-sm text-slate-500">Only one final attendance record is kept. Multiple successful methods become verification evidence.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('event-sessions.attendance', $session) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                    <i data-lucide="settings" class="size-4"></i>
                    Session Setup
                </a>
                <a href="{{ route('event-sessions.meeting', $session) }}" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white hover:bg-violet-700">
                    <i data-lucide="video" class="size-4"></i>
                    Meeting
                </a>
            </div>
        </div>

        @if(session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ $errors->first() }}</div>
        @endif

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="dashboard-card">
                <div class="flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-lg bg-violet-50 text-violet-600 ring-1 ring-violet-100"><i data-lucide="scan-line" class="size-5"></i></span>
                    <div><div class="text-xs text-slate-500">Available Methods</div><div class="mt-1 text-2xl text-slate-950">{{ $availableMethods->count() }}</div><div class="text-xs text-slate-500">for this session</div></div>
                </div>
            </article>
            <article class="dashboard-card">
                <div class="flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-lg bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100"><i data-lucide="map-pin" class="size-5"></i></span>
                    <div><div class="text-xs text-slate-500">Self Attendance</div><div class="mt-1 text-2xl text-slate-950">{{ $physicalCount }}</div><div class="text-xs text-slate-500">QR, geo, kiosk, face</div></div>
                </div>
            </article>
            <article class="dashboard-card">
                <div class="flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-lg bg-blue-50 text-blue-600 ring-1 ring-blue-100"><i data-lucide="video" class="size-5"></i></span>
                    <div><div class="text-xs text-slate-500">Online Rooms</div><div class="mt-1 text-2xl text-slate-950">{{ $onlineCount }}</div><div class="text-xs text-slate-500">selected at creation</div></div>
                </div>
            </article>
            <article class="dashboard-card">
                <div class="flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-lg bg-orange-50 text-orange-600 ring-1 ring-orange-100"><i data-lucide="shield-check" class="size-5"></i></span>
                    <div><div class="text-xs text-slate-500">Policy</div><div class="mt-1 text-xl text-slate-950">{{ Str::headline($attendanceSession->verification_policy) }}</div><div class="text-xs text-slate-500">{{ $attendanceSession->require_authenticated ? 'login required' : 'guest allowed by policy' }}</div></div>
                </div>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1fr_350px]">
            <main class="space-y-4">
                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 p-4">
                        <h2 class="text-base font-semibold text-slate-950">Self Attendance Methods</h2>
                        <p class="text-sm text-slate-500">Physical sessions can use QR, geolocation, kiosk, face evidence, or manual check-in.</p>
                    </div>
                    <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-3">
                        @forelse($availableMethods->reject(fn ($meta, $method) => in_array($method, $onlineMethods, true)) as $method => [$label, $description, $icon, $tone])
                            <form method="POST" action="{{ route('attendance.check-in', $attendanceSession) }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                                @csrf
                                <input type="hidden" name="method" value="{{ $method }}">
                                <input type="hidden" name="provider" value="{{ $method }}">
                                @if($member)
                                    <input type="hidden" name="member_id" value="{{ $member->opaqueId() }}">
                                @endif
                                <div class="flex items-start justify-between gap-3">
                                    <span class="grid size-11 place-items-center rounded-lg ring-1 {{ $tone }}"><i data-lucide="{{ $icon }}" class="size-5"></i></span>
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs text-emerald-700 ring-1 ring-emerald-100">Enabled</span>
                                </div>
                                <h3 class="mt-4 text-base font-semibold text-slate-950">{{ $label }}</h3>
                                <p class="mt-1 min-h-10 text-sm text-slate-500">{{ $description }}</p>
                                @if($method === 'geolocation')
                                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                        <input name="latitude" placeholder="Latitude" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                        <input name="longitude" placeholder="Longitude" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    </div>
                                @endif
                                @if($method === 'face')
                                    <input name="face_reference" placeholder="Face capture reference" class="mt-3 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                @endif
                                <button class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white hover:bg-violet-700">
                                    <i data-lucide="badge-check" class="size-4"></i>
                                    Check In
                                </button>
                            </form>
                        @empty
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-6 text-sm text-slate-500">No physical attendance methods are enabled for this session.</div>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 p-4">
                        <h2 class="text-base font-semibold text-slate-950">Built-in Online Rooms</h2>
                        <p class="text-sm text-slate-500">Only rooms selected when the session was created are shown to the user.</p>
                    </div>
                    <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4">
                        @forelse($availableMethods->filter(fn ($meta, $method) => in_array($method, $onlineMethods, true)) as $method => [$label, $description, $icon, $tone])
                            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <span class="grid size-11 place-items-center rounded-lg ring-1 {{ $tone }}"><i data-lucide="{{ $icon }}" class="size-5"></i></span>
                                    <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs text-blue-700 ring-1 ring-blue-100">Selected</span>
                                </div>
                                <h3 class="mt-4 text-base font-semibold text-slate-950">{{ $label }}</h3>
                                <p class="mt-1 min-h-10 text-sm text-slate-500">{{ $description }}</p>
                                <a href="{{ route('meetings.rooms.show', [$session, $method]) }}" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white hover:bg-violet-700">
                                    <i data-lucide="log-in" class="size-4"></i>
                                    Open Room
                                </a>
                            </article>
                        @empty
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-6 text-sm text-slate-500">No built-in online room was selected for this attendance session.</div>
                        @endforelse
                    </div>
                </section>
            </main>

            <aside class="space-y-4">
                <section class="dashboard-card">
                    <h2 class="text-base font-semibold text-slate-950">Session Details</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Event</dt><dd class="text-right text-slate-900">{{ $session->event->title }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Session</dt><dd class="text-right text-slate-900">{{ $session->title }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Date</dt><dd class="text-right text-slate-900">{{ $session->session_date->format('M d, Y') }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Window</dt><dd class="text-right text-slate-900">{{ $attendanceSession->opens_at?->format('h:i A') }} - {{ $attendanceSession->closes_at?->format('h:i A') }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Expected</dt><dd class="text-right text-slate-900">{{ number_format((int) $attendanceSession->expected_attendance) }}</dd></div>
                    </dl>
                </section>
                <section class="dashboard-card">
                    <h2 class="text-base font-semibold text-slate-950">Verification Rule</h2>
                    <p class="mt-3 text-sm text-slate-500">Every attempt is stored as evidence, but one final attendance record is kept per person for reports and analytics.</p>
                    <div class="mt-4 rounded-lg bg-violet-50 p-4 text-sm text-violet-700 ring-1 ring-violet-100">
                        <i data-lucide="shield-check" class="mr-2 inline size-4"></i>
                        Best valid evidence wins when multiple methods are used.
                    </div>
                </section>
            </aside>
        </section>
    </div>
</x-app-layout>
