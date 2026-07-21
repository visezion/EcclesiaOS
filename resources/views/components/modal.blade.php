@props(['name'])

<div x-data="{ open: false }" x-show="open" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/50 p-4" style="display: none;">
    <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">{{ $slot }}</div>
</div>
