<x-app-layout title="{{ $thread->subject ?: 'Conversation' }}" :breadcrumbs="$breadcrumbs" main-class="px-4 py-5 sm:px-6 lg:px-7">
    <div class="space-y-4">
        <header class="flex items-start gap-3">
            <a href="{{ route('messages.index') }}" class="grid size-11 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm hover:border-violet-200 hover:text-violet-700" aria-label="Back to messages"><i data-lucide="arrow-left" class="size-5"></i></a>
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-950">Message Center</h1>
                <p class="mt-0.5 text-sm text-slate-500">Read and reply to your internal ministry conversations.</p>
            </div>
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-4 xl:grid-cols-[220px_360px_minmax(0,1fr)]">
            @include('messages.partials.mailbox', ['active' => 'inbox', 'unreadCount' => $unreadCount, 'stats' => $stats])

            <section class="hidden max-h-[calc(100vh-9rem)] overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-sm lg:block">
                <div class="sticky top-0 z-10 border-b border-slate-100 bg-white/95 px-4 py-4 backdrop-blur">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-extrabold text-slate-900">Conversations</p>
                        <a href="{{ route('messages.create') }}" class="grid size-8 place-items-center rounded-lg bg-violet-50 text-violet-700 hover:bg-violet-100" aria-label="New message"><i data-lucide="square-pen" class="size-4"></i></a>
                    </div>
                </div>
                @include('messages.partials.thread-list', ['threads' => $threads, 'activeThreadId' => $thread->id])
            </section>

            <section class="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <header class="border-b border-slate-100 px-4 py-4 sm:px-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h2 class="truncate text-lg font-extrabold text-slate-950">{{ $thread->subject ?: 'Untitled conversation' }}</h2>
                                <span class="shrink-0 rounded-md bg-violet-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-violet-700">Private</span>
                            </div>
                            <p class="mt-1 text-xs font-medium text-slate-500">{{ $thread->participants->count() }} participants &middot; Started {{ $thread->created_at?->diffForHumans() }}</p>
                        </div>
                        <div class="flex -space-x-2">
                            @foreach ($thread->participants->take(6) as $participant)
                                @if ($participant->avatar_src)
                                    <img src="{{ $participant->avatar_src }}" alt="{{ $participant->name }}" title="{{ $participant->name }}" class="size-8 rounded-full border-2 border-white object-cover">
                                @else
                                    <span title="{{ $participant->name }}" class="grid size-8 place-items-center rounded-full border-2 border-white bg-indigo-100 text-[10px] font-extrabold text-indigo-700">{{ strtoupper(substr($participant->name, 0, 1)) }}</span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </header>

                <div class="max-h-[calc(100vh-25rem)] min-h-80 space-y-3 overflow-y-auto bg-slate-50/60 p-4 sm:p-5">
                    @foreach ($thread->messages as $message)
                        @php($isMine = $message->sender_id === auth()->id())
                        <article class="flex gap-3 {{ $isMine ? 'flex-row-reverse' : '' }}">
                            @if ($message->sender->avatar_src)
                                <img src="{{ $message->sender->avatar_src }}" alt="" class="size-9 shrink-0 rounded-full object-cover ring-2 ring-white">
                            @else
                                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-indigo-100 to-violet-100 text-xs font-extrabold text-violet-700 ring-2 ring-white">{{ strtoupper(substr($message->sender->name, 0, 1)) }}</span>
                            @endif
                            <div class="max-w-[88%] sm:max-w-[78%]">
                                <div class="mb-1 flex items-center gap-2 text-[10px] font-semibold text-slate-400 {{ $isMine ? 'justify-end' : '' }}">
                                    <span class="text-slate-600">{{ $isMine ? 'You' : $message->sender->name }}</span>
                                    <time>{{ $message->created_at?->format('M j, g:i A') }}</time>
                                </div>
                                <div class="rounded-2xl px-4 py-3 text-sm leading-6 shadow-sm {{ $isMine ? 'rounded-tr-md bg-violet-600 text-white' : 'rounded-tl-md border border-slate-200 bg-white text-slate-700' }}">
                                    <p class="whitespace-pre-wrap">{{ $message->body }}</p>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <form method="POST" action="{{ route('messages.reply', $thread) }}" class="border-t border-slate-100 bg-white p-4 sm:p-5">
                    @csrf
                    <label class="sr-only" for="reply-body">Reply</label>
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white transition focus-within:border-violet-400 focus-within:ring-2 focus-within:ring-violet-100">
                        <textarea id="reply-body" name="body" rows="3" required maxlength="10000" class="block w-full resize-none border-0 px-4 py-3 text-sm text-slate-800 outline-none focus:ring-0" placeholder="Type your message...">{{ old('body') }}</textarea>
                        <div class="flex items-center justify-between border-t border-slate-100 px-3 py-2">
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-slate-400"><i data-lucide="lock-keyhole" class="size-3.5"></i>Internal message</span>
                            <button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-bold text-white hover:bg-violet-700"><i data-lucide="send" class="size-4"></i>Send</button>
                        </div>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
