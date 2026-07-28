<x-app-layout title="Edit Counselling Case" :breadcrumbs="$breadcrumbs">
    <div class="mx-auto max-w-5xl space-y-5"><div class="flex items-center justify-between gap-3"><div><h1 class="text-2xl font-semibold text-slate-950">Edit Counselling Case</h1><p class="text-sm text-slate-500">{{ $case->member ? $case->member->first_name.' '.$case->member->last_name : 'Unknown member' }}</p></div><a href="{{ route('counselling.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700"><i data-lucide="list" class="size-4"></i>Cases</a></div>
        @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif
        <section class="dashboard-card"><form method="POST" action="{{ route('counselling.update', $case) }}" class="space-y-4">@csrf @method('PUT')
            @include('counselling.partials.case-form')
            <div class="flex justify-between gap-2"><button form="delete-case" class="rounded-lg border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700">Archive</button><div class="flex gap-2"><a href="{{ route('counselling.index') }}" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a><button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white"><i data-lucide="save" class="size-4"></i>Save Changes</button></div></div>
        </form><form id="delete-case" method="POST" action="{{ route('counselling.destroy', $case) }}" class="hidden">@csrf @method('DELETE')</form></section>
    </div>
</x-app-layout>
