<x-app-layout title="Events" :breadcrumbs="$breadcrumbs">
    <div x-data="{ createOpen: false }" class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-lg bg-violet-100 text-violet-600"><i data-lucide="calendar-plus" class="size-7"></i></div>
                <div><h1 class="text-2xl font-semibold text-slate-950">{{ $program?->name ?? 'Events' }}</h1><p class="text-sm text-slate-500">{{ $program?->description ?? 'Events grouped under programs.' }}</p></div>
            </div>
            @if($program)<button @click="createOpen = true" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white"><i data-lucide="plus" class="size-4"></i>New Event</button>@endif
        </div>
        @if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 md:flex-row md:items-center">
                <select onchange="if(this.value) location.href=this.value" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm"><option value="">Choose program</option>@foreach($programs as $item)<option value="{{ route('programs.events', $item) }}" @selected($program?->id === $item->id)>{{ $item->name }}</option>@endforeach</select>
                <div class="relative flex-1"><input class="w-full rounded-lg border border-slate-200 px-4 py-2.5 pl-10 text-sm" placeholder="Search events..."><i data-lucide="search" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i></div>
                <select class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm"><option>All Status</option></select>
            </div>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Event Name</th><th class="px-5 py-3">Description</th><th class="px-5 py-3">Event Type</th><th class="px-5 py-3">Sessions</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Actions</th></tr></thead>
                <tbody class="divide-y divide-slate-100">@forelse($events as $event)<tr>
                    <td class="px-5 py-4"><a class="text-violet-700" href="{{ $event->program ? route('event-sessions.index', [$event->program, $event]) : route('events.index') }}">{{ $event->title }}</a></td>
                    <td class="px-5 py-4 text-slate-600">{{ Str::limit($event->description, 72) ?: $event->venue }}</td>
                    <td class="px-5 py-4">{{ $event->event_type ?? $event->category }}</td>
                    <td class="px-5 py-4">{{ $event->sessions_count }}</td>
                    <td class="px-5 py-4"><span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs text-emerald-700 ring-1 ring-emerald-100">{{ Str::headline($event->status) }}</span></td>
                    <td class="px-5 py-4 text-right">@if($event->program)<a href="{{ route('event-sessions.index', [$event->program, $event]) }}" class="inline-grid size-8 place-items-center rounded-lg hover:bg-violet-50"><i data-lucide="eye" class="size-4"></i></a>@endif</td>
                </tr>@empty<tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">No events yet.</td></tr>@endforelse</tbody>
            </table></div>
            <div class="border-t border-slate-100 p-4">{{ $events->links() }}</div>
        </section>

        @if($program)
            <div x-cloak x-show="createOpen" class="fixed inset-0 z-40 bg-slate-950/40" @click="createOpen = false"></div>
            <aside x-cloak x-show="createOpen" class="fixed inset-y-0 right-0 z-50 w-full max-w-md overflow-y-auto bg-white p-6 shadow-2xl">
                <div class="mb-5 flex items-center justify-between"><h2 class="text-lg text-slate-950">New Event</h2><button @click="createOpen = false" class="rounded-lg p-2 hover:bg-slate-100"><i data-lucide="x" class="size-5"></i></button></div>
                <form method="POST" action="{{ route('programs.events.store', $program) }}" class="space-y-4">@csrf
                    <input name="title" required placeholder="Event name" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <textarea name="description" rows="3" placeholder="Description" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
                    <div class="grid gap-3 sm:grid-cols-2"><input name="event_type" placeholder="Service, Workshop..." class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="scheduled">Scheduled</option><option value="draft">Draft</option></select></div>
                    <input name="starts_at" type="datetime-local" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <input name="ends_at" type="datetime-local" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <input name="venue" placeholder="Venue" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <button class="w-full rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white">Create Event</button>
                </form>
            </aside>
        @endif
    </div>
</x-app-layout>
