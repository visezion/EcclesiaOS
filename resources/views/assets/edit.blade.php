<x-app-layout title="Edit Asset" :breadcrumbs="$breadcrumbs">
    <div class="mx-auto max-w-4xl space-y-5">
        <div class="flex items-center justify-between gap-3">
            <div><h1 class="text-2xl font-semibold text-slate-950">Edit Asset</h1><p class="text-sm text-slate-500">{{ $asset->name }}</p></div>
            <a href="{{ route('assets.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700"><i data-lucide="list" class="size-4"></i>Register</a>
        </div>
        @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif
        <section class="dashboard-card">
            <form method="POST" action="{{ route('assets.update', $asset) }}" class="space-y-4">
                @csrf @method('PUT')
                @include('assets.partials.asset-form')
                <div class="flex justify-between gap-2"><button form="delete-asset" class="rounded-lg border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700">Archive</button><div class="flex gap-2"><a href="{{ route('assets.index') }}" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a><button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white"><i data-lucide="save" class="size-4"></i>Save Changes</button></div></div>
            </form>
            <form id="delete-asset" method="POST" action="{{ route('assets.destroy', $asset) }}" class="hidden">@csrf @method('DELETE')</form>
        </section>
    </div>
</x-app-layout>
