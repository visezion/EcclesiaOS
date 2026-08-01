<x-app-layout title="Manage Reading Plans" :breadcrumbs="$breadcrumbs" main-class="px-4 py-5 sm:px-6 lg:px-7">
    <div x-data="{ createOpen: {{ $errors->any() ? 'true' : 'false' }}, editing: null }" class="space-y-5">
        @include('bible._tabs')

        <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-3">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-700"><i data-lucide="list-checks" class="size-6"></i></span>
                <div><h1 class="text-2xl font-black text-slate-950">Manage Reading Plans</h1><p class="mt-1 text-sm text-slate-500">Create complete daily schedules for your church and monitor member participation.</p></div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('bible.plans') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700"><i data-lucide="arrow-left" class="size-4"></i>Member View</a>
                <button type="button" @click="createOpen = true" class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-violet-700"><i data-lucide="plus" class="size-4"></i>New Reading Plan</button>
            </div>
        </header>

        @if(session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>@endif

        <section class="grid gap-3 sm:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="library" class="size-5"></i></span><div><p class="text-xs font-bold text-slate-500">Church Plans</p><p class="text-2xl font-black text-slate-950">{{ $plans->count() }}</p></div></div></article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-sky-50 text-sky-600"><i data-lucide="calendar-check" class="size-5"></i></span><div><p class="text-xs font-bold text-slate-500">Scheduled Days</p><p class="text-2xl font-black text-slate-950">{{ $plans->sum(fn ($plan) => $plan->days->count()) }}</p></div></div></article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-emerald-50 text-emerald-600"><i data-lucide="users" class="size-5"></i></span><div><p class="text-xs font-bold text-slate-500">Member Enrollments</p><p class="text-2xl font-black text-slate-950">{{ $plans->sum('users_count') }}</p></div></div></article>
        </section>

        <section class="space-y-3">
            @forelse($plans as $plan)
                @php($schedule = $plan->days->map(fn ($day) => $day->title.' | '.$day->passages.($day->reflection ? ' | '.$day->reflection : ''))->implode("\n"))
                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex min-w-0 items-start gap-3"><span class="grid size-11 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="book-open-check" class="size-5"></i></span><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h2 class="font-black text-slate-950">{{ $plan->name }}</h2>@if($plan->is_recommended)<span class="rounded-full bg-amber-50 px-2 py-1 text-[10px] font-black text-amber-700">Recommended</span>@endif</div><p class="mt-1 text-sm text-slate-500">{{ $plan->description }}</p><div class="mt-2 flex flex-wrap gap-4 text-xs font-semibold text-slate-500"><span><i data-lucide="tag" class="mr-1 inline size-3.5"></i>{{ $plan->category }}</span><span><i data-lucide="calendar-days" class="mr-1 inline size-3.5"></i>{{ $plan->days->count() }} days</span><span><i data-lucide="users" class="mr-1 inline size-3.5"></i>{{ $plan->users_count }} enrolled</span></div></div></div>
                        <div class="flex shrink-0 gap-2"><button type="button" @click="editing = editing === {{ $plan->id }} ? null : {{ $plan->id }}" class="inline-flex items-center gap-2 rounded-lg border border-violet-200 px-3 py-2 text-xs font-bold text-violet-700"><i data-lucide="pencil" class="size-3.5"></i>Edit Schedule</button><form method="POST" action="{{ route('bible.admin.plans.destroy', $plan) }}" onsubmit="return confirm('Delete this reading plan and all member progress?')">@csrf @method('DELETE')<button class="inline-flex items-center gap-2 rounded-lg border border-rose-200 px-3 py-2 text-xs font-bold text-rose-600"><i data-lucide="trash-2" class="size-3.5"></i>Delete</button></form></div>
                    </div>
                    <div x-cloak x-show="editing === {{ $plan->id }}" x-transition class="border-t border-slate-200 bg-slate-50/60 p-5">
                        <form method="POST" action="{{ route('bible.admin.plans.update', $plan) }}" class="grid gap-4 sm:grid-cols-2">@csrf @method('PUT')
                            <label class="text-sm font-bold text-slate-700">Plan name<input name="name" value="{{ $plan->name }}" required maxlength="160" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 px-3"></label>
                            <label class="text-sm font-bold text-slate-700">Category<input name="category" value="{{ $plan->category }}" required maxlength="80" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 px-3"></label>
                            <label class="text-sm font-bold text-slate-700 sm:col-span-2">Description<textarea name="description" required maxlength="2000" rows="3" class="mt-1.5 w-full rounded-xl border-slate-200 p-3">{{ $plan->description }}</textarea></label>
                            <label class="text-sm font-bold text-slate-700 sm:col-span-2">Daily schedule <span class="font-normal text-slate-400">(one day per line)</span><textarea name="schedule" required rows="12" class="mt-1.5 w-full rounded-xl border-slate-200 p-3 font-mono text-xs leading-6">{{ $schedule }}</textarea><span class="mt-1 block text-xs font-normal text-slate-500">Format: Day title | Bible passages | Optional reflection</span></label>
                            <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input name="is_recommended" value="1" type="checkbox" @checked($plan->is_recommended) class="rounded border-violet-300 text-violet-600">Recommended for members</label>
                            <div class="flex justify-end gap-2 sm:col-span-2"><button type="button" @click="editing = null" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700">Cancel</button><button class="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white">Save Plan</button></div>
                        </form>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center"><span class="mx-auto grid size-14 place-items-center rounded-2xl bg-violet-50 text-violet-600"><i data-lucide="calendar-plus" class="size-7"></i></span><h2 class="mt-4 font-black text-slate-950">No church reading plans yet</h2><p class="mt-1 text-sm text-slate-500">Create the first scheduled reading journey for your members.</p><button type="button" @click="createOpen = true" class="mt-4 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-bold text-white">Create Reading Plan</button></div>
            @endforelse
        </section>

        <div x-cloak x-show="createOpen" x-transition.opacity class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-950/45 p-4" @keydown.escape.window="createOpen = false" @click.self="createOpen = false">
            <form method="POST" action="{{ route('bible.admin.plans.store') }}" class="my-6 w-full max-w-3xl rounded-2xl bg-white p-6 shadow-2xl">@csrf
                <div class="flex items-start justify-between gap-4"><div><h2 class="text-xl font-black text-slate-950">Create Reading Plan</h2><p class="mt-1 text-sm text-slate-500">Add the full day-by-day schedule now. The duration is calculated automatically.</p></div><button type="button" @click="createOpen = false" class="grid size-9 shrink-0 place-items-center rounded-lg text-slate-500 hover:bg-slate-100"><i data-lucide="x" class="size-5"></i></button></div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="text-sm font-bold text-slate-700">Plan name<input name="name" value="{{ old('name') }}" required maxlength="160" placeholder="30 Days in Psalms" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 px-3"></label>
                    <label class="text-sm font-bold text-slate-700">Category<input name="category" value="{{ old('category') }}" required maxlength="80" placeholder="Psalms" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 px-3"></label>
                    <label class="text-sm font-bold text-slate-700 sm:col-span-2">Description<textarea name="description" required maxlength="2000" rows="3" class="mt-1.5 w-full rounded-xl border-slate-200 p-3" placeholder="Explain what members will read and learn...">{{ old('description') }}</textarea></label>
                    <label class="text-sm font-bold text-slate-700 sm:col-span-2">Daily schedule <span class="font-normal text-slate-400">(one day per line)</span><textarea name="schedule" required rows="12" class="mt-1.5 w-full rounded-xl border-slate-200 p-3 font-mono text-xs leading-6" placeholder="Creation | Genesis 1-2 | Reflect on God as Creator&#10;The Fall | Genesis 3-4 | Consider the consequences of sin&#10;The Flood | Genesis 5-7">{{ old('schedule') }}</textarea><span class="mt-1 block text-xs font-normal text-slate-500">Format: Day title | Bible passages | Optional reflection. Use semicolons between separate passages.</span></label>
                    <label class="flex items-center gap-2 text-sm font-bold text-slate-700 sm:col-span-2"><input name="is_recommended" value="1" type="checkbox" @checked(old('is_recommended', true)) class="rounded border-violet-300 text-violet-600">Recommend this plan to members</label>
                </div>
                @if($errors->any())<ul class="mt-4 rounded-xl bg-rose-50 p-3 text-sm text-rose-700">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
                <div class="mt-5 flex justify-end gap-2"><button type="button" @click="createOpen = false" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700">Cancel</button><button class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white"><i data-lucide="calendar-plus" class="size-4"></i>Create Scheduled Plan</button></div>
            </form>
        </div>
    </div>
</x-app-layout>
