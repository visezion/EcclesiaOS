<x-app-layout title="Attendance Methods" :breadcrumbs="$breadcrumbs">
    @php
        $methods = $attendanceSession->methods ?? [];
        $methodMeta = [
            'qr' => ['QR Code', 'Scan or enter the session QR token.', 'scan-qr-code'],
            'geolocation' => ['Geolocation', 'Check in using browser location.', 'map-pin'],
            'manual' => ['Manual Check-in', 'Leader records attendance.', 'user-check'],
            'kiosk' => ['Kiosk', 'Use a venue kiosk check-in station.', 'monitor'],
            'face' => ['Face Recognition', 'Capture face evidence for verification.', 'scan-face'],
            'zoom' => ['Zoom', 'Join Zoom and mark online presence.', 'video'],
            'google_meet' => ['Google Meet', 'Open the built-in Meet room and mark online presence.', 'calendar-clock'],
            'jitsi' => ['Jitsi Meet', 'Join Jitsi and mark online presence.', 'radio'],
            'livekit' => ['LiveKit', 'Open the built-in LiveKit room and mark online presence.', 'radio-tower'],
        ];
    @endphp
    <div class="space-y-5">
        <div class="flex items-center gap-4"><div class="grid size-14 place-items-center rounded-lg bg-violet-100 text-violet-600"><i data-lucide="scan-line" class="size-7"></i></div><div><h1 class="text-2xl font-semibold text-slate-950">Choose Your Check-in Method</h1><p class="text-sm text-slate-500">Only one final attendance record is kept. Multiple successful methods become verification evidence.</p></div></div>
        @if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($methodMeta as $method => [$label, $description, $icon])
                @if(in_array($method, $methods, true))
                    @if(in_array($method, ['zoom','google_meet','jitsi','livekit'], true))
                    <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="grid size-11 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="{{ $icon }}" class="size-5"></i></div><h2 class="mt-4 text-base text-slate-950">{{ $label }}</h2><p class="mt-1 min-h-10 text-sm text-slate-500">{{ $description }}</p>
                        <a href="{{ route('meetings.rooms.show', [$attendanceSession->eventSession, $method]) }}" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white"><i data-lucide="log-in" class="size-4"></i>Open Built-in Room</a>
                    </article>
                    @else
                    <form method="POST" action="{{ route('attendance.check-in', $attendanceSession) }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">@csrf
                        <input type="hidden" name="method" value="{{ $method }}"><input type="hidden" name="provider" value="{{ $method }}">@if($member)<input type="hidden" name="member_id" value="{{ $member->opaqueId() }}">@endif
                        <div class="grid size-11 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="{{ $icon }}" class="size-5"></i></div><h2 class="mt-4 text-base text-slate-950">{{ $label }}</h2><p class="mt-1 min-h-10 text-sm text-slate-500">{{ $description }}</p>
                        @if($method === 'geolocation')<div class="mt-3 grid gap-2"><input name="latitude" placeholder="Latitude" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><input name="longitude" placeholder="Longitude" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"></div>@endif
                        @if($method === 'face')<input name="face_reference" placeholder="Face capture reference" class="mt-3 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">@endif
                        <button class="mt-4 w-full rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white">Check In</button>
                    </form>
                    @endif
                @endif
            @endforeach
        </section>
        <section class="rounded-lg border border-violet-100 bg-violet-50 p-4 text-sm text-violet-700"><i data-lucide="shield-check" class="mr-2 inline size-4"></i>Each verification method is stored for audit and analytics. The final attendance record uses the best valid evidence for this session.</section>
    </div>
</x-app-layout>
