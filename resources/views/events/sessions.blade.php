<x-app-layout title="Event Sessions" :breadcrumbs="$breadcrumbs">
    <div x-data="{ createOpen: false }" class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4"><div class="grid size-14 place-items-center rounded-lg bg-violet-100 text-violet-600"><i data-lucide="clock" class="size-7"></i></div><div><h1 class="text-2xl font-semibold text-slate-950">{{ $event->title }}</h1><p class="text-sm text-slate-500">{{ $program->name }} event sessions, venues, meeting links, and attendance setup.</p></div></div>
            <button @click="createOpen = true" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white"><i data-lucide="plus" class="size-4"></i>New Event Session</button>
        </div>
        @if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 p-4"><div class="relative max-w-3xl"><input class="w-full rounded-lg border border-slate-200 px-4 py-2.5 pl-10 text-sm" placeholder="Search sessions..."><i data-lucide="search" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i></div></div>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Session Title</th><th class="px-5 py-3">Date</th><th class="px-5 py-3">Start Time</th><th class="px-5 py-3">End Time</th><th class="px-5 py-3">Venue / Link</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-slate-100">@forelse($sessions as $session)<tr>
                    <td class="px-5 py-4"><a href="{{ route('event-sessions.meeting', $session) }}" class="text-violet-700">{{ $session->title }}</a><div class="text-xs text-slate-500">{{ Str::headline($session->meeting_type) }}</div></td>
                    <td class="px-5 py-4">{{ $session->session_date->format('d M, Y') }}</td><td class="px-5 py-4">{{ Str::of($session->starts_at)->substr(0,5) }}</td><td class="px-5 py-4">{{ $session->ends_at ? Str::of($session->ends_at)->substr(0,5) : '-' }}</td>
                    <td class="px-5 py-4">{{ $session->venue ?: collect($session->meeting_links ?? [])->pluck('room')->filter()->first() ?: 'Not set' }}</td>
                    <td class="px-5 py-4"><span class="rounded-full bg-violet-50 px-2.5 py-1 text-xs text-violet-700 ring-1 ring-violet-100">{{ Str::headline($session->status) }}</span></td>
                    <td class="px-5 py-4 text-right"><a href="{{ route('event-sessions.meeting', $session) }}" class="inline-grid size-8 place-items-center rounded-lg hover:bg-violet-50"><i data-lucide="eye" class="size-4"></i></a><a href="{{ route('event-sessions.attendance', $session) }}" class="inline-grid size-8 place-items-center rounded-lg hover:bg-violet-50"><i data-lucide="clipboard-check" class="size-4"></i></a></td>
                </tr>@empty<tr><td colspan="7" class="px-5 py-10 text-center text-slate-500">No sessions created yet.</td></tr>@endforelse</tbody></table></div>
            <div class="border-t border-slate-100 p-4">{{ $sessions->links() }}</div>
        </section>

        <div x-cloak x-show="createOpen" class="fixed inset-0 z-40 bg-slate-950/40" @click="createOpen = false"></div>
        <aside x-cloak x-show="createOpen" class="fixed inset-y-0 right-0 z-50 w-full max-w-lg overflow-y-auto bg-white p-6 shadow-2xl">
            <div class="mb-5 flex items-center justify-between"><h2 class="text-lg text-slate-950">New Event Session</h2><button @click="createOpen = false" class="rounded-lg p-2 hover:bg-slate-100"><i data-lucide="x" class="size-5"></i></button></div>
            <form method="POST" action="{{ route('event-sessions.store', [$program, $event]) }}" class="space-y-4">@csrf
                <input name="title" required value="{{ $event->title }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <div class="grid gap-3 sm:grid-cols-3"><input name="session_date" required type="date" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><input name="starts_at" required type="time" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><input name="ends_at" type="time" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"></div>
                <div class="grid gap-3 sm:grid-cols-2"><select name="meeting_type" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="physical">Physical</option><option value="online">Online</option><option value="hybrid">Hybrid</option></select><select name="campus_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">Program campus</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}">{{ $campus->name }}</option>@endforeach</select></div>
                <input name="venue" placeholder="Venue" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><input name="address" placeholder="Address" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <div class="grid gap-3 sm:grid-cols-2"><input name="capacity" type="number" min="0" placeholder="Capacity" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="scheduled">Scheduled</option><option value="draft">Draft</option></select></div>
                <section class="rounded-lg border border-slate-200 p-4">
                    <h3 class="text-sm font-semibold text-slate-950">Built-in Online Rooms</h3>
                    <p class="mt-1 text-xs text-slate-500">Only enabled meeting methods are available. Select the rooms this session can use.</p>
                    <div class="mt-3 space-y-3">
                        @forelse($enabledMeetingProviders as $provider => $meta)
                            <div class="rounded-lg bg-slate-50 p-3">
                                <label class="mb-2 flex items-center justify-between gap-3 text-sm text-slate-700">
                                    <span class="inline-flex items-center gap-2"><i data-lucide="{{ $meta['icon'] }}" class="size-4 text-violet-600"></i>{{ $meta['label'] }}</span>
                                    <input type="checkbox" name="meeting_links[{{ $provider }}][enabled]" value="1" class="rounded border-slate-300 text-violet-600">
                                </label>
                                <div class="grid gap-2 sm:grid-cols-[1fr_120px]">
                                    <input name="meeting_links[{{ $provider }}][room]" placeholder="{{ Str::slug($meta['label']) }} room ID" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <input name="meeting_links[{{ $provider }}][access_code]" placeholder="Code" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                </div>
                            </div>
                        @empty
                            <div class="rounded-lg bg-amber-50 p-3 text-sm text-amber-700">No built-in meeting methods are enabled. Enable them in Meeting Method Setup first.</div>
                        @endforelse
                    </div>
                </section>
                <button class="w-full rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white">Create Session</button>
            </form>
        </aside>
    </div>
</x-app-layout>
