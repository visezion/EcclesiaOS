@php
    $activeThreadId ??= null;
    $emptyMessage ??= 'No messages yet';
@endphp

<div class="divide-y divide-slate-100">
    @forelse($threads as $threadItem)
        @php
            $self = $threadItem->participants->firstWhere('id', auth()->id());
            $lastReadAt = $self?->pivot?->last_read_at;
            $unread = ! $lastReadAt || ($threadItem->last_message_at && $threadItem->last_message_at->gt($lastReadAt));
            $others = $threadItem->participants->where('id', '!=', auth()->id());
            $primaryPerson = $others->first() ?: $threadItem->creator;
            $participantNames = $others->pluck('name')->join(', ');
            $selected = (int) $activeThreadId === (int) $threadItem->id;
        @endphp
        <a href="{{ route('messages.show', $threadItem) }}" class="group relative flex gap-3 px-4 py-4 transition {{ $selected ? 'bg-violet-50' : 'hover:bg-slate-50' }}">
            @if ($selected)
                <span class="absolute inset-y-0 left-0 w-1 rounded-r-full bg-violet-600"></span>
            @endif
            <span class="relative size-11 shrink-0">
                @if ($primaryPerson?->avatar_src)
                    <img src="{{ $primaryPerson->avatar_src }}" alt="" class="size-11 rounded-full object-cover ring-2 ring-white">
                @else
                    <span class="grid size-11 place-items-center rounded-full bg-gradient-to-br from-indigo-100 to-violet-100 text-sm font-extrabold text-violet-700">{{ strtoupper(substr($primaryPerson?->name ?: 'M', 0, 1)) }}</span>
                @endif
                @if ($unread)
                    <span class="absolute -right-0.5 -top-0.5 size-3 rounded-full border-2 border-white bg-violet-600"></span>
                @endif
            </span>
            <span class="min-w-0 flex-1">
                <span class="flex items-start gap-2">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm {{ $unread ? 'font-extrabold text-slate-950' : 'font-bold text-slate-800' }}">{{ $threadItem->subject ?: 'Untitled conversation' }}</span>
                        <span class="mt-0.5 block truncate text-xs font-medium text-slate-500">{{ $participantNames ?: 'Only you' }}</span>
                    </span>
                    <time class="shrink-0 text-[10px] font-semibold text-slate-400">{{ $threadItem->last_message_at?->isToday() ? $threadItem->last_message_at->format('g:i A') : $threadItem->last_message_at?->format('M j') }}</time>
                </span>
                <span class="mt-2 flex items-center gap-2">
                    <span class="min-w-0 flex-1 truncate text-xs text-slate-500">{{ $threadItem->latestMessage?->body ?: 'No message preview' }}</span>
                    @if ($threadItem->messages_count > 1)
                        <span class="inline-flex shrink-0 items-center gap-1 text-[10px] font-bold text-slate-400"><i data-lucide="message-circle" class="size-3"></i>{{ $threadItem->messages_count }}</span>
                    @endif
                </span>
            </span>
        </a>
    @empty
        <div class="grid min-h-72 place-items-center px-6 py-12 text-center">
            <div>
                <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-violet-50 text-violet-500"><i data-lucide="messages-square" class="size-7"></i></span>
                <h2 class="mt-4 text-sm font-bold text-slate-800">{{ $emptyMessage }}</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">Start a private conversation with someone on your team.</p>
                <a href="{{ route('messages.create') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-bold text-violet-700"><i data-lucide="plus" class="size-4"></i>Compose message</a>
            </div>
        </div>
    @endforelse
</div>
