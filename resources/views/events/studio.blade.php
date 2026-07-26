<x-app-layout title="{{ $session->title }} Studio" :breadcrumbs="[]" :hide-topbar="true" main-class="p-0">
    @php
        $lowerThird = $state->lower_third ?? [];
        $lowerThirdBackground = $lowerThird['background_url'] ?? null;
        $lowerThirdBackgroundStyle = $lowerThird['background_style'] ?? null;
        $lowerThirdStyle = filled($lowerThirdBackground)
            ? "background-image: linear-gradient(90deg, rgba(0,0,0,.93), rgba(7,19,33,.82), rgba(0,0,0,.58)), url('".e($lowerThirdBackground)."'); background-size: cover; background-position: center;"
            : ($lowerThirdBackgroundStyle ?: '');
        $scripture = $state->scripture ?? [];
        $audioMixer = $state->audio_mixer ?? [];
        $destinations = $state->destinations ?? [];
        $quickActions = $state->quick_actions ?? [];
        $isRecording = filter_var($quickActions['recording'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sceneTypes = ['camera' => 'Participant Camera', 'screen' => 'Participant Screen', 'scripture' => 'Scripture', 'presentation' => 'Presentation', 'countdown' => 'Countdown', 'media' => 'Media', 'agenda' => 'Agenda'];
        $viewerCount = max($participants->count(), $studioStatePayload['poll']['options'][0]['votes'] ?? 0);
        $teamAssignments = $agendaSections->flatMap(fn ($section) => $section->assignments);
        $sourceCandidates = ($sourceParticipants ?? $participants)->map(function ($record) {
            $metadata = $record->metadata ?? [];
            $name = $metadata['participant_name'] ?? ($record->member ? trim($record->member->first_name.' '.$record->member->last_name) : 'Guest');
            $identity = $metadata['livekit_identity'] ?? $record->member?->email ?? $record->member?->phone;
            $avatar = $metadata['avatar'] ?? null;

            return ['identity' => $identity, 'name' => $name, 'avatar' => $avatar];
        })->filter(fn ($candidate) => filled($candidate['identity']))->values();
        $sourceTargetScene = $state->previewScene ?: $state->liveScene;
        $sourceAssignUrl = $sourceTargetScene ? route('meetings.rooms.studio.scenes.source', [$session, $provider, $sourceTargetScene]) : null;
        $mainSourceAssignUrl = $state->liveScene ? route('meetings.rooms.studio.scenes.source', [$session, $provider, $state->liveScene]) : null;
        $sceneSourceUrls = $scenes->mapWithKeys(fn ($scene) => [(string) $scene->id => route('meetings.rooms.studio.scenes.source', [$session, $provider, $scene])])->all();
        $studioSceneOptions = $scenes->map(fn ($scene) => [
            'id' => $scene->id,
            'title' => $scene->title,
            'type' => $scene->scene_type,
            'source_identity' => $scene->settings['source_identity'] ?? null,
            'source_kind' => $scene->settings['source_kind'] ?? 'camera',
        ])->values();
        $liveSceneSettings = $state->liveScene?->settings ?? [];
        $scriptureSearchUrl = 'https://www.biblegateway.com/quicksearch/?quicksearch='.urlencode((string) ($scripture['reference'] ?? 'Matthew 18:20'));
        $panel = 'rounded-lg border border-[#1b2d42] bg-[#0b1a2b] shadow-[0_18px_45px_rgba(0,0,0,.28)]';
        $softPanel = 'rounded-lg border border-[#1b2d42] bg-[#0d2033]';
        $operatorAvatar = auth()->user()?->avatar_src;
        $operatorName = auth()->user()?->name ?? 'Studio Operator';
        $operatorRole = auth()->user()?->roles?->pluck('name')->first() ?? 'Administrator';
        $analyticsValues = [2, 4, 3, 6, 2, 7, 5, 6, 11, 8, 10, 13, 16, 15, 12, 9, 10, 13, 11, 14, 10, 15];
    @endphp

    <div x-data="meetingStudio(@js($studioLiveKitPayload), @js(['source_assign_url' => $sourceAssignUrl, 'main_source_assign_url' => $mainSourceAssignUrl, 'scene_source_urls' => $sceneSourceUrls, 'scenes' => $studioSceneOptions, 'csrf_token' => csrf_token(), 'live_scene' => $studioStatePayload['live_scene'] ?? null, 'preview_scene' => $studioStatePayload['preview_scene'] ?? null]))" class="min-h-screen overflow-hidden bg-[#050b14] text-[#e8eefc]">
            <div class="grid min-h-screen min-w-0 grid-rows-[56px_minmax(0,1fr)]">
                <header class="flex items-center justify-between gap-3 border-b border-[#142337] bg-[#071321] px-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button" x-on:click="sidebarOpen = ! sidebarOpen" class="grid size-9 place-items-center rounded-lg text-white/70 hover:bg-white/10" title="Toggle studio menu"><i data-lucide="menu" class="size-5"></i></button>
                        <div class="truncate text-lg font-semibold">{{ $session->title }}</div>
                        <span class="rounded bg-emerald-600 px-2 py-1 text-[10px] font-bold uppercase">{{ $state->stream_status }}</span>
                        <span class="hidden text-xs text-white/65 md:inline">{{ $session->session_date->format('M d, Y') }} | {{ Str::of($session->starts_at)->substr(0, 5) }}</span>
                        <span class="hidden rounded bg-violet-600/25 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-violet-100 lg:inline">{{ $session->event->program?->name ?? 'Live Program' }}</span>
                        <span class="hidden items-center gap-1 rounded bg-rose-500/12 px-3 py-1.5 text-xs font-semibold text-white/80 xl:inline-flex"><i data-lucide="radio" class="size-3.5 text-rose-400"></i>{{ now()->format('H:i:s') }}</span>
                        <span class="hidden items-center gap-1 text-xs text-white/65 xl:inline-flex"><i data-lucide="eye" class="size-4"></i>{{ number_format($viewerCount) }}</span>
                        <span class="hidden items-center gap-1 text-xs text-white/65 xl:inline-flex"><i data-lucide="heart" class="size-4 text-rose-400"></i>{{ number_format($poll?->votes()->count() ?? 0) }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="hidden items-center gap-1 text-xs font-semibold text-emerald-400 md:inline-flex"><i data-lucide="wifi" class="size-4"></i>Excellent</span>
                        <span class="hidden items-center gap-1 text-xs text-white/55 lg:inline-flex"><i data-lucide="zap" class="size-4 text-emerald-300"></i>8.2 Mbps</span>
                        <button type="button" x-on:click="navigator.clipboard?.writeText('{{ $shortRoomUrl }}'); copied = true; setTimeout(() => copied = false, 1800)" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-3 py-2 text-sm font-semibold text-white hover:bg-violet-500">
                            <i data-lucide="share-2" class="size-4"></i><span x-text="copied ? 'Copied' : 'Share Link'">Share Link</span>
                        </button>
                        <form method="POST" action="{{ route('meetings.rooms.studio.state.update', [$session, $provider]) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="stream_status" value="live">
                            <input type="hidden" name="quick_actions[recording]" value="{{ $isRecording ? '0' : '1' }}">
                            <button class="hidden items-center gap-2 rounded-lg border {{ $isRecording ? 'border-rose-400/50 bg-rose-500/12 text-rose-100' : 'border-[#26384c] text-white/80' }} px-3 py-2 text-sm font-semibold hover:bg-white/[0.06] sm:inline-flex"><span class="size-2 rounded-full {{ $isRecording ? 'bg-rose-400 animate-pulse' : 'bg-rose-500' }}"></span>{{ $isRecording ? 'Stop Rec' : 'Record' }}</button>
                        </form>
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-lg px-2 py-1 hover:bg-white/[0.05]" title="Open profile">
                            @if($operatorAvatar)
                                <img src="{{ $operatorAvatar }}" alt="{{ $operatorName }}" class="size-9 rounded-full object-cover">
                            @else
                                <span class="grid size-9 place-items-center rounded-full bg-[#1b2d42] text-xs font-bold">{{ Str::upper(Str::substr($operatorName, 0, 1)) }}</span>
                            @endif
                            <span class="hidden min-w-0 text-left xl:block">
                                <span class="block max-w-40 truncate text-xs font-bold uppercase">{{ $operatorName }}</span>
                                <span class="block text-[10px] text-white/45">{{ $operatorRole }}</span>
                            </span>
                            <i data-lucide="chevron-down" class="hidden size-4 text-white/45 xl:block"></i>
                        </a>
                    </div>
                </header>

                <main class="min-h-0 overflow-y-auto p-2">
                    @if(session('status'))
                        <div class="mb-3 rounded-lg border border-emerald-400/25 bg-emerald-500/10 p-3 text-sm text-emerald-100">{{ session('status') }}</div>
                    @endif

                    <div class="grid gap-2 2xl:grid-cols-[minmax(0,1fr)_360px]">
                        <section class="min-w-0 space-y-2">
                            <div class="grid gap-2 xl:grid-cols-[minmax(0,1.56fr)_minmax(330px,.95fr)]">
                                <section id="dashboard" class="{{ $panel }} overflow-hidden scroll-mt-4">
                                    <div class="relative aspect-video min-h-[344px] overflow-hidden bg-[radial-gradient(circle_at_32%_24%,rgba(38,105,255,.78),transparent_24%),linear-gradient(135deg,#132d5d,#0a1423_55%,#070b13)]">
                                        <video x-ref="studioLiveVideo" x-show="studioLiveSourceKind() === 'camera' && studioFeaturedHasVideo()" autoplay playsinline class="absolute inset-0 h-full w-full bg-[#050b14] object-cover"></video>
                                        <video x-ref="studioLiveScreen" x-show="studioLiveSourceKind() === 'screen' && studioFeaturedHasVideo()" autoplay playsinline class="absolute inset-0 h-full w-full bg-[#050b14] object-contain"></video>
                                        <audio x-ref="studioLiveAudio" autoplay></audio>
                                        <div class="absolute left-4 top-4 z-10 flex gap-2">
                                            <span class="rounded bg-rose-600 px-3 py-1.5 text-xs font-bold uppercase shadow-lg">Live</span>
                                            <span class="inline-flex items-center gap-1 rounded bg-black/45 px-3 py-1.5 text-xs"><i data-lucide="eye" class="size-3.5"></i>{{ number_format($viewerCount) }}</span>
                                        </div>
                                        <div x-show="studioFeaturedParticipant()" x-cloak class="absolute left-4 top-16 z-10 inline-flex items-center gap-1 rounded bg-black/60 px-3 py-1.5 text-xs text-white shadow-xl backdrop-blur">
                                            <span x-text="`${studioFeaturedParticipant()?.name || 'Live source'} - ${studioLiveSourceKind()}`"></span>
                                        </div>
                                        <div class="absolute right-4 top-4 z-10 max-w-xs rounded-lg border border-white/10 bg-black/45 px-3 py-2 text-xs text-white/80 shadow-xl backdrop-blur">
                                            <div class="font-semibold text-white">Main Screen</div>
                                            <div class="mt-1 truncate">{{ $liveSceneSettings['source_name'] ?? $state->liveScene?->title ?? 'No live scene' }}</div>
                                        </div>
                                        <div class="absolute right-4 top-20 z-10 flex h-32 items-end gap-0.5">
                                            @foreach([18, 36, 58, 82, 70, 46, 88, 100, 74, 54, 32] as $height)
                                                <span class="w-1 rounded-full {{ $height > 82 ? 'bg-rose-400' : ($height > 56 ? 'bg-amber-300' : 'bg-emerald-400') }}" style="height: {{ $height }}%"></span>
                                            @endforeach
                                        </div>
                                        <div x-show="!studioFeaturedHasVideo()" class="absolute inset-0 overflow-hidden">
                                            <div class="absolute inset-0 bg-[radial-gradient(circle_at_38%_28%,rgba(37,99,235,.62),transparent_24%),radial-gradient(circle_at_76%_24%,rgba(245,158,11,.32),transparent_12%),linear-gradient(135deg,#071224,#111749_54%,#050914)]"></div>
                                            <div class="absolute inset-0 bg-gradient-to-t from-black/72 via-transparent to-black/15"></div>
                                            <div class="absolute left-[24%] top-10 hidden h-44 w-44 rounded-full bg-violet-500/20 blur-3xl md:block"></div>
                                            <div class="absolute bottom-28 left-[28%] w-[46%] rounded-[100%] border-t border-violet-200/35"></div>
                                            <div class="absolute left-1/2 top-1/2 grid -translate-x-1/2 -translate-y-[58%] place-items-center text-center">
                                                @if($operatorAvatar)
                                                    <img src="{{ $operatorAvatar }}" alt="{{ $operatorName }}" class="size-28 rounded-full object-cover ring-2 ring-white/20 shadow-2xl">
                                                @else
                                                    <div class="grid size-28 place-items-center rounded-full bg-white/10 text-4xl font-black ring-1 ring-white/20">{{ Str::upper(Str::substr($operatorName, 0, 1)) }}</div>
                                                @endif
                                                <div class="mt-5 inline-flex items-center gap-2 rounded-full border border-white/10 bg-black/35 px-4 py-2 text-sm text-white/75 backdrop-blur">
                                                    <i data-lucide="{{ $state->liveScene?->scene_type === 'scripture' ? 'book-open' : ($state->liveScene?->scene_type === 'countdown' ? 'timer' : 'video') }}" class="size-4 text-violet-100"></i>
                                                    <span>{{ $state->liveScene?->description ?? 'Primary speaker camera' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="absolute {{ filled($state->ticker_text) ? 'bottom-16' : 'bottom-12' }} left-4 right-4 overflow-hidden rounded-xl border border-amber-300/25 bg-gradient-to-r from-black/92 via-[#071321]/95 to-black/70 p-4" style="{{ $lowerThirdStyle }}">
                                            <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-amber-300/80 to-transparent"></div>
                                            <div class="pointer-events-none absolute -top-6 left-0 right-0 h-10 opacity-80">
                                                <div class="h-full w-full border-t border-amber-300/55" style="clip-path: polygon(0 70%, 24% 42%, 52% 64%, 78% 36%, 100% 58%, 100% 100%, 0 100%);"></div>
                                            </div>
                                            <div class="relative flex items-center justify-between gap-4">
                                                <div class="flex items-center gap-4">
                                                    <div class="grid min-w-36 grid-cols-[46px_1fr] items-center gap-3 border-r border-amber-300/60 py-1 pr-5">
                                                        <div class="grid size-11 place-items-center text-amber-200"><i data-lucide="church" class="size-8"></i></div>
                                                        <div class="min-w-0">
                                                            <div class="truncate text-sm font-bold leading-none text-white">{{ $session->event->program?->name ?? 'Church Live' }}</div>
                                                            <div class="mt-1 text-[10px] uppercase tracking-[0.16em] text-white/48">Ministries</div>
                                                        </div>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <div class="truncate text-2xl font-bold">{{ $lowerThird['speaker_name'] ?: $state->liveScene?->title ?: $session->title }}</div>
                                                        <div class="truncate text-sm text-white/65">{{ $lowerThird['speaker_role'] ?: $lowerThird['service_label'] ?: $session->event->title }}</div>
                                                    </div>
                                                </div>
                                                <div class="hidden text-right text-xs text-white/55 md:block">
                                                    <div>#ChurchLive</div>
                                                    <div class="mt-1 text-white/35">#{{ Str::slug($session->event->program?->name ?? 'service', '') }}</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="absolute bottom-4 left-4 right-4 rounded-lg border border-violet-400/35 bg-black/45 px-4 py-2 text-sm shadow-xl backdrop-blur">
                                            <span class="mr-3 text-2xl leading-none text-sky-400">&ldquo;</span>
                                            <span>{{ $scripture['text'] ?? 'For where two or three are gathered in my name, there am I among them.' }}</span>
                                            <span class="float-right pl-3 text-xs text-white/55">{{ $scripture['reference'] ?? 'Matthew 18:20 (KJV)' }}</span>
                                        </div>
                                        @if(filled($state->ticker_text))
                                            <div class="absolute bottom-0 left-0 right-0 bg-violet-600/90 px-4 py-1.5 text-xs font-semibold text-white shadow-lg">
                                                <span class="mr-2 rounded bg-white/15 px-2 py-0.5 text-[10px] uppercase">Ticker</span>{{ $state->ticker_text }}
                                            </div>
                                        @endif
                                    </div>
                                </section>

                                <section class="{{ $panel }} p-3">
                                    <div class="mb-3 flex items-center justify-between">
                                        <h2 class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-400"><span class="size-2 rounded-full bg-emerald-400"></span>Preview <span class="text-white/45">(Next Scene)</span></h2>
                                        <i data-lucide="grip" class="size-4 text-white/45"></i>
                                    </div>
                                    <div class="relative aspect-video overflow-hidden rounded-lg border border-[#1b2d42] bg-[radial-gradient(circle_at_top,rgba(124,58,237,.55),transparent_34%),linear-gradient(135deg,#17254a,#050914)] p-4">
                                        <video x-ref="studioPreviewVideo" x-show="studioPreviewSourceKind() === 'camera' && studioPreviewHasVideo()" autoplay playsinline muted class="absolute inset-0 h-full w-full bg-[#050b14] object-cover"></video>
                                        <video x-ref="studioPreviewScreen" x-show="studioPreviewSourceKind() === 'screen' && studioPreviewHasVideo()" autoplay playsinline muted class="absolute inset-0 h-full w-full bg-[#050b14] object-contain"></video>
                                        <audio x-ref="studioPreviewAudio" autoplay muted></audio>
                                        <div x-show="studioPreviewHasVideo()" class="absolute inset-0 bg-gradient-to-t from-black/55 via-transparent to-transparent"></div>
                                        <div class="absolute left-3 top-3 rounded bg-black/45 px-2 py-1 text-xs">{{ $state->previewScene?->title ?? 'Main Camera' }}</div>
                                        <div x-show="!studioPreviewHasVideo()" class="absolute bottom-3 left-3 right-3 grid place-items-center rounded-lg border border-dashed border-white/25 bg-black/30 p-8 text-center">
                                            <i data-lucide="video" class="mb-2 size-6 text-white/75"></i>
                                            <div class="text-sm">{{ $state->previewScene?->description ?? 'Primary speaker camera' }}</div>
                                        </div>
                                        <div class="absolute bottom-3 right-3 flex h-24 items-end gap-0.5">
                                            @foreach([22, 42, 66, 88, 60, 36, 78, 92, 52] as $height)
                                                <span class="w-1 rounded-full {{ $height > 74 ? 'bg-rose-400' : ($height > 50 ? 'bg-amber-300' : 'bg-emerald-400') }}" style="height: {{ $height }}%"></span>
                                            @endforeach
                                        </div>
                                    </div>
                                    <form method="POST" action="{{ route('meetings.rooms.studio.state.update', [$session, $provider]) }}" class="mt-3 grid grid-cols-[1fr_86px_42px] gap-2">
                                        @csrf @method('PUT')
                                        <select name="quick_actions[transition]" class="rounded-lg border border-[#1b2d42] bg-[#081525] px-3 py-2 text-xs text-white">
                                            @foreach(['fade' => 'Fade', 'cut' => 'Cut', 'dissolve' => 'Dissolve'] as $value => $label)
                                                <option value="{{ $value }}" @selected(($quickActions['transition'] ?? 'fade') === $value) class="text-slate-900">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <input name="quick_actions[transition_duration]" value="{{ $quickActions['transition_duration'] ?? '1.0s' }}" class="rounded-lg border border-[#1b2d42] bg-[#081525] px-3 py-2 text-xs text-white">
                                        <button class="grid place-items-center rounded-lg bg-white/10 text-white/75 hover:bg-white/15" title="Save transition"><i data-lucide="save" class="size-4"></i></button>
                                    </form>
                                    <form method="POST" action="{{ $state->previewScene ? route('meetings.rooms.studio.scenes.live', [$session, $provider, $state->previewScene]) : '#' }}" class="mt-3">
                                        @csrf
                                        <button @disabled(! $state->previewScene) class="w-full rounded-lg bg-violet-600 px-4 py-3 text-sm font-bold text-white hover:bg-violet-500 disabled:cursor-not-allowed disabled:opacity-50">Take Live</button>
                                    </form>
                                </section>
                            </div>

                            <div class="grid gap-2 xl:grid-cols-[minmax(0,1fr)_340px]">
                            <section id="stream-scenes" class="{{ $panel }} p-3 scroll-mt-4">
                                <div class="mb-3 flex gap-2 overflow-x-auto border-b border-[#1b2d42] text-sm">
                                    @foreach([['scenes', 'Scenes'], ['sources', 'Sources'], ['overlays', 'Overlays'], ['media', 'Media'], ['lowerThirds', 'Lower Thirds']] as [$tab, $label])
                                        <button type="button" x-on:click="sceneTab = '{{ $tab }}'" class="-mb-px whitespace-nowrap border-b-2 px-3 pb-3 transition" x-bind:class="sceneTab === '{{ $tab }}' ? 'border-violet-500 text-white' : 'border-transparent text-white/45 hover:text-white'">{{ $label }}</button>
                                    @endforeach
                                    <button type="button" x-on:click="sceneTab = 'scenes'" class="-mb-px whitespace-nowrap border-b-2 border-transparent px-3 pb-3 text-white/45 transition hover:text-white">Live Screens</button>
                                </div>
                                <div x-show="sceneTab === 'scenes'" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                                    @foreach($scenes as $scene)
                                        @php
                                            $sceneSettings = $scene->settings ?? [];
                                            $sceneIcon = match ($scene->scene_type) {
                                                'screen' => 'screen-share',
                                                'scripture' => 'book-open',
                                                'presentation' => 'monitor',
                                                'countdown' => 'timer',
                                                'media' => 'image',
                                                'agenda' => 'list-checks',
                                                default => 'video',
                                            };
                                        @endphp
                                        <article class="group overflow-hidden rounded-lg border {{ $scene->is_live ? 'border-violet-500 shadow-[0_0_0_1px_rgba(139,92,246,.45)]' : 'border-[#1b2d42]' }} bg-[#071421]">
                                            <div class="relative aspect-video overflow-hidden bg-[radial-gradient(circle_at_30%_18%,rgba(79,70,229,.76),transparent_32%),linear-gradient(135deg,#122850,#050914)]">
                                                <div class="absolute inset-0 bg-gradient-to-t from-black/65 via-transparent to-transparent"></div>
                                                @if($scene->scene_type === 'countdown')
                                                    <div class="absolute inset-0 grid place-items-center text-center">
                                                        <div class="text-[10px] uppercase tracking-[0.22em] text-white/35">Starting In</div>
                                                        <div class="text-3xl font-light">{{ str_pad((string) ($sceneSettings['minutes'] ?? $quickActions['countdown_minutes'] ?? 5), 2, '0', STR_PAD_LEFT) }}:00</div>
                                                    </div>
                                                @elseif($scene->scene_type === 'scripture')
                                                    <div class="absolute inset-0 p-3 text-[10px] leading-4 text-white/75">
                                                        <div class="mb-1 font-semibold text-violet-200">{{ $sceneSettings['reference'] ?? $scripture['reference'] ?? 'Scripture' }}</div>
                                                        {{ Str::limit($sceneSettings['text'] ?? $scripture['text'] ?? 'For where two or three are gathered in my name, there am I among them.', 88) }}
                                                    </div>
                                                @elseif(in_array($scene->scene_type, ['media', 'presentation'], true) && filled($sceneSettings['media_url'] ?? $scene->media_url))
                                                    <div class="absolute inset-0 grid place-items-center p-3 text-center text-[10px] text-white/70">
                                                        <i data-lucide="{{ $sceneIcon }}" class="mb-2 size-6 text-violet-200"></i>
                                                        <span class="break-all">{{ Str::limit($sceneSettings['media_url'] ?? $scene->media_url, 80) }}</span>
                                                    </div>
                                                @elseif($scene->scene_type === 'agenda' && filled($sceneSettings['notes'] ?? null))
                                                    <div class="absolute inset-0 p-3 text-[10px] leading-4 text-white/75">{{ Str::limit($sceneSettings['notes'], 110) }}</div>
                                                @else
                                                    <div class="absolute inset-0 grid place-items-center text-white/75"><i data-lucide="{{ $sceneIcon }}" class="size-7"></i></div>
                                                @endif
                                                <div class="absolute bottom-1 right-1 flex h-12 items-end gap-0.5">
                                                    @foreach([20, 44, 76, 58, 92, 62, 34] as $height)
                                                        <span class="w-1 rounded-full {{ $height > 74 ? 'bg-rose-400' : ($height > 50 ? 'bg-amber-300' : 'bg-emerald-400') }}" style="height: {{ $height }}%"></span>
                                                    @endforeach
                                                </div>
                                                <span class="absolute left-2 top-2 rounded bg-black/55 px-1.5 py-0.5 text-[10px]">{{ $scene->position }}</span>
                                            </div>
                                            <div class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-2 px-2 py-2 text-[11px]">
                                                <div class="min-w-0">
                                                    <div class="truncate font-semibold">{{ $scene->title }}</div>
                                                    <div class="truncate text-white/45">{{ $sceneTypes[$scene->scene_type] ?? Str::headline($scene->scene_type) }}</div>
                                                </div>
                                                <form method="POST" action="{{ route('meetings.rooms.studio.scenes.preview', [$session, $provider, $scene]) }}">@csrf<button class="rounded border border-violet-400/45 px-2 py-1 text-violet-200 hover:bg-violet-500/10">Preview</button></form>
                                                <form method="POST" action="{{ route('meetings.rooms.studio.scenes.live', [$session, $provider, $scene]) }}">@csrf<button class="rounded bg-emerald-600 px-2 py-1 font-semibold text-white hover:bg-emerald-500">Live</button></form>
                                                <form method="POST" action="{{ route('meetings.rooms.studio.scenes.destroy', [$session, $provider, $scene]) }}" onsubmit="return confirm('Delete this studio screen?');">
                                                    @csrf @method('DELETE')
                                                    <button @disabled($scenes->count() <= 1) class="grid size-7 place-items-center rounded text-white/50 hover:bg-rose-500/15 hover:text-rose-200 disabled:cursor-not-allowed disabled:opacity-30" title="{{ $scenes->count() <= 1 ? 'Keep at least one screen' : 'Delete screen' }}"><i data-lucide="trash-2" class="size-3.5"></i></button>
                                                </form>
                                            </div>
                                            <form method="POST" action="{{ route('meetings.rooms.studio.scenes.source', [$session, $provider, $scene]) }}" x-data="{ sourceIdentity: @js((string) ($sceneSettings['source_identity'] ?? '')), sourceName: @js((string) ($sceneSettings['source_name'] ?? '')) }" class="space-y-2 border-t border-[#1b2d42] p-2">
                                                @csrf @method('PUT')
                                                <div class="flex items-center justify-between gap-2 text-[10px] font-semibold uppercase tracking-wide text-white/45">
                                                    <span>Input Source</span>
                                                    <span class="truncate text-white/35" x-text="sourceName || sourceIdentity || 'Auto source'">{{ $sceneSettings['source_name'] ?? $sceneSettings['source_identity'] ?? 'Auto source' }}</span>
                                                </div>
                                                <div class="grid gap-2">
                                                    <select name="source_kind" class="rounded border border-[#1b2d42] bg-black/20 px-2 py-1.5 text-[11px] text-white outline-none">
                                                        <option value="camera" @selected(($sceneSettings['source_kind'] ?? 'camera') === 'camera') class="text-slate-900">Participant camera</option>
                                                        <option value="screen" @selected(($sceneSettings['source_kind'] ?? 'camera') === 'screen') class="text-slate-900">Participant screen</option>
                                                    </select>
                                                    <select name="source_identity" x-model="sourceIdentity" x-on:change="sourceName = $event.target.selectedOptions[0]?.dataset.name || ''" class="rounded border border-[#1b2d42] bg-black/20 px-2 py-1.5 text-[11px] text-white outline-none">
                                                        <option value="" class="text-slate-900">Auto / no assigned participant</option>
                                                        @if(filled($sceneSettings['source_identity'] ?? null) && ! $sourceCandidates->pluck('identity')->contains($sceneSettings['source_identity']))
                                                            <option value="{{ $sceneSettings['source_identity'] }}" data-name="{{ $sceneSettings['source_name'] ?? $sceneSettings['source_identity'] }}" selected class="text-slate-900">{{ $sceneSettings['source_name'] ?? $sceneSettings['source_identity'] }}</option>
                                                        @endif
                                                        @foreach($sourceCandidates as $candidate)
                                                            <option value="{{ $candidate['identity'] }}" data-name="{{ $candidate['name'] }}" @selected(($sceneSettings['source_identity'] ?? null) === $candidate['identity']) class="text-slate-900">{{ $candidate['name'] }}</option>
                                                        @endforeach
                                                        <template x-for="participant in liveParticipants" :key="participant.identity">
                                                            <option :value="participant.identity" :data-name="participant.name" x-text="`${participant.name} (live)`"></option>
                                                        </template>
                                                    </select>
                                                    <input name="source_name" x-model="sourceName" x-bind:placeholder="sourceIdentity ? 'Display name for this source' : 'Auto source name'" class="rounded border border-[#1b2d42] bg-black/20 px-2 py-1.5 text-[11px] text-white outline-none placeholder:text-white/35">
                                                    <button class="rounded bg-white/10 px-2 py-1.5 text-[11px] font-semibold text-white/75 hover:bg-violet-500/20">Save Source</button>
                                                </div>
                                            </form>
                                        </article>
                                    @endforeach
                                    <form method="POST" action="{{ route('meetings.rooms.studio.scenes.store', [$session, $provider]) }}" x-data="{ sceneType: 'camera', sourceIdentity: '', sourceName: '' }" class="grid gap-2 rounded-lg border border-dashed border-[#26384c] bg-[#071421] p-3 transition hover:border-violet-400/60 hover:bg-white/[0.03]">
                                        @csrf
                                        <div class="flex items-center gap-2 text-xs font-semibold text-white/65">
                                            <i data-lucide="plus" class="size-4 text-violet-200"></i>
                                            <span>Add Scene</span>
                                            <span class="sr-only">Add Screen</span>
                                        </div>
                                        <input id="add-studio-screen-title" name="title" required placeholder="Screen title" class="rounded-lg border border-[#1b2d42] bg-black/20 px-3 py-2 text-xs text-white outline-none placeholder:text-white/35">
                                        <select name="scene_type" x-model="sceneType" class="rounded-lg border border-[#1b2d42] bg-black/20 px-3 py-2 text-xs text-white outline-none">
                                            @foreach($sceneTypes as $value => $label)
                                                <option value="{{ $value }}" class="text-slate-900">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <div x-show="sceneType === 'camera' || sceneType === 'screen'" class="grid gap-2">
                                            <input type="hidden" name="source_kind" x-bind:value="sceneType === 'screen' ? 'screen' : 'camera'">
                                            <select name="source_identity" x-model="sourceIdentity" x-on:change="sourceName = $event.target.selectedOptions[0]?.dataset.name || ''" class="rounded-lg border border-[#1b2d42] bg-black/20 px-3 py-2 text-xs text-white outline-none">
                                                <option value="" class="text-slate-900">Auto select participant</option>
                                                @foreach($sourceCandidates as $candidate)
                                                    <option value="{{ $candidate['identity'] }}" data-name="{{ $candidate['name'] }}" class="text-slate-900">{{ $candidate['name'] }}</option>
                                                @endforeach
                                                <template x-for="participant in liveParticipants" :key="participant.identity">
                                                    <option :value="participant.identity" :data-name="participant.name" x-text="`${participant.name} (live)`"></option>
                                                </template>
                                            </select>
                                            <input name="source_name" x-model="sourceName" placeholder="Source display name" class="rounded-lg border border-[#1b2d42] bg-black/20 px-3 py-2 text-xs text-white outline-none placeholder:text-white/35">
                                        </div>
                                        <div x-show="sceneType === 'scripture'" class="grid gap-2">
                                            <input name="scripture_reference" placeholder="Reference, e.g. Matthew 18:20" class="rounded-lg border border-[#1b2d42] bg-black/20 px-3 py-2 text-xs text-white outline-none placeholder:text-white/35">
                                            <textarea name="scripture_text" rows="3" placeholder="Verse text to show on screen" class="rounded-lg border border-[#1b2d42] bg-black/20 px-3 py-2 text-xs text-white outline-none placeholder:text-white/35"></textarea>
                                        </div>
                                        <div x-show="sceneType === 'countdown'" class="grid gap-2">
                                            <input name="countdown_minutes" type="number" min="0" max="240" placeholder="Countdown minutes" class="rounded-lg border border-[#1b2d42] bg-black/20 px-3 py-2 text-xs text-white outline-none placeholder:text-white/35">
                                        </div>
                                        <div x-show="sceneType === 'media' || sceneType === 'presentation'" class="grid gap-2">
                                            <input name="media_url" placeholder="Media, slide, or presentation URL" class="rounded-lg border border-[#1b2d42] bg-black/20 px-3 py-2 text-xs text-white outline-none placeholder:text-white/35">
                                        </div>
                                        <div x-show="sceneType === 'agenda'" class="grid gap-2">
                                            <textarea name="agenda_notes" rows="3" placeholder="Agenda or run-of-service notes" class="rounded-lg border border-[#1b2d42] bg-black/20 px-3 py-2 text-xs text-white outline-none placeholder:text-white/35"></textarea>
                                        </div>
                                        <input name="description" placeholder="Purpose or source note" class="rounded-lg border border-[#1b2d42] bg-black/20 px-3 py-2 text-xs text-white outline-none placeholder:text-white/35">
                                        <button class="rounded-lg bg-violet-600 px-3 py-2 text-xs font-bold text-white hover:bg-violet-500">Create Scene</button>
                                    </form>
                                </div>
                                <div x-cloak x-show="sceneTab === 'sources'" class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_320px]">
                                    <div class="rounded-lg border border-violet-400/25 bg-violet-500/10 p-3 lg:col-span-2">
                                        <label class="text-xs font-semibold uppercase tracking-wide text-violet-100">Target Scene For Live Participant Assignment</label>
                                        <select x-model="selectedSceneId" class="mt-2 w-full rounded-lg border border-violet-400/30 bg-[#071421] px-3 py-2 text-sm text-white outline-none">
                                            <template x-for="scene in studioScenes" :key="scene.id">
                                                <option :value="String(scene.id)" x-text="`${scene.title} (${scene.source_kind || 'camera'})`"></option>
                                            </template>
                                        </select>
                                        <p class="mt-2 text-xs text-white/50">Choose any scene, then assign a participant camera or screen below. Different scenes can use different people and different source types.</p>
                                    </div>
                                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                        <template x-for="participant in liveParticipants" :key="participant.identity">
                                            <article class="rounded-lg border border-emerald-400/30 bg-emerald-500/10 p-3">
                                                <div class="flex items-center gap-3">
                                                    <img x-show="participant.avatar" :src="participant.avatar" :alt="participant.name" class="size-10 rounded-full object-cover ring-1 ring-emerald-300/30">
                                                    <div x-show="!participant.avatar" class="grid size-10 place-items-center rounded-full bg-emerald-500/20 text-xs font-bold" x-text="participant.name.slice(0, 1).toUpperCase()"></div>
                                                    <div class="min-w-0">
                                                        <div class="truncate text-sm font-semibold" x-text="participant.name"></div>
                                                        <div class="truncate text-xs text-white/45" x-text="participant.identity"></div>
                                                    </div>
                                                </div>
                                                <div class="mt-3 grid grid-cols-2 gap-2">
                                                    <button type="button" x-on:click="assignStudioSource(participant, 'camera', selectedSceneId)" class="w-full rounded bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-200 hover:bg-emerald-500/20" :disabled="!selectedSceneSourceUrl()">
                                                        <i data-lucide="video" class="mr-1 inline size-3.5"></i>Scene Cam
                                                    </button>
                                                    <button type="button" x-on:click="assignStudioSource(participant, 'screen', selectedSceneId)" class="w-full rounded bg-sky-500/10 px-3 py-2 text-xs font-semibold text-sky-200 hover:bg-sky-500/20" :disabled="!selectedSceneSourceUrl()">
                                                        <i data-lucide="screen-share" class="mr-1 inline size-3.5"></i>Scene Screen
                                                    </button>
                                                    <button type="button" x-on:click="assignStudioSource(participant, 'camera', 'main')" class="w-full rounded bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-100 hover:bg-rose-500/20" :disabled="!mainSourceAssignUrl">
                                                        <i data-lucide="monitor-up" class="mr-1 inline size-3.5"></i>Main Cam
                                                    </button>
                                                    <button type="button" x-on:click="assignStudioSource(participant, 'screen', 'main')" class="w-full rounded bg-violet-500/10 px-3 py-2 text-xs font-semibold text-violet-100 hover:bg-violet-500/20" :disabled="!mainSourceAssignUrl">
                                                        <i data-lucide="screen-share" class="mr-1 inline size-3.5"></i>Main Screen
                                                    </button>
                                                </div>
                                            </article>
                                        </template>
                                        @forelse($sourceCandidates as $candidate)
                                            <article x-show="liveParticipants.length === 0" class="rounded-lg border border-[#1b2d42] bg-[#071421] p-3">
                                                <div class="flex items-center gap-3">
                                                    @if($candidate['avatar'] ?? null)
                                                        <img src="{{ $candidate['avatar'] }}" alt="{{ $candidate['name'] }}" class="size-10 rounded-full object-cover ring-1 ring-white/15">
                                                    @else
                                                        <div class="grid size-10 place-items-center rounded-full bg-slate-700 text-xs font-bold">{{ Str::upper(Str::substr($candidate['name'], 0, 1)) }}</div>
                                                    @endif
                                                    <div class="min-w-0">
                                                        <div class="truncate text-sm font-semibold">{{ $candidate['name'] }}</div>
                                                        <div class="truncate text-xs text-white/45">{{ $candidate['identity'] }}</div>
                                                    </div>
                                                </div>
                                                @if($sourceTargetScene)
                                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                                        <form method="POST" action="{{ route('meetings.rooms.studio.scenes.source', [$session, $provider, $sourceTargetScene]) }}">
                                                            @csrf @method('PUT')
                                                            <input type="hidden" name="manual_source_identity" value="{{ $candidate['identity'] }}">
                                                            <input type="hidden" name="source_name" value="{{ $candidate['name'] }}">
                                                            <input type="hidden" name="source_kind" value="camera">
                                                            <button class="w-full rounded bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-200 hover:bg-emerald-500/20"><i data-lucide="video" class="mr-1 inline size-3.5"></i>Preview Cam</button>
                                                        </form>
                                                        <form method="POST" action="{{ route('meetings.rooms.studio.scenes.source', [$session, $provider, $sourceTargetScene]) }}">
                                                            @csrf @method('PUT')
                                                            <input type="hidden" name="manual_source_identity" value="{{ $candidate['identity'] }}">
                                                            <input type="hidden" name="source_name" value="{{ $candidate['name'] }} screen">
                                                            <input type="hidden" name="source_kind" value="screen">
                                                            <button class="w-full rounded bg-sky-500/10 px-3 py-2 text-xs font-semibold text-sky-200 hover:bg-sky-500/20"><i data-lucide="screen-share" class="mr-1 inline size-3.5"></i>Preview Screen</button>
                                                        </form>
                                                        @if($state->liveScene)
                                                            <form method="POST" action="{{ route('meetings.rooms.studio.scenes.source', [$session, $provider, $state->liveScene]) }}">
                                                                @csrf @method('PUT')
                                                                <input type="hidden" name="manual_source_identity" value="{{ $candidate['identity'] }}">
                                                                <input type="hidden" name="source_name" value="{{ $candidate['name'] }}">
                                                                <input type="hidden" name="source_kind" value="camera">
                                                                <button class="w-full rounded bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-100 hover:bg-rose-500/20"><i data-lucide="monitor-up" class="mr-1 inline size-3.5"></i>Main Cam</button>
                                                            </form>
                                                            <form method="POST" action="{{ route('meetings.rooms.studio.scenes.source', [$session, $provider, $state->liveScene]) }}">
                                                                @csrf @method('PUT')
                                                                <input type="hidden" name="manual_source_identity" value="{{ $candidate['identity'] }}">
                                                                <input type="hidden" name="source_name" value="{{ $candidate['name'] }} screen">
                                                                <input type="hidden" name="source_kind" value="screen">
                                                                <button class="w-full rounded bg-violet-500/10 px-3 py-2 text-xs font-semibold text-violet-100 hover:bg-violet-500/20"><i data-lucide="screen-share" class="mr-1 inline size-3.5"></i>Main Screen</button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                @endif
                                            </article>
                                        @empty
                                            <div x-show="liveParticipants.length === 0" class="rounded-lg border border-dashed border-[#26384c] p-5 text-sm text-white/55 sm:col-span-2 xl:col-span-3">No active LiveKit participants yet. Participants appear here after they join the room.</div>
                                        @endforelse
                                    </div>
                                    <div class="rounded-lg border border-[#1b2d42] bg-[#071421] p-4 text-sm">
                                        <div class="font-semibold">Assigning to</div>
                                        <div class="mt-1 text-white/60" x-text="selectedSceneTitle()">{{ $sourceTargetScene?->title ?? 'No scene selected' }}</div>
                                        <div class="mt-3 rounded bg-black/25 px-3 py-2 text-xs text-white/55" x-text="liveParticipantStatus"></div>
                                        <div class="mt-4 text-xs text-white/45">Use Scene Cam for a participant camera feed or Scene Screen for their shared screen. Each scene stores its own input source and renders it when previewed or taken live.</div>
                                    </div>
                                </div>
                                <div x-cloak x-show="sceneTab === 'overlays'" class="grid gap-3 lg:grid-cols-3">
                                    @foreach([
                                        ['chat_visible', $state->chat_visible ? '0' : '1', $state->chat_visible ? 'Hide Chat' : 'Show Chat', 'messages-square'],
                                        ['qna_enabled', $state->qna_enabled ? '0' : '1', $state->qna_enabled ? 'Disable Q&A' : 'Enable Q&A', 'message-circle-question'],
                                        ['poll_visible', $state->poll_visible ? '0' : '1', $state->poll_visible ? 'Hide Polls' : 'Show Polls', 'bar-chart-3'],
                                    ] as [$name, $value, $label, $icon])
                                        <form method="POST" action="{{ route('meetings.rooms.studio.state.update', [$session, $provider]) }}" class="rounded-lg border border-[#1b2d42] bg-[#071421] p-4">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                            <button class="flex w-full items-center gap-3 text-left"><i data-lucide="{{ $icon }}" class="size-5 text-violet-200"></i><span class="text-sm font-semibold">{{ $label }}</span></button>
                                        </form>
                                    @endforeach
                                    <form method="POST" action="{{ route('meetings.rooms.studio.state.update', [$session, $provider]) }}" class="rounded-lg border border-[#1b2d42] bg-[#071421] p-4 lg:col-span-3">
                                        @csrf @method('PUT')
                                        <label class="text-xs font-semibold uppercase tracking-wide text-white/45">Ticker / Scroller</label>
                                        <div class="mt-2 grid gap-2 sm:grid-cols-[1fr_140px]">
                                            <input name="ticker_text" value="{{ $state->ticker_text }}" placeholder="Announcement text" class="rounded bg-white/10 px-3 py-2 text-sm text-white outline-none placeholder:text-white/35">
                                            <button class="rounded bg-violet-600 px-3 py-2 text-sm font-semibold hover:bg-violet-500">Update Ticker</button>
                                        </div>
                                    </form>
                                </div>
                                <div x-cloak x-show="sceneTab === 'media'" class="grid gap-3 lg:grid-cols-2">
                                    <form method="POST" action="{{ route('meetings.rooms.studio.scenes.store', [$session, $provider]) }}" class="rounded-lg border border-[#1b2d42] bg-[#071421] p-4">
                                        @csrf
                                        <input type="hidden" name="scene_type" value="media">
                                        <label class="text-xs font-semibold uppercase tracking-wide text-white/45">Create Media Scene</label>
                                        <input name="title" required placeholder="Media title" class="mt-2 w-full rounded bg-white/10 px-3 py-2 text-sm text-white outline-none placeholder:text-white/35">
                                        <input name="description" placeholder="Media notes or URL" class="mt-2 w-full rounded bg-white/10 px-3 py-2 text-sm text-white outline-none placeholder:text-white/35">
                                        <button class="mt-3 rounded bg-violet-600 px-3 py-2 text-sm font-semibold hover:bg-violet-500">Add Media Scene</button>
                                    </form>
                                    <form method="POST" action="{{ route('meetings.rooms.studio.scenes.store', [$session, $provider]) }}" class="rounded-lg border border-[#1b2d42] bg-[#071421] p-4">
                                        @csrf
                                        <input type="hidden" name="scene_type" value="presentation">
                                        <label class="text-xs font-semibold uppercase tracking-wide text-white/45">Create Presentation Scene</label>
                                        <input name="title" required placeholder="Presentation title" class="mt-2 w-full rounded bg-white/10 px-3 py-2 text-sm text-white outline-none placeholder:text-white/35">
                                        <input name="description" placeholder="Slide deck or run-of-show note" class="mt-2 w-full rounded bg-white/10 px-3 py-2 text-sm text-white outline-none placeholder:text-white/35">
                                        <button class="mt-3 rounded bg-violet-600 px-3 py-2 text-sm font-semibold hover:bg-violet-500">Add Presentation</button>
                                    </form>
                                </div>
                                <div x-cloak x-show="sceneTab === 'lowerThirds'" class="grid gap-4 rounded-lg border border-[#1b2d42] bg-[#071421] p-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                                    <div class="space-y-4">
                                        <div>
                                            <h3 class="text-sm font-semibold">Bottom Title</h3>
                                            <p class="mt-1 text-xs text-white/45">Set the name, role, and background that viewers see on the live room lower title.</p>
                                        </div>
                                        <form method="POST" enctype="multipart/form-data" action="{{ route('meetings.rooms.studio.state.update', [$session, $provider]) }}" class="grid gap-3 lg:grid-cols-3">
                                            @csrf @method('PUT')
                                            <input name="speaker_name" value="{{ $lowerThird['speaker_name'] ?? '' }}" placeholder="Speaker name" class="rounded bg-white/10 px-3 py-2 text-sm text-white outline-none placeholder:text-white/35">
                                            <input name="speaker_role" value="{{ $lowerThird['speaker_role'] ?? '' }}" placeholder="Speaker role" class="rounded bg-white/10 px-3 py-2 text-sm text-white outline-none placeholder:text-white/35">
                                            <input name="service_label" value="{{ $lowerThird['service_label'] ?? $session->title }}" placeholder="Small top label" class="rounded bg-white/10 px-3 py-2 text-sm text-white outline-none placeholder:text-white/35">
                                            <label class="flex cursor-pointer items-center gap-2 rounded bg-white/10 px-3 py-2 text-sm text-white/70 outline-none hover:bg-white/15 lg:col-span-2">
                                                <i data-lucide="upload" class="size-4 text-violet-200"></i>
                                                <span class="min-w-0 flex-1 truncate">{{ filled($lowerThirdBackground) ? 'Replace Uploaded Background' : 'Upload Background Image' }}</span>
                                                <input type="file" name="lower_third_background" accept="image/png,image/jpeg,image/webp" class="hidden">
                                            </label>
                                            <button class="rounded bg-violet-600 px-3 py-2 text-sm font-semibold hover:bg-violet-500">Save Bottom Title</button>
                                        </form>

                                        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                                            @foreach($lowerThirdBackgroundPresets as $presetKey => $preset)
                                                <form method="POST" action="{{ route('meetings.rooms.studio.state.update', [$session, $provider]) }}">
                                                    @csrf @method('PUT')
                                                    <input type="hidden" name="lower_third_background_preset" value="{{ $presetKey }}">
                                                    <button class="group w-full overflow-hidden rounded-lg border border-[#26384c] bg-white/[0.04] p-2 text-left hover:border-violet-400/60">
                                                        <span class="block h-12 rounded" style="{{ $preset['style'] }}"></span>
                                                        <span class="mt-2 block text-xs font-semibold text-white">{{ $preset['label'] }}</span>
                                                        <span class="text-[10px] text-white/40">Create background</span>
                                                    </button>
                                                </form>
                                            @endforeach
                                        </div>

                                        @if(filled($lowerThirdBackground) || filled($lowerThirdBackgroundStyle))
                                            <form method="POST" action="{{ route('meetings.rooms.studio.state.update', [$session, $provider]) }}">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="remove_lower_third_background" value="1">
                                                <button class="inline-flex items-center gap-2 rounded-lg border border-rose-400/25 bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-100 hover:bg-rose-500/20">
                                                    <i data-lucide="trash-2" class="size-4"></i>Remove Background
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                    <div class="rounded-lg border border-[#1b2d42] bg-black/25 p-3">
                                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-white/45">Live Preview</div>
                                        <div class="relative overflow-hidden rounded-lg border border-amber-300/20 bg-gradient-to-r from-black/90 via-[#071321]/92 to-black/70 p-4" style="{{ $lowerThirdStyle }}">
                                            <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-amber-300/90 to-transparent"></div>
                                            <div class="relative flex items-center gap-3">
                                                <span class="grid size-12 shrink-0 place-items-center rounded-lg bg-amber-500/15 text-amber-200 ring-1 ring-amber-200/20"><i data-lucide="church" class="size-6"></i></span>
                                                <div class="min-w-0">
                                                    <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-amber-200/85">{{ $lowerThird['service_label'] ?? $session->title }}</div>
                                                    <div class="truncate text-xl font-black leading-tight text-white">{{ $lowerThird['speaker_name'] ?? $session->title }}</div>
                                                    <div class="mt-1 truncate text-sm font-medium text-white/68">{{ $lowerThird['speaker_role'] ?? $session->event->title }}</div>
                                                </div>
                                            </div>
                                        </div>
                                        <p class="mt-3 text-xs text-white/45">Uploaded images override presets. Presets are stored with the Studio state and appear in the participant room instantly.</p>
                                    </div>
                                </div>
                            </section>

                            <div class="space-y-2">
                                <section id="ai-tools" class="{{ $panel }} relative p-3 scroll-mt-4">
                                    <span id="bible-scripture" class="absolute -top-3"></span>
                                    <span id="lower-thirds" class="absolute -top-3"></span>
                                    <span id="countdown" class="absolute -top-3"></span>
                                    <span id="ticker-scroller" class="absolute -top-3"></span>
                                    <div class="mb-3 flex items-center justify-between gap-2">
                                        <h2 class="text-sm font-semibold">AI Bible Assistant</h2>
                                        <span class="rounded bg-violet-500/20 px-2 py-1 text-[10px] font-semibold uppercase text-violet-200">Beta</span>
                                    </div>
                                    <div class="mb-3 grid grid-cols-3 overflow-hidden rounded-lg border border-[#1b2d42] bg-[#071421] p-1 text-center text-xs">
                                        <span class="rounded px-2 py-2 text-white/55">Bible Search</span>
                                        <span class="rounded bg-violet-600/20 px-2 py-2 text-violet-100">Auto Reference</span>
                                        <span class="rounded px-2 py-2 text-white/55">Complete Verse</span>
                                    </div>
                                    <p class="mb-3 text-xs text-white/45">AI is analyzing the message and suggests this Bible reference:</p>
                                    <div class="rounded-lg border border-violet-400/40 bg-violet-500/10 p-4">
                                        <div class="mb-2 flex items-center gap-2 text-sm font-semibold text-violet-200"><i data-lucide="scan-search" class="size-4"></i>{{ $scripture['reference'] ?? 'Matthew 18:20 (KJV)' }}</div>
                                        <p class="text-sm leading-5 text-white/75">{{ $scripture['text'] ?? 'For where two or three are gathered together in my name, there am I in the midst of them.' }}</p>
                                    </div>
                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                        <form method="POST" action="{{ route('meetings.rooms.studio.state.update', [$session, $provider]) }}">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="scripture_reference" value="{{ $scripture['reference'] ?? 'Matthew 18:20 (KJV)' }}">
                                            <input type="hidden" name="scripture_text" value="{{ $scripture['text'] ?? 'For where two or three are gathered together in my name, there am I in the midst of them.' }}">
                                            <button class="w-full rounded-lg bg-violet-600 px-3 py-2 text-xs font-bold">Add to Screen</button>
                                        </form>
                                        <a href="{{ $scriptureSearchUrl }}" target="_blank" rel="noopener" class="rounded-lg border border-[#26384c] px-3 py-2 text-center text-xs text-white/70 hover:bg-white/[0.06]">Find More References</a>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                        @foreach(['Matthew 18:19', 'John 14:23', '1 Corinthians 5:4'] as $reference)
                                            <a href="https://www.biblegateway.com/quicksearch/?quicksearch={{ urlencode($reference) }}" target="_blank" rel="noopener" class="rounded-lg border border-[#1b2d42] px-3 py-2 text-white/55 hover:bg-white/[0.06]">{{ $reference }}</a>
                                        @endforeach
                                        <a href="{{ $scriptureSearchUrl }}" target="_blank" rel="noopener" class="ml-auto px-2 py-2 text-violet-300">View All</a>
                                    </div>
                                </section>
                            </div>
                            </div>

                            <div class="grid gap-2 xl:grid-cols-[minmax(0,1fr)_300px_350px]">
                                <section id="caption-controls" class="{{ $panel }} p-3">
                                    <div class="mb-3 flex items-center gap-2">
                                        <h2 class="text-sm font-semibold">AI Subtitle & Captions</h2>
                                        <span class="rounded bg-violet-500/20 px-2 py-1 text-[10px] font-semibold uppercase text-violet-200">Beta</span>
                                    </div>
                                    <form method="POST" action="{{ route('meetings.rooms.studio.state.update', [$session, $provider]) }}" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_230px]">
                                        @csrf @method('PUT')
                                        <div class="space-y-3">
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <label class="text-xs text-white/55">Language
                                                    <select name="quick_actions[caption_language]" class="mt-1 w-full rounded-lg border border-[#1b2d42] bg-[#071421] px-3 py-2 text-sm text-white">
                                                        @foreach(['English', 'French', 'Spanish', 'Portuguese'] as $language)
                                                            <option value="{{ $language }}" @selected(($quickActions['caption_language'] ?? 'English') === $language) class="text-slate-900">{{ $language }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                                <label class="text-xs text-white/55">Display Style
                                                    <div class="mt-1 grid grid-cols-4 gap-2">
                                                        @foreach(['compact', 'standard', 'highlight', 'large'] as $style)
                                                            <label class="cursor-pointer rounded-lg border border-[#26384c] bg-white/[0.04] px-2 py-2 text-center text-xs has-[:checked]:text-violet-200 has-[:checked]:ring-1 has-[:checked]:ring-violet-400/50">
                                                                <input type="radio" name="quick_actions[caption_style]" value="{{ $style }}" @checked(($quickActions['caption_style'] ?? 'highlight') === $style) class="sr-only">
                                                                Aa
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </label>
                                            </div>
                                            <label class="block text-xs text-white/55">Display Size
                                                <input name="quick_actions[caption_size]" type="range" min="70" max="140" value="{{ $quickActions['caption_size'] ?? 100 }}" class="mt-3 w-full accent-violet-500">
                                            </label>
                                            <div class="grid gap-2 sm:grid-cols-2">
                                                <label class="flex items-center justify-between rounded-lg border border-[#1b2d42] bg-[#071421] px-3 py-2 text-xs">
                                                    <span>Enable Auto Subtitle</span>
                                                    <input type="hidden" name="quick_actions[auto_subtitle]" value="0">
                                                    <input type="checkbox" name="quick_actions[auto_subtitle]" value="1" @checked(($quickActions['auto_subtitle'] ?? true)) class="accent-violet-500">
                                                </label>
                                                <label class="flex items-center justify-between rounded-lg border border-[#1b2d42] bg-[#071421] px-3 py-2 text-xs">
                                                    <span>Include Bible References</span>
                                                    <input type="hidden" name="quick_actions[include_bible_references]" value="0">
                                                    <input type="checkbox" name="quick_actions[include_bible_references]" value="1" @checked(($quickActions['include_bible_references'] ?? true)) class="accent-violet-500">
                                                </label>
                                            </div>
                                        </div>
                                        <div class="flex flex-col justify-between gap-3">
                                            <div class="rounded-lg border border-[#1b2d42] bg-black/25 p-3 text-xs leading-5 text-white/75">
                                                {{ Str::limit($scripture['text'] ?? 'For where two or three are gathered in my name, there am I among them.', 96) }}
                                                <div class="mt-2 text-violet-300">{{ $scripture['reference'] ?? 'Matthew 18:20 (KJV)' }}</div>
                                            </div>
                                            <button class="rounded-lg bg-violet-600 px-3 py-2 text-xs font-bold hover:bg-violet-500">Save Captions</button>
                                        </div>
                                    </form>
                                </section>

                                <section id="audio-mixer" class="{{ $panel }} p-3 scroll-mt-4">
                                    <div class="mb-3 flex items-center justify-between">
                                        <h2 class="text-sm font-semibold">Audio Mixer</h2>
                                        <button form="studio-audio-mixer-form" class="rounded-lg border border-violet-400/40 px-3 py-1.5 text-xs font-semibold text-violet-200 hover:bg-violet-500/10">Save Mixer</button>
                                    </div>
                                    <form id="studio-audio-mixer-form" method="POST" action="{{ route('meetings.rooms.studio.state.update', [$session, $provider]) }}" class="space-y-2">
                                        @csrf @method('PUT')
                                        @foreach($audioMixer as $label => $level)
                                            <label class="grid grid-cols-[96px_1fr_42px] items-center gap-2 text-[11px]">
                                                <span>{{ Str::headline($label) }}</span>
                                                <input type="range" name="audio_mixer[{{ $label }}]" min="-60" max="12" value="{{ (int) $level }}" class="accent-emerald-400">
                                                <span class="text-white/55">{{ $level }} dB</span>
                                            </label>
                                        @endforeach
                                    </form>
                                </section>

                                <section class="{{ $panel }} p-3">
                                    <h2 class="mb-3 text-sm font-semibold">Quick Actions</h2>
                                    <div class="grid grid-cols-4 gap-2 text-[11px]">
                                        @foreach([
                                            ['chat_visible', $state->chat_visible ? '0' : '1', $state->chat_visible ? 'Hide Chat' : 'Show Chat', 'message-square-off'],
                                            ['qna_enabled', $state->qna_enabled ? '0' : '1', $state->qna_enabled ? 'Close Q&A' : 'Open Q&A', 'circle-help'],
                                            ['poll_visible', $state->poll_visible ? '0' : '1', $state->poll_visible ? 'Hide Poll' : 'Show Poll', 'bar-chart-3'],
                                            ['countdown_minutes', '5', '5 Min Timer', 'timer'],
                                        ] as [$name, $value, $label, $icon])
                                            <form method="POST" action="{{ route('meetings.rooms.studio.state.update', [$session, $provider]) }}">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                                <button class="h-full w-full rounded-lg border border-[#1b2d42] bg-white/[0.04] p-3 text-center hover:bg-white/[0.08] {{ $label === 'End Stream' ? 'text-rose-100' : '' }}"><i data-lucide="{{ $icon }}" class="mx-auto mb-2 size-5 text-violet-200"></i>{{ $label }}</button>
                                            </form>
                                        @endforeach
                                    </div>
                                    <div class="mt-4 grid grid-cols-2 gap-3">
                                        <form method="POST" action="{{ route('meetings.rooms.studio.state.update', [$session, $provider]) }}">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="stream_status" value="live">
                                            <button class="flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-3 text-sm font-bold hover:bg-emerald-500"><i data-lucide="radio-tower" class="size-4"></i>Broadcast</button>
                                        </form>
                                        <form method="POST" action="{{ route('meetings.rooms.studio.state.update', [$session, $provider]) }}">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="stream_status" value="ended">
                                            <button class="flex w-full items-center justify-center gap-2 rounded-lg bg-rose-600 px-3 py-3 text-sm font-bold hover:bg-rose-500"><i data-lucide="log-out" class="size-4"></i>End Stream</button>
                                        </form>
                                    </div>
                                </section>
                            </div>

                            <div class="grid gap-2 xl:grid-cols-[minmax(0,1fr)_320px_330px]">
                                <section id="backroom" class="{{ $panel }} p-3">
                                    <h2 class="mb-3 text-sm font-semibold">Backroom <span class="font-normal text-white/45">(Admin Control Center)</span></h2>
                                    <div class="flex min-h-28 flex-wrap items-center justify-center gap-5">
                                        @forelse($teamAssignments->take(6) as $assignment)
                                            @php
                                                $assignmentName = $assignment->member ? trim($assignment->member->first_name.' '.$assignment->member->last_name) : ($assignment->user?->name ?? 'Assigned');
                                                $assignmentAvatar = $assignment->user?->avatar_src;
                                            @endphp
                                            <div class="text-center text-xs">
                                                @if($assignmentAvatar)
                                                    <img src="{{ $assignmentAvatar }}" alt="{{ $assignmentName }}" class="mx-auto size-12 rounded-full object-cover ring-1 ring-white/15">
                                                @else
                                                    <div class="mx-auto grid size-12 place-items-center rounded-full bg-gradient-to-br from-slate-600 to-slate-900 font-bold ring-1 ring-white/10">{{ Str::upper(Str::substr($assignmentName, 0, 1)) }}</div>
                                                @endif
                                                <div class="mt-2 max-w-20 truncate">{{ $assignmentName }}</div>
                                                <div class="text-white/45">{{ $assignment->role_title }}</div>
                                            </div>
                                        @empty
                                            <div class="text-center">
                                                <div class="mx-auto mb-2 grid size-16 place-items-center rounded-lg border border-[#26384c] text-white/35"><i data-lucide="users-round" class="size-8"></i></div>
                                                <p class="text-xs text-white/50">No backroom assignments yet.</p>
                                            </div>
                                        @endforelse
                                    </div>
                                    <a href="#participants" class="mt-2 flex w-full items-center justify-center rounded-lg bg-violet-600 px-3 py-2 text-xs font-bold hover:bg-violet-500">Manage Backroom</a>
                                </section>

                                <section class="{{ $panel }} p-3">
                                    <div class="mb-3 flex items-center justify-between">
                                        <h2 class="text-sm font-semibold">Automations</h2>
                                        <i data-lucide="settings-2" class="size-4 text-white/35"></i>
                                    </div>
                                    <form method="POST" action="{{ route('meetings.rooms.studio.state.update', [$session, $provider]) }}" class="space-y-2">
                                        @csrf @method('PUT')
                                        @foreach([
                                            'auto_lower_third' => 'Auto Lower Third',
                                            'auto_bible_reference' => 'Auto Bible Reference',
                                            'auto_share_welcome_slide' => 'Auto Share Welcome Slide',
                                            'auto_end_stream_reminder' => 'Auto End Stream Reminder',
                                        ] as $name => $label)
                                            <label class="flex items-center justify-between rounded-lg border border-[#1b2d42] bg-black/15 px-3 py-2 text-xs text-white/70">
                                                <span>{{ $label }}</span>
                                                <span class="inline-flex items-center gap-2">
                                                    <input type="hidden" name="quick_actions[{{ $name }}]" value="0">
                                                    <input type="checkbox" name="quick_actions[{{ $name }}]" value="1" @checked(($quickActions[$name] ?? true)) class="accent-emerald-400">
                                                    <i data-lucide="save" class="size-3.5 text-white/35"></i>
                                                </span>
                                            </label>
                                        @endforeach
                                        <button class="w-full rounded-lg bg-white/[0.04] px-3 py-2 text-xs font-semibold text-white/65 hover:bg-white/[0.08]">Manage Automations</button>
                                    </form>
                                </section>

                                <section class="{{ $panel }} p-3">
                                    <div class="mb-3 flex items-center justify-between">
                                        <h2 class="text-sm font-semibold">Shortcuts</h2>
                                        <i data-lucide="settings-2" class="size-4 text-white/35"></i>
                                    </div>
                                    <div class="divide-y divide-[#1b2d42] rounded-lg border border-[#1b2d42] bg-black/15 text-xs">
                                        @foreach([
                                            ['Start Countdown', 'Ctrl + 1'],
                                            ['Next Scene', 'Ctrl + 2'],
                                            ['Show Bible Slide', 'Ctrl + 3'],
                                            ['Mute All', 'Ctrl + M'],
                                            ['End Stream', 'Ctrl + E'],
                                        ] as [$label, $keys])
                                            <div class="flex items-center justify-between px-3 py-2">
                                                <span class="text-white/62">{{ $label }}</span>
                                                <span class="text-white/35">{{ $keys }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                    <a href="#settings" class="mt-2 block text-center text-xs text-white/45 hover:text-white">Open Studio Settings</a>
                                </section>
                            </div>
                        </section>

                        <aside class="space-y-2 2xl:sticky 2xl:top-2 2xl:self-start">
                            <section id="participants" class="{{ $panel }} p-3 scroll-mt-4">
                                <div class="mb-3 flex items-center gap-5 border-b border-[#1b2d42] text-sm">
                                    <span class="-mb-px border-b-2 border-violet-500 pb-3 text-violet-100">Participants <span class="text-white/45" x-text="liveParticipants.length ? `${liveParticipants.length} live` : '{{ $participants->count() }} active'">{{ $participants->count() }} active</span></span>
                                    <a href="#chat-q-a" class="pb-3 text-white/55 hover:text-white">Chat</a>
                                    <a href="#chat-q-a" class="pb-3 text-white/55 hover:text-white">Q&A</a>
                                    <a href="#polls" class="pb-3 text-white/55 hover:text-white">Polls</a>
                                </div>
                                <div class="mb-3 flex items-center justify-between text-xs text-white/55">
                                    <span x-text="liveParticipantStatus">LiveKit online records</span>
                                    <span class="inline-flex items-center gap-1"><i data-lucide="users-round" class="size-4"></i><span x-text="liveParticipants.length || {{ $participants->count() }}">{{ $participants->count() }}</span></span>
                                </div>
                                <div class="max-h-[238px] divide-y divide-[#1b2d42] overflow-y-auto">
                                    <template x-for="participant in liveParticipants" :key="participant.identity">
                                        <div class="flex items-center gap-3 py-3">
                                            <img x-show="participant.avatar" :src="participant.avatar" :alt="participant.name" class="size-10 rounded-full object-cover ring-1 ring-emerald-300/30">
                                            <div x-show="!participant.avatar" class="grid size-10 place-items-center rounded-full bg-violet-600 text-sm font-bold" x-text="participant.name.slice(0, 1).toUpperCase()"></div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2 truncate text-sm"><span class="truncate" x-text="participant.name"></span><span class="size-1.5 shrink-0 rounded-full bg-emerald-300"></span></div>
                                                <div class="truncate text-xs text-white/45" x-text="participant.identity"></div>
                                                <div class="truncate text-[10px] text-white/35">
                                                    <span x-show="participant.hasCamera">Camera</span>
                                                    <span x-show="participant.hasCamera && participant.hasScreen"> | </span>
                                                    <span x-show="participant.hasScreen">Screen</span>
                                                    <span x-show="!participant.hasCamera && !participant.hasScreen">Connected, no video source</span>
                                                </div>
                                            </div>
                                            <button type="button" x-on:click="assignStudioSource(participant, 'camera', 'main')" class="grid size-8 place-items-center rounded bg-rose-500/10 text-rose-200 hover:bg-rose-500/20" title="Put camera on Main Screen" :disabled="!mainSourceAssignUrl"><i data-lucide="monitor-up" class="size-4"></i></button>
                                            <button type="button" x-on:click="assignStudioSource(participant, 'screen', 'main')" class="grid size-8 place-items-center rounded bg-violet-500/10 text-violet-200 hover:bg-violet-500/20" title="Put screen share on Main Screen" :disabled="!mainSourceAssignUrl"><i data-lucide="screen-share" class="size-4"></i></button>
                                        </div>
                                    </template>
                                    @forelse($participants as $participant)
                                        @php
                                            $participantMetadata = $participant->metadata ?? [];
                                            $participantName = $participantMetadata['participant_name'] ?? ($participant->member ? trim($participant->member->first_name.' '.$participant->member->last_name) : 'Guest');
                                            $participantIdentity = $participantMetadata['livekit_identity'] ?? $participant->member?->email ?? $participant->member?->phone;
                                            $participantAvatar = $participantMetadata['avatar'] ?? null;
                                            $participantRoom = $participantMetadata['room'] ?? null;
                                            $lastSeen = filled($participantMetadata['last_seen_at'] ?? null) ? \Illuminate\Support\Carbon::parse($participantMetadata['last_seen_at'])->diffForHumans() : null;
                                        @endphp
                                        <div x-show="liveParticipants.length === 0" class="flex items-center gap-3 py-3">
                                            @if($participantAvatar)
                                                <img src="{{ $participantAvatar }}" alt="{{ $participantName }}" class="size-9 rounded-full object-cover ring-1 ring-white/15">
                                            @else
                                                <div class="grid size-9 place-items-center rounded-full bg-slate-700 text-xs font-bold">{{ Str::upper(Str::substr($participantName, 0, 1)) }}</div>
                                            @endif
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2 truncate text-sm"><span class="truncate">{{ $participantName }}</span><span class="size-1.5 shrink-0 rounded-full bg-emerald-300"></span></div>
                                                <div class="truncate text-xs text-white/45">{{ $participantIdentity ?: Str::headline($participant->final_method ?? $provider) }}</div>
                                                <div class="truncate text-[10px] text-white/35">{{ $participantRoom ? 'Room: '.$participantRoom : 'Room source pending' }}{{ $lastSeen ? ' | Seen '.$lastSeen : '' }}</div>
                                            </div>
                                            @if($state->liveScene && filled($participantIdentity))
                                                <form method="POST" action="{{ route('meetings.rooms.studio.scenes.source', [$session, $provider, $state->liveScene]) }}">
                                                    @csrf @method('PUT')
                                                    <input type="hidden" name="manual_source_identity" value="{{ $participantIdentity }}">
                                                    <input type="hidden" name="source_name" value="{{ $participantName }}">
                                                    <input type="hidden" name="source_kind" value="camera">
                                                    <button class="grid size-8 place-items-center rounded bg-rose-500/10 text-rose-200 hover:bg-rose-500/20" title="Put camera on Main Screen"><i data-lucide="monitor-up" class="size-4"></i></button>
                                                </form>
                                                <form method="POST" action="{{ route('meetings.rooms.studio.scenes.source', [$session, $provider, $state->liveScene]) }}">
                                                    @csrf @method('PUT')
                                                    <input type="hidden" name="manual_source_identity" value="{{ $participantIdentity }}">
                                                    <input type="hidden" name="source_name" value="{{ $participantName }} screen">
                                                    <input type="hidden" name="source_kind" value="screen">
                                                    <button class="grid size-8 place-items-center rounded bg-violet-500/10 text-violet-200 hover:bg-violet-500/20" title="Put screen share on Main Screen"><i data-lucide="screen-share" class="size-4"></i></button>
                                                </form>
                                            @endif
                                        </div>
                                    @empty
                                        <p x-show="liveParticipants.length === 0" class="rounded-lg border border-dashed border-[#26384c] p-4 text-center text-sm text-white/50">No participants have checked in yet.</p>
                                    @endforelse
                                </div>
                                <a href="{{ $roomUrl }}" class="mt-3 flex w-full items-center justify-center gap-2 rounded-lg border border-violet-400/50 px-3 py-2 text-xs font-semibold text-violet-200 hover:bg-violet-500/10">
                                    <i data-lucide="user-plus" class="size-4"></i>Invite Participant
                                </a>
                            </section>

                            <section id="chat-q-a" class="{{ $panel }} p-3 scroll-mt-4">
                                <div class="mb-3 flex gap-7 border-b border-[#1b2d42] text-sm">
                                    <span class="-mb-px border-b-2 border-violet-500 pb-3">Chat</span>
                                    <span class="pb-3 text-white/45">Q&A</span>
                                    <span class="pb-3 text-white/45">Polls</span>
                                </div>
                                <div class="max-h-64 space-y-3 overflow-y-auto">
                                    @forelse($questions as $question)
                                        <article class="rounded-lg bg-black/20 p-3">
                                            <div class="flex items-start justify-between gap-2">
                                                <div>
                                                    <div class="text-xs font-semibold text-violet-200">{{ $question->author_name }} <span class="font-normal text-white/35">{{ $question->created_at?->format('h:i A') }}</span></div>
                                                    <p class="mt-1 text-sm leading-5">{{ $question->body }}</p>
                                                </div>
                                                @if($question->is_pinned)<i data-lucide="star" class="size-4 text-amber-300"></i>@endif
                                            </div>
                                            <div class="mt-3 flex gap-2">
                                                <form method="POST" action="{{ route('meetings.rooms.studio.qna.update', [$session, $provider, $question]) }}">
                                                    @csrf @method('PUT')
                                                    <input type="hidden" name="action" value="{{ $question->is_pinned ? 'unpin' : 'pin' }}">
                                                    <button class="rounded bg-white/10 px-2 py-1 text-xs">{{ $question->is_pinned ? 'Unpin' : 'Pin' }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('meetings.rooms.studio.qna.update', [$session, $provider, $question]) }}">
                                                    @csrf @method('PUT')
                                                    <input type="hidden" name="action" value="{{ $question->status === 'answered' ? 'open' : 'answered' }}">
                                                    <button class="rounded bg-white/10 px-2 py-1 text-xs">{{ $question->status === 'answered' ? 'Reopen' : 'Answered' }}</button>
                                                </form>
                                            </div>
                                        </article>
                                    @empty
                                        <p class="rounded-lg border border-dashed border-[#26384c] p-4 text-center text-sm text-white/50">No questions yet.</p>
                                    @endforelse
                                </div>
                            </section>

                            <section id="polls" class="{{ $panel }} p-3 scroll-mt-4">
                                <div class="mb-3 flex gap-7 border-b border-[#1b2d42] text-sm">
                                    <span class="pb-3 text-white/45">Chat</span>
                                    <span class="pb-3 text-white/45">Q&A</span>
                                    <span class="-mb-px border-b-2 border-violet-500 pb-3">Polls</span>
                                </div>
                                <div class="grid gap-3">
                                    <div>
                                        <h2 class="mb-3 text-sm font-semibold">Current Poll</h2>
                                        @if($poll)
                                            <h3 class="text-sm font-semibold">{{ $poll->question }}</h3>
                                            <div class="mt-3 space-y-2">
                                                @php
                                                    $totalVotes = max(1, $poll->options->sum('votes_count'));
                                                @endphp
                                                @foreach($poll->options as $option)
                                                    <div>
                                                        <div class="mb-1 flex justify-between text-xs"><span>{{ $option->label }}</span><span>{{ round(($option->votes_count / $totalVotes) * 100) }}%</span></div>
                                                        <div class="h-2 rounded bg-white/10"><div class="h-full rounded bg-violet-500" style="width: {{ round(($option->votes_count / $totalVotes) * 100) }}%"></div></div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="rounded-lg border border-dashed border-[#26384c] p-3 text-center text-sm text-white/50">No poll has been created.</p>
                                        @endif
                                    </div>
                                    <form method="POST" action="{{ route('meetings.rooms.studio.polls.store', [$session, $provider]) }}" class="space-y-2">
                                        @csrf
                                        <h2 class="mb-2 text-sm font-semibold">Create Poll</h2>
                                        <input name="question" required placeholder="Poll question" class="w-full rounded bg-white/10 px-3 py-1.5 text-sm text-white outline-none placeholder:text-white/35">
                                        @for($i = 0; $i < 4; $i++)
                                            <input name="options[]" placeholder="Option {{ $i + 1 }}" class="w-full rounded bg-white/10 px-3 py-1.5 text-sm text-white outline-none placeholder:text-white/35">
                                        @endfor
                                        <button class="w-full rounded bg-violet-600 px-3 py-2 text-sm font-semibold hover:bg-violet-500">Publish Poll</button>
                                    </form>
                                    @if($poll)
                                        <div class="flex gap-2">
                                            <form method="POST" action="{{ route('meetings.rooms.studio.polls.update', [$session, $provider, $poll]) }}">@csrf @method('PUT')<input type="hidden" name="action" value="{{ $poll->is_open ? 'close' : 'open' }}"><button class="rounded bg-white/10 px-3 py-2 text-xs">{{ $poll->is_open ? 'Close' : 'Open' }}</button></form>
                                            <form method="POST" action="{{ route('meetings.rooms.studio.polls.update', [$session, $provider, $poll]) }}">@csrf @method('PUT')<input type="hidden" name="action" value="{{ $poll->show_results ? 'hide_results' : 'show_results' }}"><button class="rounded bg-white/10 px-3 py-2 text-xs">{{ $poll->show_results ? 'Hide Results' : 'Show Results' }}</button></form>
                                        </div>
                                    @endif
                                </div>
                            </section>

                            <section id="analytics" class="{{ $panel }} p-3 scroll-mt-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <h2 class="text-sm font-semibold">Stream Analytics</h2>
                                    <a href="{{ $roomUrl }}" class="text-xs text-sky-400 hover:text-sky-300">See Details</a>
                                </div>
                                <div class="grid grid-cols-3 gap-2 text-xs">
                                    <div class="rounded-lg border border-[#1b2d42] bg-white/[0.03] p-2">
                                        <div class="text-3xl font-light text-sky-400">{{ number_format($viewerCount) }}</div>
                                        <div class="text-white/55">Viewers</div>
                                    </div>
                                    <div class="rounded-lg border border-[#1b2d42] bg-white/[0.03] p-2">
                                        <div class="text-3xl font-light text-violet-400">{{ $questions->count() }}</div>
                                        <div class="text-white/55">Q&A</div>
                                    </div>
                                    <div class="rounded-lg border border-[#1b2d42] bg-white/[0.03] p-2">
                                        <div class="text-3xl font-light text-emerald-400">{{ $poll?->votes()->count() ?? 0 }}</div>
                                        <div class="text-white/55">Votes</div>
                                    </div>
                                </div>
                                <div class="mt-3 flex h-20 items-end gap-1 border-b border-l border-[#26384c] px-2">
                                    @foreach($analyticsValues as $height)
                                        <span class="flex-1 rounded-t bg-sky-500/80" style="height: {{ $height * 5 }}%"></span>
                                    @endforeach
                                </div>
                            </section>

                            <section id="settings" class="{{ $panel }} p-3 scroll-mt-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <h2 class="text-sm font-semibold">Stream Destinations</h2>
                                    <a href="#add-destination" class="inline-flex items-center gap-1 text-xs text-violet-300 hover:text-violet-200"><i data-lucide="plus" class="size-4"></i>Add Destination</a>
                                </div>
                                <div class="space-y-2">
                                    @forelse($destinations as $destination)
                                        <div class="flex items-center justify-between rounded bg-black/20 px-3 py-2 text-sm">
                                            <span>{{ $destination['name'] ?? 'Destination' }}</span>
                                            <span class="rounded bg-emerald-500/20 px-2 py-1 text-[10px] font-bold text-emerald-200">{{ Str::upper($destination['status'] ?? 'ready') }}</span>
                                        </div>
                                    @empty
                                        @foreach(['YouTube Live', 'Facebook Live', 'OBS (RTMP)'] as $destinationName)
                                            <div class="flex items-center justify-between rounded bg-black/20 px-3 py-2 text-sm">
                                                <span class="inline-flex items-center gap-2">
                                                    <span class="grid size-6 place-items-center rounded {{ $loop->first ? 'bg-red-600' : ($loop->iteration === 2 ? 'bg-blue-600' : 'bg-white/10') }}"><i data-lucide="{{ $loop->first ? 'play' : ($loop->iteration === 2 ? 'globe-2' : 'radio') }}" class="size-3.5"></i></span>
                                                    {{ $destinationName }}
                                                </span>
                                                <span class="rounded bg-emerald-500/20 px-2 py-1 text-[10px] font-bold text-emerald-200">READY</span>
                                            </div>
                                        @endforeach
                                    @endforelse
                                </div>
                                <form id="add-destination" method="POST" action="{{ route('meetings.rooms.studio.state.update', [$session, $provider]) }}" class="mt-4 space-y-2">
                                    @csrf @method('PUT')
                                    <input name="destination_name" required placeholder="Destination name" class="w-full rounded bg-white/10 px-3 py-2 text-sm text-white outline-none placeholder:text-white/35">
                                    <select name="destination_status" class="w-full rounded bg-white/10 px-3 py-2 text-sm text-white outline-none">
                                        @foreach(['ready', 'live', 'paused', 'offline'] as $status)
                                            <option value="{{ $status }}" class="text-slate-900">{{ Str::headline($status) }}</option>
                                        @endforeach
                                    </select>
                                    <button class="w-full rounded bg-white/10 px-3 py-2 text-sm font-semibold hover:bg-white/15">Add Destination</button>
                                </form>
                            </section>

                        </aside>
                    </div>
                </main>
            </div>
        </div>
</x-app-layout>
