<x-app-layout title="Built-in Meeting Room" :breadcrumbs="$breadcrumbs">
    @php
        $participantCount = $provider === 'livekit' ? ($activeRoomParticipants?->count() ?? 0) : $attendanceSession->records->count();
        $room = ($session->meeting_links[$provider]['room'] ?? 'kingdomlife-'.$provider.'-'.$session->id);
    @endphp

    <div x-data="meetingRoom('meeting-note-{{ $session->opaqueId() }}-{{ $provider }}', @js($liveKitPayload ?? null))" class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-lg bg-violet-100 text-violet-600">
                    <i data-lucide="{{ $meta['icon'] }}" class="size-7"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-semibold text-slate-950">Built-in {{ $meta['label'] }} Room</h1>
                    <p class="text-sm text-slate-500">{{ $session->title }} | {{ $session->session_date->format('M d, Y') }} {{ Str::of($session->starts_at)->substr(0,5) }}</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('event-sessions.meeting', $session) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700"><i data-lucide="settings" class="size-4"></i>Settings</a>
                @if($provider === 'livekit' && filled($liveKitPayload ?? null))
                    <a x-cloak x-show="attendanceRecordUrl" :href="attendanceRecordUrl" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white"><i data-lucide="badge-check" class="size-4"></i>Attendance Record</a>
                @elseif($record)
                    <a href="{{ route('attendance.records.show', [$attendanceSession, $member->opaqueId()]) }}" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white"><i data-lucide="badge-check" class="size-4"></i>Attendance Record</a>
                @endif
            </div>
        </div>

        @if($provider === 'livekit' && filled($liveKitPayload ?? null))
            <section class="rounded-lg border p-3 text-sm" :class="attendanceMarked ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700'">
                <i data-lucide="shield-check" class="mr-2 inline size-4"></i>
                <span x-text="attendanceMarked ? 'Your attendance is marked from a verified LiveKit room connection.' : 'Join LiveKit to mark attendance. Opening this page alone does not check you in.'"></span>
            </section>
        @else
            <section class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                <i data-lucide="shield-check" class="mr-2 inline size-4"></i>
                @if($record)
                    Your attendance was marked automatically from this built-in room.
                @else
                    Room opened. Sign in as a linked member to mark attendance automatically.
                @endif
            </section>
        @endif

        <div class="grid gap-4 xl:grid-cols-[1fr_340px]">
            <main class="space-y-4">
                <section class="rounded-lg border border-slate-200 bg-slate-950 p-4 shadow-sm">
                    <div class="grid min-h-[460px] gap-3 md:grid-cols-2">
                        <div class="relative overflow-hidden rounded-lg bg-gradient-to-br from-violet-900 via-slate-900 to-blue-950">
                            <video x-ref="preview" x-show="camera && stream" autoplay muted playsinline class="absolute inset-0 h-full w-full object-cover"></video>
                            <div x-show="!stream" class="absolute inset-0 grid place-items-center">
                                <div class="grid size-24 place-items-center rounded-full bg-white/10 text-3xl text-white ring-1 ring-white/20">
                                    {{ $member ? Str::substr($member->first_name, 0, 1).Str::substr($member->last_name, 0, 1) : 'G' }}
                                </div>
                            </div>
                            <div class="absolute bottom-3 left-3 rounded bg-slate-950/70 px-3 py-1 text-sm text-white">{{ $member ? trim($member->first_name.' '.$member->last_name) : 'Guest' }}</div>
                            <div x-show="!camera" class="absolute inset-0 grid place-items-center bg-slate-950/90 text-sm text-white"><i data-lucide="video-off" class="mb-2 size-8"></i>Camera off</div>
                        </div>
                        <div class="relative overflow-hidden rounded-lg bg-gradient-to-br from-slate-800 via-slate-900 to-violet-950">
                            <div class="absolute inset-0 grid place-items-center">
                                <div class="text-center text-white">
                                    <div class="mx-auto grid size-20 place-items-center rounded-full bg-white/10 ring-1 ring-white/20"><i data-lucide="users" class="size-9"></i></div>
                                    <div class="mt-3 text-lg">{{ $session->event->title }}</div>
                                    @if($provider === 'livekit' && filled($liveKitPayload ?? null))
                                        <div class="text-sm text-white/70" x-text="`${checkedInCount} participant${checkedInCount === 1 ? '' : 's'} checked in`"></div>
                                    @else
                                        <div class="text-sm text-white/70">{{ $participantCount }} participant{{ $participantCount === 1 ? '' : 's' }} checked in</div>
                                    @endif
                                </div>
                            </div>
                            <div x-show="screen" class="absolute inset-4 rounded-lg border border-white/20 bg-white/10 p-4 text-white">
                                <div class="flex items-center gap-2 text-sm"><i data-lucide="screen-share" class="size-4"></i>Screen sharing active</div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                        @if($provider === 'livekit' && filled($liveKitPayload ?? null))
                            <button type="button" @click="toggleLiveKit()" :disabled="liveKitConnecting" :class="liveKitConnected ? 'bg-rose-600 text-white' : 'bg-emerald-600 text-white'" class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm disabled:cursor-not-allowed disabled:opacity-70">
                                <i data-lucide="radio-tower" class="size-4"></i>
                                <span x-text="liveKitConnecting ? 'Connecting...' : (liveKitConnected ? 'Leave LiveKit' : 'Join LiveKit')"></span>
                            </button>
                        @endif
                        <button type="button" @click="toggleMute()" :class="muted ? 'bg-rose-600 text-white' : 'bg-white text-slate-700'" class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm"><i data-lucide="mic" class="size-4"></i><span x-text="muted ? 'Unmute' : 'Mute'"></span></button>
                        <button type="button" @click="toggleCamera()" :class="camera ? 'bg-white text-slate-700' : 'bg-rose-600 text-white'" class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm"><i data-lucide="video" class="size-4"></i><span x-text="stream ? (camera ? 'Camera' : 'Camera Off') : 'Start Camera'"></span></button>
                        <button type="button" @click="screen = !screen" :class="screen ? 'bg-violet-600 text-white' : 'bg-white text-slate-700'" class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm"><i data-lucide="screen-share" class="size-4"></i>Share</button>
                        <button type="button" @click="hand = !hand" :class="hand ? 'bg-amber-500 text-white' : 'bg-white text-slate-700'" class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm"><i data-lucide="hand" class="size-4"></i>Raise Hand</button>
                        <button type="button" @click="chat = !chat" class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2.5 text-sm text-slate-700"><i data-lucide="messages-square" class="size-4"></i>Chat</button>
                    </div>
                    @if($provider === 'livekit' && filled($liveKitPayload ?? null))
                        <p class="mt-3 text-center text-sm" :class="liveKitConnected ? 'text-emerald-200' : 'text-slate-300'" x-text="`${liveKitStatus} | LiveKit remote participants: ${remoteParticipantCount}`"></p>
                        <p x-show="liveKitError" x-text="liveKitError" class="mt-2 text-center text-sm text-rose-200"></p>
                    @endif
                    <p x-show="mediaError" x-text="mediaError" class="mt-3 text-center text-sm text-amber-200"></p>
                </section>
            </main>

            <aside class="space-y-4">
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base text-slate-950">Room Details</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Room ID</dt><dd class="text-right">{{ $room }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Mode</dt><dd>{{ Str::headline($provider) }}</dd></div>
                        @if($provider === 'livekit' && filled($liveKitPayload ?? null))
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Attendance</dt><dd :class="attendanceMarked ? 'text-emerald-600' : 'text-amber-600'" x-text="attendanceMarked ? 'Marked' : 'Pending LiveKit join'"></dd></div>
                        @else
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Attendance</dt><dd class="text-emerald-600">{{ $record ? 'Marked' : 'Pending' }}</dd></div>
                        @endif
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Policy</dt><dd>{{ Str::headline($attendanceSession->verification_policy) }}</dd></div>
                    </dl>
                </section>

                @if($provider === 'livekit' && filled($liveKitPayload ?? null))
                    <section class="rounded-lg border border-violet-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center gap-2">
                            <span class="grid size-9 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="radio-tower" class="size-4"></i></span>
                            <h2 class="text-base text-slate-950">LiveKit Connection</h2>
                        </div>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Server</dt><dd class="break-all text-right">{{ $liveKitPayload['server_url'] }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Identity</dt><dd class="break-all text-right">{{ $liveKitPayload['identity'] }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Token TTL</dt><dd>{{ $liveKitPayload['ttl_label'] }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Expires</dt><dd class="text-right">{{ \Illuminate\Support\Carbon::parse($liveKitPayload['expires_at'])->format('h:i A') }}</dd></div>
                        </dl>
                        <div class="mt-4 rounded-lg bg-violet-50 p-3 text-xs text-violet-700">
                            <div class="flex items-center gap-2 text-violet-950"><i data-lucide="key-round" class="size-4"></i>Signed token ready</div>
                            <code class="mt-2 block truncate">{{ $liveKitPayload['token'] }}</code>
                        </div>
                    </section>
                @endif

                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base text-slate-950">Checked-In Participants</h2>
                    <div class="mt-4 space-y-3">
                        @forelse(($provider === 'livekit' ? $activeRoomParticipants : $attendanceSession->records) as $participantRecord)
                            <div class="flex items-center gap-3">
                                <div class="grid size-9 place-items-center rounded-full bg-violet-100 text-sm text-violet-700">{{ $participantRecord->member ? Str::substr($participantRecord->member->first_name,0,1).Str::substr($participantRecord->member->last_name,0,1) : 'G' }}</div>
                                <div class="min-w-0">
                                    <div class="truncate text-sm text-slate-900">{{ $participantRecord->member ? trim($participantRecord->member->first_name.' '.$participantRecord->member->last_name) : 'Guest' }}</div>
                                    <div class="text-xs text-slate-500">{{ $participantRecord->checked_in_at?->format('h:i A') }} | {{ Str::headline($participantRecord->final_method ?? 'manual') }}</div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No checked-in participants yet.</p>
                        @endforelse
                    </div>
                </section>

                <section x-show="chat" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base text-slate-950">Room Notes</h2>
                    <textarea x-model="note" class="mt-3 h-28 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Local meeting notes"></textarea>
                    <button type="button" @click="saveNote()" class="mt-3 w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm text-slate-700">Save Note Locally</button>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
