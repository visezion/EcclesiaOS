<x-app-layout title="Create Sermon" :breadcrumbs="$breadcrumbs">
    <div class="mx-auto max-w-4xl space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-xs font-black uppercase tracking-[.18em] text-violet-600">Sermons & Media</p><h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Create sermon</h1><p class="mt-2 text-sm text-slate-500">Add a message to your church’s published sermon library.</p></div><a href="{{ route('sermons.index') }}" class="text-sm font-bold text-violet-700">← Back to library</a></div>
        @if ($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>@endif
        <section class="dashboard-card">@include('sermons.partials.form', ['sermon' => $sermon])</section>
    </div>
    <style>.field-label{display:block;margin-bottom:.4rem;font-size:.72rem;font-weight:700;color:#475569}.field-input{display:block;width:100%;border-radius:.7rem;border:1px solid #e2e8f0;background:#fff;padding:.65rem .75rem;font-size:.875rem;color:#0f172a;outline:none}.field-input:focus{border-color:#8b5cf6;box-shadow:0 0 0 3px rgb(139 92 246 / .12)}</style>
</x-app-layout>
