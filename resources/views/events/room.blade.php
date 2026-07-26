<x-app-layout title="{{ $session->event->title }} Live Room" :breadcrumbs="[]" :hide-topbar="true" :chromeless="filled($guestParticipant ?? null)" main-class="p-0">
    @php
        $participantCount = $provider === 'livekit' ? ($activeRoomParticipants?->count() ?? 0) : $attendanceSession->records->count();
        $room = ($session->meeting_links[$provider]['room'] ?? 'kingdomlife-'.$provider.'-'.$session->id);
        $guestName = trim((string) data_get($guestParticipant ?? [], 'name', ''));
        $memberName = $guestName ?: ($member ? trim($member->first_name.' '.$member->last_name) : 'Guest');
        $memberInitials = Str::upper(Str::of($memberName)->explode(' ')->filter()->take(2)->map(fn ($part) => Str::substr($part, 0, 1))->implode('')) ?: 'G';
        $memberAvatarUrl = auth()->user()?->avatar_src;
        $isGuestParticipant = filled($guestName);
        $roomDisplayName = $session->event->title.' Live Room';
        $sessionStartsAt = Str::of($session->starts_at)->substr(0, 5);
        $sessionEndsAt = filled($session->ends_at) ? Str::of($session->ends_at)->substr(0, 5) : null;
        $connectionReady = $provider === 'livekit' && filled($liveKitPayload ?? null);
        $eventDescription = $session->event->description ?? 'Join this live church meeting with secure access, attendance verification, chat, questions, polls, screen share, and participant controls.';
        $eventDate = $session->session_date->format('M d, Y');
        $eventTime = $sessionStartsAt.($sessionEndsAt ? ' - '.$sessionEndsAt : '');
        $shortRoomCode = Str::lower(base_convert((string) $session->getKey(), 10, 36));
        $roomUrl = route('meetings.rooms.short', [$shortRoomCode, $provider]);
        $shareTitle = $session->title ?: $session->event->title;
        $shareText = $shareTitle.' - '.$eventDate.' '.$eventTime;
        $whatsappShareUrl = 'https://wa.me/?text='.rawurlencode($shareText.' '.$roomUrl);
        $linkedinShareUrl = 'https://www.linkedin.com/sharing/share-offsite/?url='.rawurlencode($roomUrl);
        $facebookShareUrl = 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($roomUrl);
        $twitterShareUrl = 'https://twitter.com/intent/tweet?text='.rawurlencode($shareText).'&url='.rawurlencode($roomUrl);
        $venue = $session->venue ?: ($session->event->venue ?: ($session->campus?->name ?? 'Virtual Event'));
        $timezone = $session->timezone ?: config('app.timezone');
        $calendarStart = \Illuminate\Support\Carbon::parse($session->session_date->format('Y-m-d').' '.$sessionStartsAt, $timezone)->utc();
        $calendarEnd = $sessionEndsAt
            ? \Illuminate\Support\Carbon::parse($session->session_date->format('Y-m-d').' '.$sessionEndsAt, $timezone)->utc()
            : $calendarStart->copy()->addHour();
        $calendarUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
            .'&text='.rawurlencode($session->title)
            .'&details='.rawurlencode(($eventDescription ?: $session->event->title)."\n\nRoom: ".$roomUrl)
            .'&location='.rawurlencode($venue)
            .'&dates='.$calendarStart->format('Ymd\THis\Z').'/'.$calendarEnd->format('Ymd\THis\Z');
        $teamAssignments = $agendaSections->flatMap(fn ($section) => $section->assignments);
    @endphp

    <div
        x-data="meetingRoom('meeting-note-{{ $session->opaqueId() }}-{{ $provider }}', @js($liveKitPayload ?? null), @js($memberName), @js(['can_manage_interactions' => $canManageRoomInteractions, 'avatar' => $memberAvatarUrl]))"
        x-init="sidePanel = 'chat'; panelTab = 'chat'"
        class="min-h-screen overflow-hidden bg-sidebar text-white"
    >
        <span class="sr-only">Built-in {{ $meta['label'] }} Room</span>
        <div
            x-ref="meetingShell"
            class="grid min-h-screen bg-[radial-gradient(circle_at_top_left,rgba(124,58,237,0.18),transparent_32%),linear-gradient(135deg,#050914,#0c1320_55%,#050914)] fullscreen:h-screen fullscreen:min-h-screen fullscreen:overflow-hidden"
            :class="fullscreen ? 'lg:grid-cols-1' : 'lg:grid-cols-1'"
        >
            <section class="min-w-0 p-3 sm:p-4 lg:p-6 fullscreen:p-3">
                <header x-show="!fullscreen" class="mb-5 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded bg-violet-600/25 px-2.5 py-1 text-xs font-semibold text-violet-100">{{ $session->event->program?->name ?? 'Meeting' }}</span>
                            <span class="rounded bg-white/10 px-2.5 py-1 text-xs font-semibold text-white/70">{{ Str::headline($session->status) }}</span>
                        </div>
                        <h1 class="mt-2 truncate text-2xl font-bold text-white">{{ $session->title }}</h1>
                        <p class="mt-1 text-sm text-white/60">{{ $eventDate }} | {{ $eventTime }} | {{ $venue }}</p>
                    </div>
                    <div class="flex items-center justify-between gap-4 xl:justify-end">
                        <div class="flex items-center gap-2">
                            @if($isGuestParticipant)
                                <a href="{{ route('meetings.rooms.show', [$session, $provider]) }}" class="grid size-10 place-items-center rounded-lg text-white/75 hover:bg-white/10 hover:text-white" title="Log in for member access">
                                    <i data-lucide="log-in" class="size-5"></i>
                                </a>
                            @else
                                <a href="{{ route('event-sessions.meeting', $session) }}" class="grid size-10 place-items-center rounded-lg text-white/75 hover:bg-white/10 hover:text-white" title="Room settings">
                                    <i data-lucide="settings" class="size-5"></i>
                                </a>
                            @endif
                            <a href="{{ $calendarUrl }}" target="_blank" rel="noopener" class="grid size-10 place-items-center rounded-lg text-white/75 hover:bg-white/10 hover:text-white" title="Add to calendar">
                                <i data-lucide="calendar-plus" class="size-5"></i>
                            </a>
                        </div>
                        <div class="flex min-w-0 items-center gap-3">
                            @if($memberAvatarUrl)
                                <img src="{{ $memberAvatarUrl }}" alt="{{ $memberName }}" class="size-10 shrink-0 rounded-full object-cover ring-1 ring-white/20">
                            @else
                                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-white/10 text-sm font-semibold ring-1 ring-white/15">{{ $memberInitials }}</span>
                            @endif
                            <div class="hidden min-w-0 sm:block">
                                <div class="truncate text-sm font-semibold">{{ $memberName }}</div>
                                <div class="truncate text-xs text-white/45" x-text="liveKitConnected ? 'In room' : '{{ $isGuestParticipant ? 'Guest access' : ($connectionReady ? 'Ready to join' : 'Preview mode') }}'"></div>
                            </div>
                        </div>
                    </div>
                </header>

                <div
                    class="grid gap-4 fullscreen:h-full fullscreen:overflow-hidden"
                    :class="sidePanel ? 'xl:grid-cols-[minmax(0,1fr)_390px]' : 'xl:grid-cols-1'"
                >
                    <main class="min-w-0 space-y-4 fullscreen:h-full fullscreen:min-h-0 fullscreen:overflow-hidden">
                        <section class="overflow-hidden rounded-lg border border-white/10 bg-black/30 shadow-2xl shadow-black/30 fullscreen:flex fullscreen:h-full fullscreen:min-h-0 fullscreen:flex-col">
                            <div class="relative bg-black fullscreen:min-h-0 fullscreen:flex-1">
                                <div x-show="roomView === 'speaker'" class="relative aspect-video min-h-[360px] overflow-hidden bg-brand-navy fullscreen:h-full fullscreen:min-h-0 fullscreen:aspect-auto">
                                    <template x-if="primaryParticipant">
                                        <div class="absolute inset-0">
                                            <video x-bind:data-livekit-video="primaryParticipant.identity" autoplay playsinline class="h-full w-full bg-brand-navy object-cover"></video>
                                            <div x-show="!primaryParticipant.hasVideo" class="absolute inset-0 grid place-items-center bg-gradient-to-br from-[#0b1020] via-[#23115c] to-[#050914]">
                                                <img x-show="primaryParticipant.avatar" :src="primaryParticipant.avatar" :alt="primaryParticipant.name" class="size-28 rounded-full object-cover ring-1 ring-white/25">
                                                <div x-show="!primaryParticipant.avatar" class="grid size-28 place-items-center rounded-full bg-white/10 text-4xl font-semibold ring-1 ring-white/20" x-text="primaryParticipant.initials"></div>
                                            </div>
                                            <audio x-bind:data-livekit-audio="primaryParticipant.identity" autoplay></audio>
                                        </div>
                                    </template>

                                    <div x-show="!primaryParticipant" class="absolute inset-0 bg-gradient-to-br from-[#061225] via-[#14347d] to-[#050914]">
                                        <video x-ref="speakerPreview" x-show="camera && stream && !liveKitConnected" autoplay muted playsinline class="absolute inset-0 h-full w-full object-cover"></video>
                                        <video x-ref="speakerLocalLiveKitVideo" x-show="camera && liveKitConnected" autoplay muted playsinline class="absolute inset-0 h-full w-full object-cover"></video>
                                        <div class="absolute inset-0 opacity-60" style="background: radial-gradient(circle at 34% 30%, rgba(59, 130, 246, .85), transparent 26%), radial-gradient(circle at 70% 65%, rgba(124, 58, 237, .65), transparent 30%);"></div>
                                        <div x-show="!camera" class="absolute inset-0 grid place-items-center">
                                            <div class="text-center">
                                                @if($memberAvatarUrl)
                                                    <img src="{{ $memberAvatarUrl }}" alt="{{ $memberName }}" class="mx-auto size-28 rounded-full object-cover ring-1 ring-white/25">
                                                @else
                                                    <div class="mx-auto grid size-28 place-items-center rounded-full bg-white/10 text-4xl font-semibold ring-1 ring-white/20">{{ $memberInitials }}</div>
                                                @endif
                                                <div class="mt-4 text-xl font-semibold text-white">{{ $session->title }}</div>
                                                <div class="mt-1 text-sm text-white/60">{{ $connectionReady ? 'Join the room to start the live meeting.' : 'Local meeting preview' }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div x-show="sidePanel" class="absolute right-4 top-16 hidden w-48 space-y-3 lg:block">
                                        <div class="relative aspect-video overflow-hidden rounded-lg border border-white/15 bg-black/60 shadow-xl">
                                            <video x-ref="preview" x-show="camera && stream && !liveKitConnected" autoplay muted playsinline class="absolute inset-0 h-full w-full object-cover"></video>
                                            <video x-ref="localLiveKitVideo" x-show="camera && liveKitConnected" autoplay muted playsinline class="absolute inset-0 h-full w-full object-cover"></video>
                                            <div x-show="!camera" class="absolute inset-0 grid place-items-center bg-white/5">
                                                @if($memberAvatarUrl)
                                                    <img src="{{ $memberAvatarUrl }}" alt="{{ $memberName }}" class="size-14 rounded-full object-cover ring-1 ring-white/20">
                                                @else
                                                    <div class="grid size-14 place-items-center rounded-full bg-white/10 text-lg font-semibold">{{ $memberInitials }}</div>
                                                @endif
                                            </div>
                                            <div class="absolute bottom-2 left-2 right-2 truncate rounded bg-black/60 px-2 py-1 text-xs">{{ $memberName }} (You)</div>
                                        </div>
                                        <template x-for="participant in visibleRemoteParticipants().slice(0, 2)" :key="participant.identity">
                                            <div class="relative aspect-video overflow-hidden rounded-lg border border-white/15 bg-black/60 shadow-xl">
                                                <video x-bind:data-livekit-video="participant.identity" autoplay playsinline class="absolute inset-0 h-full w-full object-cover"></video>
                                                <div x-show="!participant.hasVideo" class="absolute inset-0 grid place-items-center bg-white/5">
                                                    <img x-show="participant.avatar" :src="participant.avatar" :alt="participant.name" class="size-14 rounded-full object-cover ring-1 ring-white/20">
                                                    <div x-show="!participant.avatar" class="grid size-14 place-items-center rounded-full bg-white/10 text-lg font-semibold" x-text="participant.initials"></div>
                                                </div>
                                                <audio x-bind:data-livekit-audio="participant.identity" autoplay></audio>
                                                <div class="absolute bottom-2 left-2 right-2 truncate rounded bg-black/60 px-2 py-1 text-xs" x-text="participant.name"></div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <div x-show="roomView === 'gallery'" class="grid min-h-[520px] gap-3 p-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 fullscreen:h-full fullscreen:min-h-0 fullscreen:auto-rows-fr fullscreen:overflow-y-auto">
                                    <div class="relative aspect-video min-h-44 overflow-hidden rounded-lg bg-gradient-to-br from-[#061225] via-[#14347d] to-[#050914] ring-1 ring-white/10">
                                        <video x-ref="galleryPreview" x-show="camera && stream && !liveKitConnected" autoplay muted playsinline class="absolute inset-0 h-full w-full object-cover"></video>
                                        <video x-ref="galleryLocalLiveKitVideo" x-show="camera && liveKitConnected" autoplay muted playsinline class="absolute inset-0 h-full w-full object-cover"></video>
                                        <div x-show="!camera" class="absolute inset-0 grid place-items-center">
                                            @if($memberAvatarUrl)
                                                <img src="{{ $memberAvatarUrl }}" alt="{{ $memberName }}" class="size-20 rounded-full object-cover ring-1 ring-white/25">
                                            @else
                                                <div class="grid size-20 place-items-center rounded-full bg-white/10 text-2xl font-semibold ring-1 ring-white/20">{{ $memberInitials }}</div>
                                            @endif
                                        </div>
                                        <div class="absolute bottom-3 left-3 right-3 flex items-center justify-between gap-2 rounded bg-black/60 px-3 py-2 text-sm">
                                            <span class="truncate">{{ $memberName }} (You)</span>
                                            <i x-show="muted" data-lucide="mic-off" class="size-4 text-rose-300"></i>
                                            <i x-show="!muted" data-lucide="mic" class="size-4 text-emerald-300"></i>
                                        </div>
                                    </div>

                                    <template x-for="participant in remoteParticipants" :key="participant.identity">
                                        <div class="relative aspect-video min-h-44 overflow-hidden rounded-lg bg-brand-navy ring-1 ring-white/10">
                                            <video x-bind:data-livekit-video="participant.identity" autoplay playsinline class="absolute inset-0 h-full w-full object-cover"></video>
                                            <div x-show="!participant.hasVideo" class="absolute inset-0 grid place-items-center bg-gradient-to-br from-[#0b1020] to-[#23115c]">
                                                <img x-show="participant.avatar" :src="participant.avatar" :alt="participant.name" class="size-20 rounded-full object-cover ring-1 ring-white/25">
                                                <div x-show="!participant.avatar" class="grid size-20 place-items-center rounded-full bg-white/10 text-2xl font-semibold ring-1 ring-white/20" x-text="participant.initials"></div>
                                            </div>
                                            <audio x-bind:data-livekit-audio="participant.identity" autoplay></audio>
                                            <div class="absolute bottom-3 left-3 right-3 flex items-center justify-between gap-2 rounded bg-black/60 px-3 py-2 text-sm">
                                                <span class="truncate" x-text="participant.name"></span>
                                                <i x-show="participant.isSpeaking" data-lucide="mic" class="size-4 text-emerald-300"></i>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <div x-show="roomView === 'focus'" class="relative aspect-video min-h-[520px] overflow-hidden bg-brand-navy fullscreen:h-full fullscreen:min-h-0 fullscreen:aspect-auto">
                                    <template x-if="focusParticipant()">
                                        <div class="absolute inset-0">
                                            <video x-bind:data-livekit-video="focusParticipant().identity" autoplay playsinline class="h-full w-full bg-brand-navy object-contain"></video>
                                            <div x-show="!focusParticipant().hasVideo" class="absolute inset-0 grid place-items-center bg-gradient-to-br from-[#0b1020] via-[#23115c] to-[#050914]">
                                                <img x-show="focusParticipant().avatar" :src="focusParticipant().avatar" :alt="focusParticipant().name" class="size-32 rounded-full object-cover ring-1 ring-white/25">
                                                <div x-show="!focusParticipant().avatar" class="grid size-32 place-items-center rounded-full bg-white/10 text-5xl font-semibold ring-1 ring-white/20" x-text="focusParticipant().initials"></div>
                                            </div>
                                            <audio x-bind:data-livekit-audio="focusParticipant().identity" autoplay></audio>
                                        </div>
                                    </template>
                                    <div x-show="!focusParticipant()" class="absolute inset-0 grid place-items-center bg-gradient-to-br from-[#061225] via-[#14347d] to-[#050914]">
                                        <div class="text-center">
                                            @if($memberAvatarUrl)
                                                <img src="{{ $memberAvatarUrl }}" alt="{{ $memberName }}" class="mx-auto size-32 rounded-full object-cover ring-1 ring-white/25">
                                            @else
                                                <div class="mx-auto grid size-32 place-items-center rounded-full bg-white/10 text-5xl font-semibold ring-1 ring-white/20">{{ $memberInitials }}</div>
                                            @endif
                                            <div class="mt-4 text-xl font-semibold">Focus view is ready.</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="pointer-events-none absolute left-4 top-4 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center rounded px-3 py-1.5 text-xs font-bold uppercase tracking-wide" :class="liveKitConnected ? 'bg-rose-600 text-white' : 'bg-amber-500 text-white'">
                                        <span x-text="liveKitConnected ? 'Live' : 'Ready'"></span>
                                    </span>
                                    <span class="inline-flex items-center gap-2 rounded bg-black/45 px-3 py-1.5 text-sm font-medium backdrop-blur">
                                        <i data-lucide="eye" class="size-4"></i>
                                        <span x-text="`${totalParticipantCount()} attending`"></span>
                                    </span>
                                </div>

                                <div x-show="screen" class="absolute inset-4 grid place-items-center rounded-lg border border-white/20 bg-black/50 p-4 backdrop-blur">
                                    <div class="text-center">
                                        <i data-lucide="screen-share" class="mx-auto size-10"></i>
                                        <div class="mt-2 text-sm font-semibold">Screen sharing enabled</div>
                                    </div>
                                </div>

                                <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent p-4">
                                    <div class="mb-4 h-1.5 overflow-hidden rounded-full bg-white/15">
                                        <div class="h-full w-[76%] rounded-full bg-rose-500"></div>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2">
                                            @if($connectionReady)
                                                <button type="button" @click="toggleLiveKit()" :disabled="liveKitConnecting" class="grid size-10 place-items-center rounded-lg text-white disabled:cursor-not-allowed disabled:opacity-70" :class="liveKitConnected ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700'" title="Join or leave room">
                                                    <i data-lucide="radio-tower" class="size-5"></i>
                                                </button>
                                            @endif
                                            <button type="button" @click="toggleMute()" class="grid size-10 place-items-center rounded-lg hover:bg-white/10" :class="muted ? 'text-rose-300' : 'text-white'" title="Mute or unmute">
                                                <i x-show="muted" data-lucide="mic-off" class="size-5"></i>
                                                <i x-show="!muted" data-lucide="mic" class="size-5"></i>
                                            </button>
                                            <button type="button" @click="toggleCamera()" class="grid size-10 place-items-center rounded-lg hover:bg-white/10" :class="camera ? 'text-white' : 'text-rose-300'" title="Camera">
                                                <i x-show="camera" data-lucide="video" class="size-5"></i>
                                                <i x-show="!camera" data-lucide="video-off" class="size-5"></i>
                                            </button>
                                            <span class="hidden items-center gap-2 text-sm sm:inline-flex">
                                                <span class="size-2 rounded-full bg-rose-500"></span>
                                                <span x-text="liveKitConnected ? 'LIVE' : 'PREVIEW'"></span>
                                            </span>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <button type="button" @click="roomView = 'speaker'" class="grid size-10 place-items-center rounded-lg hover:bg-white/10" :class="roomView === 'speaker' ? 'bg-violet-600 text-white' : 'text-white'" title="Speaker view">
                                                <i data-lucide="monitor-play" class="size-5"></i>
                                            </button>
                                            <button type="button" @click="roomView = 'gallery'" class="grid size-10 place-items-center rounded-lg hover:bg-white/10" :class="roomView === 'gallery' ? 'bg-violet-600 text-white' : 'text-white'" title="Gallery view">
                                                <i data-lucide="layout-grid" class="size-5"></i>
                                            </button>
                                            <button type="button" @click="roomView = 'focus'" class="grid size-10 place-items-center rounded-lg hover:bg-white/10" :class="roomView === 'focus' ? 'bg-violet-600 text-white' : 'text-white'" title="Focus view">
                                                <i data-lucide="maximize" class="size-5"></i>
                                            </button>
                                            <button type="button" @click="toggleScreenShare()" class="grid size-10 place-items-center rounded-lg hover:bg-white/10" :class="screen ? 'text-violet-300' : 'text-white'" title="Share screen">
                                                <i data-lucide="screen-share" class="size-5"></i>
                                            </button>
                                            <button type="button" @click="openPanel('participants')" class="grid size-10 place-items-center rounded-lg hover:bg-white/10" :class="sidePanel === 'participants' ? 'bg-violet-600 text-white' : 'text-white'" title="Participants">
                                                <i data-lucide="users-round" class="size-5"></i>
                                            </button>
                                            <button type="button" @click="openPanel('chat', 'chat')" class="grid size-10 place-items-center rounded-lg hover:bg-white/10" :class="sidePanel === 'chat' ? 'bg-violet-600 text-white' : 'text-white'" title="Chat">
                                                <i data-lucide="messages-square" class="size-5"></i>
                                            </button>
                                            <button type="button" @click="openPanel('details')" class="grid size-10 place-items-center rounded-lg hover:bg-white/10" :class="sidePanel === 'details' ? 'bg-violet-600 text-white' : 'text-white'" title="Details">
                                                <i data-lucide="settings" class="size-5"></i>
                                            </button>
                                            <button type="button" @click="toggleFullscreen($refs.meetingShell)" class="grid size-10 place-items-center rounded-lg hover:bg-white/10" title="Fullscreen">
                                                <i x-show="!fullscreen" data-lucide="maximize" class="size-5"></i>
                                                <i x-show="fullscreen" data-lucide="minimize" class="size-5"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div x-show="!fullscreen" class="border-t border-white/10 bg-[#07101d]/95 p-4 sm:p-5">
                                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                    <div class="min-w-0">
                                        <span class="inline-flex rounded bg-violet-600/30 px-2.5 py-1 text-xs font-semibold text-violet-200">{{ $session->event->title }}</span>
                                        <h1 class="mt-3 text-2xl font-bold leading-tight text-white sm:text-3xl">{{ $roomDisplayName }}</h1>
                                        <p class="mt-2 max-w-3xl text-sm leading-6 text-white/75">{{ $eventDescription }}</p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 xl:justify-end xl:pt-7">
                                        <span class="mr-1 text-sm text-white/55">Share</span>
                                        <button type="button" @click="shareRoom(@js($shareTitle), @js($roomUrl))" class="grid size-10 place-items-center rounded-full bg-white/10 text-white ring-1 ring-white/10 hover:bg-white/15" title="Share short meeting link">
                                            <i data-lucide="send" class="size-4"></i>
                                        </button>
                                        <button type="button" @click="copyRoomLink(@js($roomUrl))" class="grid size-10 place-items-center rounded-full bg-blue-600 text-white hover:bg-blue-500" title="Copy short link">
                                            <i data-lucide="link" class="size-4"></i>
                                        </button>
                                        <a href="{{ $whatsappShareUrl }}" target="_blank" rel="noopener noreferrer" class="grid size-10 place-items-center rounded-full bg-[#25D366] text-white hover:brightness-110" title="Share on WhatsApp" aria-label="Share on WhatsApp">
                                            <svg viewBox="0 0 24 24" class="size-5" aria-hidden="true" fill="currentColor">
                                                <path d="M19.05 4.91A9.82 9.82 0 0 0 12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.9 9.9 0 0 0 4.73 1.2h.01c5.46 0 9.91-4.44 9.91-9.9a9.86 9.86 0 0 0-2.9-7.01Zm-7.01 15.24h-.01a8.23 8.23 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.22 8.22 0 0 1-1.26-4.38c0-4.55 3.7-8.25 8.26-8.25a8.2 8.2 0 0 1 5.83 2.42 8.2 8.2 0 0 1 2.41 5.84c0 4.54-3.7 8.23-8.25 8.23Zm4.52-6.16c-.25-.12-1.47-.72-1.69-.8-.23-.09-.39-.13-.56.12-.16.25-.64.8-.79.97-.14.16-.29.18-.54.06-.25-.13-1.05-.39-2-1.23-.74-.66-1.24-1.47-1.38-1.72-.15-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.15.16-.25.25-.42.08-.16.04-.31-.02-.43-.06-.13-.56-1.34-.76-1.84-.2-.48-.4-.41-.56-.42h-.48c-.17 0-.43.06-.66.31-.22.25-.86.84-.86 2.05s.88 2.38 1 2.54c.13.17 1.74 2.65 4.21 3.72.59.25 1.05.4 1.41.51.59.19 1.13.16 1.55.1.48-.07 1.47-.6 1.67-1.18.21-.58.21-1.08.15-1.18-.06-.11-.22-.17-.47-.29Z"/>
                                            </svg>
                                        </a>
                                        <a href="{{ $linkedinShareUrl }}" target="_blank" rel="noopener noreferrer" class="grid size-10 place-items-center rounded-full bg-[#0A66C2] text-white hover:brightness-110" title="Share on LinkedIn" aria-label="Share on LinkedIn">
                                            <svg viewBox="0 0 24 24" class="size-5" aria-hidden="true" fill="currentColor">
                                                <path d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.47-.9 1.63-1.85 3.36-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43a2.06 2.06 0 1 1 0-4.12 2.06 2.06 0 0 1 0 4.12Zm1.78 13.02H3.56V9h3.56v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.72v20.56C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.72V1.72C24 .77 23.2 0 22.22 0Z"/>
                                            </svg>
                                        </a>
                                        <a href="{{ $facebookShareUrl }}" target="_blank" rel="noopener noreferrer" class="grid size-10 place-items-center rounded-full bg-[#1877F2] text-white hover:brightness-110" title="Share on Facebook" aria-label="Share on Facebook">
                                            <svg viewBox="0 0 24 24" class="size-5" aria-hidden="true" fill="currentColor">
                                                <path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.96h-1.51c-1.49 0-1.96.93-1.96 1.89v2.26h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07Z"/>
                                            </svg>
                                        </a>
                                        <a href="{{ $twitterShareUrl }}" target="_blank" rel="noopener noreferrer" class="grid size-10 place-items-center rounded-full bg-white text-slate-950 hover:bg-slate-200" title="Share on Twitter/X" aria-label="Share on Twitter/X">
                                            <svg viewBox="0 0 24 24" class="size-4" aria-hidden="true" fill="currentColor">
                                                <path d="M18.9 2h3.68l-8.04 9.19L24 22h-7.41l-5.8-7.59L4.15 22H.47l8.6-9.83L0 2h7.59l5.24 6.93L18.9 2Zm-1.29 18.1h2.04L6.48 3.8H4.29l13.32 16.3Z"/>
                                            </svg>
                                        </a>
                                    </div>
                                </div>

                                <div class="mt-5 grid gap-3 border-t border-white/10 pt-5 text-sm text-white/70 md:grid-cols-[1fr_1fr_1fr_auto] md:items-center">
                                    <div class="flex items-center gap-3">
                                        <i data-lucide="calendar" class="size-5 text-white/55"></i>
                                        <span>{{ $eventDate }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <i data-lucide="clock" class="size-5 text-white/55"></i>
                                        <span>{{ $eventTime }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <i data-lucide="map-pin" class="size-5 text-white/55"></i>
                                        <span>{{ $venue }}</span>
                                    </div>
                                    <a href="{{ $calendarUrl }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2 rounded-lg border border-white/10 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/10">
                                        <i data-lucide="calendar-plus" class="size-4"></i>
                                        Add to Calendar
                                    </a>
                                </div>

                                @if($connectionReady)
                                    <p class="mt-3 text-sm" :class="liveKitConnected ? 'text-emerald-300' : 'text-amber-300'" x-text="`${liveKitStatus} | Remote participants: ${remoteParticipantCount}`"></p>
                                    <p x-show="liveKitError" x-text="liveKitError" class="mt-2 text-sm text-rose-300"></p>
                                @endif
                                <p x-show="mediaError" x-text="mediaError" class="mt-2 text-sm text-amber-300"></p>
                            </div>
                        </section>

                        <section x-show="!fullscreen" x-data="{ eventTab: 'about' }" class="rounded-lg border border-white/10 bg-white/[0.045] p-4 sm:p-5">
                            <div class="flex flex-wrap gap-6 border-b border-white/10">
                                @foreach(['about' => 'About', 'team' => 'Team', 'agenda' => 'Agenda', 'details' => 'Details'] as $tab => $label)
                                    <button type="button" @click="eventTab = '{{ $tab }}'" class="-mb-px border-b-2 px-1 pb-4 text-sm font-semibold" :class="eventTab === '{{ $tab }}' ? 'border-violet-500 text-white' : 'border-transparent text-white/60 hover:text-white'">{{ $label }}</button>
                                @endforeach
                            </div>
                            <div class="grid gap-5 pt-5 lg:grid-cols-[1fr_auto]">
                                <div class="max-w-4xl text-sm leading-6 text-white/75">
                                    <div x-show="eventTab === 'about'" class="space-y-3">
                                        <p>{{ $eventDescription }}</p>
                                        @if($session->event->program?->description)
                                            <p class="text-white/60">{{ $session->event->program->description }}</p>
                                        @endif
                                    </div>
                                    <div x-show="eventTab === 'team'" class="space-y-3">
                                        @forelse($teamAssignments as $assignment)
                                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-white/10 bg-black/20 p-3">
                                                <div>
                                                    <div class="font-semibold text-white">{{ $assignment->user?->name ?? trim(($assignment->member?->first_name ?? '').' '.($assignment->member?->last_name ?? '')) ?: 'Unassigned' }}</div>
                                                    <div class="text-xs text-white/55">{{ $assignment->role_title }} | {{ $assignment->section->title }}</div>
                                                </div>
                                                <span class="rounded-full bg-white/10 px-2.5 py-1 text-xs font-semibold text-white/65">{{ Str::headline($assignment->status) }}</span>
                                            </div>
                                        @empty
                                            <p>No team assignments have been added for this event yet.</p>
                                        @endforelse
                                    </div>
                                    <div x-show="eventTab === 'agenda'" class="space-y-3">
                                        @forelse($agendaSections as $section)
                                            <article class="rounded-lg border border-white/10 bg-black/20 p-3">
                                                <div class="flex flex-wrap items-start justify-between gap-3">
                                                    <div>
                                                        <div class="text-xs font-semibold uppercase text-violet-200">#{{ $section->position }} {{ Str::headline($section->section_type) }}</div>
                                                        <h3 class="mt-1 font-semibold text-white">{{ $section->title }}</h3>
                                                    </div>
                                                    <div class="text-right text-xs text-white/55">
                                                        <div>{{ $section->planned_start_time ? Str::of($section->planned_start_time)->substr(0, 5) : $eventTime }}</div>
                                                        @if($section->planned_duration_minutes)
                                                            <div>{{ $section->planned_duration_minutes }} min</div>
                                                        @endif
                                                    </div>
                                                </div>
                                                @if($section->description)
                                                    <p class="mt-2 text-sm text-white/65">{{ $section->description }}</p>
                                                @endif
                                            </article>
                                        @empty
                                            <p>No agenda sections have been added yet. Add program sections from the event page to show the order of service here.</p>
                                        @endforelse
                                    </div>
                                    <div x-show="eventTab === 'details'" class="grid gap-3 sm:grid-cols-2">
                                        <div class="rounded-lg border border-white/10 bg-black/20 p-3"><span class="block text-xs text-white/45">Event</span><span class="font-semibold text-white">{{ $session->event->title }}</span></div>
                                        <div class="rounded-lg border border-white/10 bg-black/20 p-3"><span class="block text-xs text-white/45">Program</span><span class="font-semibold text-white">{{ $session->event->program?->name ?? 'Not assigned' }}</span></div>
                                        <div class="rounded-lg border border-white/10 bg-black/20 p-3"><span class="block text-xs text-white/45">Meeting Type</span><span class="font-semibold text-white">{{ Str::headline($session->meeting_type) }}</span></div>
                                        <div class="rounded-lg border border-white/10 bg-black/20 p-3"><span class="block text-xs text-white/45">Capacity</span><span class="font-semibold text-white">{{ $session->capacity ?: 'Not set' }}</span></div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-3 divide-x divide-white/10 rounded-lg bg-black/20 text-center">
                                    <div class="px-6 py-4">
                                        <div class="flex items-center justify-center gap-2 text-violet-300"><i data-lucide="users-round" class="size-4"></i><span class="font-semibold" x-text="totalParticipantCount()"></span></div>
                                        <div class="mt-1 text-xs text-white/55">Attendees</div>
                                    </div>
                                    <div class="px-6 py-4">
                                        <div class="flex items-center justify-center gap-2 text-violet-300"><i data-lucide="mic" class="size-4"></i><span class="font-semibold" x-text="remoteParticipants.length + 1"></span></div>
                                        <div class="mt-1 text-xs text-white/55">Speakers</div>
                                    </div>
                                    <div class="px-6 py-4">
                                        <div class="flex items-center justify-center gap-2 text-violet-300"><i data-lucide="bar-chart-3" class="size-4"></i><span class="font-semibold">{{ $agendaSections->count() }}</span></div>
                                        <div class="mt-1 text-xs text-white/55">Agenda</div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </main>

                    <aside x-show="sidePanel" x-cloak class="space-y-4 fullscreen:h-full fullscreen:min-h-0 fullscreen:overflow-hidden">
                        <section x-show="sidePanel === 'chat'" class="flex min-h-[480px] flex-col overflow-hidden rounded-lg border border-white/10 bg-white/[0.055] shadow-xl shadow-black/20 fullscreen:h-full fullscreen:min-h-0">
                            <div class="grid grid-cols-3 border-b border-white/10 text-sm font-semibold">
                                <button type="button" @click="panelTab = 'chat'" class="border-b-2 px-3 py-4" :class="panelTab === 'chat' ? 'border-violet-500 text-white' : 'border-transparent text-white/60 hover:text-white'">Live Chat</button>
                                <button type="button" @click="panelTab = 'qna'" class="border-b-2 px-3 py-4" :class="panelTab === 'qna' ? 'border-violet-500 text-white' : 'border-transparent text-white/60 hover:text-white'">Q&amp;A</button>
                                <button type="button" @click="panelTab = 'polls'" class="border-b-2 px-3 py-4" :class="panelTab === 'polls' ? 'border-violet-500 text-white' : 'border-transparent text-white/60 hover:text-white'">Polls</button>
                            </div>

                            <div x-show="panelTab === 'chat'" class="flex min-h-0 flex-1 flex-col">
                                <div x-ref="chatScroll" class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                                    <template x-for="message in chatMessages" :key="message.id">
                                        <div class="flex gap-3">
                                            <img x-show="message.avatar" :src="message.avatar" :alt="message.author" class="size-9 shrink-0 rounded-full object-cover ring-1 ring-white/20">
                                            <div x-show="!message.avatar" class="grid size-9 shrink-0 place-items-center rounded-full bg-white/10 text-xs font-semibold" x-text="participantInitials(message.author)"></div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                                    <span class="font-semibold" :class="message.local ? 'text-violet-300' : 'text-rose-300'" x-text="message.author"></span>
                                                    <span class="text-white/35" x-text="message.at"></span>
                                                    <span x-show="message.direct" class="rounded-full bg-violet-500/20 px-2 py-0.5 text-[10px] font-semibold text-violet-200">
                                                        <span x-text="message.local ? `to ${message.recipientName || 'person'}` : 'direct'"></span>
                                                    </span>
                                                </div>
                                                <p class="mt-1 whitespace-pre-wrap text-sm leading-5 text-white" x-text="message.body"></p>
                                            </div>
                                        </div>
                                    </template>
                                    <div x-show="chatMessages.length === 0" class="grid h-full min-h-56 place-items-center text-center text-sm text-white/50">
                                        No chat messages yet.
                                    </div>
                                </div>

                                <form @submit.prevent="sendChatMessage()" class="border-t border-white/10 p-4">
                                    <div class="mb-2 flex items-center gap-2">
                                        <button type="button" @click="clearChatRecipient()" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold" :class="!chatRecipientIdentity ? 'bg-violet-600 text-white' : 'bg-white/10 text-white/70 hover:text-white'">
                                            <i data-lucide="users-round" class="size-3.5"></i>
                                            All
                                        </button>
                                        <template x-if="chatRecipientIdentity">
                                            <button type="button" @click="clearChatRecipient()" class="inline-flex min-w-0 items-center gap-1 rounded-lg bg-violet-600 px-2.5 py-1.5 text-xs font-semibold text-white">
                                                <i data-lucide="user-round" class="size-3.5"></i>
                                                <span class="truncate" x-text="chatRecipientName"></span>
                                                <i data-lucide="x" class="size-3.5"></i>
                                            </button>
                                        </template>
                                    </div>
                                    <div class="relative flex gap-2">
                                        <input x-ref="chatInput" x-model="chatDraft" @input="handleChatInput()" @keydown.escape="mentionOpen = false" class="min-w-0 flex-1 rounded-lg border border-white/10 bg-white/10 px-3 py-3 text-sm text-white outline-none placeholder:text-white/45 focus:border-violet-400 focus:ring-2 focus:ring-violet-500/25" placeholder="Type @ for direct message...">
                                        <div x-cloak x-show="mentionOpen" class="absolute bottom-14 left-0 z-20 max-h-56 w-full overflow-y-auto rounded-lg border border-white/10 bg-[#0b1320] p-2 shadow-lg">
                                            <template x-for="participant in filteredMentionRecipients()" :key="participant.identity">
                                                <button type="button" @click="selectMentionRecipient(participant)" class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm hover:bg-white/10">
                                                    <img x-show="participant.avatar" :src="participant.avatar" :alt="participant.name" class="size-8 rounded-full object-cover ring-1 ring-white/20">
                                                    <span x-show="!participant.avatar" class="grid size-8 place-items-center rounded-full bg-violet-500/25 text-xs font-semibold text-violet-100" x-text="participant.initials"></span>
                                                    <span class="min-w-0">
                                                        <span class="block truncate font-semibold" x-text="participant.name"></span>
                                                        <span class="block truncate text-xs text-white/45" x-text="participant.identity"></span>
                                                    </span>
                                                </button>
                                            </template>
                                            <div x-show="filteredMentionRecipients().length === 0" class="px-2 py-3 text-center text-xs text-white/50">No room participants available.</div>
                                        </div>
                                        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-500">
                                            Send
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div x-show="panelTab === 'qna'" class="flex min-h-0 flex-1 flex-col">
                                <div class="border-b border-white/10 p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <h2 class="text-sm font-semibold text-white">Questions & Answers</h2>
                                            <p class="mt-1 text-xs" :class="qnaEnabled ? 'text-emerald-200' : 'text-amber-200'" x-text="qnaEnabled ? 'Q&A is open for this meeting.' : 'Q&A is closed by the host.'"></p>
                                        </div>
                                        <div x-show="canManageInteractions" class="flex items-center gap-2">
                                            <button type="button" @click="setQnaEnabled(!qnaEnabled)" class="rounded-lg bg-white/10 px-3 py-2 text-xs font-semibold text-white hover:bg-white/15" x-text="qnaEnabled ? 'Close' : 'Open'"></button>
                                            <button type="button" @click="clearQuestions()" class="rounded-lg bg-rose-600/80 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-600">Clear</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
                                    <template x-for="question in qnaItems" :key="question.id">
                                        <article class="rounded-lg border border-white/10 bg-black/20 p-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-center gap-2 text-xs">
                                                        <span class="font-semibold text-violet-300" x-text="question.author"></span>
                                                        <span class="text-white/35" x-text="question.at"></span>
                                                    </div>
                                                    <p class="mt-2 text-sm leading-5 text-white" x-text="question.body"></p>
                                                </div>
                                                <button type="button" @click="upvoteQuestion(question.id)" class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-white/10 px-2 py-1 text-xs font-semibold text-white hover:bg-white/15">
                                                    <i data-lucide="arrow-up" class="size-3.5"></i>
                                                    <span x-text="question.votes || 0"></span>
                                                </button>
                                            </div>
                                        </article>
                                    </template>
                                    <div x-show="qnaItems.length === 0" class="grid h-full min-h-56 place-items-center text-center text-sm text-white/50">
                                        No questions yet.
                                    </div>
                                </div>
                                <form @submit.prevent="sendQuestion()" class="border-t border-white/10 p-4">
                                    <textarea x-model="questionDraft" rows="3" :disabled="!qnaEnabled" class="w-full rounded-lg border border-white/10 bg-white/10 px-3 py-3 text-sm text-white outline-none placeholder:text-white/45 disabled:cursor-not-allowed disabled:opacity-60 focus:border-violet-400 focus:ring-2 focus:ring-violet-500/25" placeholder="Ask a question for the host..."></textarea>
                                    <button type="submit" :disabled="!qnaEnabled" class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-3 text-sm font-semibold text-white hover:bg-violet-500 disabled:cursor-not-allowed disabled:opacity-60">
                                        <i data-lucide="circle-help" class="size-4"></i>
                                        Submit Question
                                    </button>
                                </form>
                            </div>

                            <div x-show="panelTab === 'polls'" class="min-h-0 flex-1 overflow-y-auto p-4">
                                <div x-show="canManageInteractions" class="mb-5 rounded-lg border border-white/10 bg-black/20 p-4">
                                    <div class="mb-3 flex items-center justify-between gap-3">
                                        <h2 class="text-sm font-semibold text-white">Create Poll</h2>
                                        <button x-show="hasActivePoll()" type="button" @click="pollOpen ? closePoll() : reopenPoll()" class="rounded-lg bg-white/10 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/15" x-text="pollOpen ? 'Close Poll' : 'Reopen Poll'"></button>
                                    </div>
                                    <input x-model="pollDraftQuestion" class="w-full rounded-lg border border-white/10 bg-white/10 px-3 py-2.5 text-sm text-white outline-none placeholder:text-white/45 focus:border-violet-400 focus:ring-2 focus:ring-violet-500/25" placeholder="Poll question">
                                    <div class="mt-3 grid gap-2">
                                        <template x-for="(option, index) in pollDraftOptions" :key="index">
                                            <input x-model="pollDraftOptions[index]" class="w-full rounded-lg border border-white/10 bg-white/10 px-3 py-2.5 text-sm text-white outline-none placeholder:text-white/45 focus:border-violet-400 focus:ring-2 focus:ring-violet-500/25" :placeholder="`Answer ${index + 1}`">
                                        </template>
                                    </div>
                                    <button type="button" @click="createPoll()" class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-500">
                                        <i data-lucide="bar-chart-3" class="size-4"></i>
                                        Publish Poll
                                    </button>
                                </div>

                                <div x-show="hasActivePoll()">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="rounded-lg bg-violet-600/20 px-3 py-2 text-xs font-semibold text-violet-200">Current Poll</div>
                                        <span class="rounded-full px-3 py-1.5 text-xs font-semibold" :class="pollOpen ? 'bg-emerald-600/25 text-emerald-200' : 'bg-amber-600/25 text-amber-100'" x-text="pollOpen ? 'Open' : 'Closed'"></span>
                                    </div>
                                    <h2 class="mt-4 text-base font-semibold leading-6 text-white" x-text="pollQuestion"></h2>
                                    <div class="mt-5 space-y-4">
                                        <template x-for="option in pollOptions" :key="option">
                                            <button type="button" @click="votePoll(option)" :disabled="!pollOpen" class="block w-full text-left disabled:cursor-not-allowed disabled:opacity-70">
                                                <div class="mb-2 flex items-center justify-between gap-3 text-sm text-white">
                                                    <span x-text="option"></span>
                                                    <span x-text="`${pollPercent(option)}%`"></span>
                                                </div>
                                                <div class="h-2 overflow-hidden rounded-full bg-white/10">
                                                    <div class="h-full rounded-full bg-violet-500" :style="`width: ${pollPercent(option)}%`"></div>
                                                </div>
                                            </button>
                                        </template>
                                    </div>
                                    <div class="mt-6 flex items-center justify-between text-sm text-white/50">
                                        <span x-text="`${pollTotalVotes()} votes`"></span>
                                        <span x-text="pollOpen ? 'Select an answer to vote.' : 'Poll is closed.'"></span>
                                    </div>
                                </div>
                                <div x-show="!hasActivePoll()" class="grid min-h-56 place-items-center rounded-lg border border-dashed border-white/15 p-5 text-center text-sm text-white/55">
                                    <div>
                                        <i data-lucide="bar-chart-3" class="mx-auto mb-3 size-8 text-white/45"></i>
                                        <p>No poll is active for this meeting.</p>
                                        <p x-show="canManageInteractions" class="mt-1 text-xs text-white/40">Create and publish a poll above.</p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section x-show="sidePanel === 'participants'" class="overflow-hidden rounded-lg border border-white/10 bg-white/[0.055] shadow-xl shadow-black/20 fullscreen:h-full">
                            <div class="flex items-center justify-between border-b border-white/10 p-4">
                                <h2 class="text-base font-semibold">Participants</h2>
                                <span class="rounded-full bg-white/10 px-2.5 py-1 text-xs font-semibold" x-text="totalParticipantCount()"></span>
                            </div>
                            <div class="max-h-[calc(100vh-210px)] space-y-2 overflow-y-auto p-4">
                                <div class="flex items-center gap-3 rounded-lg border border-white/10 bg-black/20 p-3">
                                    @if($memberAvatarUrl)
                                        <img src="{{ $memberAvatarUrl }}" alt="{{ $memberName }}" class="size-10 rounded-full object-cover ring-1 ring-white/20">
                                    @else
                                        <div class="grid size-10 place-items-center rounded-full bg-violet-500/25 text-sm font-semibold text-violet-100">{{ $memberInitials }}</div>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-semibold">{{ $memberName }} (You)</div>
                                        <div class="text-xs text-white/50" x-text="liveKitConnected ? 'Joined' : 'Not joined'"></div>
                                    </div>
                                    <div class="flex items-center gap-1 text-white/55">
                                        <i x-show="muted" data-lucide="mic-off" class="size-4"></i>
                                        <i x-show="!muted" data-lucide="mic" class="size-4"></i>
                                        <i x-show="camera" data-lucide="video" class="size-4"></i>
                                        <i x-show="!camera" data-lucide="video-off" class="size-4"></i>
                                    </div>
                                </div>
                                <template x-for="participant in remoteParticipants" :key="participant.identity">
                                    <div class="flex items-center gap-3 rounded-lg border border-white/10 bg-black/20 p-3">
                                        <img x-show="participant.avatar" :src="participant.avatar" :alt="participant.name" class="size-10 rounded-full object-cover ring-1 ring-white/20">
                                        <div x-show="!participant.avatar" class="grid size-10 place-items-center rounded-full bg-white/10 text-sm font-semibold" x-text="participant.initials"></div>
                                        <div class="min-w-0 flex-1">
                                            <div class="truncate text-sm font-semibold" x-text="participant.name"></div>
                                            <div class="text-xs text-white/50" x-text="participant.isSpeaking ? 'Speaking' : 'Connected'"></div>
                                        </div>
                                        <button type="button" @click="setChatRecipient(participant); sidePanel = 'chat'; panelTab = 'chat'" class="grid size-9 place-items-center rounded-lg bg-white/10 text-white hover:bg-violet-600" title="Direct message">
                                            <i data-lucide="message-square-text" class="size-4"></i>
                                        </button>
                                    </div>
                                </template>
                                @forelse(($provider === 'livekit' ? $activeRoomParticipants : $attendanceSession->records) as $participantRecord)
                                    <div x-show="!liveKitConnected" class="flex items-center gap-3 rounded-lg border border-white/10 bg-black/20 p-3">
                                        @if($memberAvatarUrl && $participantRecord->member_id === $member?->id)
                                            <img src="{{ $memberAvatarUrl }}" alt="{{ $memberName }}" class="size-10 rounded-full object-cover ring-1 ring-white/20">
                                        @else
                                            <div class="grid size-10 place-items-center rounded-full bg-white/10 text-sm font-semibold">{{ $participantRecord->member ? Str::upper(Str::substr($participantRecord->member->first_name,0,1).Str::substr($participantRecord->member->last_name,0,1)) : 'G' }}</div>
                                        @endif
                                        <div class="min-w-0">
                                            <div class="truncate text-sm font-semibold">{{ $participantRecord->member ? trim($participantRecord->member->first_name.' '.$participantRecord->member->last_name) : 'Guest' }}</div>
                                            <div class="text-xs text-white/50">{{ $participantRecord->checked_in_at?->format('h:i A') }} | {{ Str::headline($participantRecord->final_method ?? 'manual') }}</div>
                                        </div>
                                    </div>
                                @empty
                                    <div x-show="!liveKitConnected" class="rounded-lg border border-dashed border-white/15 p-4 text-center text-sm text-white/50">No checked-in participants yet.</div>
                                @endforelse
                            </div>
                        </section>

                        <section x-show="sidePanel === 'details'" class="space-y-4">
                            <div class="rounded-lg border border-white/10 bg-white/[0.055] p-5 shadow-xl shadow-black/20">
                                <div class="mb-4 flex items-center gap-2">
                                    <span class="grid size-9 place-items-center rounded-lg bg-white/10"><i data-lucide="list-checks" class="size-4"></i></span>
                                    <h2 class="text-base font-semibold">Room Details</h2>
                                </div>
                                <dl class="space-y-3 text-sm">
                                    <div class="flex justify-between gap-4"><dt class="text-white/50">Room ID</dt><dd class="break-all text-right font-medium">{{ $room }}</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-white/50">Mode</dt><dd class="font-medium">{{ Str::headline($provider) }}</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-white/50">Campus</dt><dd class="text-right font-medium">{{ $session->campus?->name ?? 'Unassigned' }}</dd></div>
                                    <div class="flex justify-between gap-4"><dt class="text-white/50">Token TTL</dt><dd class="font-medium">{{ $liveKitPayload['ttl_label'] ?? 'Session scoped' }}</dd></div>
                                    @if($connectionReady)
                                        <div class="flex justify-between gap-4"><dt class="text-white/50">Expires</dt><dd class="text-right font-medium">{{ \Illuminate\Support\Carbon::parse($liveKitPayload['expires_at'])->format('h:i A') }}</dd></div>
                                    @endif
                                </dl>
                                @if($connectionReady)
                                    <a x-cloak x-show="attendanceRecordUrl" :href="attendanceRecordUrl" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-500">
                                        <i data-lucide="badge-check" class="size-4"></i>
                                        Attendance Record
                                    </a>
                                @elseif($record)
                                    <a href="{{ route('attendance.records.show', [$attendanceSession, $member->opaqueId()]) }}" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-500">
                                        <i data-lucide="badge-check" class="size-4"></i>
                                        Attendance Record
                                    </a>
                                @endif
                            </div>

                            @if($connectionReady)
                                <div class="rounded-lg border border-white/10 bg-white/[0.055] p-5 shadow-xl shadow-black/20">
                                    <div class="flex items-center gap-2">
                                        <span class="grid size-9 place-items-center rounded-lg bg-violet-500/25 text-violet-100"><i data-lucide="radio-tower" class="size-4"></i></span>
                                        <h2 class="text-base font-semibold">LiveKit Connection Health</h2>
                                    </div>
                                    <dl class="mt-4 space-y-3 text-sm">
                                        <div class="flex justify-between gap-4"><dt class="text-white/50">Server</dt><dd class="break-all text-right font-medium">{{ $liveKitPayload['server_url'] }}</dd></div>
                                        <div class="flex justify-between gap-4"><dt class="text-white/50">Identity</dt><dd class="break-all text-right font-medium">{{ $liveKitPayload['identity'] }}</dd></div>
                                        <div class="flex justify-between gap-4"><dt class="text-white/50">Remote</dt><dd class="font-medium" x-text="remoteParticipantCount"></dd></div>
                                        <div class="flex justify-between gap-4"><dt class="text-white/50">Attendance</dt><dd :class="attendanceMarked ? 'text-emerald-300' : 'text-amber-300'" class="font-medium" x-text="attendanceMarked ? 'Marked' : 'Pending'"></dd></div>
                                    </dl>
                                </div>
                            @endif
                        </section>

                        <section x-show="sidePanel === 'chat' && panelTab !== 'polls'" class="rounded-lg border border-white/10 bg-white/[0.055] p-5 shadow-xl shadow-black/20">
                            <div class="mb-5 flex items-center justify-between gap-3">
                                <span class="rounded-lg bg-violet-600/25 px-3 py-1.5 text-xs font-semibold text-violet-200">Current Poll</span>
                                <span x-show="hasActivePoll()" class="inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-xs font-semibold" :class="pollOpen ? 'bg-emerald-600/25 text-emerald-200' : 'bg-amber-600/25 text-amber-100'">
                                    <span class="size-2 rounded-full" :class="pollOpen ? 'bg-emerald-400' : 'bg-amber-300'"></span>
                                    <span x-text="pollOpen ? 'Open' : 'Closed'"></span>
                                </span>
                            </div>
                            <div x-show="hasActivePoll()">
                                <h2 class="text-base font-semibold leading-6 text-white" x-text="pollQuestion"></h2>
                                <div class="mt-5 space-y-4">
                                    <template x-for="option in pollOptions" :key="option">
                                        <button type="button" @click="votePoll(option)" :disabled="!pollOpen" class="block w-full text-left disabled:cursor-not-allowed disabled:opacity-70">
                                            <div class="mb-2 flex items-center justify-between gap-3 text-sm text-white">
                                                <span x-text="option"></span>
                                                <span x-text="`${pollPercent(option)}%`"></span>
                                            </div>
                                            <div class="h-2 overflow-hidden rounded-full bg-white/10">
                                                <div class="h-full rounded-full bg-violet-500" :style="`width: ${pollPercent(option)}%`"></div>
                                            </div>
                                        </button>
                                    </template>
                                </div>
                                <div class="mt-6 flex items-center justify-between text-sm text-white/50">
                                    <span x-text="`${pollTotalVotes()} votes`"></span>
                                    <span x-text="pollOpen ? 'Select an answer to vote.' : 'Poll is closed.'"></span>
                                </div>
                            </div>
                            <div x-show="!hasActivePoll()" class="rounded-lg border border-dashed border-white/15 p-5 text-center text-sm text-white/55">
                                No poll is active for this meeting.
                            </div>
                        </section>
                    </aside>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
