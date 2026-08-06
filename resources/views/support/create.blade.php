<x-app-layout title="Submit Support Ticket" :breadcrumbs="$breadcrumbs" main-class="px-4 py-5 sm:px-6 lg:px-7">
    <div
        x-data="{
            category: @js(old('category', 'bug')),
            attachments: [],
            previewAttachments(event) {
                this.setAttachments(event.target.files);
            },
            setAttachments(fileList) {
                this.attachments.forEach(item => item.preview && URL.revokeObjectURL(item.preview));
                const selectedFiles = Array.from(fileList).slice(0, 3);
                const transfer = new DataTransfer();
                selectedFiles.forEach(file => transfer.items.add(file));
                this.$refs.attachmentInput.files = transfer.files;

                this.attachments = selectedFiles.map(file => ({
                    file,
                    name: file.name,
                    size: this.formatFileSize(file.size),
                    extension: (file.name.split('.').pop() || 'FILE').toUpperCase(),
                    preview: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
                }));
            },
            removeAttachment(index) {
                const removed = this.attachments[index];
                if (removed?.preview) URL.revokeObjectURL(removed.preview);
                this.attachments.splice(index, 1);

                const transfer = new DataTransfer();
                this.attachments.forEach(item => transfer.items.add(item.file));
                this.$refs.attachmentInput.files = transfer.files;
            },
            formatFileSize(bytes) {
                if (bytes < 1024) return `${bytes} B`;
                if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
                return `${(bytes / 1048576).toFixed(1)} MB`;
            },
        }"
        class="space-y-5"
    >
        <header class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-center gap-4">
                <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-violet-50 text-violet-600">
                    <i data-lucide="list-plus" class="size-7"></i>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wide text-violet-600">Support Center</p>
                    <h1 class="text-2xl font-black text-slate-950">Submit a new ticket</h1>
                    <p class="mt-0.5 text-sm text-slate-500">Share enough detail for the support team to understand, reproduce, and track your request.</p>
                </div>
            </div>
            <a href="{{ route('support.index') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"><i data-lucide="arrow-left" class="size-4"></i>Back to tickets</a>
        </header>

        <x-support-nav />

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <div class="flex items-center gap-2 font-black"><i data-lucide="circle-alert" class="size-4"></i>Please review the highlighted information.</div>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-xs">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('support.tickets.store') }}" enctype="multipart/form-data" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
            @csrf
            <div class="space-y-5">
                <section class="dashboard-card">
                    <div class="flex items-start gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-violet-50 text-violet-600"><span class="text-sm font-black">1</span></span>
                        <div><h2 class="font-black text-slate-950">What do you need help with?</h2><p class="mt-1 text-xs text-slate-500">Choose the category that best matches your request.</p></div>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 2xl:grid-cols-3">
                        @foreach($categories as $value => $label)
                            @php($icon = match($value) { 'bug' => 'bug', 'idea' => 'lightbulb', 'feature_expansion' => 'maximize', 'new_feature' => 'sparkles', 'integration' => 'plug-zap', 'performance' => 'gauge', 'security' => 'shield-alert', 'account' => 'lock-keyhole', 'data' => 'chart-no-axes-combined', 'billing' => 'receipt', 'training' => 'graduation-cap', 'how_to' => 'circle-help', default => 'messages-square' })
                            <label class="cursor-pointer rounded-xl border p-3 transition" :class="category === '{{ $value }}' ? 'border-violet-600 bg-violet-50 ring-2 ring-violet-100' : 'border-slate-200 hover:border-violet-200'">
                                <input type="radio" name="category" value="{{ $value }}" x-model="category" class="sr-only">
                                <span class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-lg bg-white text-violet-600 shadow-sm"><i data-lucide="{{ $icon }}" class="size-4"></i></span><span class="text-xs font-black text-slate-800">{{ $label }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="flex items-start gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-violet-50 text-violet-600"><span class="text-sm font-black">2</span></span>
                        <div><h2 class="font-black text-slate-950">Describe the request</h2><p class="mt-1 text-xs text-slate-500">Specific details help us respond faster.</p></div>
                    </div>
                    <div class="mt-4 space-y-4">
                        <label class="block text-sm font-bold text-slate-700">Subject
                            <input name="subject" value="{{ old('subject') }}" required maxlength="180" class="mt-1.5 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm outline-none focus:border-violet-400 focus:ring-4 focus:ring-violet-100" placeholder="Short, clear summary">
                        </label>
                        <label class="block text-sm font-bold text-slate-700">Description
                            <textarea name="description" required rows="6" class="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm leading-6 outline-none focus:border-violet-400 focus:ring-4 focus:ring-violet-100" placeholder="For a bug: what happened, steps to reproduce it, and any error shown. For an idea: explain the need and who will benefit.">{{ old('description') }}</textarea>
                            <span class="mt-1 block text-[11px] font-normal text-slate-500">Do not include passwords, API keys, payment credentials, or other secrets.</span>
                        </label>
                        <label class="block text-sm font-bold text-slate-700">Expected outcome <span class="font-normal text-slate-400">(optional)</span>
                            <textarea name="expected_outcome" rows="3" class="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-3 text-sm leading-6 outline-none focus:border-violet-400 focus:ring-4 focus:ring-violet-100" placeholder="What should happen, or what value should the new function add?">{{ old('expected_outcome') }}</textarea>
                        </label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block text-sm font-bold text-slate-700">Affected page URL <span class="font-normal text-slate-400">(optional)</span>
                                <input name="page_url" value="{{ old('page_url') }}" type="url" class="mt-1.5 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm" placeholder="https://...">
                            </label>
                            <label class="block text-sm font-bold text-slate-700">Browser/device <span class="font-normal text-slate-400">(optional)</span>
                                <input name="browser" value="{{ old('browser') }}" maxlength="255" class="mt-1.5 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm" placeholder="Chrome on Windows, iPhone...">
                            </label>
                        </div>
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="flex items-start gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-violet-50 text-violet-600"><span class="text-sm font-black">3</span></span>
                        <div><h2 class="font-black text-slate-950">Priority and evidence</h2><p class="mt-1 text-xs text-slate-500">Attach screenshots, PDFs, logs, text, or CSV files when helpful.</p></div>
                    </div>
                    <div class="mt-4 grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)]">
                        <label class="block text-sm font-bold text-slate-700">Priority
                            <select name="priority" class="mt-1.5 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm">
                                @foreach($priorities as $value => $label)<option value="{{ $value }}" @selected(old('priority', 'normal') === $value)>{{ $label }}</option>@endforeach
                            </select>
                            <span class="mt-1 block text-[10px] font-normal leading-4 text-slate-500">Normal is recommended for standard requests. Use High or Urgent only when work is blocked.</span>
                        </label>
                        <div>
                            <span class="block text-sm font-bold text-slate-700">Attachments <span class="font-normal text-slate-400">(optional)</span></span>
                            <label
                                x-on:dragover.prevent
                                x-on:drop.prevent="setAttachments($event.dataTransfer.files)"
                                class="mt-1.5 flex min-h-28 cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-center transition hover:border-violet-600 hover:bg-violet-50"
                            >
                                <input
                                    x-ref="attachmentInput"
                                    x-on:change="previewAttachments($event)"
                                    type="file"
                                    name="attachments[]"
                                    multiple
                                    accept=".jpg,.jpeg,.png,.webp,.pdf,.txt,.csv,.log"
                                    class="sr-only"
                                >
                                <span class="grid size-10 place-items-center rounded-xl bg-white text-violet-600 shadow-sm">
                                    <i data-lucide="upload" class="size-5"></i>
                                </span>
                                <span class="mt-2 text-xs font-bold text-slate-700">Choose files or drag them here</span>
                                <span class="mt-1 text-[10px] text-slate-500">Images, PDF, logs, text or CSV · Up to 3 files · 10 MB each</span>
                            </label>

                            <div x-show="attachments.length" x-cloak class="mt-3 grid gap-2 sm:grid-cols-2 2xl:grid-cols-3">
                                <template x-for="(item, index) in attachments" :key="`${item.name}-${index}`">
                                    <article class="group relative flex min-w-0 items-center gap-3 overflow-hidden rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm">
                                        <div class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-lg bg-violet-50 text-violet-700">
                                            <template x-if="item.preview">
                                                <img :src="item.preview" :alt="item.name" class="size-full object-cover">
                                            </template>
                                            <template x-if="! item.preview">
                                                <span class="text-[9px] font-black tracking-wide" x-text="item.extension"></span>
                                            </template>
                                        </div>
                                        <div class="min-w-0 flex-1 pr-6">
                                            <p class="truncate text-xs font-bold text-slate-800" x-text="item.name"></p>
                                            <p class="mt-1 text-[10px] text-slate-500" x-text="item.size"></p>
                                            <span class="mt-1 inline-flex items-center gap-1 text-[9px] font-bold text-emerald-600">
                                                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                                Ready to upload
                                            </span>
                                        </div>
                                        <button
                                            type="button"
                                            x-on:click="removeAttachment(index)"
                                            class="absolute right-2 top-2 grid size-6 place-items-center rounded-full bg-slate-100 text-slate-500 transition hover:bg-rose-50 hover:text-rose-600"
                                            title="Remove attachment"
                                            aria-label="Remove attachment"
                                        >
                                            <span class="text-base leading-none">&times;</span>
                                        </button>
                                    </article>
                                </template>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <a href="{{ route('support.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-5 text-sm font-bold text-slate-600 hover:bg-slate-50">Cancel</a>
                    <button class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-violet-600 px-5 text-sm font-bold text-white transition hover:bg-violet-700">
                        <i data-lucide="send" class="size-4"></i>
                        Submit ticket
                    </button>
                </div>
            </div>

            <aside class="space-y-4 xl:sticky xl:top-24 xl:h-fit">
                <section class="dashboard-card border-t-4" style="border-top-color: var(--brand-primary);">
                    <span class="grid size-10 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="route" class="size-5"></i></span>
                    <h2 class="mt-4 font-black text-slate-950">What happens next?</h2>
                    <ol class="mt-4 space-y-4 text-xs text-slate-600">
                        @foreach(['A tracking number is created immediately.', 'Support reviews and categorizes the request.', 'You can follow progress, pending work, and replies.', 'You receive an in-app notification when it changes.'] as $index => $step)
                            <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-violet-50 text-[10px] font-black text-violet-700">{{ $index + 1 }}</span><span class="pt-1 leading-4">{{ $step }}</span></li>
                        @endforeach
                    </ol>
                </section>
                <section class="dashboard-card">
                    <h3 class="flex items-center gap-2 text-sm font-black text-slate-950"><i data-lucide="shield-check" class="size-4 text-emerald-600"></i>Private attachments</h3>
                    <p class="mt-2 text-xs leading-5 text-slate-500">Files are stored privately and can only be downloaded by people authorized to view the ticket.</p>
                </section>
            </aside>
        </form>
    </div>
</x-app-layout>
