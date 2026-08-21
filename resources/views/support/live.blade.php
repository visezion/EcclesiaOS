<x-app-layout title="Live Support" :breadcrumbs="$breadcrumbs">
    <div class="space-y-4">
        @if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>@endif

        <header class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4"><span class="grid size-14 place-items-center rounded-2xl bg-violet-50 text-violet-600"><i data-lucide="headphones" class="size-7"></i></span><div><p class="text-xs font-bold uppercase tracking-wide text-violet-600">Support Center</p><h1 class="text-2xl font-black text-slate-950">Live Support</h1><p class="mt-0.5 text-sm text-slate-500">Start a real-time conversation with the central support team.</p></div></div>
            <div id="live-status" class="rounded-xl border px-4 py-3 {{ data_get($live, 'online') ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white' }}"><span class="inline-flex items-center gap-2 text-sm font-black {{ data_get($live, 'online') ? 'text-emerald-700' : 'text-slate-600' }}"><span class="size-2.5 rounded-full bg-current"></span><span data-live-status-label>Central Support is {{ data_get($live, 'online') ? 'online' : 'offline' }}</span></span><p class="mt-1 text-[10px] text-slate-500">Messages refresh automatically. If no agent is available, continue with a private ticket.</p></div>
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
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]" data-live-chat>
                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center gap-3 border-b border-slate-100 p-4"><span class="grid size-9 place-items-center rounded-full bg-violet-50 text-xs font-black text-violet-700">{{ strtoupper(substr(data_get($live, 'agent.name', 'S'), 0, 1)) }}</span><div><h2 class="text-sm font-black text-slate-950">{{ data_get($live, 'agent.name', 'Central Support') }}</h2><p class="text-[10px] text-emerald-600">{{ data_get($live, 'online') ? 'Online' : 'Awaiting agent' }}</p></div></div>
                    <div id="live-messages" class="min-h-80 space-y-3 bg-slate-50/60 p-4" aria-live="polite">
                        @forelse(data_get($live, 'messages', []) as $message)
                            <div class="flex {{ data_get($message, 'mine') ? 'justify-end' : 'justify-start' }}"><div class="max-w-[80%] rounded-2xl px-4 py-3 text-sm leading-6 {{ data_get($message, 'mine') ? 'bg-violet-600 text-white' : 'border border-slate-200 bg-white text-slate-700' }}"><p>{{ data_get($message, 'body') }}</p><span class="mt-1 block text-[9px] opacity-70">{{ data_get($message, 'sent_at') }}</span></div></div>
                        @empty
                            <div class="grid min-h-72 place-items-center text-center"><div><i data-lucide="messages-square" class="mx-auto size-8 text-slate-300"></i><h3 class="mt-3 font-black text-slate-800">Start a conversation</h3><p class="mt-1 text-xs text-slate-500">Describe the problem and an available agent will respond.</p></div></div>
                        @endforelse
                    </div>
                    <form method="POST" action="{{ route('support.live.messages') }}" class="border-t border-slate-100 p-4" data-live-form>@csrf<div class="flex gap-2"><textarea name="message" required maxlength="5000" rows="2" class="min-h-11 min-w-0 flex-1 resize-y rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm" placeholder="Write a clear message to Central Support..."></textarea><button type="submit" class="self-end rounded-xl bg-violet-600 px-4 py-3 text-xs font-bold text-white disabled:cursor-wait disabled:opacity-60"><i data-lucide="send" class="mr-1 inline-block size-4"></i><span>Send</span></button></div><div class="mt-2 flex justify-between text-[10px] text-slate-400"><span>Press Enter to send · Shift+Enter for a new line</span><span data-live-count>0 / 5000</span></div></form>
                </section>
                <aside class="space-y-4"><section class="dashboard-card"><h2 class="font-black text-slate-950">Case context</h2><dl class="mt-3 space-y-3 text-xs"><div class="flex justify-between gap-3"><dt class="text-slate-500">Church</dt><dd class="font-bold text-slate-800">{{ auth()->user()->church?->name ?? 'Platform' }}</dd></div><div class="flex justify-between gap-3"><dt class="text-slate-500">Installation</dt><dd class="font-mono text-[10px] font-bold text-slate-800">{{ $connection['installation_id'] }}</dd></div><div class="flex justify-between gap-3"><dt class="text-slate-500">Environment</dt><dd class="font-bold text-emerald-600">{{ app()->environment() }}</dd></div></dl></section><section class="dashboard-card"><h2 class="font-black text-slate-950">Conversation safety</h2><p class="mt-2 text-xs leading-5 text-slate-500">Never send passwords, API credentials, payment secrets, or private member information through live chat.</p></section></aside>
            </div>
        @endif
    </div>
</x-app-layout>

@push('scripts')
<script>
(() => {
    const chat = document.querySelector('[data-live-chat]');
    const messages = document.getElementById('live-messages');
    const form = document.querySelector('[data-live-form]');
    const textarea = form?.querySelector('textarea');
    const count = form?.querySelector('[data-live-count]');
    const status = document.querySelector('[data-live-status-label]');
    if (!chat || !messages || !form || !textarea) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const updatesUrl = @js(route('support.live.updates'));
    const sendUrl = form.action;
    const formatTime = value => value ? new Intl.DateTimeFormat([], {hour: 'numeric', minute: '2-digit'}).format(new Date(value)) : '';
    const render = items => {
        messages.replaceChildren();
        if (!items?.length) {
            messages.innerHTML = '<div class="grid min-h-72 place-items-center text-center"><div><div class="mx-auto grid size-8 place-items-center rounded-xl bg-slate-100 text-slate-400">···</div><h3 class="mt-3 font-black text-slate-800">Start a conversation</h3><p class="mt-1 text-xs text-slate-500">Describe the problem and an available agent will respond.</p></div></div>';
            return;
        }
        items.forEach(item => {
            const row = document.createElement('div');
            row.className = 'flex ' + (item.mine ? 'justify-end' : 'justify-start');
            const bubble = document.createElement('div');
            bubble.className = 'max-w-[80%] rounded-2xl px-4 py-3 text-sm leading-6 ' + (item.mine ? 'bg-violet-600 text-white' : 'border border-slate-200 bg-white text-slate-700');
            const body = document.createElement('p');
            body.textContent = item.body || '';
            const time = document.createElement('span');
            time.className = 'mt-1 block text-[9px] opacity-70';
            time.textContent = formatTime(item.sent_at);
            bubble.append(body, time); row.append(bubble); messages.append(row);
        });
        messages.scrollTop = messages.scrollHeight;
    };
    const refresh = async () => {
        try {
            const response = await fetch(updatesUrl, {headers: {'Accept': 'application/json'}});
            if (!response.ok) return;
            const data = await response.json();
            render(data.messages || []);
            if (status) status.textContent = 'Central Support is ' + (data.online ? 'online' : 'offline');
        } catch (_) {}
    };
    textarea.addEventListener('input', () => { if (count) count.textContent = textarea.value.length + ' / 5000'; });
    textarea.addEventListener('keydown', event => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); form.requestSubmit(); } });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!textarea.value.trim()) return;
        const button = form.querySelector('button'); button.disabled = true;
        try {
            const response = await fetch(sendUrl, {method: 'POST', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf}, body: JSON.stringify({message: textarea.value})});
            if (!response.ok) throw new Error('send failed');
            textarea.value = ''; if (count) count.textContent = '0 / 5000'; await refresh();
        } catch (_) { window.location.reload(); } finally { button.disabled = false; textarea.focus(); }
    });
    refresh(); window.setInterval(refresh, 5000);
})();
</script>
@endpush
