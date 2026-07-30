<x-app-layout title="Compose Message" :breadcrumbs="$breadcrumbs">
    <div class="mx-auto max-w-5xl space-y-5">
        <header class="flex items-start gap-3">
            <a href="{{ route('messages.index') }}" class="grid size-11 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm hover:border-violet-200 hover:text-violet-700" aria-label="Back to messages"><i data-lucide="arrow-left" class="size-5"></i></a>
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-950">New Message</h1>
                <p class="mt-0.5 text-sm text-slate-500">Start a private conversation with one or more people on your team.</p>
            </div>
        </header>

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_260px]">
            <form method="POST" action="{{ route('messages.store') }}" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                @csrf
                <div class="space-y-5 p-5 sm:p-6">
                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Recipients</span>
                        <select name="recipients[]" multiple required class="mt-2 min-h-48 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm focus:border-violet-400 focus:bg-white focus:ring-2 focus:ring-violet-100">
                            @foreach ($users as $user)
                                <option value="{{ $user->opaqueId() }}" @selected(in_array($user->opaqueId(), old('recipients', []), true))>{{ $user->name }}{{ $user->title ? ' - '.$user->title : '' }}</option>
                            @endforeach
                        </select>
                        <span class="mt-2 flex items-center gap-1.5 text-xs text-slate-500"><i data-lucide="info" class="size-3.5"></i>Hold Ctrl on Windows or Command on Mac to select multiple people.</span>
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Subject</span>
                        <input name="subject" value="{{ old('subject') }}" maxlength="160" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-violet-400 focus:ring-2 focus:ring-violet-100" placeholder="What is this conversation about?">
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Message</span>
                        <textarea name="body" rows="9" maxlength="10000" required class="mt-2 w-full resize-y rounded-xl border border-slate-200 px-4 py-3 text-sm leading-6 focus:border-violet-400 focus:ring-2 focus:ring-violet-100" placeholder="Write your message...">{{ old('body') }}</textarea>
                    </label>
                </div>
                <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500"><i data-lucide="lock-keyhole" class="size-3.5"></i>Visible only to selected recipients</span>
                    <div class="flex gap-2">
                        <a href="{{ route('messages.index') }}" class="flex-1 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-center text-sm font-bold text-slate-600 hover:bg-slate-50 sm:flex-none">Cancel</a>
                        <button class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-violet-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-violet-700 sm:flex-none"><i data-lucide="send" class="size-4"></i>Send message</button>
                    </div>
                </div>
            </form>

            <aside class="space-y-3">
                <div class="rounded-xl border border-violet-100 bg-gradient-to-br from-violet-50 to-indigo-50 p-4">
                    <span class="grid size-10 place-items-center rounded-xl bg-white text-violet-600 shadow-sm"><i data-lucide="users" class="size-5"></i></span>
                    <h2 class="mt-3 text-sm font-extrabold text-slate-900">{{ number_format($users->count()) }} people available</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-600">You can message active users from your church workspace.</p>
                </div>
                <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4">
                    <div class="flex gap-3">
                        <i data-lucide="shield-check" class="mt-0.5 size-5 shrink-0 text-emerald-600"></i>
                        <div><h2 class="text-sm font-extrabold text-emerald-900">Secure communication</h2><p class="mt-1 text-xs leading-5 text-emerald-700">Access is permission-based and message activity is recorded.</p></div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
