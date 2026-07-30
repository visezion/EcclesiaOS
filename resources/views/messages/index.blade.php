<x-app-layout title="Message Center" :breadcrumbs="$breadcrumbs" main-class="px-4 py-5 sm:px-6 lg:px-7">
    @php
        $selectedThread = $selectedThreadId ? $threads->firstWhere('id', $selectedThreadId) : null;
    @endphp

    <div
        x-data="{
            folder: @js($initialFolder),
            search: '',
            recipientFilter: '',
            scopeFilter: '',
            statusFilter: '',
            sortOrder: 'newest',
            selected: @js((string) $selectedThreadId),
            unreadCount: @js($unreadCount),
            readThreads: [],
            starredThreads: @js($threads->filter(fn ($thread) => filled($thread->participants->firstWhere('id', auth()->id())?->pivot?->starred_at))->pluck('id')->map(fn ($id) => (string) $id)->values()),
            archivedThreads: @js($threads->filter(fn ($thread) => filled($thread->participants->firstWhere('id', auth()->id())?->pivot?->archived_at))->pluck('id')->map(fn ($id) => (string) $id)->values()),
            composeOpen: @js($composeOpen),
            mobileView: 'list',
            draftSubject: localStorage.getItem('messageDraftSubject') || '',
            draftBody: localStorage.getItem('messageDraftBody') || '',
            draftHtml: '',
            draftId: '',
            draftSaveState: 'idle',
            draftSavedAt: '',
            draftSaveError: '',
            linkedType: '',
            composeConversationType: 'private',
            composePermissionScope: 'church',
            auditOpen: false,
            auditEvents: [],
            visibleThread(element) {
                const id = element.dataset.threadId;
                const folderMatch = (this.folder === 'inbox' && !this.archivedThreads.includes(id))
                    || (this.folder === 'sent' && element.dataset.sent === '1' && !this.archivedThreads.includes(id))
                    || (this.folder === 'unread' && element.dataset.unread === '1')
                    || (this.folder === 'group' && element.dataset.group === '1')
                    || (this.folder === 'starred' && this.starredThreads.includes(id))
                    || (this.folder === 'archived' && this.archivedThreads.includes(id))
                    || (this.folder === 'awaiting' && element.dataset.awaiting === '1')
                    || (this.folder === 'internal' && element.dataset.internal === '1')
                    || (this.folder === 'mentions' && element.dataset.mentions === '1')
                    || (this.folder === 'attachments' && element.dataset.attachments === '1');
                const recipientMatch = !this.recipientFilter || element.dataset.participants.includes(this.recipientFilter);
                const scopeMatch = !this.scopeFilter || (this.scopeFilter === 'group' ? element.dataset.group === '1' : element.dataset.group === '0');
                const statusMatch = !this.statusFilter || (this.statusFilter === 'unread' ? element.dataset.unread === '1' : element.dataset.unread === '0');
                return folderMatch && recipientMatch && scopeMatch && statusMatch && element.dataset.search.includes(this.search.toLowerCase());
            },
            saveDraft() {
                localStorage.setItem('messageDraftSubject', this.draftSubject);
                localStorage.setItem('messageDraftBody', this.draftBody);
                if (this.draftSaveState === 'saved') this.draftSaveState = 'dirty';
            },
            clearDraft() {
                localStorage.removeItem('messageDraftSubject');
                localStorage.removeItem('messageDraftBody');
            },
            recipientKinds() {
                return {
                    private: ['user'],
                    group: ['user'],
                    ministry: ['ministry'],
                    department: ['user'],
                    campus: ['campus'],
                    role: ['role'],
                    leadership: ['leadership'],
                    event: ['user'],
                    task: ['user'],
                    report: ['user'],
                    approval: ['user'],
                    announcement: ['user', 'role', 'ministry', 'campus', 'leadership'],
                }[this.composeConversationType] || ['user'];
            },
            recipientTypeVisible(kind) {
                return this.recipientKinds().includes(kind);
            },
            recommendedPermissionScope() {
                return {
                    campus: 'campus',
                    ministry: 'ministry',
                    leadership: 'leadership',
                }[this.composeConversationType] || 'church';
            },
            syncConversationType(type) {
                this.composeConversationType = type;
                this.composePermissionScope = this.recommendedPermissionScope();
                this.$nextTick(() => {
                    const recipients = document.getElementById('message-recipients');
                    Array.from(recipients?.options || []).forEach(option => {
                        if (!this.recipientTypeVisible(option.dataset.recipientKind)) option.selected = false;
                    });
                });
            },
            format(command, value = null) {
                document.execCommand(command, false, value);
                this.syncEditor(document.getElementById('message-rich-editor'));
            },
            syncEditor(editor) {
                this.draftHtml = editor?.innerHTML || '';
                this.draftBody = editor?.innerText || '';
                this.saveDraft();
            },
            insertLink() {
                const url = window.prompt('Enter an HTTPS link');
                if (url && /^https?:\/\//i.test(url)) this.format('createLink', url);
            },
            async saveRemoteDraft() {
                const form = document.getElementById('message-compose-form');
                const recipients = Array.from(form.elements.namedItem('recipients[]').selectedOptions).map(option => option.value);
                const url = this.draftId ? @js(route('messages.drafts.update', '__draft__')).replace('__draft__', this.draftId) : @js(route('messages.drafts.store'));
                this.draftSaveState = 'saving';
                this.draftSaveError = '';

                try {
                    const response = await fetch(url, {
                        method: this.draftId ? 'PUT' : 'POST',
                        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ recipients, subject: this.draftSubject, body: this.draftBody, body_html: this.draftHtml, conversation_type: form.conversation_type.value, scheduled_at: form.scheduled_at.value || null })
                    });
                    const payload = await response.json();
                    if (!response.ok) throw new Error(payload.message || 'Draft could not be saved.');
                    this.draftId = payload.id;
                    this.draftSavedAt = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    this.draftSaveState = 'saved';
                } catch (error) {
                    this.draftSaveError = error.message || 'Draft could not be saved.';
                    this.draftSaveState = 'error';
                }
            },
            loadDraft(draft) {
                this.draftId = draft.id;
                this.draftSubject = draft.subject || '';
                this.draftBody = draft.body || '';
                this.draftHtml = draft.body_html || '';
                this.draftSaveState = 'saved';
                this.draftSavedAt = 'previously';
                this.draftSaveError = '';
                this.composeOpen = true;
                this.$nextTick(() => {
                    const form = document.getElementById('message-compose-form');
                    document.getElementById('message-rich-editor').innerHTML = this.draftHtml || this.draftBody;
                    this.syncConversationType(draft.conversation_type || 'private');
                    form.scheduled_at.value = draft.scheduled_at || '';
                    this.$nextTick(() => {
                        const recipients = draft.recipients || [];
                        Array.from(form.elements.namedItem('recipients[]').options).forEach(option => {
                            option.selected = this.recipientTypeVisible(option.dataset.recipientKind) && recipients.includes(option.value);
                        });
                    });
                });
            },
            async deleteRemoteDraft(id) {
                if (!window.confirm('Delete this saved draft?')) return;
                const url = @js(route('messages.drafts.destroy', '__draft__')).replace('__draft__', id);
                const response = await fetch(url, {
                    method: 'DELETE',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                });
                if (response.ok) window.location.reload();
            },
            async performAction(url, action) {
                const response = await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ action }) });
                if (response.ok) window.location.reload();
            },
            async markAllRead() {
                const response = await fetch(@js(route('messages.read-all')), { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
                if (response.ok) { this.unreadCount = 0; document.querySelectorAll('[data-thread-id]').forEach(row => row.dataset.unread = '0'); }
            },
            async viewAudit(url) {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                this.auditEvents = (await response.json()).events || [];
                this.auditOpen = true;
            },
            async reportConversation(url) {
                const details = window.prompt('Describe the concern. This will be sent to administrators.');
                if (!details) return;
                const response = await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ reason: 'other', details }) });
                if (response.ok) window.alert('The conversation was reported for review.');
            },
            async changeParticipant(url, action, user = null, selectId = null) {
                const recipients = selectId ? [document.getElementById(selectId).value] : [];
                const response = await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ action, user, recipients }) });
                if (response.ok) window.location.reload();
            },
            async toggleState(id, state, url) {
                const collection = state === 'starred' ? this.starredThreads : this.archivedThreads;
                const enabled = !collection.includes(String(id));
                this[state === 'starred' ? 'starredThreads' : 'archivedThreads'] = enabled
                    ? [...collection, String(id)]
                    : collection.filter(item => item !== String(id));
                await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify({ state, enabled })
                });
            },
            async markUnread(id, url) {
                const row = document.querySelector(`[data-thread-id='${id}']`);
                if (row && row.dataset.unread !== '1') { row.dataset.unread = '1'; this.unreadCount += 1; this.readThreads = this.readThreads.filter(item => item !== String(id)); }
                await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ state: 'read', enabled: false }) });
            },
            selectThread(id, readUrl) {
                const row = document.querySelector(`[data-thread-id='${id}']`);
                if (row?.dataset.unread === '1') {
                    row.dataset.unread = '0';
                    this.unreadCount = Math.max(0, this.unreadCount - 1);
                    this.readThreads.push(String(id));
                    fetch(readUrl, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                        }
                    });
                }
                this.selected = String(id);
                this.composeOpen = false;
                this.mobileView = 'conversation';
                this.scrollToLatest(id, 'smooth');
            },
            scrollToLatest(id, behavior = 'auto') {
                this.$nextTick(() => {
                    const stream = document.getElementById(`message-stream-${id}`);
                    stream?.scrollTo({ top: stream.scrollHeight, behavior });
                });
            }
        }"
        x-init="scrollToLatest(selected)"
        x-on:keydown.escape.window="composeOpen = false"
        class="space-y-4"
    >
        <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-center gap-3">
                <span class="grid size-11 place-items-center rounded-xl bg-violet-100 text-violet-700 shadow-sm"><i data-lucide="mail" class="size-5"></i></span>
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-950">Message Center</h1>
                    <p class="mt-0.5 text-sm text-slate-500">Manage private conversations and internal ministry communication.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" x-on:click="markAllRead()" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-600 shadow-sm hover:text-violet-700"><i data-lucide="check-check" class="size-4"></i>Mark all read</button>
                @if($canSendMessages)
                    <button type="button" x-on:click="composeOpen = true" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-violet-700"><i data-lucide="square-pen" class="size-4"></i>New message</button>
                @endif
            </div>
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ([
                ['label' => 'Unread messages', 'value' => $unreadCount, 'icon' => 'mail', 'classes' => 'bg-violet-50 text-violet-700', 'live' => true],
                ['label' => 'Open conversations', 'value' => $stats['active'], 'icon' => 'messages-square', 'classes' => 'bg-blue-50 text-blue-700'],
                ['label' => 'Draft messages', 'value' => $stats['drafts'], 'icon' => 'file-text', 'classes' => 'bg-orange-50 text-orange-700'],
                ['label' => 'Average response', 'value' => $stats['response_minutes'], 'suffix' => 'm', 'icon' => 'clock-3', 'classes' => 'bg-emerald-50 text-emerald-700'],
            ] as $metric)
                <section class="flex min-h-24 items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl {{ $metric['classes'] }}"><i data-lucide="{{ $metric['icon'] }}" class="size-5"></i></span>
                    <div><p class="text-xs font-medium text-slate-500">{{ $metric['label'] }}</p><p class="mt-0.5 text-xl font-extrabold text-slate-950" @if($metric['live'] ?? false) x-text="unreadCount" @endif>{{ number_format($metric['value']) }}{{ $metric['suffix'] ?? '' }}</p></div>
                </section>
            @endforeach
            <article class="flex min-h-24 items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:col-span-2 xl:col-span-1">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-700"><i data-lucide="shield-check" class="size-5"></i></span>
                <div><p class="text-xs font-medium text-slate-500">Audit & compliance</p><p class="mt-0.5 text-sm font-extrabold text-emerald-700">Active</p></div>
            </article>
        </div>

        <div class="grid gap-3 xl:grid-cols-[205px_350px_minmax(0,1fr)] 2xl:grid-cols-[190px_330px_minmax(420px,1fr)_210px]">
            <aside class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" x-bind:class="mobileView === 'conversation' ? 'hidden xl:block' : ''">
                @if($canSendMessages)
                    <div class="border-b border-slate-100 p-3">
                        <button type="button" x-on:click="composeOpen = true" class="flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-violet-700 to-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-sm hover:brightness-110"><i data-lucide="plus" class="size-4"></i>New message</button>
                    </div>
                @endif
                <nav class="p-3" aria-label="Message folders">
                    <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Folders</p>
                    <button type="button" x-on:click="folder = 'inbox'" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold" x-bind:class="folder === 'inbox' ? 'bg-violet-50 text-violet-700' : 'text-slate-600 hover:bg-slate-50'">
                        <i data-lucide="inbox" class="size-4"></i>Inbox
                        <span x-show="unreadCount > 0" x-text="unreadCount" class="ml-auto rounded-full bg-violet-600 px-2 py-0.5 text-[11px] font-bold text-white">{{ $unreadCount }}</span>
                    </button>
                    <button type="button" x-on:click="folder = 'sent'" class="mt-1 flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold" x-bind:class="folder === 'sent' ? 'bg-violet-50 text-violet-700' : 'text-slate-600 hover:bg-slate-50'">
                        <i data-lucide="send" class="size-4"></i>Sent<span class="ml-auto text-xs text-slate-400">{{ $stats['sent'] }}</span>
                    </button>
                    @if($canSendMessages)
                        <button type="button" x-on:click="composeOpen = true" class="mt-1 flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-slate-600 hover:bg-slate-50">
                            <i data-lucide="file-text" class="size-4"></i>Drafts<span x-show="draftSubject || draftBody" class="ml-auto rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-bold text-violet-700">1</span>
                        </button>
                    @endif
                    <button type="button" x-on:click="folder = 'archived'" class="mt-1 flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold" x-bind:class="folder === 'archived' ? 'bg-violet-50 text-violet-700' : 'text-slate-600 hover:bg-slate-50'">
                        <i data-lucide="archive" class="size-4"></i>Archived<span class="ml-auto text-xs text-slate-400" x-text="archivedThreads.length"></span>
                    </button>
                    <p class="mt-5 px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Smart filters</p>
                    <button type="button" x-on:click="folder = 'starred'" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold" x-bind:class="folder === 'starred' ? 'bg-violet-50 text-violet-700' : 'text-slate-600 hover:bg-slate-50'">
                        <i data-lucide="star" class="size-4 text-amber-500"></i>Starred<span class="ml-auto text-xs text-slate-400" x-text="starredThreads.length"></span>
                    </button>
                    <button type="button" x-on:click="folder = 'unread'" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold" x-bind:class="folder === 'unread' ? 'bg-violet-50 text-violet-700' : 'text-slate-600 hover:bg-slate-50'">
                        <i data-lucide="circle-dot" class="size-4"></i>Unread<span class="ml-auto text-xs text-slate-400" x-text="unreadCount">{{ $unreadCount }}</span>
                    </button>
                    <button type="button" x-on:click="folder = 'attachments'" class="mt-1 flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold" x-bind:class="folder === 'attachments' ? 'bg-violet-50 text-violet-700' : 'text-slate-600 hover:bg-slate-50'">
                        <i data-lucide="paperclip" class="size-4"></i>Attachments
                    </button>
                    <button type="button" x-on:click="folder = 'awaiting'" class="mt-1 flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold" x-bind:class="folder === 'awaiting' ? 'bg-violet-50 text-violet-700' : 'text-slate-600 hover:bg-slate-50'">
                        <i data-lucide="clock-3" class="size-4"></i>Awaiting reply
                    </button>
                    <button type="button" x-on:click="folder = 'internal'" class="mt-1 flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold" x-bind:class="folder === 'internal' ? 'bg-violet-50 text-violet-700' : 'text-slate-600 hover:bg-slate-50'">
                        <i data-lucide="lock" class="size-4"></i>Internal notes
                    </button>
                    <button type="button" x-on:click="folder = 'mentions'" class="mt-1 flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold" x-bind:class="folder === 'mentions' ? 'bg-violet-50 text-violet-700' : 'text-slate-600 hover:bg-slate-50'">
                        <i data-lucide="mail-plus" class="size-4"></i>Mentions
                    </button>
                    <button type="button" x-on:click="folder = 'group'" class="mt-1 flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-semibold" x-bind:class="folder === 'group' ? 'bg-violet-50 text-violet-700' : 'text-slate-600 hover:bg-slate-50'">
                        <i data-lucide="users-round" class="size-4"></i>Group conversations
                    </button>
                </nav>
                <div class="border-t border-slate-100 p-3">
                    <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Summary</p>
                    <div class="space-y-1 text-sm">
                        <div class="flex items-center gap-3 rounded-lg px-3 py-2 text-slate-600"><i data-lucide="messages-square" class="size-4 text-slate-400"></i>Conversations<span class="ml-auto font-bold text-slate-800">{{ $stats['conversations'] }}</span></div>
                        <div class="flex items-center gap-3 rounded-lg px-3 py-2 text-slate-600"><i data-lucide="users" class="size-4 text-slate-400"></i>Available people<span class="ml-auto font-bold text-slate-800">{{ $stats['members'] }}</span></div>
                    </div>
                </div>
                <div class="m-3 mt-1 rounded-xl border border-emerald-100 bg-emerald-50 p-3">
                    <div class="flex gap-2.5"><span class="grid size-8 shrink-0 place-items-center rounded-lg bg-white text-emerald-600 shadow-sm"><i data-lucide="shield-check" class="size-4"></i></span><div><p class="text-xs font-bold text-emerald-800">Private and audited</p><p class="mt-0.5 text-[11px] leading-4 text-emerald-700">Messages stay inside your church workspace.</p></div></div>
                </div>
            </aside>

            <section class="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" x-bind:class="mobileView === 'conversation' ? 'hidden xl:block' : ''">
                <div class="border-b border-slate-100 p-3">
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2.5 focus-within:border-violet-400 focus-within:ring-2 focus-within:ring-violet-100">
                        <i data-lucide="search" class="size-4 text-slate-400"></i>
                        <span class="sr-only">Search messages</span>
                        <input x-model="search" class="min-w-0 flex-1 border-0 p-0 text-sm shadow-none focus:ring-0" placeholder="Search messages...">
                        <button x-show="search" x-on:click="search = ''" type="button" class="text-slate-400 hover:text-slate-700" aria-label="Clear search"><i data-lucide="x" class="size-4"></i></button>
                    </label>
                    <div class="mt-2 grid grid-cols-3 gap-2">
                        <select x-model="recipientFilter" class="min-w-0 rounded-lg border border-slate-200 px-2 py-2 text-[11px] text-slate-600">
                            <option value="">All people</option>
                            @foreach ($users as $user)<option value="{{ strtolower($user->name) }}">{{ $user->name }}</option>@endforeach
                        </select>
                        <select x-model="scopeFilter" class="min-w-0 rounded-lg border border-slate-200 px-2 py-2 text-[11px] text-slate-600"><option value="">All scopes</option><option value="private">Private</option><option value="group">Group</option></select>
                        <select x-model="statusFilter" class="min-w-0 rounded-lg border border-slate-200 px-2 py-2 text-[11px] text-slate-600"><option value="">All status</option><option value="unread">Unread</option><option value="read">Read</option></select>
                    </div>
                </div>
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <p class="text-sm font-extrabold capitalize text-slate-900" x-text="folder + ' conversations'"></p>
                    <button type="button" x-on:click="sortOrder = sortOrder === 'newest' ? 'oldest' : 'newest'" class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-400 hover:text-violet-700"><span x-text="sortOrder === 'newest' ? 'Newest' : 'Oldest'"></span><i data-lucide="list-filter" class="size-3.5"></i></button>
                </div>
                <div class="grid grid-cols-3 border-b border-slate-100 px-3 pt-1">
                    <button type="button" x-on:click="scopeFilter = ''" class="border-b-2 px-2 py-2 text-[11px] font-bold" x-bind:class="scopeFilter === '' ? 'border-violet-600 text-violet-700' : 'border-transparent text-slate-400'">All</button>
                    <button type="button" x-on:click="scopeFilter = 'private'" class="border-b-2 px-2 py-2 text-[11px] font-bold" x-bind:class="scopeFilter === 'private' ? 'border-violet-600 text-violet-700' : 'border-transparent text-slate-400'">Private</button>
                    <button type="button" x-on:click="scopeFilter = 'group'" class="border-b-2 px-2 py-2 text-[11px] font-bold" x-bind:class="scopeFilter === 'group' ? 'border-violet-600 text-violet-700' : 'border-transparent text-slate-400'">Groups</button>
                </div>
                <div class="flex max-h-[620px] flex-col divide-y divide-slate-100 overflow-y-auto" x-bind:class="sortOrder === 'oldest' ? 'flex-col-reverse' : ''">
                    @forelse ($threads as $thread)
                        @php
                            $self = $thread->participants->firstWhere('id', auth()->id());
                            $lastReadAt = $self?->pivot?->last_read_at;
                            $unread = ! $lastReadAt || ($thread->last_message_at && $thread->last_message_at->gt($lastReadAt));
                            $others = $thread->participants->where('id', '!=', auth()->id());
                            $primaryPerson = $others->first() ?: $thread->creator;
                            $participantNames = $others->pluck('name')->join(', ') ?: 'Only you';
                            $sentByMe = $thread->messages->contains('sender_id', auth()->id());
                            $isGroup = $thread->participants->count() > 2;
                            $awaitingReply = $thread->latestMessage?->sender_id === auth()->id();
                            $hasInternalNote = $thread->messages->contains('is_internal_note', true);
                            $hasMention = $thread->messages->contains(fn ($message) => str_contains(strtolower($message->body), '@'.strtolower(strtok(auth()->user()->name, ' '))));
                            $attachmentCount = $thread->messages->sum(fn ($message) => $message->attachments->count());
                            $searchable = \Illuminate\Support\Str::lower(($thread->subject ?? '').' '.$participantNames.' '.($thread->latestMessage?->body ?? ''));
                        @endphp
                        <a
                            href="{{ route('messages.show', $thread) }}"
                            data-thread-id="{{ $thread->id }}"
                            data-unread="{{ $unread ? '1' : '0' }}"
                            data-sent="{{ $sentByMe ? '1' : '0' }}"
                            data-group="{{ $isGroup ? '1' : '0' }}"
                            data-awaiting="{{ $awaitingReply ? '1' : '0' }}"
                            data-internal="{{ $hasInternalNote ? '1' : '0' }}"
                            data-mentions="{{ $hasMention ? '1' : '0' }}"
                            data-attachments="{{ $attachmentCount > 0 ? '1' : '0' }}"
                            data-search="{{ e($searchable) }}"
                            data-participants="{{ e(strtolower($participantNames)) }}"
                            x-on:click.prevent="selectThread('{{ $thread->id }}', @js(route('messages.read', $thread)))"
                            x-show="visibleThread($el)"
                            class="group relative flex gap-3 px-4 py-4 transition hover:bg-slate-50"
                            x-bind:class="selected === '{{ $thread->id }}' ? 'bg-violet-50' : ''"
                        >
                            <span x-show="selected === '{{ $thread->id }}'" class="absolute inset-y-0 left-0 w-1 rounded-r-full bg-violet-600"></span>
                            <span class="relative size-11 shrink-0">
                                @if ($primaryPerson?->avatar_src)
                                    <img src="{{ $primaryPerson->avatar_src }}" alt="" class="size-11 rounded-full object-cover ring-2 ring-white">
                                @else
                                    <span class="grid size-11 place-items-center rounded-full bg-gradient-to-br from-indigo-100 to-violet-100 text-sm font-extrabold text-violet-700">{{ strtoupper(substr($primaryPerson?->name ?: 'M', 0, 1)) }}</span>
                                @endif
                                @if ($unread)<span x-show="!readThreads.includes('{{ $thread->id }}')" class="absolute -right-0.5 -top-0.5 size-3 rounded-full border-2 border-white bg-violet-600"></span>@endif
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-start gap-2"><span class="min-w-0 flex-1"><span class="block truncate text-sm {{ $unread ? 'font-extrabold text-slate-950' : 'font-bold text-slate-800' }}">{{ $thread->subject ?: 'Untitled conversation' }}</span><span class="mt-0.5 block truncate text-xs font-medium text-slate-500">{{ $participantNames }}</span></span><time class="shrink-0 text-[10px] font-semibold text-slate-400">{{ $thread->last_message_at?->isToday() ? $thread->last_message_at->format('g:i A') : $thread->last_message_at?->format('M j') }}</time></span>
                                <span class="mt-2 flex items-center gap-2"><span class="min-w-0 flex-1 truncate text-xs text-slate-500">{{ $thread->latestMessage?->body ?: 'No message preview' }}</span>@if($thread->linked_type)<span class="rounded bg-blue-50 px-1.5 py-0.5 text-[9px] font-bold capitalize text-blue-600">{{ $thread->linked_type }}</span>@endif @if($attachmentCount)<span class="inline-flex items-center gap-0.5 text-[10px] text-slate-400"><i data-lucide="paperclip" class="size-3"></i>{{ $attachmentCount }}</span>@endif</span>
                            </span>
                        </a>
                    @empty
                        <div class="grid min-h-72 place-items-center px-6 py-12 text-center"><div><span class="mx-auto grid size-14 place-items-center rounded-2xl bg-violet-50 text-violet-500"><i data-lucide="messages-square" class="size-7"></i></span><h2 class="mt-4 text-sm font-bold text-slate-800">No messages yet</h2><p class="mt-1 text-xs text-slate-500">Start a private conversation with someone on your team.</p></div></div>
                    @endforelse
                </div>
            </section>

            <section class="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" x-bind:class="mobileView === 'list' ? 'hidden xl:block' : ''">
                <button type="button" x-on:click="mobileView = 'list'" class="m-3 inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 xl:hidden"><i data-lucide="arrow-left" class="size-4"></i>Conversations</button>
                @forelse ($threads as $detailThread)
                    @php
                        $detailMembership = $detailThread->participants->firstWhere('id', auth()->id());
                        $canManageDetail = auth()->user()->isSuperAdministrator() || auth()->user()->hasPermission('administer messages') || $detailThread->created_by === auth()->id() || $detailMembership?->pivot?->participant_role === 'administrator';
                    @endphp
                    <div x-show="selected === '{{ $detailThread->id }}'" x-cloak>
                        <header class="border-b border-slate-100 px-4 py-4 sm:px-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h2 class="truncate text-lg font-extrabold text-slate-950">{{ $detailThread->subject ?: 'Untitled conversation' }}</h2>
                                        <span class="shrink-0 rounded-md bg-violet-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-violet-700">{{ $detailThread->participants->count() > 2 ? 'Group' : 'Private' }}</span>
                                    </div>
                                    <p class="mt-1 text-xs font-medium text-slate-500">{{ $detailThread->participants->count() }} participants &middot; Updated {{ $detailThread->last_message_at?->diffForHumans() }}</p>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button type="button" x-on:click="toggleState('{{ $detailThread->id }}', 'starred', @js(route('messages.state', $detailThread)))" class="grid size-9 place-items-center rounded-lg hover:bg-amber-50" x-bind:class="starredThreads.includes('{{ $detailThread->id }}') ? 'text-amber-500' : 'text-slate-400'" aria-label="Toggle starred"><i data-lucide="star" class="size-4"></i></button>
                                    <button type="button" x-on:click="toggleState('{{ $detailThread->id }}', 'archived', @js(route('messages.state', $detailThread)))" class="grid size-9 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Toggle archived"><i data-lucide="archive" class="size-4"></i></button>
                                    <details class="relative"><summary class="grid size-9 cursor-pointer list-none place-items-center rounded-lg text-slate-400 hover:bg-slate-100"><i data-lucide="ellipsis" class="size-4"></i></summary><div class="absolute right-0 z-20 mt-1 w-48 rounded-lg border border-slate-200 bg-white p-1.5 text-xs font-semibold text-slate-600 shadow-xl">
                                        <button type="button" x-on:click="markUnread('{{ $detailThread->id }}', @js(route('messages.state', $detailThread)))" class="flex w-full items-center gap-2 rounded px-2 py-2 hover:bg-slate-50"><i data-lucide="mail" class="size-3.5"></i>Mark unread</button>
                                        @if($canManageDetail)
                                        @if($detailThread->status === 'active')<button type="button" x-on:click="performAction(@js(route('messages.action', $detailThread)), 'close')" class="flex w-full items-center gap-2 rounded px-2 py-2 hover:bg-slate-50"><i data-lucide="circle-pause" class="size-3.5"></i>Close conversation</button>@else<button type="button" x-on:click="performAction(@js(route('messages.action', $detailThread)), 'reopen')" class="flex w-full items-center gap-2 rounded px-2 py-2 hover:bg-slate-50"><i data-lucide="rotate-ccw" class="size-3.5"></i>Reopen conversation</button>@endif
                                        <button type="button" x-on:click="performAction(@js(route('messages.action', $detailThread)), '{{ $detailThread->replies_restricted ? 'unrestrict' : 'restrict' }}')" class="flex w-full items-center gap-2 rounded px-2 py-2 hover:bg-slate-50"><i data-lucide="lock" class="size-3.5"></i>{{ $detailThread->replies_restricted ? 'Allow replies' : 'Restrict replies' }}</button>
                                        <button type="button" x-on:click="if(confirm('Delete this conversation?')) performAction(@js(route('messages.action', $detailThread)), 'delete')" class="flex w-full items-center gap-2 rounded px-2 py-2 text-rose-600 hover:bg-rose-50"><i data-lucide="trash-2" class="size-3.5"></i>Delete conversation</button>
                                        @endif
                                        @if(auth()->user()->isSuperAdministrator() || auth()->user()->hasPermission('export message history'))
                                        <a href="{{ route('messages.export', $detailThread) }}" class="flex items-center gap-2 rounded px-2 py-2 hover:bg-slate-50"><i data-lucide="download" class="size-3.5"></i>Export history</a>
                                        @endif
                                        @if($canManageDetail || auth()->user()->hasPermission('view audit log'))
                                        <button type="button" x-on:click="viewAudit(@js(route('messages.audit', $detailThread)))" class="flex w-full items-center gap-2 rounded px-2 py-2 hover:bg-slate-50"><i data-lucide="history" class="size-3.5"></i>View audit log</button>
                                        @endif
                                        @if($detailThread->created_by !== auth()->id())<button type="button" x-on:click="performAction(@js(route('messages.action', $detailThread)), 'leave')" class="flex w-full items-center gap-2 rounded px-2 py-2 text-rose-600 hover:bg-rose-50"><i data-lucide="log-out" class="size-3.5"></i>Leave group</button>@endif
                                        <button type="button" x-on:click="reportConversation(@js(route('messages.report', $detailThread)))" class="flex w-full items-center gap-2 rounded px-2 py-2 text-amber-700 hover:bg-amber-50"><i data-lucide="shield-alert" class="size-3.5"></i>Report concern</button>
                                    </div></details>
                                </div>
                            </div>
                            <div class="mt-3 flex items-center justify-between">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Participants</p>
                                <div class="flex -space-x-2">@foreach($detailThread->participants->take(8) as $participant)@if($participant->avatar_src)<img src="{{ $participant->avatar_src }}" alt="{{ $participant->name }}" title="{{ $participant->name }}" class="size-8 rounded-full border-2 border-white object-cover">@else<span title="{{ $participant->name }}" class="grid size-8 place-items-center rounded-full border-2 border-white bg-indigo-100 text-[10px] font-extrabold text-indigo-700">{{ strtoupper(substr($participant->name, 0, 1)) }}</span>@endif @endforeach</div>
                            </div>
                        </header>
                        <div>
                            <div class="min-w-0">
                                <div id="message-stream-{{ $detailThread->id }}" class="max-h-[520px] min-h-80 space-y-3 overflow-y-auto bg-white p-3 sm:p-4">
                                    @foreach ($detailThread->messages as $message)
                                        @php
                                            $isMine = $message->sender_id === auth()->id();
                                            $senderRole = $message->sender->roles->first()?->name ?? $message->sender->title;
                                        @endphp
                                        <details @if($loop->last) open @endif class="group overflow-hidden rounded-xl border shadow-sm transition {{ $message->is_internal_note ? 'border-amber-200 bg-amber-50/70' : 'border-slate-200 bg-white open:border-violet-200' }}">
                                            <summary class="flex cursor-pointer list-none items-start gap-3 px-3.5 py-3.5 hover:bg-slate-50/80 [&::-webkit-details-marker]:hidden">
                                                @if ($message->sender->avatar_src)
                                                    <img src="{{ $message->sender->avatar_src }}" alt="" class="size-10 shrink-0 rounded-full object-cover ring-2 ring-slate-100">
                                                @else
                                                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-gradient-to-br from-indigo-100 to-violet-100 text-xs font-extrabold text-violet-700 ring-2 ring-slate-100">{{ strtoupper(substr($message->sender->name, 0, 1)) }}</span>
                                                @endif
                                                <span class="min-w-0 flex-1">
                                                    <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                        <span class="truncate text-xs font-extrabold text-slate-900">{{ $isMine ? 'You' : $message->sender->name }}</span>
                                                        <span class="truncate text-[10px] font-medium text-slate-400">&lt;{{ $message->sender->email }}&gt;</span>
                                                        @if($senderRole)
                                                            <span class="rounded-md bg-violet-50 px-2 py-0.5 text-[9px] font-bold text-violet-700">{{ $senderRole }}</span>
                                                        @endif
                                                        @if($message->is_internal_note)
                                                            <span class="rounded-md bg-amber-100 px-2 py-0.5 text-[9px] font-extrabold uppercase tracking-wide text-amber-700">Internal note</span>
                                                        @endif
                                                    </span>
                                                    <span class="mt-0.5 block truncate text-[10px] text-slate-400">{{ $message->is_internal_note ? 'Private note in this conversation' : 'to '.$detailThread->participants->where('id', '!=', $message->sender_id)->pluck('name')->take(3)->join(', ') }}</span>
                                                    <span class="mt-1.5 block truncate text-[11px] text-slate-500 group-open:hidden">{{ \Illuminate\Support\Str::limit(trim($message->body), 110) }}</span>
                                                </span>
                                                <span class="flex shrink-0 items-center gap-2">
                                                    @if($message->attachments->isNotEmpty())
                                                        <span class="hidden items-center gap-1 text-[9px] font-bold text-slate-400 sm:inline-flex"><i data-lucide="paperclip" class="size-3"></i>{{ $message->attachments->count() }}</span>
                                                    @endif
                                                    <time class="text-right text-[9px] font-medium leading-4 text-slate-400"><span class="block">{{ $message->created_at?->format('M j') }}</span><span>{{ $message->created_at?->format('g:i A') }}</span></time>
                                                    <i data-lucide="chevron-down" class="mt-1 size-3.5 text-slate-400 transition group-open:rotate-180"></i>
                                                </span>
                                            </summary>
                                            <div class="border-t px-3.5 pb-4 pt-3 {{ $message->is_internal_note ? 'border-amber-200' : 'border-slate-100' }}">
                                                <div class="mb-4 flex items-center gap-2 text-[10px] text-slate-400 sm:ml-[52px]">
                                                    <i data-lucide="{{ $isMine ? 'send' : 'inbox' }}" class="size-3"></i>
                                                    <span>{{ $isMine ? 'Sent from your account' : 'Received by you' }}</span>
                                                </div>
                                                <div class="ml-0 break-words text-xs leading-6 {{ $message->is_internal_note ? 'text-amber-900' : 'text-slate-700' }} sm:ml-[52px] [&_a]:font-semibold [&_a]:text-violet-700 [&_a]:underline [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5">{!! $message->body_html ?: nl2br(e($message->body)) !!}</div>
                                                @if ($message->attachments->isNotEmpty())
                                                    <div class="ml-0 mt-3 sm:ml-[52px]">
                                                        <p class="mb-2 text-[9px] font-extrabold uppercase tracking-wide text-slate-400">Attachments ({{ $message->attachments->count() }})</p>
                                                        <div class="grid gap-2 sm:grid-cols-2">
                                                        @foreach ($message->attachments as $attachment)
                                                            <a href="{{ route('messages.attachments.download', $attachment) }}" class="group flex items-center gap-2.5 rounded-lg border border-slate-200 bg-white p-2.5 text-left transition hover:border-violet-300 hover:shadow-sm">
                                                                @if($attachment->is_image)<img src="{{ route('messages.attachments.download', ['attachment' => $attachment, 'preview' => 1]) }}" alt="" class="size-10 rounded-md object-cover ring-1 ring-slate-100">@else<span class="grid size-10 place-items-center rounded-md bg-rose-50 text-rose-600"><i data-lucide="file-text" class="size-4"></i></span>@endif
                                                                <span class="min-w-0 flex-1"><span class="block truncate text-[10px] font-bold text-slate-700">{{ $attachment->original_name }}</span><span class="text-[9px] text-slate-400">{{ strtoupper(pathinfo($attachment->original_name, PATHINFO_EXTENSION)) }} &middot; {{ number_format($attachment->size / 1024, 1) }} KB</span></span>
                                                                <i data-lucide="download" class="size-3.5 shrink-0 text-slate-400 group-hover:text-violet-600"></i>
                                                            </a>
                                                        @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                               <!--@if(auth()->user()->isSuperAdministrator() || auth()->user()->hasPermission('forward messages'))
                                                    <details class="ml-0 mt-2 text-left sm:ml-[52px]"><summary class="inline-flex cursor-pointer items-center gap-1 text-[10px] font-bold text-slate-400 hover:text-violet-600"><i data-lucide="repeat-2" class="size-3"></i>Forward</summary><form method="POST" action="{{ route('messages.forward', $message) }}" class="mt-2 flex gap-2">@csrf<select name="recipients[]" required class="min-w-0 flex-1 rounded-lg border border-slate-200 px-2 py-1.5 text-[10px]">@foreach($users as $user)<option value="user:{{ $user->opaqueId() }}">{{ $user->name }}</option>@endforeach</select><button class="rounded-lg bg-violet-600 px-2.5 py-1.5 text-[10px] font-bold text-white">Forward</button></form></details>
                                                @endif-->
                                            </div>
                                        </details>
                                    @endforeach
                                </div>
                                @if($detailThread->status === 'active' && (! $detailThread->replies_restricted || $canManageDetail))
                                    <form method="POST" action="{{ route('messages.reply', $detailThread) }}" enctype="multipart/form-data" class="border-t border-slate-100 bg-slate-50/50 p-3 sm:p-4" x-data="messageReply" x-on:submit="releaseAttachmentUrls()">
                                        @csrf
                                        <input type="hidden" name="body" x-bind:value="plain">
                                        <input type="hidden" name="body_html" x-bind:value="html">
                                        <div class="overflow-hidden rounded-xl border border-violet-300 bg-white shadow-sm ring-1 ring-violet-100 focus-within:border-violet-500 focus-within:ring-2 focus-within:ring-violet-200">
                                            <div class="flex flex-wrap gap-1 border-b border-slate-100 px-2.5 py-2">
                                                <button type="button" x-on:mousedown.prevent="format('bold')" class="grid size-8 place-items-center rounded-md text-slate-600 hover:bg-violet-50 hover:text-violet-700" aria-label="Bold"><i data-lucide="bold" class="size-3.5"></i></button>
                                                <button type="button" x-on:mousedown.prevent="format('italic')" class="grid size-8 place-items-center rounded-md text-slate-600 hover:bg-violet-50 hover:text-violet-700" aria-label="Italic"><i data-lucide="italic" class="size-3.5"></i></button>
                                                <button type="button" x-on:mousedown.prevent="format('underline')" class="grid size-8 place-items-center rounded-md text-slate-600 hover:bg-violet-50 hover:text-violet-700" aria-label="Underline"><i data-lucide="underline" class="size-3.5"></i></button>
                                                <span class="mx-1 my-1 w-px bg-slate-200"></span>
                                                <button type="button" x-on:mousedown.prevent="format('insertUnorderedList')" class="grid size-8 place-items-center rounded-md text-slate-600 hover:bg-violet-50 hover:text-violet-700" aria-label="Bullet list"><i data-lucide="list" class="size-3.5"></i></button>
                                                <button type="button" x-on:mousedown.prevent="format('insertOrderedList')" class="grid size-8 place-items-center rounded-md text-slate-600 hover:bg-violet-50 hover:text-violet-700" aria-label="Numbered list"><i data-lucide="list-ordered" class="size-3.5"></i></button>
                                                <button type="button" x-on:mousedown.prevent="insertLink()" class="grid size-8 place-items-center rounded-md text-slate-600 hover:bg-violet-50 hover:text-violet-700" aria-label="Insert link"><i data-lucide="link" class="size-3.5"></i></button>
                                                <button type="button" x-on:mousedown.prevent="format('insertText', ':)')" class="grid size-8 place-items-center rounded-md text-xs font-bold text-slate-600 hover:bg-violet-50 hover:text-violet-700" aria-label="Add smile">:)</button>
                                             </div>
                                             <div x-ref="editor" contenteditable="true" role="textbox" aria-label="Message reply" x-on:input="syncMessage()" x-on:keydown="handleEditorKeydown($event)" class="min-h-28 px-4 py-3 text-xs leading-5 text-slate-700 outline-none empty:before:text-slate-400 empty:before:content-['Type_your_message...']"></div>
                                             <div x-show="attachments.length" x-cloak class="border-t border-slate-100 bg-slate-50/80 p-2.5">
                                                 <p class="mb-2 flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-wide text-slate-400"><i data-lucide="paperclip" class="size-3"></i>Ready to send</p>
                                                 <div class="grid gap-2 sm:grid-cols-2">
                                                     <template x-for="attachment in attachments" x-bind:key="attachment.index + attachment.name">
                                                         <div class="group flex min-w-0 items-center gap-2 rounded-lg border border-slate-200 bg-white p-2 shadow-sm">
                                                             <template x-if="attachment.image"><img x-bind:src="attachment.previewUrl" alt="" class="size-10 shrink-0 rounded-md object-cover ring-1 ring-slate-200"></template>
                                                             <template x-if="!attachment.image"><span class="grid size-10 shrink-0 place-items-center rounded-md bg-violet-50 text-[9px] font-black text-violet-700 ring-1 ring-violet-100" x-text="attachment.extension"></span></template>
                                                             <span class="min-w-0 flex-1"><span class="block truncate text-[10px] font-bold text-slate-700" x-text="attachment.name"></span><span class="mt-0.5 block text-[9px] text-slate-400" x-text="attachment.size"></span></span>
                                                             <button type="button" x-on:click="removeAttachment($refs.attachments, attachment.index)" class="grid size-7 shrink-0 place-items-center rounded-md text-base font-bold text-slate-400 hover:bg-rose-50 hover:text-rose-600" aria-label="Remove attachment">&times;</button>
                                                         </div>
                                                     </template>
                                                 </div>
                                             </div>
                                             <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-3 py-2.5">
                                                 <div class="flex flex-wrap items-center gap-3">
                                                      <label class="cursor-pointer rounded-md p-1.5 text-violet-600 hover:bg-violet-50" title="Attach files"><i data-lucide="paperclip" class="size-4"></i><input x-ref="attachments" type="file" name="attachments[]" multiple class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.png,.jpg,.jpeg,.webp,.gif" x-on:change="updateAttachments($el)"></label>
                                                      <span x-show="attachments.length" class="text-[10px] font-bold text-violet-600" x-text="attachments.length + (attachments.length === 1 ? ' file' : ' files')"></span>
                                                      <label class="inline-flex cursor-pointer items-center gap-2 text-[10px] font-semibold text-slate-500" title="When enabled, use Shift+Enter for a new line">
                                                          <input type="checkbox" x-model="sendOnEnter" x-on:change="saveSendPreference()" class="size-3.5 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                                                          Enter sends
                                                      </label>
                                                  </div>
                                                 <div class="flex items-center gap-3">
                                                     <label class="inline-flex cursor-pointer items-center gap-2 text-[10px] font-semibold text-slate-500">
                                                         <i data-lucide="lock-keyhole" class="size-3.5"></i>Internal note
                                                         <input type="checkbox" name="is_internal_note" value="1" class="peer sr-only">
                                                         <span class="relative h-5 w-9 rounded-full bg-slate-200 transition peer-checked:bg-violet-600 after:absolute after:left-0.5 after:top-0.5 after:size-4 after:rounded-full after:bg-white after:shadow-sm after:transition peer-checked:after:translate-x-4"></span>
                                                     </label>
                                                     <button class="inline-flex h-9 items-center overflow-hidden rounded-lg bg-gradient-to-r from-violet-700 to-indigo-600 text-xs font-bold text-white shadow-sm hover:brightness-110"><span class="inline-flex h-full items-center gap-2 px-4"><i data-lucide="send" class="size-3.5"></i>Send</span><span class="grid h-full w-8 place-items-center border-l border-white/20"><i data-lucide="chevron-down" class="size-3"></i></span></button>
                                                 </div>
                                            </div>
                                        </div>
                                    </form>
                                @else
                                    <div class="border-t border-slate-100 bg-slate-50 p-4 text-center text-xs font-semibold text-slate-500">{{ $detailThread->replies_restricted ? 'Replies are restricted to conversation administrators.' : 'This conversation is '.$detailThread->status.'. Reopen it to send replies.' }}</div>
                                @endif
                            </div>
                            <aside class="hidden">
                                <h3 class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Participants</h3>
                                <div class="mt-3 space-y-3">
                                    @foreach ($detailThread->participants as $participant)
                                        <div class="flex items-center gap-2.5">
                                            @if ($participant->avatar_src)<img src="{{ $participant->avatar_src }}" alt="" class="size-8 rounded-full object-cover">@else<span class="grid size-8 place-items-center rounded-full bg-indigo-100 text-[10px] font-extrabold text-indigo-700">{{ strtoupper(substr($participant->name, 0, 1)) }}</span>@endif
                                            <div class="min-w-0"><p class="truncate text-xs font-bold text-slate-800">{{ $participant->name }}</p><p class="truncate text-[10px] text-slate-400">{{ $participant->title ?: 'Team member' }}</p></div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="my-4 border-t border-slate-100"></div>
                                <h3 class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Permission & audit</h3>
                                <div class="mt-3 space-y-2 text-[11px] text-slate-600"><p class="flex items-center gap-2"><i data-lucide="circle-check" class="size-4 text-emerald-500"></i>Permission-based</p><p class="flex items-center gap-2"><i data-lucide="circle-check" class="size-4 text-emerald-500"></i>Activity recorded</p></div>
                                <div class="my-4 border-t border-slate-100"></div>
                                <dl class="space-y-2 text-[10px]"><div class="flex justify-between"><dt class="text-slate-400">Conversation</dt><dd class="font-bold text-slate-700">#{{ str_pad((string) $detailThread->id, 6, '0', STR_PAD_LEFT) }}</dd></div><div class="flex justify-between"><dt class="text-slate-400">Created</dt><dd class="font-bold text-slate-700">{{ $detailThread->created_at?->format('M j, Y') }}</dd></div><div class="flex justify-between"><dt class="text-slate-400">Status</dt><dd class="font-bold text-emerald-600">Active</dd></div></dl>
                            </aside>
                        </div>
                    </div>
                @empty
                    <div class="grid min-h-[560px] place-items-center p-8 text-center"><div class="max-w-sm"><span class="mx-auto grid size-20 place-items-center rounded-[1.75rem] bg-gradient-to-br from-violet-100 via-white to-sky-100 text-violet-600 shadow-inner ring-1 ring-violet-100"><i data-lucide="message-square-text" class="size-9"></i></span><h2 class="mt-5 text-xl font-extrabold text-slate-950">Your ministry conversations</h2><p class="mt-2 text-sm leading-6 text-slate-500">Start a secure conversation with someone on your team.</p></div></div>
                @endforelse
            </section>

            <aside class="hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm 2xl:block">
                @foreach ($threads as $infoThread)
                    @php
                        $infoMembership = $infoThread->participants->firstWhere('id', auth()->id());
                        $canManageInfo = auth()->user()->isSuperAdministrator() || auth()->user()->hasPermission('administer messages') || $infoThread->created_by === auth()->id() || $infoMembership?->pivot?->participant_role === 'administrator';
                    @endphp
                    <div x-show="selected === '{{ $infoThread->id }}'" x-cloak class="divide-y divide-slate-100">
                        @if($infoThread->linked_type)
                            <section class="p-4">
                                <div class="flex items-center justify-between"><h3 class="text-xs font-extrabold text-slate-900">Linked record</h3><span class="rounded bg-violet-50 px-2 py-1 text-[9px] font-bold capitalize text-violet-700">{{ $infoThread->linked_type }}</span></div>
                                <p class="mt-3 text-xs font-bold text-slate-700">{{ $infoThread->linked_label }}</p>
                                @if($infoThread->linked_type === 'report')<a href="{{ route('leadership-reports.show', \App\Support\OpaqueId::encode($infoThread->linked_id, \App\Models\LeadershipReport::class)) }}" class="mt-3 inline-flex items-center gap-1 text-[10px] font-bold text-violet-700"><i data-lucide="external-link" class="size-3"></i>Open record</a>@elseif($infoThread->linked_type === 'event')<a href="{{ route('events.index') }}" class="mt-3 inline-flex items-center gap-1 text-[10px] font-bold text-violet-700"><i data-lucide="external-link" class="size-3"></i>Open events</a>@elseif($infoThread->linked_type === 'approval')<a href="{{ route('workflows.index') }}" class="mt-3 inline-flex items-center gap-1 text-[10px] font-bold text-violet-700"><i data-lucide="external-link" class="size-3"></i>Open approvals</a>@endif
                            </section>
                        @endif
                        <section class="p-4">
                            <div class="flex items-center justify-between"><h3 class="text-xs font-extrabold text-slate-900">Conversation details</h3><span class="rounded-md bg-violet-50 px-2 py-1 text-[9px] font-bold text-violet-700">{{ $infoThread->participants->count() > 2 ? 'Group' : 'Private' }}</span></div>
                            <p class="mt-3 text-sm font-extrabold text-slate-900">{{ $infoThread->subject ?: 'Untitled conversation' }}</p>
                            <p class="mt-1 text-[10px] leading-4 text-slate-500">Created {{ $infoThread->created_at?->format('M j, Y') }}</p>
                        </section>
                        <section class="p-4">
                            <h3 class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Recipients breakdown</h3>
                            <div class="mt-3 flex items-center gap-2 text-xs text-slate-600"><i data-lucide="users" class="size-4 text-violet-500"></i>Users<strong class="ml-auto text-slate-900">{{ $infoThread->participants->count() }}</strong></div>
                            @foreach($infoThread->recipients->groupBy('recipient_type') as $recipientType => $recipientGroup)<div class="mt-2 flex items-center gap-2 text-[10px] capitalize text-slate-500"><i data-lucide="circle-dot" class="size-3 text-violet-400"></i>{{ $recipientType }} selections<strong class="ml-auto text-slate-700">{{ $recipientGroup->count() }}</strong></div>@endforeach
                            <div class="mt-3 space-y-2.5">
                                @foreach ($infoThread->participants as $participant)
                                    <div class="flex items-center gap-2">
                                        @if($participant->avatar_src)<img src="{{ $participant->avatar_src }}" alt="" class="size-7 rounded-full object-cover">@else<span class="grid size-7 place-items-center rounded-full bg-indigo-100 text-[9px] font-extrabold text-indigo-700">{{ strtoupper(substr($participant->name, 0, 1)) }}</span>@endif
                                        <div class="min-w-0 flex-1"><p class="truncate text-[11px] font-bold text-slate-700">{{ $participant->name }}</p><p class="truncate text-[9px] text-slate-400">{{ $participant->pivot->participant_role }} &middot; {{ $participant->roles->pluck('name')->join(', ') ?: ($participant->title ?: 'Team member') }}</p></div>
                                        @if($canManageInfo && $participant->id !== $infoThread->created_by)<details class="relative"><summary class="cursor-pointer list-none text-slate-400"><i data-lucide="ellipsis" class="size-3"></i></summary><div class="absolute right-0 z-20 mt-1 w-28 rounded border border-slate-200 bg-white p-1 text-[9px] shadow-lg"><button type="button" x-on:click="changeParticipant(@js(route('messages.participants', $infoThread)), '{{ $participant->pivot->participant_role === 'administrator' ? 'demote' : 'promote' }}', @js($participant->opaqueId()))" class="block w-full rounded px-2 py-1.5 text-left hover:bg-slate-50">{{ $participant->pivot->participant_role === 'administrator' ? 'Make member' : 'Make admin' }}</button><button type="button" x-on:click="changeParticipant(@js(route('messages.participants', $infoThread)), 'remove', @js($participant->opaqueId()))" class="block w-full rounded px-2 py-1.5 text-left text-rose-600 hover:bg-rose-50">Remove</button></div></details>@endif
                                    </div>
                                @endforeach
                            </div>
                            @if($canManageInfo)
                                <div class="mt-3 flex gap-1"><select id="add-participant-{{ $infoThread->id }}" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-2 py-1.5 text-[9px]">@foreach($users as $user)<option value="user:{{ $user->opaqueId() }}">{{ $user->name }}</option>@endforeach</select><button type="button" x-on:click="changeParticipant(@js(route('messages.participants', $infoThread)), 'add', null, 'add-participant-{{ $infoThread->id }}')" class="rounded-lg bg-violet-600 px-2 text-[9px] font-bold text-white">Add</button></div>
                            @endif
                        </section>
                        <section class="p-4">
                            <h3 class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Permission & audit</h3>
                            <div class="mt-3 space-y-2 text-[11px] text-slate-600"><p class="flex items-center gap-2"><i data-lucide="circle-check" class="size-4 text-emerald-500"></i>Permission-based messaging</p><p class="flex items-center gap-2"><i data-lucide="circle-check" class="size-4 text-emerald-500"></i>Audit activity enabled</p></div>
                        </section>
                        <section class="p-4">
                            <h3 class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Conversation info</h3>
                            <dl class="mt-3 space-y-2 text-[10px]"><div class="flex justify-between gap-2"><dt class="text-slate-400">ID</dt><dd class="font-bold text-slate-700">MSG-{{ str_pad((string) $infoThread->id, 6, '0', STR_PAD_LEFT) }}</dd></div><div class="flex justify-between gap-2"><dt class="text-slate-400">Created by</dt><dd class="truncate font-bold text-slate-700">{{ $infoThread->creator->name }}</dd></div><div class="flex justify-between gap-2"><dt class="text-slate-400">Type</dt><dd class="font-bold capitalize text-slate-700">{{ $infoThread->type }}</dd></div><div class="flex justify-between gap-2"><dt class="text-slate-400">Messages</dt><dd class="font-bold text-slate-700">{{ $infoThread->messages_count }}</dd></div><div class="flex justify-between gap-2"><dt class="text-slate-400">Last activity</dt><dd class="font-bold text-slate-700">{{ $infoThread->last_message_at?->diffForHumans() }}</dd></div><div class="flex justify-between gap-2"><dt class="text-slate-400">Retention</dt><dd class="font-bold text-slate-700">{{ $infoThread->retention_until?->format('M j, Y') ?: 'Policy default' }}</dd></div><div class="flex justify-between gap-2"><dt class="text-slate-400">Scope</dt><dd class="font-bold capitalize text-slate-700">{{ $infoThread->permission_scope }}</dd></div><div class="flex justify-between gap-2"><dt class="text-slate-400">Status</dt><dd class="font-bold capitalize text-emerald-600">{{ $infoThread->status }}</dd></div></dl>
                        </section>
                    </div>
                @endforeach
            </aside>
        </div>

        @if($canSendMessages)
        <div x-cloak x-show="composeOpen" x-transition.opacity class="fixed inset-0 z-50 grid place-items-center bg-slate-950/55 p-4" x-on:click.self="composeOpen = false">
            <section x-show="composeOpen" x-transition class="max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="text-lg font-extrabold text-slate-950">New message</h2><p class="text-xs text-slate-500">Start a private team conversation.</p></div><button type="button" x-on:click="composeOpen = false" class="grid size-9 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700"><i data-lucide="x" class="size-5"></i></button></header>
                <form id="message-compose-form" method="POST" action="{{ route('messages.store') }}" enctype="multipart/form-data" x-data="messageAttachments" x-on:submit="releaseAttachmentUrls(); clearDraft()">
                    @csrf
                    <input type="hidden" name="draft_id" x-bind:value="draftId">
                    <div class="grid lg:grid-cols-[minmax(0,1fr)_250px]">
                        <div class="space-y-4 p-5">
                            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Conversation type
                                    <select name="conversation_type" x-model="composeConversationType" x-on:change="syncConversationType($el.value)" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm">
                                        <option value="private">Private</option><option value="group">Group</option><option value="ministry">Ministry</option><option value="department">Department</option><option value="campus">Campus</option><option value="role">Role based</option><option value="leadership">Leadership</option><option value="event">Event related</option><option value="task">Task related</option><option value="report">Report related</option><option value="approval">Approval related</option><option value="announcement">Announcement</option>
                                    </select>
                                    </label>
                                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Permission scope
                                        <select name="permission_scope" x-model="composePermissionScope" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"><option value="church">Church</option><option value="campus">Campus</option><option value="ministry">Ministry</option><option value="leadership">Leadership</option><option value="restricted">Restricted</option></select>
                                    </label>
                                </div>
                                <p class="mt-2 flex items-center gap-1.5 text-[11px] text-slate-500"><i data-lucide="shield-check" class="size-3.5 text-emerald-500"></i>The recommended scope is selected automatically. Choose Restricted only for sensitive recipients.</p>
                            </div>
                            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500"><span x-text="'Recipients for ' + composeConversationType.replaceAll('_', ' ')"></span>
                                <select id="message-recipients" name="recipients[]" multiple required class="mt-2 min-h-44 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                    <optgroup label="Individual users" x-show="recipientTypeVisible('user')" x-bind:disabled="!recipientTypeVisible('user')">@foreach($users as $user)<option data-recipient-kind="user" value="user:{{ $user->opaqueId() }}">{{ $user->name }}{{ $user->title ? ' - '.$user->title : '' }}</option>@endforeach</optgroup>
                                    @if($roles->isNotEmpty())<optgroup label="Roles" x-show="recipientTypeVisible('role')" x-bind:disabled="!recipientTypeVisible('role')">@foreach($roles as $role)<option data-recipient-kind="role" value="role:{{ $role->opaqueId() }}">Role: {{ $role->name }}</option>@endforeach</optgroup>@endif
                                    @if($ministries->isNotEmpty())<optgroup label="Ministries" x-show="recipientTypeVisible('ministry')" x-bind:disabled="!recipientTypeVisible('ministry')">@foreach($ministries as $ministry)<option data-recipient-kind="ministry" value="ministry:{{ $ministry->opaqueId() }}">Ministry: {{ $ministry->name }}</option>@endforeach</optgroup>@endif
                                    @if($campuses->isNotEmpty())<optgroup label="Campuses" x-show="recipientTypeVisible('campus')" x-bind:disabled="!recipientTypeVisible('campus')">@foreach($campuses as $campus)<option data-recipient-kind="campus" value="campus:{{ $campus->opaqueId() }}">Campus: {{ $campus->name }}</option>@endforeach</optgroup>@endif
                                    <optgroup label="Leadership" x-show="recipientTypeVisible('leadership')" x-bind:disabled="!recipientTypeVisible('leadership')"><option data-recipient-kind="leadership" value="leadership:all">Leadership group</option></optgroup>
                                </select>
                                <span class="mt-1.5 block text-[11px] font-normal normal-case tracking-normal text-slate-400">Recipient choices are filtered by conversation type and remain limited to your church.</span>
                            </label>
                            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Subject<input name="subject" x-model="draftSubject" x-on:input="saveDraft()" maxlength="160" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" placeholder="What is this conversation about?"></label>
                            <div>
                                <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Message</span>
                                <input type="hidden" name="body" x-bind:value="draftBody">
                                <input type="hidden" name="body_html" x-bind:value="draftHtml">
                                <div class="mt-2 overflow-hidden rounded-xl border border-slate-200 focus-within:border-violet-400 focus-within:ring-2 focus-within:ring-violet-100">
                                    <div class="flex flex-wrap gap-1 border-b border-slate-100 px-2 py-1.5">
                                        <button type="button" x-on:mousedown.prevent="format('bold')" class="grid size-8 place-items-center rounded hover:bg-slate-100"><i data-lucide="bold" class="size-4"></i></button>
                                        <button type="button" x-on:mousedown.prevent="format('italic')" class="grid size-8 place-items-center rounded hover:bg-slate-100"><i data-lucide="italic" class="size-4"></i></button>
                                        <button type="button" x-on:mousedown.prevent="format('underline')" class="grid size-8 place-items-center rounded hover:bg-slate-100"><i data-lucide="underline" class="size-4"></i></button>
                                        <button type="button" x-on:mousedown.prevent="format('insertUnorderedList')" class="grid size-8 place-items-center rounded hover:bg-slate-100"><i data-lucide="list" class="size-4"></i></button>
                                        <button type="button" x-on:mousedown.prevent="format('insertOrderedList')" class="grid size-8 place-items-center rounded hover:bg-slate-100"><i data-lucide="list-ordered" class="size-4"></i></button>
                                        <button type="button" x-on:mousedown.prevent="insertLink()" class="grid size-8 place-items-center rounded hover:bg-slate-100"><i data-lucide="link" class="size-4"></i></button>
                                        <button type="button" x-on:mousedown.prevent="format('insertText', ':)')" class="grid size-8 place-items-center rounded text-xs font-bold hover:bg-slate-100" aria-label="Add smile">:)</button>
                                        <span class="ml-auto self-center text-[10px] text-slate-400">Type @name to mention a user</span>
                                    </div>
                                    <div id="message-rich-editor" contenteditable="true" role="textbox" x-on:input="syncEditor($el)" class="min-h-44 px-4 py-3 text-sm leading-6 outline-none empty:before:text-slate-400 empty:before:content-['Write_your_message...']"></div>
                                </div>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Linked record
                                    <select name="linked_type" x-model="linkedType" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><option value="">None</option><option value="event">Event</option><option value="report">Leadership report</option><option value="approval">Workflow approval</option></select>
                                </label>
                                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Record
                                    @foreach($linkableRecords as $recordType => $records)
                                        <select name="linked_id" x-show="linkedType === '{{ $recordType }}'" x-bind:disabled="linkedType !== '{{ $recordType }}'" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><option value="">Select {{ $recordType }}</option>@foreach($records as $record)<option value="{{ $record['id'] }}">{{ $record['label'] }}</option>@endforeach</select>
                                    @endforeach
                                    <span x-show="!linkedType" class="mt-2 block rounded-xl border border-dashed border-slate-200 px-3 py-2.5 text-sm font-normal normal-case text-slate-400">Choose a record type first</span>
                                </label>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Attachments<input x-ref="attachments" type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.png,.jpg,.jpeg,.webp,.gif" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-violet-50 file:px-3 file:py-2 file:font-bold file:text-violet-700" x-on:change="updateAttachments($el)"></label>
                                 <label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Schedule delivery<input type="datetime-local" name="scheduled_at" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm"></label>
                                <div x-show="attachments.length" x-cloak class="rounded-xl border border-violet-100 bg-violet-50/40 p-3 sm:col-span-2">
                                    <div class="mb-2 flex items-center justify-between gap-3"><p class="text-[10px] font-extrabold uppercase tracking-wide text-violet-700">Attachment preview</p><span class="text-[10px] font-semibold text-slate-400" x-text="attachments.length + (attachments.length === 1 ? ' file selected' : ' files selected')"></span></div>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <template x-for="attachment in attachments" x-bind:key="attachment.index + attachment.name">
                                            <div class="flex min-w-0 items-center gap-3 rounded-xl border border-white bg-white p-2.5 shadow-sm">
                                                <template x-if="attachment.image"><img x-bind:src="attachment.previewUrl" alt="" class="size-12 shrink-0 rounded-lg object-cover ring-1 ring-slate-200"></template>
                                                <template x-if="!attachment.image"><span class="grid size-12 shrink-0 place-items-center rounded-lg bg-slate-100 text-[10px] font-black text-slate-600" x-text="attachment.extension"></span></template>
                                                <span class="min-w-0 flex-1"><span class="block truncate text-xs font-bold text-slate-700" x-text="attachment.name"></span><span class="mt-1 block text-[10px] text-slate-400" x-text="attachment.size"></span></span>
                                                <button type="button" x-on:click="removeAttachment($refs.attachments, attachment.index)" class="grid size-8 shrink-0 place-items-center rounded-lg text-lg font-bold text-slate-400 hover:bg-rose-50 hover:text-rose-600" aria-label="Remove attachment">&times;</button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                             </div>
                        </div>
                        <aside class="border-t border-slate-100 bg-slate-50 p-4 lg:border-l lg:border-t-0">
                            <h3 class="text-xs font-extrabold uppercase tracking-wide text-slate-500">Saved drafts</h3>
                            <div class="mt-3 space-y-2">
                                @forelse($drafts as $draft)
                                    <div class="flex items-center gap-1 rounded-lg border border-slate-200 bg-white p-1 hover:border-violet-200">
                                        <button type="button" x-on:click="loadDraft(@js(['id' => $draft->opaqueId(), 'subject' => $draft->subject, 'body' => $draft->body, 'body_html' => $draft->body_html, 'recipients' => $draft->recipients, 'conversation_type' => $draft->conversation_type, 'scheduled_at' => $draft->scheduled_at?->format('Y-m-d\TH:i')]))" class="min-w-0 flex-1 p-2 text-left">
                                            <span class="block truncate text-xs font-bold text-slate-700">{{ $draft->subject ?: 'Untitled draft' }}</span>
                                            <span class="mt-1 block text-[10px] text-slate-400">{{ $draft->updated_at?->diffForHumans() }}</span>
                                        </button>
                                        <button type="button" x-on:click="deleteRemoteDraft(@js($draft->opaqueId()))" class="grid size-8 shrink-0 place-items-center rounded text-slate-400 hover:bg-rose-50 hover:text-rose-600" aria-label="Delete draft"><i data-lucide="trash-2" class="size-3.5"></i></button>
                                    </div>
                                @empty
                                    <p class="rounded-lg border border-dashed border-slate-200 p-4 text-center text-xs text-slate-400">No saved drafts</p>
                                @endforelse
                            </div>
                            <div class="mt-4 rounded-xl border border-emerald-100 bg-emerald-50 p-3 text-[10px] leading-4 text-emerald-700"><strong class="block text-xs text-emerald-800">Secure delivery</strong>Files use private storage. Rich content is sanitized before it is saved.</div>
                        </aside>
                    </div>
                    <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50 px-5 py-4">
                        <div class="min-w-0 text-xs">
                            <span x-show="draftSaveState === 'saved'" x-cloak class="inline-flex items-center gap-1.5 font-semibold text-emerald-600"><i data-lucide="circle-check" class="size-4"></i>Draft saved <span x-show="draftSavedAt" x-text="'at ' + draftSavedAt"></span></span>
                            <span x-show="draftSaveState === 'dirty'" x-cloak class="inline-flex items-center gap-1.5 font-semibold text-amber-600"><i data-lucide="circle-alert" class="size-4"></i>Unsaved changes</span>
                            <span x-show="draftSaveState === 'error'" x-cloak class="inline-flex items-center gap-1.5 font-semibold text-rose-600"><i data-lucide="circle-alert" class="size-4"></i><span x-text="draftSaveError"></span></span>
                            <span x-show="draftSaveState === 'idle'" class="inline-flex items-center gap-1.5 text-slate-500"><i data-lucide="lock-keyhole" class="size-3.5"></i>Permission-based delivery</span>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" x-on:click="saveRemoteDraft()" x-bind:disabled="draftSaveState === 'saving'" class="inline-flex min-w-28 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-600 disabled:cursor-wait disabled:opacity-70">
                                <i x-show="draftSaveState === 'saving'" data-lucide="loader-circle" class="size-4 animate-spin"></i>
                                <i x-show="draftSaveState === 'saved'" x-cloak data-lucide="circle-check" class="size-4 text-emerald-600"></i>
                                <span x-text="draftSaveState === 'saving' ? 'Saving...' : (draftSaveState === 'saved' ? 'Saved' : 'Save draft')">Save draft</span>
                            </button>
                            <button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-violet-700"><i data-lucide="send" class="size-4"></i>Send or schedule</button>
                        </div>
                    </footer>
                </form>
            </section>
        </div>
        @endif

        <div x-cloak x-show="auditOpen" x-transition.opacity class="fixed inset-0 z-50 grid place-items-center bg-slate-950/55 p-4" x-on:click.self="auditOpen = false">
            <section class="max-h-[85vh] w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="text-lg font-extrabold text-slate-950">Conversation audit log</h2><p class="text-xs text-slate-500">Immutable security and compliance history.</p></div><button type="button" x-on:click="auditOpen = false" class="grid size-9 place-items-center rounded-lg text-slate-400 hover:bg-slate-100"><i data-lucide="x" class="size-5"></i></button></header>
                <div class="max-h-[65vh] overflow-y-auto p-4">
                    <template x-for="event in auditEvents" x-bind:key="event.id"><article class="flex gap-3 border-b border-slate-100 px-2 py-3"><span class="grid size-8 shrink-0 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="history" class="size-4"></i></span><div class="min-w-0"><p class="text-xs font-bold capitalize text-slate-800" x-text="event.action.replaceAll('_', ' ')"></p><p class="mt-1 text-[10px] text-slate-400"><span x-text="event.actor?.name || 'System'"></span> &middot; <span x-text="new Date(event.created_at).toLocaleString()"></span> &middot; <span x-text="event.ip_address || 'No IP'"></span></p></div></article></template>
                    <p x-show="auditEvents.length === 0" class="py-12 text-center text-sm text-slate-400">No audit events recorded yet.</p>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
