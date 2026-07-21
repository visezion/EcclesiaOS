@props(['icon' => 'inbox', 'title', 'message'])

<div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center">
    <i data-lucide="{{ $icon }}" class="mx-auto size-10 text-slate-400"></i>
    <h2 class="mt-3 text-base font-bold text-slate-900">{{ $title }}</h2>
    <p class="mt-1 text-sm text-slate-500">{{ $message }}</p>
</div>
