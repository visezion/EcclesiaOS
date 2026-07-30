@php
    $active ??= 'inbox';
    $unreadCount ??= 0;
    $stats ??= ['conversations' => 0, 'sent' => 0, 'members' => 0];
@endphp

<aside class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 p-3">
        <a href="{{ route('messages.create') }}" class="flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-violet-700 to-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:brightness-110">
            <i data-lucide="plus" class="size-4"></i>
            New message
        </a>
    </div>

    <nav class="p-3" aria-label="Message folders">
        <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Folders</p>
        <a href="{{ route('messages.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold {{ $active === 'inbox' ? 'bg-violet-50 text-violet-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
            <i data-lucide="inbox" class="size-4"></i>
            Inbox
            @if ($unreadCount > 0)
                <span class="ml-auto rounded-full bg-violet-600 px-2 py-0.5 text-[11px] font-bold text-white">{{ $unreadCount }}</span>
            @endif
        </a>
        <a href="{{ route('messages.sent') }}" class="mt-1 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold {{ $active === 'sent' ? 'bg-violet-50 text-violet-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
            <i data-lucide="send" class="size-4"></i>
            Sent
            <span class="ml-auto text-xs font-medium text-slate-400">{{ number_format($stats['sent']) }}</span>
        </a>
    </nav>

    <div class="border-t border-slate-100 p-3">
        <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Summary</p>
        <dl class="space-y-1">
            <div class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-600">
                <i data-lucide="messages-square" class="size-4 text-slate-400"></i>
                <dt>Conversations</dt>
                <dd class="ml-auto font-bold text-slate-800">{{ number_format($stats['conversations']) }}</dd>
            </div>
            <div class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-600">
                <i data-lucide="users" class="size-4 text-slate-400"></i>
                <dt>Available people</dt>
                <dd class="ml-auto font-bold text-slate-800">{{ number_format($stats['members']) }}</dd>
            </div>
        </dl>
    </div>

    <div class="m-3 mt-1 rounded-xl border border-emerald-100 bg-emerald-50 p-3">
        <div class="flex gap-2.5">
            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-white text-emerald-600 shadow-sm"><i data-lucide="shield-check" class="size-4"></i></span>
            <div>
                <p class="text-xs font-bold text-emerald-800">Private & audited</p>
                <p class="mt-0.5 text-[11px] leading-4 text-emerald-700">Messages stay inside your church workspace.</p>
            </div>
        </div>
    </div>
</aside>
