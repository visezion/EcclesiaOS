<x-app-layout title="Message Templates" :breadcrumbs="$breadcrumbs">
    @php
        $cards = [
            ['label' => 'Total Templates', 'value' => $stats['total'], 'icon' => 'file-search', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Active Templates', 'value' => $stats['active'], 'icon' => 'shield-check', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Draft Templates', 'value' => $stats['draft'], 'icon' => 'file-chart-column', 'tone' => 'bg-amber-50 text-amber-600 ring-amber-100'],
            ['label' => 'Approval Pending', 'value' => $stats['pending'], 'icon' => 'clock', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100'],
            ['label' => 'Localized Templates', 'value' => $stats['localized'], 'icon' => 'globe-2', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Recently Updated', 'value' => $stats['updated'], 'icon' => 'refresh-cw', 'tone' => 'bg-cyan-50 text-cyan-600 ring-cyan-100'],
        ];
    @endphp

    <div class="space-y-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div><h1 class="text-2xl font-semibold text-slate-950">Message Templates</h1><p class="text-sm text-slate-500">Create, manage, approve, and reuse communication templates across all channels.</p></div>
            <a href="#template-form" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white"><i data-lucide="plus" class="size-4"></i>Create New Template</a>
        </div>
        @include('communications.partials.flash')
        @include('communications.partials.subnav')

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            @foreach($cards as $card)
                <article class="dashboard-card"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-lg ring-1 {{ $card['tone'] }}"><i data-lucide="{{ $card['icon'] }}" class="size-5"></i></span><div><div class="text-xs text-slate-500">{{ $card['label'] }}</div><div class="mt-1 text-2xl text-slate-950">{{ number_format($card['value']) }}</div></div></div></article>
            @endforeach
        </section>

        <form method="GET" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid gap-3 xl:grid-cols-[1fr_170px_170px_170px_auto] xl:items-end">
                <label class="text-sm text-slate-600">Search<input name="q" value="{{ request('q') }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm" placeholder="Search templates by name, trigger, or subject..."></label>
                <label class="text-sm text-slate-600">Category<select name="category" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"><option value="">All Categories</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(request('category') === $category)>{{ Str::headline($category) }}</option>@endforeach</select></label>
                <label class="text-sm text-slate-600">Channel<select name="channel" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"><option value="">All Channels</option>@foreach($channels as $key => $channel)<option value="{{ $key }}" @selected(request('channel') === $key)>{{ $channel['label'] }}</option>@endforeach</select></label>
                <label class="text-sm text-slate-600">Status<select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"><option value="">All Statuses</option>@foreach(['active','draft','inactive'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ Str::headline($status) }}</option>@endforeach</select></label>
                <button class="rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white">Apply</button>
            </div>
        </form>

        <section class="grid gap-4 xl:grid-cols-[1fr_410px]">
            <main class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 p-4"><h2 class="text-base font-semibold text-slate-950">Template Directory</h2><span class="text-sm text-slate-500">{{ number_format($templates->total()) }} templates</span></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3"></th><th class="px-5 py-3">Template Name</th><th class="px-5 py-3">Category</th><th class="px-5 py-3">Trigger</th><th class="px-5 py-3">Channels</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Owner</th><th class="px-5 py-3 text-right">Actions</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($templates as $template)
                                <tr class="hover:bg-slate-50/70">
                                    <td class="px-5 py-4"><input type="checkbox" class="rounded border-slate-300 text-violet-600"></td>
                                    <td class="px-5 py-4"><div class="font-medium text-slate-950">{{ $template->name }}</div><div class="text-xs text-slate-500">{{ $template->subject ?: 'No subject' }}</div></td>
                                    <td class="px-5 py-4"><span class="rounded-full bg-violet-50 px-2 py-1 text-xs text-violet-700">{{ Str::headline($template->category) }}</span></td>
                                    <td class="px-5 py-4">{{ $template->trigger_event ?: 'Manual' }}</td>
                                    <td class="px-5 py-4">@include('communications.partials.channel-chips', ['selected' => $template->channels ?? [], 'channels' => $channels])</td>
                                    <td class="px-5 py-4"><span class="rounded-full px-2 py-1 text-xs {{ $template->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ Str::headline($template->status) }}</span></td>
                                    <td class="px-5 py-4">{{ $template->owner?->name ?? 'System' }}</td>
                                    <td class="px-5 py-4 text-right">
                                        <form method="POST" action="{{ route('communications.templates.clone', $template) }}" class="inline">@csrf<button class="inline-grid size-8 place-items-center rounded-lg hover:bg-violet-50" title="Clone"><i data-lucide="copy" class="size-4"></i></button></form>
                                        <form method="POST" action="{{ route('communications.templates.destroy', $template) }}" class="inline" onsubmit="return confirm('Delete this unused template?')">@csrf @method('DELETE')<button class="inline-grid size-8 place-items-center rounded-lg hover:bg-rose-50" title="Delete"><i data-lucide="x" class="size-4"></i></button></form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-5 py-12 text-center"><x-empty-state icon="file-search" title="No templates found" message="Create a template to power event, attendance, care, and campaign communications." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 p-4">{{ $templates->links() }}</div>
            </main>

            <aside class="space-y-4">
                <form id="template-form" method="POST" action="{{ $selected ? route('communications.templates.update', $selected) : route('communications.templates.store') }}" class="dashboard-card">
                    @csrf
                    @if($selected) @method('PUT') @endif
                    <h2 class="text-base font-semibold text-slate-950">{{ $selected ? 'Edit Selected Template' : 'Create Template' }}</h2>
                    <div class="mt-4 grid gap-3 text-sm">
                        <label class="text-slate-600">Template Name<input name="name" value="{{ old('name', $selected?->name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5"></label>
                        <label class="text-slate-600">Subject<input name="subject" value="{{ old('subject', $selected?->subject) }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5"></label>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="text-slate-600">Category<select name="category" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5">@foreach($categories as $category)<option value="{{ $category }}" @selected(old('category', $selected?->category ?? 'events') === $category)>{{ Str::headline($category) }}</option>@endforeach</select></label>
                            <label class="text-slate-600">Trigger<select name="trigger_event" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5"><option value="">Manual</option>@foreach($triggersList as $trigger)<option value="{{ $trigger }}" @selected(old('trigger_event', $selected?->trigger_event) === $trigger)>{{ $trigger }}</option>@endforeach</select></label>
                        </div>
                        <textarea name="body" rows="9" required class="rounded-lg border border-slate-200 px-3 py-2.5" placeholder="Write the message body...">{{ old('body', $selected?->body) }}</textarea>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach($channels as $key => $channel)
                                <label class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"><span class="inline-flex items-center gap-2"><i data-lucide="{{ $channel['icon'] }}" class="size-4 text-violet-600"></i>{{ $channel['label'] }}</span><input type="checkbox" name="channels[]" value="{{ $key }}" @checked(in_array($key, old('channels', $selected?->channels ?? ['email']), true)) class="rounded border-slate-300 text-violet-600"></label>
                            @endforeach
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <label class="text-slate-600">Language<input name="language" value="{{ old('language', $selected?->language ?? 'en') }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5"></label>
                            <label class="text-slate-600">Status<select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5">@foreach(['active','draft','inactive'] as $status)<option value="{{ $status }}" @selected(old('status', $selected?->status ?? 'active') === $status)>{{ Str::headline($status) }}</option>@endforeach</select></label>
                            <label class="text-slate-600">Approval<select name="approval_state" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5">@foreach(['approved','pending','rejected'] as $state)<option value="{{ $state }}" @selected(old('approval_state', $selected?->approval_state ?? 'approved') === $state)>{{ Str::headline($state) }}</option>@endforeach</select></label>
                        </div>
                    </div>
                    <button class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white"><i data-lucide="save" class="size-4"></i>{{ $selected ? 'Save Template' : 'Create Template' }}</button>
                </form>
                <section class="dashboard-card">
                    <h2 class="text-base font-semibold text-slate-950">Template Status Distribution</h2>
                    <div class="mt-4 h-44"><canvas data-chart="doughnut" data-labels='@json(collect($statusBreakdown)->pluck("label"))' data-values='@json(collect($statusBreakdown)->pluck("value"))' data-colors='@json(collect($statusBreakdown)->pluck("color"))'></canvas></div>
                </section>
                <section class="dashboard-card">
                    <h2 class="text-base font-semibold text-slate-950">Top Templates by Usage</h2>
                    <div class="mt-4 space-y-2 text-sm">@forelse($templateUsage as $template)<div class="flex justify-between"><span class="truncate">{{ $template->name }}</span><span>{{ number_format($template->usage_count) }}</span></div>@empty<p class="text-slate-500">No template usage yet.</p>@endforelse</div>
                </section>
            </aside>
        </section>
    </div>
</x-app-layout>
