<x-app-layout title="Join {{ $session->title }}" :chromeless="true">
    @php
        $sessionStartsAt = \Illuminate\Support\Str::of($session->starts_at)->substr(0, 5);
        $sessionEndsAt = filled($session->ends_at) ? \Illuminate\Support\Str::of($session->ends_at)->substr(0, 5) : null;
        $eventDate = $session->session_date->format('M d, Y');
        $eventTime = $sessionStartsAt.($sessionEndsAt ? ' - '.$sessionEndsAt : '');
        $venue = $session->venue ?: ($session->event->venue ?: ($session->campus?->name ?? 'Virtual Event'));
    @endphp

    <main class="grid min-h-screen place-items-center bg-[radial-gradient(circle_at_top_left,rgba(124,58,237,0.22),transparent_34%),linear-gradient(135deg,#050914,#0c1320_55%,#050914)] px-4 py-10 text-white">
        <section class="w-full max-w-5xl overflow-hidden rounded-lg border border-white/10 bg-white/[0.06] shadow-2xl shadow-black/30 backdrop-blur">
            <div class="grid lg:grid-cols-[1.05fr_0.95fr]">
                <div class="p-6 sm:p-8 lg:p-10">
                    <div class="mb-8 flex items-center gap-3">
                        <div class="grid size-12 place-items-center rounded-xl bg-violet-600 text-white shadow-lg shadow-violet-950/30">
                            <i data-lucide="{{ $meta['icon'] }}" class="size-7"></i>
                        </div>
                        <div>
                            <div class="text-lg font-semibold">{{ config('app.name') }}</div>
                            <div class="text-sm text-white/55">Guest meeting access</div>
                        </div>
                    </div>

                    <span class="inline-flex rounded bg-violet-600/25 px-3 py-1.5 text-xs font-semibold text-violet-100">{{ $session->event->program?->name ?? 'Meeting' }}</span>
                    <h1 class="mt-4 text-3xl font-bold leading-tight text-white sm:text-4xl">{{ $session->title }}</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-white/70">Enter your name so hosts and participants can recognize you in chat, Q&amp;A, and the participant list.</p>

                    <div class="mt-8 grid gap-3 text-sm text-white/70 sm:grid-cols-3">
                        <div class="rounded-lg border border-white/10 bg-black/20 p-4">
                            <i data-lucide="calendar" class="mb-3 size-5 text-violet-200"></i>
                            <div class="font-semibold text-white">{{ $eventDate }}</div>
                            <div class="mt-1">{{ $eventTime }}</div>
                        </div>
                        <div class="rounded-lg border border-white/10 bg-black/20 p-4">
                            <i data-lucide="map-pin" class="mb-3 size-5 text-violet-200"></i>
                            <div class="font-semibold text-white">{{ $venue }}</div>
                            <div class="mt-1">Meeting location</div>
                        </div>
                        <div class="rounded-lg border border-white/10 bg-black/20 p-4">
                            <i data-lucide="{{ $meta['icon'] }}" class="mb-3 size-5 text-violet-200"></i>
                            <div class="font-semibold text-white">{{ $meta['label'] }}</div>
                            <div class="mt-1">Live room</div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-white/10 bg-black/20 p-6 sm:p-8 lg:border-l lg:border-t-0 lg:p-10">
                    <h2 class="text-xl font-semibold text-white">Join as guest</h2>
                    <p class="mt-2 text-sm leading-6 text-white/60">Guest access lets you enter the meeting with your name only. Members and team leaders should log in for full account access.</p>

                    <form method="POST" action="{{ $joinUrl }}" class="mt-6 space-y-5">
                        @csrf
                        <div>
                            <label for="guest_name" class="text-sm font-semibold text-white">Your name</label>
                            <input id="guest_name" name="guest_name" value="{{ old('guest_name') }}" required minlength="2" maxlength="80" autofocus placeholder="Example: Mary Johnson" class="mt-2 w-full rounded-lg border border-white/10 bg-white/10 px-4 py-3 text-sm text-white outline-none placeholder:text-white/35 focus:border-violet-300 focus:ring-4 focus:ring-violet-500/20">
                            @error('guest_name')
                                <p class="mt-2 text-sm text-rose-200">{{ $message }}</p>
                            @else
                                <p class="mt-2 text-xs leading-5 text-white/45">Use the name you want shown inside the live room.</p>
                            @enderror
                        </div>

                        <button class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-3 text-sm font-semibold text-white hover:bg-violet-500 focus-visible:ring-2 focus-visible:ring-violet-300">
                            <i data-lucide="door-open" class="size-4"></i>
                            Continue to meeting
                        </button>
                    </form>

                    <div class="mt-5 rounded-lg border border-white/10 bg-white/[0.06] p-4 text-sm leading-6 text-white/65">
                        <div class="flex gap-3">
                            <i data-lucide="info" class="mt-0.5 size-4 shrink-0 text-violet-200"></i>
                            <p>Keep your camera and microphone off until you are ready. The host may use chat, Q&amp;A, polls, and screen share during the meeting.</p>
                        </div>
                    </div>

                    <a href="{{ $loginUrl }}" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-white/15 px-4 py-3 text-sm font-semibold text-white hover:bg-white/10">
                        <i data-lucide="log-in" class="size-4"></i>
                        Log in instead
                    </a>
                </div>
            </div>
        </section>
    </main>
</x-app-layout>
