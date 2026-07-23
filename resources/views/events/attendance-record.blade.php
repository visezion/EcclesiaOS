<x-app-layout title="Final Attendance Record" :breadcrumbs="$breadcrumbs">
    @php
        $session = $attendanceSession->eventSession;
        $member = $record->member;
        $initials = $member ? Str::upper(Str::substr($member->first_name ?? '', 0, 1).Str::substr($member->last_name ?? '', 0, 1)) : 'G';
        $methodIcons = [
            'qr' => 'scan-qr-code',
            'geolocation' => 'map-pin',
            'manual' => 'user-check',
            'kiosk' => 'monitor',
            'face' => 'scan-face',
            'zoom' => 'video',
            'google_meet' => 'calendar-clock',
            'jitsi' => 'radio',
            'livekit' => 'radio-tower',
        ];
        $successCount = $record->verifications->where('status', 'success')->count();
        $failedCount = $record->verifications->where('status', '!=', 'success')->count();
        $bestConfidence = (int) $record->verifications->max('confidence');
        $statCards = [
            ['label' => 'Verification Attempts', 'value' => $record->verifications->count(), 'hint' => 'stored evidence', 'icon' => 'clipboard-check', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Successful', 'value' => $successCount, 'hint' => 'valid methods', 'icon' => 'badge-check', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Failed', 'value' => $failedCount, 'hint' => 'review evidence', 'icon' => 'shield-check', 'tone' => 'bg-rose-50 text-rose-600 ring-rose-100'],
            ['label' => 'Confidence', 'value' => $bestConfidence.'%', 'hint' => 'best verification', 'icon' => 'gauge', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
        ];
    @endphp

    <div class="space-y-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-lg bg-violet-100 text-violet-600">
                    <i data-lucide="badge-check" class="size-7"></i>
                </div>
                <div>
                    <div class="mb-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        <a href="{{ route('attendance.index') }}" class="text-violet-600 hover:text-violet-700">Attendance</a>
                        <i data-lucide="chevron-right" class="size-3"></i>
                        <a href="{{ route('event-sessions.attendance', $session) }}" class="text-violet-600 hover:text-violet-700">{{ $attendanceSession->title }}</a>
                        <i data-lucide="chevron-right" class="size-3"></i>
                        <span>Final Record</span>
                    </div>
                    <h1 class="text-2xl font-semibold text-slate-950">Attendance Record (Final)</h1>
                    <p class="text-sm text-slate-500">{{ $session->title }} · {{ $session->session_date->format('M d, Y') }}</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('attendance.methods', $attendanceSession) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                    <i data-lucide="scan-line" class="size-4"></i>
                    Methods
                </a>
                <a href="{{ route('event-sessions.attendance', $session) }}" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white hover:bg-violet-700">
                    <i data-lucide="clipboard-check" class="size-4"></i>
                    Attendance Setup
                </a>
            </div>
        </div>

        @if(session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>
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
                            <div class="mt-1 text-2xl text-slate-950">{{ is_numeric($card['value']) ? number_format($card['value']) : $card['value'] }}</div>
                            <div class="text-xs text-slate-500">{{ $card['hint'] }}</div>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="grid gap-4 xl:grid-cols-[360px_1fr]">
            <aside class="space-y-4">
                <section class="dashboard-card text-center">
                    <div class="mx-auto grid size-24 place-items-center rounded-full bg-violet-100 text-2xl font-semibold text-violet-700">{{ $initials ?: 'G' }}</div>
                    <h2 class="mt-4 text-xl font-semibold text-slate-950">{{ $member?->first_name ?? 'Guest' }} {{ $member?->last_name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $member?->email ?? 'Guest attendance' }}</p>
                    <span class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-sm text-emerald-700 ring-1 ring-emerald-100">
                        <i data-lucide="badge-check" class="size-3.5"></i>
                        {{ Str::headline($record->status) }}
                    </span>
                </section>
                <section class="dashboard-card">
                    <h2 class="text-base font-semibold text-slate-950">Session</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Program</dt><dd class="text-right text-slate-900">{{ $session->event->program?->name ?? 'Unassigned' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Event</dt><dd class="text-right text-slate-900">{{ $session->event->title }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Session</dt><dd class="text-right text-slate-900">{{ $session->title }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Type</dt><dd class="text-right text-slate-900">{{ Str::headline($session->meeting_type) }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Policy</dt><dd class="text-right text-slate-900">{{ Str::headline($attendanceSession->verification_policy) }}</dd></div>
                    </dl>
                </section>
            </aside>

            <main class="space-y-4">
                <section class="dashboard-card">
                    <h2 class="text-base font-semibold text-slate-950">Final Summary</h2>
                    <dl class="mt-4 grid gap-3 text-sm md:grid-cols-2">
                        <div class="rounded-lg bg-slate-50 p-3"><dt class="text-slate-500">Service Date</dt><dd class="mt-1 text-slate-950">{{ $record->service_date?->format('M d, Y') }}</dd></div>
                        <div class="rounded-lg bg-slate-50 p-3"><dt class="text-slate-500">Check-in Time</dt><dd class="mt-1 text-slate-950">{{ $record->checked_in_at?->format('h:i A') }}</dd></div>
                        <div class="rounded-lg bg-slate-50 p-3"><dt class="text-slate-500">Final Method</dt><dd class="mt-1 text-slate-950">{{ Str::headline($record->final_method ?? 'manual') }}</dd></div>
                        <div class="rounded-lg bg-slate-50 p-3"><dt class="text-slate-500">Record ID</dt><dd class="mt-1 text-slate-950">{{ $record->opaqueId() }}</dd></div>
                    </dl>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-2 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-950">Verification Evidence</h2>
                            <p class="text-sm text-slate-500">All successful and failed attempts kept for audit, reporting, and analytics.</p>
                        </div>
                        <span class="inline-flex w-fit items-center gap-2 rounded-full bg-violet-50 px-3 py-1 text-xs text-violet-700 ring-1 ring-violet-100">
                            <i data-lucide="database" class="size-3.5"></i>
                            Stored evidence
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[860px] text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-5 py-3">Method</th>
                                    <th class="px-5 py-3">Provider</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3">Verified At</th>
                                    <th class="px-5 py-3">Details</th>
                                    <th class="px-5 py-3">Confidence</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($record->verifications as $verification)
                                    @php
                                        $meta = $verification->metadata ?? [];
                                        $isSuccess = $verification->status === 'success';
                                    @endphp
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="px-5 py-4">
                                            <span class="inline-flex items-center gap-2 font-medium text-slate-950">
                                                <i data-lucide="{{ $methodIcons[$verification->method] ?? 'shield-check' }}" class="size-4 text-violet-600"></i>
                                                {{ Str::headline($verification->method) }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4">{{ Str::headline($verification->provider ?? 'system') }}</td>
                                        <td class="px-5 py-4">
                                            <span class="rounded-full px-2.5 py-1 text-xs ring-1 {{ $isSuccess ? 'bg-emerald-50 text-emerald-700 ring-emerald-100' : 'bg-rose-50 text-rose-700 ring-rose-100' }}">{{ Str::headline($verification->status) }}</span>
                                        </td>
                                        <td class="px-5 py-4">{{ $verification->verified_at?->format('M d, h:i A') }}</td>
                                        <td class="max-w-sm px-5 py-4 text-xs text-slate-500">
                                            @if($verification->method === 'geolocation')
                                                Lat {{ $meta['latitude'] ?? 'n/a' }}, Lng {{ $meta['longitude'] ?? 'n/a' }}
                                            @elseif($verification->method === 'face')
                                                Face ref {{ $meta['face_reference'] ?? 'stored' }}
                                            @elseif($meta['auto_online'] ?? false)
                                                Online room presence verified automatically.
                                            @else
                                                System evidence recorded.
                                            @endif
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="flex items-center gap-2">
                                                <div class="h-2 w-20 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-violet-600" style="width: {{ min(100, max(0, (int) $verification->confidence)) }}%"></div></div>
                                                <span class="text-xs text-slate-600">{{ (int) $verification->confidence }}%</span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-500">No verification evidence is attached to this record.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </section>
    </div>
</x-app-layout>
