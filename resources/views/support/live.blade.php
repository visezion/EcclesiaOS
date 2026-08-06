<x-app-layout title="Live Support" :breadcrumbs="$breadcrumbs">
    <div class="space-y-4">
        @if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>@endif

        <header class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4"><span class="grid size-14 place-items-center rounded-2xl bg-violet-50 text-violet-600"><i data-lucide="headphones" class="size-7"></i></span><div><p class="text-xs font-bold uppercase tracking-wide text-violet-600">Support Center</p><h1 class="text-2xl font-black text-slate-950">Live Support</h1><p class="mt-0.5 text-sm text-slate-500">Start a real-time conversation with the central support team.</p></div></div>
            <div class="rounded-xl border px-4 py-3 {{ data_get($live, 'online') ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white' }}"><span class="inline-flex items-center gap-2 text-sm font-black {{ data_get($live, 'online') ? 'text-emerald-700' : 'text-slate-600' }}"><span class="size-2.5 rounded-full bg-current"></span>Central Support is {{ data_get($live, 'online') ? 'online' : 'offline' }}</span><p class="mt-1 text-[10px] text-slate-500">If no agent is available, continue with a private ticket.</p></div>
        </header>

        <x-support-nav />

        <section class="grid gap-3 sm:grid-cols-3">
            <div class="dashboard-card"><div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-emerald-50 text-emerald-600"><i data-lucide="users" class="size-5"></i></span><div><div class="text-lg font-black text-slate-950">{{ data_get($live, 'agents_online') === null ? '—' : number_format((int) data_get($live, 'agents_online')) }}</div><div class="text-xs text-slate-500">Agents available</div></div></div></div>
            <div class="dashboard-card"><div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="users-round" class="size-5"></i></span><div><div class="text-lg font-black text-slate-950">{{ data_get($live, 'queue_position') ?? '—' }}</div><div class="text-xs text-slate-500">Current queue position</div></div></div></div>
            <div class="dashboard-card"><div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-blue-50 text-blue-600"><i data-lucide="clock-3" class="size-5"></i></span><div><div class="text-lg font-black text-slate-950">{{ data_get($live, 'average_response') ?? '—' }}</div><div class="text-xs text-slate-500">Average response time</div></div></div></div>
        </section>

        @if($unavailable)
            <section class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
                <div class="dashboard-card py-14 text-center"><span class="mx-auto grid size-14 place-items-center rounded-2xl bg-amber-50 text-amber-600"><i data-lucide="headphones" class="size-7"></i></span><h2 class="mt-4 font-black text-slate-950">Live support is not connected</h2><p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">{{ $unavailable }}</p><div class="mt-5 flex flex-wrap justify-center gap-2"><a href="{{ route('support.tickets.create') }}" class="inline-flex h-10 items-center gap-2 rounded-lg bg-violet-600 px-4 text-xs font-bold text-white"><i data-lucide="list-plus" class="size-4"></i>Submit a ticket</a>@if(auth()->user()->isSuperAdministrator() || auth()->user()->hasPermission('manage settings'))<a href="{{ route('central-support.index') }}" class="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-200 px-4 text-xs font-bold text-slate-700">Connection settings</a>@endif</div></div>
                <aside class="dashboard-card"><h2 class="font-black text-slate-950">While you wait</h2><div class="mt-3 space-y-2"><a href="{{ route('support.knowledge') }}" class="flex items-center gap-3 rounded-lg bg-slate-50 p-3 text-xs font-bold text-slate-700"><i data-lucide="book-open" class="size-4 text-violet-600"></i>Search the Knowledge Base</a><a href="{{ route('support.community') }}" class="flex items-center gap-3 rounded-lg bg-slate-50 p-3 text-xs font-bold text-slate-700"><i data-lucide="messages-square" class="size-4 text-violet-600"></i>Browse Community Solutions</a></div></aside>
            </section>
        @else
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center gap-3 border-b border-slate-100 p-4"><span class="grid size-9 place-items-center rounded-full bg-violet-50 text-xs font-black text-violet-700">{{ strtoupper(substr(data_get($live, 'agent.name', 'S'), 0, 1)) }}</span><div><h2 class="text-sm font-black text-slate-950">{{ data_get($live, 'agent.name', 'Central Support') }}</h2><p class="text-[10px] text-emerald-600">{{ data_get($live, 'online') ? 'Online' : 'Awaiting agent' }}</p></div></div>
                    <div class="min-h-80 space-y-3 bg-slate-50/60 p-4">
                        @forelse(data_get($live, 'messages', []) as $message)
                            <div class="flex {{ data_get($message, 'mine') ? 'justify-end' : 'justify-start' }}"><div class="max-w-[80%] rounded-xl px-4 py-3 text-sm leading-6 {{ data_get($message, 'mine') ? 'bg-violet-600 text-white' : 'border border-slate-200 bg-white text-slate-700' }}"><p>{{ data_get($message, 'body') }}</p><span class="mt-1 block text-[9px] opacity-70">{{ data_get($message, 'sent_at') }}</span></div></div>
                        @empty
                            <div class="grid min-h-72 place-items-center text-center"><div><i data-lucide="messages-square" class="mx-auto size-8 text-slate-300"></i><h3 class="mt-3 font-black text-slate-800">Start a conversation</h3><p class="mt-1 text-xs text-slate-500">Describe the problem and an available agent will respond.</p></div></div>
                        @endforelse
                    </div>
                    <form method="POST" action="{{ route('support.live.messages') }}" class="flex gap-2 border-t border-slate-100 p-4">@csrf<input name="message" required class="h-11 min-w-0 flex-1 rounded-lg border border-slate-200 px-3 text-sm" placeholder="Type your message"><button class="grid size-11 place-items-center rounded-lg bg-violet-600 text-white"><i data-lucide="send" class="size-4"></i></button></form>
                </section>
                <aside class="space-y-4"><section class="dashboard-card"><h2 class="font-black text-slate-950">Case context</h2><dl class="mt-3 space-y-3 text-xs"><div class="flex justify-between gap-3"><dt class="text-slate-500">Church</dt><dd class="font-bold text-slate-800">{{ auth()->user()->church?->name ?? 'Platform' }}</dd></div><div class="flex justify-between gap-3"><dt class="text-slate-500">Installation</dt><dd class="font-mono text-[10px] font-bold text-slate-800">{{ $connection['installation_id'] }}</dd></div><div class="flex justify-between gap-3"><dt class="text-slate-500">Environment</dt><dd class="font-bold text-emerald-600">{{ app()->environment() }}</dd></div></dl></section><section class="dashboard-card"><h2 class="font-black text-slate-950">Conversation safety</h2><p class="mt-2 text-xs leading-5 text-slate-500">Never send passwords, API credentials, payment secrets, or private member information through live chat.</p></section></aside>
            </div>
        @endif
    </div>
</x-app-layout>
