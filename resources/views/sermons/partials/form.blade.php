@php($editing = $sermon !== null)
<form method="POST" action="{{ $editing ? route('sermons.update', $sermon) : route('sermons.store') }}" enctype="multipart/form-data" class="space-y-5">
    @csrf
    @if ($editing) @method('PUT') @endif
    <div class="grid gap-4 md:grid-cols-2">
        <label class="md:col-span-2"><span class="field-label">Title</span><input name="title" value="{{ old('title', $sermon?->title) }}" required class="field-input" placeholder="The grace to begin again"></label>
        <label><span class="field-label">Speaker</span><input name="speaker" value="{{ old('speaker', $sermon?->speaker) }}" class="field-input" placeholder="Pastor name"></label>
        <label><span class="field-label">Scripture</span><input name="scripture" value="{{ old('scripture', $sermon?->scripture) }}" class="field-input" placeholder="John 3:16"></label>
        <label><span class="field-label">Date preached</span><input type="date" name="preached_at" value="{{ old('preached_at', $sermon?->preached_at?->format('Y-m-d')) }}" class="field-input"></label>
        <label><span class="field-label">Status</span><select name="status" class="field-input"><option value="published" @selected(old('status', $sermon?->status ?? 'published') === 'published')>Published</option><option value="draft" @selected(old('status', $sermon?->status) === 'draft')>Draft</option></select></label>
        <label class="md:col-span-2"><span class="field-label">Summary</span><textarea name="summary" rows="5" class="field-input" placeholder="Short description for the website">{{ old('summary', $sermon?->summary) }}</textarea></label>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <h2 class="text-sm font-bold text-slate-900">Media</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <label><span class="field-label">Video URL <span class="font-normal text-slate-400">(optional)</span></span><input type="url" name="video_url" value="{{ old('video_url', $sermon?->video_url) }}" class="field-input" placeholder="https://youtube.com/..."></label>
            <label><span class="field-label">Upload video <span class="font-normal text-slate-400">(optional)</span></span><input type="file" name="video_file" accept="video/mp4,video/webm,video/ogg" class="field-input"></label>
            <label><span class="field-label">Audio URL <span class="font-normal text-slate-400">(optional)</span></span><input type="url" name="audio_url" value="{{ old('audio_url', $sermon?->audio_url) }}" class="field-input" placeholder="https://..."></label>
            <label><span class="field-label">Thumbnail URL <span class="font-normal text-slate-400">(optional)</span></span><input type="url" name="thumbnail_url" value="{{ old('thumbnail_url', $sermon?->thumbnail_url) }}" class="field-input" placeholder="Select from the media library or paste a URL"></label>
            <label class="md:col-span-2"><span class="field-label">Choose thumbnail from media library</span><input type="file" name="thumbnail_file" accept="image/*" class="field-input"><small class="mt-1 block text-xs text-slate-500">Click the upload field to choose an existing library image or upload a new one.</small></label>
        </div>
    </div>
    <div class="flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-5"><a href="{{ route('sermons.index') }}" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-600">Cancel</a><button class="rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white hover:bg-violet-700">{{ $editing ? 'Save changes' : 'Create sermon' }}</button></div>
</form>
