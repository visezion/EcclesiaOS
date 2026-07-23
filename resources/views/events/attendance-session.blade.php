<x-app-layout title="Attendance Session" :breadcrumbs="$breadcrumbs">
    @php
        $enabled = $attendanceSession->methods ?? [];
        $links = $session->meeting_links ?? [];
        $onlineMethods = ['zoom', 'google_meet', 'jitsi', 'livekit'];
        $methodOptions = [
            'manual' => ['Manual Check-in', 'Leader records attendance for the member.', 'user-check', 'bg-violet-50 text-violet-600 ring-violet-100'],
            'qr' => ['QR Code', 'Member scans the session code.', 'scan-qr-code', 'bg-blue-50 text-blue-600 ring-blue-100'],
            'geolocation' => ['Geolocation', 'Browser location must be inside the approved radius.', 'map-pin', 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            'kiosk' => ['Kiosk', 'A shared station at the venue records the check-in.', 'monitor', 'bg-slate-50 text-slate-600 ring-slate-100'],
            'face' => ['Face Recognition', 'Face evidence is saved for verification review.', 'scan-face', 'bg-rose-50 text-rose-600 ring-rose-100'],
        ];
        foreach ([
            'zoom' => ['Zoom', 'Built-in Zoom-style room marks online presence.', 'video', 'bg-indigo-50 text-indigo-600 ring-indigo-100'],
            'google_meet' => ['Google Meet', 'Built-in Meet room marks online presence.', 'calendar-clock', 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            'jitsi' => ['Jitsi Meet', 'Built-in Jitsi room marks online presence.', 'radio', 'bg-orange-50 text-orange-600 ring-orange-100'],
            'livekit' => ['LiveKit', 'Built-in LiveKit room marks online presence.', 'radio-tower', 'bg-cyan-50 text-cyan-600 ring-cyan-100'],
        ] as $method => $meta) {
            if (in_array($method, $selectedOnlineMethods ?? [], true)) {
                $methodOptions[$method] = $meta;
            }
        }
        $expected = (int) ($attendanceSession->expected_attendance ?? 0);
        $recordCount = $records->total();
        $completion = $expected > 0 ? min(100, round(($recordCount / $expected) * 100)) : 0;
        $statusTone = [
            'scheduled' => 'bg-blue-50 text-blue-700 ring-blue-100',
            'open' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
            'closed' => 'bg-slate-100 text-slate-700 ring-slate-200',
        ][$attendanceSession->status] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
        $statCards = [
            ['label' => 'Final Records', 'value' => number_format($recordCount), 'hint' => 'unique member records', 'icon' => 'badge-check', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Expected Attendance', 'value' => number_format($expected), 'hint' => 'configured capacity target', 'icon' => 'users-round', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Completion', 'value' => $completion.'%', 'hint' => 'records vs expected', 'icon' => 'gauge', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Enabled Methods', 'value' => count($enabled), 'hint' => 'available check-in choices', 'icon' => 'scan-line', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100'],
        ];
    @endphp

    <div class="space-y-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-lg bg-violet-100 text-violet-600">
                    <i data-lucide="clipboard-check" class="size-7"></i>
                </div>
                <div>
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-2.5 py-1 text-xs ring-1 {{ $statusTone }}">{{ Str::headline($attendanceSession->status) }}</span>
                        <span class="rounded-full bg-slate-50 px-2.5 py-1 text-xs text-slate-600 ring-1 ring-slate-100">{{ Str::headline($session->meeting_type) }}</span>
                    </div>
                    <h1 class="text-2xl font-semibold text-slate-950">{{ $attendanceSession->title }}</h1>
                    <p class="text-sm text-slate-500">{{ $session->event->program?->name }} | {{ $session->title }} | {{ $session->session_date->format('M d, Y') }}</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('event-sessions.meeting', $session) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                    <i data-lucide="video" class="size-4"></i>
                    Meeting Setup
                </a>
                <a href="{{ route('attendance.methods', $attendanceSession) }}" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white hover:bg-violet-700">
                    <i data-lucide="scan-line" class="size-4"></i>
                    Open Check-in
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
            @foreach($statCards as $card)
                <article class="dashboard-card">
                    <div class="flex items-center gap-3">
                        <span class="grid size-11 place-items-center rounded-lg ring-1 {{ $card['tone'] }}">
                            <i data-lucide="{{ $card['icon'] }}" class="size-5"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="truncate text-xs text-slate-500">{{ $card['label'] }}</div>
                            <div class="mt-1 text-2xl text-slate-950">{{ $card['value'] }}</div>
                            <div class="text-xs text-slate-500">{{ $card['hint'] }}</div>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="grid gap-4 xl:grid-cols-[390px_1fr_320px]">
            <form method="POST" action="{{ route('event-sessions.attendance.update', $session) }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                @csrf
                @method('PUT')
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Attendance Policy</h2>
                        <p class="mt-1 text-sm text-slate-500">Configure the allowed methods and final record rules.</p>
                    </div>
                    <i data-lucide="settings" class="size-5 text-violet-600"></i>
                </div>

                <div class="mt-5 space-y-4 text-sm">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-slate-600">
                            Status
                            <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5">
                                @foreach(['scheduled' => 'Scheduled', 'open' => 'Open', 'closed' => 'Closed'] as $key => $label)
                                    <option value="{{ $key }}" @selected($attendanceSession->status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-slate-600">
                            Expected
                            <input name="expected_attendance" type="number" min="0" value="{{ $attendanceSession->expected_attendance }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5">
                        </label>
                    </div>

                    <label class="block text-slate-600">
                        Verification Policy
                        <select name="verification_policy" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5">
                            <option value="any_one" @selected($attendanceSession->verification_policy === 'any_one')>Any One Valid Method</option>
                            <option value="best_confidence" @selected($attendanceSession->verification_policy === 'best_confidence')>Best Confidence Wins</option>
                            <option value="manual_review" @selected($attendanceSession->verification_policy === 'manual_review')>Manual Review Required</option>
                        </select>
                    </label>

                    <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                        <div class="mb-3 flex items-center gap-2 text-slate-900">
                            <i data-lucide="map-pin" class="size-4 text-emerald-600"></i>
                            Location Controls
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block text-slate-600">
                                Latitude
                                <input name="geo_latitude" type="number" step="any" value="{{ $attendanceSession->geo_latitude }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5">
                            </label>
                            <label class="block text-slate-600">
                                Longitude
                                <input name="geo_longitude" type="number" step="any" value="{{ $attendanceSession->geo_longitude }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5">
                            </label>
                        </div>
                        <label class="mt-3 block text-slate-600">
                            Accuracy Radius
                            <input name="geo_radius_meters" type="number" min="10" max="50000" value="{{ $attendanceSession->geo_radius_meters }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5">
                        </label>
                    </div>

                    <div class="grid gap-2">
                        <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2.5">
                            <span>
                                <span class="block text-slate-900">Require authenticated user</span>
                                <span class="text-xs text-slate-500">Members must be signed in before self check-in.</span>
                            </span>
                            <input type="checkbox" name="require_authenticated" value="1" @checked($attendanceSession->require_authenticated) class="rounded border-slate-300 text-violet-600">
                        </label>
                        <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2.5">
                            <span>
                                <span class="block text-slate-900">Allow guests</span>
                                <span class="text-xs text-slate-500">Permit non-member attendance records.</span>
                            </span>
                            <input type="checkbox" name="allow_guests" value="1" @checked($attendanceSession->allow_guests) class="rounded border-slate-300 text-violet-600">
                        </label>
                    </div>
                </div>

                <div class="mt-5">
                    <h3 class="text-sm font-semibold text-slate-950">Allowed Attendance Methods</h3>
                    <div class="mt-3 grid gap-2">
                        @foreach($methodOptions as $method => [$label, $description, $icon, $tone])
                            <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-3 hover:border-violet-200 hover:bg-violet-50/40">
                                <span class="grid size-9 shrink-0 place-items-center rounded-lg ring-1 {{ $tone }}">
                                    <i data-lucide="{{ $icon }}" class="size-4"></i>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-slate-900">{{ $label }}</span>
                                    <span class="block truncate text-xs text-slate-500">{{ $description }}</span>
                                </span>
                                <input type="checkbox" name="methods[]" value="{{ $method }}" @checked(in_array($method, $enabled, true)) class="rounded border-slate-300 text-violet-600">
                            </label>
                        @endforeach
                    </div>
                </div>

                <button class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white hover:bg-violet-700">
                    <i data-lucide="save" class="size-4"></i>
                    Save Attendance Policy
                </button>
            </form>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-2 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">Final Attendance Records</h2>
                        <p class="text-sm text-slate-500">One final record is stored per member for this session.</p>
                    </div>
                    <span class="rounded-full bg-violet-50 px-3 py-1 text-xs text-violet-700 ring-1 ring-violet-100">{{ number_format($records->total()) }} records</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Member</th>
                                <th class="px-5 py-3">Final Method</th>
                                <th class="px-5 py-3">Checked In</th>
                                <th class="px-5 py-3">Evidence</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Record</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($records as $record)
                                @php
                                    $memberName = trim(($record->member?->first_name ?? 'Guest').' '.($record->member?->last_name ?? ''));
                                    $initials = $record->member
                                        ? Str::substr($record->member->first_name, 0, 1).Str::substr($record->member->last_name, 0, 1)
                                        : 'G';
                                    $recordMemberKey = $record->member?->opaqueId() ?? 'guest';
                                @endphp
                                <tr class="hover:bg-slate-50/70">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <span class="grid size-10 place-items-center rounded-full bg-violet-100 text-sm text-violet-700">{{ $initials }}</span>
                                            <span>
                                                <span class="block font-medium text-slate-950">{{ $memberName }}</span>
                                                <span class="text-xs text-slate-500">{{ $record->member?->email ?? 'Guest attendance' }}</span>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-2.5 py-1 text-xs text-slate-700 ring-1 ring-slate-100">
                                            <i data-lucide="{{ $methodOptions[$record->final_method ?? 'manual'][2] ?? 'circle-dot' }}" class="size-3.5"></i>
                                            {{ Str::headline($record->final_method ?? 'manual') }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="text-slate-900">{{ $record->checked_in_at?->format('M d, Y') ?? 'Not recorded' }}</div>
                                        <div class="text-xs text-slate-500">{{ $record->checked_in_at?->format('h:i A') }}</div>
                                    </td>
                                    <td class="px-5 py-4">{{ $record->verifications->count() }} methods</td>
                                    <td class="px-5 py-4">
                                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs text-emerald-700 ring-1 ring-emerald-100">{{ Str::headline($record->status) }}</span>
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <a href="{{ route('attendance.records.show', [$attendanceSession, $recordMemberKey]) }}" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm text-violet-700 hover:bg-violet-50">
                                            <i data-lucide="eye" class="size-4"></i>
                                            View
                                        </a>
                                        <form method="POST" action="{{ route('attendance.records.destroy', $record) }}" onsubmit="return confirm('Delete this attendance record and its verification evidence?')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm text-rose-700 hover:bg-rose-50">
                                                <i data-lucide="trash-2" class="size-4"></i>
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-14 text-center">
                                        <div class="mx-auto grid size-12 place-items-center rounded-lg bg-violet-50 text-violet-600">
                                            <i data-lucide="clipboard-check" class="size-6"></i>
                                        </div>
                                        <h2 class="mt-3 text-base font-semibold text-slate-950">No attendance records yet</h2>
                                        <p class="mt-1 text-sm text-slate-500">Open check-in or join a selected built-in room to create records.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 p-4">{{ $records->links() }}</div>
            </section>

            <aside class="space-y-4">
                <section class="dashboard-card">
                    <h2 class="text-base font-semibold text-slate-950">Session Summary</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Date</dt>
                            <dd class="text-right text-slate-900">{{ $session->session_date->format('M d, Y') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Time</dt>
                            <dd class="text-right text-slate-900">{{ Str::of($session->starts_at)->substr(0, 5) }} - {{ Str::of($session->ends_at)->substr(0, 5) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Venue</dt>
                            <dd class="text-right text-slate-900">{{ $session->venue ?: 'Built-in online room' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Campus</dt>
                            <dd class="text-right text-slate-900">{{ $session->campus?->name ?? 'All campuses' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Check-in Window</dt>
                            <dd class="text-right text-slate-900">{{ $attendanceSession->opens_at?->format('h:i A') }} - {{ $attendanceSession->closes_at?->format('h:i A') }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-semibold text-slate-950">Selected Built-in Rooms</h2>
                        <i data-lucide="video" class="size-5 text-violet-600"></i>
                    </div>
                    <div class="mt-4 space-y-2">
                        @forelse($selectedOnlineMethods ?? [] as $provider)
                            <a href="{{ route('meetings.rooms.show', [$session, $provider]) }}" class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2.5 text-sm hover:bg-violet-50">
                                <span class="flex items-center gap-2 text-slate-900">
                                    <i data-lucide="{{ $methodOptions[$provider][2] ?? 'video' }}" class="size-4 text-violet-600"></i>
                                    {{ Str::headline($provider) }}
                                </span>
                                <span class="text-xs text-slate-500">{{ $links[$provider]['room'] ?? 'Open room' }}</span>
                            </a>
                        @empty
                            <p class="rounded-lg bg-slate-50 p-3 text-sm text-slate-500">No built-in online room was selected for this event session.</p>
                        @endforelse
                    </div>
                </section>

                <section class="dashboard-card">
                    <h2 class="text-base font-semibold text-slate-950">Final Record Rules</h2>
                    <div class="mt-4 space-y-3 text-sm">
                        @foreach([
                            ['One record per person', 'Repeated attempts update evidence, not duplicate people.'],
                            ['Multiple methods allowed', 'QR, location, face, kiosk, manual, or selected online rooms can verify attendance.'],
                            ['Report ready', 'Records and verification evidence are stored for audit and analytics.'],
                        ] as [$label, $copy])
                            <div class="flex gap-3">
                                <i data-lucide="check-circle-2" class="mt-0.5 size-4 text-emerald-600"></i>
                                <div>
                                    <div class="font-medium text-slate-900">{{ $label }}</div>
                                    <div class="text-xs text-slate-500">{{ $copy }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </aside>
        </section>
    </div>
</x-app-layout>
