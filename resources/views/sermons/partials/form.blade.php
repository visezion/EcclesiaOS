@php($editing = $sermon !== null)
<form method="POST" action="{{ $editing ? route('sermons.update', $sermon) : route('sermons.store') }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
        <div class="space-y-6">
            <section>
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4"><span class="grid size-9 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="file-text" class="size-4"></i></span><div><h2 class="text-sm font-bold text-slate-950">Message details</h2><p class="mt-0.5 text-xs text-slate-500">The title and details shown across your sermon library.</p></div></div>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <label class="md:col-span-2"><span class="field-label">Sermon title <b>*</b></span><input name="title" value="{{ old('title', $sermon?->title) }}" required class="field-input" placeholder="The grace to begin again"><span class="field-help">Use a clear, memorable title for your congregation.</span></label>
                    <label><span class="field-label">Speaker</span><input name="speaker" value="{{ old('speaker', $sermon?->speaker) }}" class="field-input" placeholder="Pastor name"></label>
                    <label><span class="field-label">Scripture reference</span><input name="scripture" value="{{ old('scripture', $sermon?->scripture) }}" class="field-input" placeholder="John 3:16"></label>
                    <label><span class="field-label">Date preached</span><input type="date" name="preached_at" value="{{ old('preached_at', $sermon?->preached_at?->format('Y-m-d')) }}" class="field-input"></label>
                    <label><span class="field-label">Publishing status</span><select name="status" class="field-input"><option value="published" @selected(old('status', $sermon?->status ?? 'published') === 'published')>Published</option><option value="draft" @selected(old('status', $sermon?->status) === 'draft')>Draft</option></select></label>
                    <label class="md:col-span-2"><span class="field-label">Summary</span><textarea name="summary" rows="5" class="field-input resize-y" placeholder="Short description for the website">{{ old('summary', $sermon?->summary) }}</textarea><span class="field-help">A concise description helps people decide which message to watch or listen to.</span></label>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 sm:p-5">
                <div class="flex items-center gap-3 border-b border-slate-200 pb-4"><span class="grid size-9 place-items-center rounded-lg bg-white text-violet-600 shadow-sm"><i data-lucide="video" class="size-4"></i></span><div><h2 class="text-sm font-bold text-slate-950">Media & playback</h2><p class="mt-0.5 text-xs text-slate-500">Add one or more ways for people to experience this message.</p></div></div>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <label><span class="field-label">Video URL</span><input type="url" name="video_url" value="{{ old('video_url', $sermon?->video_url) }}" class="field-input" placeholder="https://..."><span class="field-help">YouTube, Vimeo, or another public video URL.</span></label>
                    <label><span class="field-label">Upload MP4 video</span><input type="file" name="video_file" accept="video/mp4,video/webm,video/ogg" class="field-input"><span class="field-help">MP4, WebM, or OGG video files.</span></label>
                    <label><span class="field-label">Audio URL</span><input type="url" name="audio_url" value="{{ old('audio_url', $sermon?->audio_url) }}" class="field-input" placeholder="https://..."><span class="field-help">Use a public audio or podcast URL.</span></label>
                    <label><span class="field-label">Thumbnail URL</span><input type="url" name="thumbnail_url" value="{{ old('thumbnail_url', $sermon?->thumbnail_url) }}" class="field-input" placeholder="https://..."><span class="field-help">Optional cover image for the library card.</span></label>
                    <label class="md:col-span-2"><span class="field-label">Upload thumbnail</span><input type="file" name="thumbnail_file" accept="image/*" class="field-input"><span class="field-help">Choose a wide, high-quality image for the sermon thumbnail.</span></label>
                </div>
            </section>
        </div>

        <aside class="space-y-4">
            <section class="rounded-2xl border border-slate-200 bg-white p-4"><div class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-lg bg-emerald-50 text-emerald-600"><i data-lucide="check-check" class="size-4"></i></span><div><h2 class="text-sm font-bold text-slate-950">Publishing checklist</h2><p class="text-xs text-slate-500">A quick review before saving.</p></div></div><ul class="mt-4 space-y-3 text-xs text-slate-600"><li class="flex gap-2"><i data-lucide="check" class="mt-0.5 size-3.5 text-emerald-600"></i><span>Add a clear sermon title.</span></li><li class="flex gap-2"><i data-lucide="check" class="mt-0.5 size-3.5 text-emerald-600"></i><span>Include the speaker and date when available.</span></li><li class="flex gap-2"><i data-lucide="check" class="mt-0.5 size-3.5 text-emerald-600"></i><span>Add a video or audio source for playback.</span></li><li class="flex gap-2"><i data-lucide="check" class="mt-0.5 size-3.5 text-emerald-600"></i><span>Use Published when the message is ready.</span></li></ul></section>
            <section class="rounded-2xl border border-violet-100 bg-violet-50/70 p-4"><div class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-lg bg-white text-violet-600 shadow-sm"><i data-lucide="youtube" class="size-4"></i></span><div><h2 class="text-sm font-bold text-slate-950">YouTube integration</h2><p class="text-xs text-slate-600">Synced content updates automatically.</p></div></div><p class="mt-4 text-xs leading-5 text-slate-600">Connect your channel from the Sermons & Media page to import regular uploads, live streams, and completed broadcasts.</p><a href="{{ route('youtube-integration.index') }}" class="mt-4 inline-flex items-center gap-1.5 text-xs font-bold text-violet-700 hover:text-violet-800">Open integration settings <i data-lucide="arrow-right" class="size-3.5"></i></a></section>
            @if ($editing && $sermon?->youtube_video_id)<section class="rounded-2xl border border-red-100 bg-red-50/70 p-4"><div class="flex items-center gap-2 text-xs font-bold text-red-700"><i data-lucide="youtube" class="size-4"></i>Imported from YouTube</div><p class="mt-2 text-[11px] leading-5 text-red-700/80">This message is linked to video ID <span class="font-mono">{{ $sermon->youtube_video_id }}</span>. Sync updates its metadata automatically.</p></section>@endif
        </aside>
    </div>

    <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs text-slate-400"><i data-lucide="lock" class="mr-1 inline size-3.5"></i>Only authorized church administrators can edit sermons.</p><div class="flex flex-wrap justify-end gap-3"><a href="{{ route('sermons.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50">Cancel</a><button class="inline-flex items-center justify-center gap-2 rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-violet-200 transition hover:bg-violet-700"><i data-lucide="save" class="size-4"></i>{{ $editing ? 'Save changes' : 'Create sermon' }}</button></div></div>
</form>

<style>.field-label{display:block;margin-bottom:.4rem;font-size:.72rem;font-weight:700;color:#334155}.field-label b{color:#8b5cf6}.field-help{display:block;margin-top:.35rem;font-size:.68rem;line-height:1.25rem;color:#94a3b8}.field-input{display:block;width:100%;border-radius:.7rem;border:1px solid #e2e8f0;background:#fff;padding:.65rem .75rem;font-size:.875rem;color:#0f172a;outline:none;transition:border-color .15s,box-shadow .15s}.field-input:focus{border-color:#8b5cf6;box-shadow:0 0 0 3px rgb(139 92 246 / .12)}textarea.field-input{min-height:7rem}</style>
