<x-app-layout title="Message Templates" :breadcrumbs="$breadcrumbs">
    @php
        $cards = [
            ['label' => 'Total Templates', 'value' => $stats['total'], 'hint' => '+ 12.4% vs last 30 days', 'icon' => 'file-search', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
            ['label' => 'Active Templates', 'value' => $stats['active'], 'hint' => '+ 8.7% vs last 30 days', 'icon' => 'shield-check', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
            ['label' => 'Draft Templates', 'value' => $stats['draft'], 'hint' => '- 3.2% vs last 30 days', 'icon' => 'file-chart-column', 'tone' => 'bg-amber-50 text-amber-600 ring-amber-100'],
            ['label' => 'Approval Pending', 'value' => $stats['pending'], 'hint' => '+ 4.5% vs last 30 days', 'icon' => 'clock', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100'],
            ['label' => 'Localized Templates', 'value' => $stats['localized'], 'hint' => '+ 9.3% vs last 30 days', 'icon' => 'globe-2', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
            ['label' => 'Recently Updated', 'value' => $stats['updated'], 'hint' => 'in last 7 days', 'icon' => 'refresh-cw', 'tone' => 'bg-cyan-50 text-cyan-600 ring-cyan-100'],
        ];
        $categoryTone = [
            'events' => 'bg-violet-50 text-violet-700',
            'attendance' => 'bg-emerald-50 text-emerald-700',
            'care' => 'bg-orange-50 text-orange-700',
            'volunteers' => 'bg-amber-50 text-amber-700',
            'registration' => 'bg-blue-50 text-blue-700',
            'system' => 'bg-slate-50 text-slate-700',
        ];
        $selectedChannels = old('channels', $selected?->channels ?? ['email']);
        $variables = collect($selected?->variables ?? ['memberName', 'eventTitle', 'eventDate', 'eventTime', 'eventVenue', 'meetingLink', 'qrLink', 'followUpNote'])->unique()->values();
        $statusTotal = max(collect($statusBreakdown)->sum('value'), 1);
        $usageTotal = max($templateUsage->max('usage_count') ?? 1, 1);
        $wordCount = str_word_count(strip_tags((string) old('body', $selected?->body ?? '')));
        $characterCount = strlen((string) old('body', $selected?->body ?? ''));
        $formAction = $selected ? route('communications.templates.update', $selected) : route('communications.templates.store');
    @endphp

    <div class="space-y-4">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-950">Message Templates</h1>
                <p class="text-sm text-slate-500">Create, manage, and reuse communication templates across all channels.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('communications.templates', ['new' => 1]) }}#template-form" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white shadow-sm hover:bg-violet-700">
                    <i data-lucide="plus" class="size-4"></i>
                    Create New Template
                </a>
                <a href="{{ route('communications.templates.export') }}" class="inline-grid size-10 place-items-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50" title="Download templates">
                    <i data-lucide="download" class="size-4"></i>
                </a>
            </div>
        </div>

        @include('communications.partials.flash')

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
            @foreach($cards as $card)
                <article class="dashboard-card p-4">
                    <div class="flex items-center gap-3">
                        <span class="grid size-11 shrink-0 place-items-center rounded-full ring-1 {{ $card['tone'] }}">
                            <i data-lucide="{{ $card['icon'] }}" class="size-5"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="truncate text-xs text-slate-500">{{ $card['label'] }}</div>
                            <div class="mt-1 text-2xl font-semibold leading-none text-slate-950">{{ number_format($card['value']) }}</div>
                            <div class="mt-1 text-xs {{ str_starts_with($card['hint'], '-') ? 'text-rose-600' : 'text-emerald-600' }}">{{ $card['hint'] }}</div>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <form method="GET" class="dashboard-card p-0">
            <div class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-[1.6fr_repeat(6,minmax(0,1fr))_auto_auto_auto] xl:items-end">
                <label class="text-xs font-medium text-slate-600 xl:col-span-2">Search
                    <span class="relative mt-1 block">
                        <i data-lucide="search" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"></i>
                        <input name="q" value="{{ request('q') }}" class="w-full rounded-lg border border-slate-200 py-2.5 pl-9 pr-3 text-sm" placeholder="Search templates by name, category, trigger, or owner...">
                    </span>
                </label>
                <label class="text-xs font-medium text-slate-600">Category
                    <select name="category" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}" @selected(request('category') === $category)>{{ Str::headline($category) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-medium text-slate-600">Trigger
                    <select name="trigger" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        <option value="">All Triggers</option>
                        @foreach($triggersList as $trigger)
                            <option value="{{ $trigger }}" @selected(request('trigger') === $trigger)>{{ $trigger }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-medium text-slate-600">Channel
                    <select name="channel" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        <option value="">All Channels</option>
                        @foreach($channels as $key => $channel)
                            <option value="{{ $key }}" @selected(request('channel') === $key)>{{ $channel['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-medium text-slate-600">Language
                    <select name="language" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        <option value="">All Languages</option>
                        @foreach($languages as $language)
                            <option value="{{ $language }}" @selected(request('language') === $language)>{{ strtoupper($language) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-medium text-slate-600">Status
                    <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        <option value="">All Statuses</option>
                        @foreach(['active','draft','inactive'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ Str::headline($status) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-medium text-slate-600">Approval
                    <select name="approval_state" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
                        <option value="">All Approval States</option>
                        @foreach(['approved','pending','rejected'] as $state)
                            <option value="{{ $state }}" @selected(request('approval_state') === $state)>{{ Str::headline($state) }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-50 px-4 py-2.5 text-sm font-medium text-violet-700">
                    <i data-lucide="sliders-horizontal" class="size-4"></i>
                    Filters
                </button>
                <a href="{{ route('communications.templates', array_merge(request()->except('compact', 'page'), ['compact' => request('compact') ? null : 1])) }}" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-700">
                    <i data-lucide="columns-3" class="size-4"></i>
                    Columns
                </a>
                <a href="{{ route('communications.templates.export') }}" class="inline-grid size-10 place-items-center rounded-lg border border-slate-200 bg-white text-slate-600" title="Export templates">
                    <i data-lucide="download" class="size-4"></i>
                </a>
            </div>
        </form>

        <section class="dashboard-card p-0">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1120px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="w-12 px-4 py-3"></th>
                            <th class="px-4 py-3">Template Name</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Trigger Event</th>
                            <th class="px-4 py-3">Channels</th>
                            @unless(request('compact'))<th class="px-4 py-3">Language</th>@endunless
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Last Updated</th>
                            @unless(request('compact'))<th class="px-4 py-3">Owner</th>@endunless
                            <th class="px-4 py-3">Approval State</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($templates as $template)
                            @php($selectedRow = $selected?->is($template))
                            <tr class="{{ $selectedRow ? 'bg-violet-50/70' : 'hover:bg-slate-50/70' }}">
                                <td class="px-4 py-3"><input type="checkbox" @checked($selectedRow) class="rounded border-slate-300 text-violet-600"></td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('communications.templates', array_merge(request()->except('template', 'new', 'page'), ['template' => $template->opaqueId()])) }}#template-form" class="font-medium text-slate-950 hover:text-violet-700">{{ $template->name }}</a>
                                    <div class="truncate text-xs text-slate-500">{{ $template->subject ?: 'No subject line' }}</div>
                                </td>
                                <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs {{ $categoryTone[$template->category] ?? 'bg-slate-50 text-slate-700' }}">{{ Str::headline($template->category) }}</span></td>
                                <td class="px-4 py-3 text-slate-700">{{ $template->trigger_event ?: 'Manual' }}</td>
                                <td class="px-4 py-3">@include('communications.partials.channel-chips', ['selected' => $template->channels ?? [], 'channels' => $channels])</td>
                                @unless(request('compact'))<td class="px-4 py-3">{{ strtoupper($template->language) }}</td>@endunless
                                <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs {{ $template->status === 'active' ? 'bg-emerald-50 text-emerald-700' : ($template->status === 'draft' ? 'bg-amber-50 text-amber-700' : 'bg-slate-50 text-slate-700') }}"><span class="mr-1 inline-block size-1.5 rounded-full {{ $template->status === 'active' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>{{ Str::headline($template->status) }}</span></td>
                                <td class="px-4 py-3 text-slate-600">{{ $template->updated_at?->format('M d, Y h:i A') }}</td>
                                @unless(request('compact'))<td class="px-4 py-3">{{ $template->owner?->name ?? 'System' }}</td>@endunless
                                <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs {{ $template->approval_state === 'approved' ? 'bg-emerald-50 text-emerald-700' : ($template->approval_state === 'pending' ? 'bg-orange-50 text-orange-700' : 'bg-rose-50 text-rose-700') }}">{{ Str::headline($template->approval_state) }}</span></td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <a href="{{ route('communications.templates', array_merge(request()->except('template', 'new', 'page'), ['template' => $template->opaqueId()])) }}#template-form" class="inline-grid size-8 place-items-center rounded-lg hover:bg-violet-50" title="Edit"><i data-lucide="pencil" class="size-4"></i></a>
                                        <form method="POST" action="{{ route('communications.templates.clone', $template) }}">@csrf<button class="inline-grid size-8 place-items-center rounded-lg hover:bg-violet-50" title="Clone"><i data-lucide="copy" class="size-4"></i></button></form>
                                        <form method="POST" action="{{ route('communications.templates.destroy', $template) }}" onsubmit="return confirm('Delete this unused template?')">@csrf @method('DELETE')<button class="inline-grid size-8 place-items-center rounded-lg hover:bg-rose-50" title="Delete"><i data-lucide="ellipsis" class="size-4"></i></button></form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="px-5 py-12 text-center"><x-empty-state icon="file-search" title="No templates found" message="Create a template to power event, attendance, care, and campaign communications." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-4">{{ $templates->links() }}</div>
        </section>

        <section class="grid items-start gap-4 2xl:grid-cols-[280px_minmax(420px,1fr)_235px_360px_280px]">
            <aside class="space-y-4">
                <section class="dashboard-card p-0">
                    <div class="border-b border-slate-100 p-4">
                        <div class="text-xs font-medium text-slate-500">Selected Template</div>
                        <h2 class="mt-1 text-base font-semibold text-slate-950">{{ $selected?->name ?? 'New Template' }}</h2>
                        <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs {{ $selected?->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $selected ? Str::headline($selected->status) : 'Draft' }}</span>
                    </div>
                    <dl class="grid gap-3 p-4 text-sm">
                        <div class="grid grid-cols-2 gap-3"><dt class="text-slate-500">Trigger Event</dt><dd class="text-slate-900">{{ $selected?->trigger_event ?? 'Manual' }}</dd></div>
                        <div class="grid grid-cols-2 gap-3"><dt class="text-slate-500">Category</dt><dd class="text-slate-900">{{ $selected ? Str::headline($selected->category) : 'Events' }}</dd></div>
                        <div class="grid grid-cols-2 gap-3"><dt class="text-slate-500">Owner</dt><dd class="text-slate-900">{{ $selected?->owner?->name ?? auth()->user()?->name }}</dd></div>
                        <div class="grid grid-cols-2 gap-3"><dt class="text-slate-500">Created</dt><dd class="text-slate-900">{{ $selected?->created_at?->format('M d, Y h:i A') ?? now()->format('M d, Y h:i A') }}</dd></div>
                    </dl>
                    <div class="border-t border-slate-100 p-4">
                        <div class="mb-3 text-sm font-semibold text-slate-950">Channels</div>
                        <div class="grid grid-cols-5 gap-2 text-center text-xs">
                            @foreach($channels as $key => $channel)
                                <span class="rounded-lg p-2 {{ in_array($key, $selectedChannels, true) ? $channel['tone'] : 'bg-slate-50 text-slate-400' }}">
                                    <i data-lucide="{{ $channel['icon'] }}" class="mx-auto mb-1 size-4"></i>{{ $channel['label'] }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                    <a href="#template-form" class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm text-violet-700">
                        Template Settings
                        <i data-lucide="chevron-down" class="size-4"></i>
                    </a>
                </section>
            </aside>

            <form id="template-form" method="POST" action="{{ $formAction }}" class="dashboard-card p-0">
                @csrf
                @if($selected) @method('PUT') @endif
                <div class="flex items-center gap-5 border-b border-slate-100 px-3 py-2 text-xs">
                    <span class="border-b-2 border-violet-600 pb-2 font-medium text-violet-700">Template Editor</span>
                    <span class="text-slate-500">Channel Previews</span>
                    <span class="text-slate-500">Variables</span>
                    <span class="text-slate-500">Analytics</span>
                </div>
                <div class="space-y-3 p-3">
                    <div class="grid gap-3 xl:grid-cols-[0.9fr_1.35fr]">
                        <label class="block text-xs font-medium text-slate-600">Template Name
                            <input name="name" value="{{ old('name', $selected?->name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Event Session Created - Email">
                        </label>
                        <label class="block text-xs font-medium text-slate-600">Subject Line
                            <div class="mt-1 grid gap-2 md:grid-cols-[1fr_auto]">
                                <input id="template-subject" name="subject" value="{{ old('subject', $selected?->subject) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="New Event Session Created: @{{eventTitle}}">
                                <button type="button" onclick="insertTemplateVariable('@{{eventTitle}}', 'template-subject')" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-violet-700">Insert Variable</button>
                            </div>
                        </label>
                    </div>
                    <div class="grid gap-3 md:grid-cols-4">
                        <label class="text-xs font-medium text-slate-600">Category
                            <select name="category" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">@foreach($categories as $category)<option value="{{ $category }}" @selected(old('category', $selected?->category ?? 'events') === $category)>{{ Str::headline($category) }}</option>@endforeach</select>
                        </label>
                        <label class="text-xs font-medium text-slate-600">Trigger
                            <select name="trigger_event" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">Manual</option>@foreach($triggersList as $trigger)<option value="{{ $trigger }}" @selected(old('trigger_event', $selected?->trigger_event) === $trigger)>{{ $trigger }}</option>@endforeach</select>
                        </label>
                        <label class="text-xs font-medium text-slate-600">Language
                            <input name="language" value="{{ old('language', $selected?->language ?? 'en') }}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        </label>
                        <label class="text-xs font-medium text-slate-600">Status
                            <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">@foreach(['active','draft','inactive'] as $status)<option value="{{ $status }}" @selected(old('status', $selected?->status ?? 'active') === $status)>{{ Str::headline($status) }}</option>@endforeach</select>
                        </label>
                    </div>
                    <label class="block text-xs font-medium text-slate-600">Message Body
                        <div class="mt-1 overflow-hidden rounded-lg border border-slate-200">
                            <div class="flex flex-wrap items-center gap-1 border-b border-slate-100 bg-slate-50 px-2 py-1.5">
                                <button type="button" onclick="wrapTemplateSelection('**', '**')" class="inline-grid size-7 place-items-center rounded hover:bg-white" title="Bold"><i data-lucide="bold" class="size-3.5"></i></button>
                                <button type="button" onclick="wrapTemplateSelection('_', '_')" class="inline-grid size-7 place-items-center rounded hover:bg-white" title="Italic"><i data-lucide="italic" class="size-3.5"></i></button>
                                <button type="button" onclick="wrapTemplateSelection('<u>', '</u>')" class="inline-grid size-7 place-items-center rounded hover:bg-white" title="Underline"><i data-lucide="underline" class="size-3.5"></i></button>
                                <button type="button" onclick="insertTemplateVariable('@{{meetingLink}}')" class="inline-grid size-7 place-items-center rounded hover:bg-white" title="Meeting link"><i data-lucide="link" class="size-3.5"></i></button>
                                <button type="button" onclick="insertTemplateVariable('@{{qrLink}}')" class="inline-grid size-7 place-items-center rounded hover:bg-white" title="QR link"><i data-lucide="image" class="size-3.5"></i></button>
                                <button type="button" onclick="insertTemplateVariable('@{{memberName}}')" class="ml-auto rounded-lg bg-white px-3 py-1.5 text-xs font-medium text-violet-700">Insert Variable</button>
                            </div>
                            <textarea id="template-body" name="body" rows="7" required class="w-full border-0 px-3 py-2.5 text-sm leading-5 focus:ring-0" placeholder="Write the message body...">{{ old('body', $selected?->body) }}</textarea>
                        </div>
                    </label>
                    <div class="grid gap-3 md:grid-cols-[130px_150px_1fr]">
                        <label class="text-xs font-medium text-slate-600">Approval
                            <select name="approval_state" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">@foreach(['approved','pending','rejected'] as $state)<option value="{{ $state }}" @selected(old('approval_state', $selected?->approval_state ?? 'approved') === $state)>{{ Str::headline($state) }}</option>@endforeach</select>
                        </label>
                        <label class="text-xs font-medium text-slate-600">Campus
                            <select name="campus_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">All Campuses</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected(old('campus_id', $selected?->campus_id) == $campus->id)>{{ $campus->name }}</option>@endforeach</select>
                        </label>
                        <div class="text-xs font-medium text-slate-600">Channels
                            <div class="mt-1 grid grid-cols-2 gap-1.5 lg:grid-cols-1">
                                @foreach($channels as $key => $channel)
                                    <label class="inline-flex items-center gap-1.5 rounded-lg bg-slate-50 px-2.5 py-1.5 text-xs"><input type="checkbox" name="channels[]" value="{{ $key }}" @checked(in_array($key, $selectedChannels, true)) class="rounded border-slate-300 text-violet-600"><i data-lucide="{{ $channel['icon'] }}" class="size-3.5 text-violet-600"></i>{{ $channel['label'] }}</label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-3 py-2 text-xs text-slate-500">
                    <span>Characters: {{ number_format($characterCount) }} / Words: {{ number_format($wordCount) }}</span>
                    <span class="inline-flex items-center gap-2"><i data-lucide="check" class="size-3.5 text-emerald-600"></i>Auto-saved locally</span>
                    <button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-3 py-2 text-sm text-white"><i data-lucide="save" class="size-4"></i>{{ $selected ? 'Save Template' : 'Create Template' }}</button>
                </div>
            </form>

            <aside class="space-y-4">
                <section class="dashboard-card p-0">
                    <div class="border-b border-slate-100 px-3 py-2.5"><h2 class="text-sm font-semibold text-slate-950">Personalization Variables</h2></div>
                    <div class="divide-y divide-slate-100 text-xs">
                        @foreach($variables as $variable)
                            @php($token = '{{'.$variable.'}}')
                            <button type="button" onclick="insertTemplateVariable('{{ $token }}')" class="grid w-full grid-cols-[1fr_auto] gap-3 px-3 py-2.5 text-left hover:bg-violet-50">
                                <span class="font-medium text-violet-700">{{ $token }}</span>
                                <span class="text-xs text-slate-500">{{ Str::headline($variable) }}</span>
                            </button>
                        @endforeach
                    </div>
                </section>
                <section class="dashboard-card p-0">
                    <div class="border-b border-slate-100 px-3 py-2.5"><h2 class="text-sm font-semibold text-slate-950">Reusable Blocks</h2></div>
                    <div class="divide-y divide-slate-100 text-sm">
                        @foreach(['Meeting Link Block' => '{{meetingLink}}', 'QR Code Block' => '{{qrLink}}', 'Follow-up Note' => '{{followUpNote}}'] as $label => $token)
                            <button type="button" onclick="insertTemplateVariable('{{ $token }}')" class="flex w-full items-center justify-between px-3 py-2.5 text-left hover:bg-violet-50"><span>{{ $label }}</span><span class="rounded-full bg-slate-50 px-2 py-1 text-xs text-slate-500">Dynamic</span></button>
                        @endforeach
                    </div>
                    <a href="#template-form" class="block border-t border-slate-100 px-3 py-2.5 text-xs font-medium text-violet-700">Manage Blocks</a>
                </section>
            </aside>

            <aside class="space-y-4">
                <section class="dashboard-card p-0">
                    <div class="border-b border-slate-100 px-4 py-2.5"><h2 class="text-sm font-semibold text-slate-950">Template Utilities</h2></div>
                    <div class="grid gap-2 p-3">
                        @if($selected)
                            <form method="POST" action="{{ route('communications.templates.test-send', $selected) }}">@csrf<button class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-violet-50"><i data-lucide="send" class="mr-2 inline size-4 text-violet-600"></i>Test Send</button></form>
                            <form method="POST" action="{{ route('communications.templates.clone', $selected) }}">@csrf<button class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-violet-50"><i data-lucide="copy" class="mr-2 inline size-4 text-violet-600"></i>Clone Template</button></form>
                        @else
                            <button form="template-form" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-violet-50"><i data-lucide="save" class="mr-2 inline size-4 text-violet-600"></i>Create First</button>
                        @endif
                        <a href="{{ route('communications.delivery-logs', ['status' => 'failed']) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-center text-sm text-slate-700 hover:bg-violet-50">More Actions</a>
                    </div>
                </section>
                <section class="dashboard-card p-0">
                    <div class="border-b border-slate-100 px-4 py-2.5"><h2 class="text-sm font-semibold text-slate-950">Approval Workflow</h2></div>
                    <div class="flex items-center gap-2 p-3 text-xs">
                        <span class="rounded-full bg-slate-50 px-3 py-1.5">Draft</span><i data-lucide="arrow-right" class="size-3 text-slate-400"></i><span class="rounded-full bg-orange-50 px-3 py-1.5 text-orange-700">Pending</span><i data-lucide="arrow-right" class="size-3 text-slate-400"></i><span class="rounded-full bg-emerald-50 px-3 py-1.5 text-emerald-700">Approved</span>
                    </div>
                    <div class="border-t border-slate-100 px-4 py-2.5 text-xs text-slate-500">Last approved by {{ $selected?->owner?->name ?? 'Admin Team' }} on {{ $selected?->updated_at?->format('M d, Y') ?? now()->format('M d, Y') }}</div>
                </section>
                <section class="dashboard-card p-0">
                    <div class="border-b border-slate-100 px-4 py-2.5"><h2 class="text-sm font-semibold text-slate-950">Channel Compatibility</h2></div>
                    <div class="grid grid-cols-5 gap-2 p-3 text-center text-xs">
                        @foreach($channels as $key => $channel)
                            <span><i data-lucide="{{ $channel['icon'] }}" class="mx-auto mb-1 size-4 {{ in_array($key, $selectedChannels, true) ? 'text-violet-600' : 'text-slate-300' }}"></i>{{ $channel['label'] }}<i data-lucide="{{ in_array($key, $selectedChannels, true) ? 'check-circle' : 'circle' }}" class="mx-auto mt-1 size-3 {{ in_array($key, $selectedChannels, true) ? 'text-emerald-600' : 'text-slate-300' }}"></i></span>
                        @endforeach
                    </div>
                </section>
            </aside>

            <aside class="space-y-4">
                <section class="dashboard-card p-0">
                    <div class="border-b border-slate-100 px-4 py-2.5"><h2 class="text-sm font-semibold text-slate-950">Template Status Distribution</h2></div>
                    <div class="grid gap-3 p-3 md:grid-cols-[125px_1fr] md:items-center">
                        <div class="relative h-32">
                            <canvas data-chart="doughnut" data-labels='@json(collect($statusBreakdown)->pluck("label"))' data-values='@json(collect($statusBreakdown)->pluck("value"))' data-colors='@json(collect($statusBreakdown)->pluck("color"))'></canvas>
                            <div class="pointer-events-none absolute inset-0 grid place-items-center text-center"><span><span class="block text-xl font-semibold text-slate-950">{{ number_format($statusTotal) }}</span><span class="text-xs text-slate-500">Total</span></span></div>
                        </div>
                        <div class="space-y-2 text-sm">
                            @foreach($statusBreakdown as $row)
                                @php($percent = round(($row['value'] / $statusTotal) * 100, 1))
                                <div class="grid grid-cols-[1fr_auto] gap-2"><span class="inline-flex items-center gap-2 text-slate-600"><span class="size-2.5 rounded-full" style="background: {{ $row['color'] }}"></span>{{ $row['label'] }}</span><span>{{ number_format($row['value']) }} <span class="text-slate-400">({{ $percent }}%)</span></span></div>
                            @endforeach
                        </div>
                    </div>
                </section>
                <section class="dashboard-card p-0">
                    <div class="border-b border-slate-100 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Top Templates by Usage <span class="font-normal text-slate-500">(Last 30 Days)</span></h2></div>
                    <div class="space-y-3 p-4 text-sm">
                        @forelse($templateUsage as $template)
                            @php($width = min(100, round(($template->usage_count / $usageTotal) * 100)))
                            <div>
                                <div class="mb-1 flex justify-between gap-3"><span class="truncate text-slate-700">{{ $template->name }}</span><span class="text-slate-500">{{ number_format($template->usage_count) }}</span></div>
                                <div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-violet-500" style="width: {{ $width }}%"></div></div>
                            </div>
                        @empty
                            <p class="text-slate-500">No template usage yet.</p>
                        @endforelse
                    </div>
                    <a href="{{ route('communications.delivery-logs') }}" class="block border-t border-slate-100 px-4 py-3 text-center text-xs font-medium text-violet-700">View All Analytics</a>
                </section>
                <section class="dashboard-card p-0">
                    <div class="border-b border-slate-100 px-4 py-3"><h2 class="text-sm font-semibold text-slate-950">Usage Trend <span class="font-normal text-slate-500">(Last 30 Days)</span></h2></div>
                    <div class="h-40 p-4"><canvas data-chart="multi-line" data-labels='@json($usageTrend["labels"])' data-datasets='@json($usageTrend["datasets"])'></canvas></div>
                </section>
            </aside>
        </section>

        <footer class="flex flex-col gap-2 py-2 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between">
            <span>Copyright 2024 Kingdom Life Global Church. All rights reserved.</span>
            <span class="flex items-center gap-8">
                <span>Version 2.4.0</span>
                <a href="#" class="hover:text-violet-600">Privacy Policy</a>
                <a href="#" class="hover:text-violet-600">Terms of Service</a>
                <a href="#" class="hover:text-violet-600">Support</a>
            </span>
        </footer>
    </div>

    <script>
        function insertTemplateVariable(value, targetId) {
            const target = document.getElementById(targetId || 'template-body');
            if (!target || !value) return;
            const start = target.selectionStart || target.value.length;
            const end = target.selectionEnd || target.value.length;
            target.value = target.value.slice(0, start) + value + target.value.slice(end);
            target.focus();
            target.selectionStart = target.selectionEnd = start + value.length;
        }

        function wrapTemplateSelection(prefix, suffix) {
            const target = document.getElementById('template-body');
            if (!target) return;
            const start = target.selectionStart || 0;
            const end = target.selectionEnd || target.value.length;
            const selected = target.value.slice(start, end) || 'text';
            const value = prefix + selected + suffix;
            target.value = target.value.slice(0, start) + value + target.value.slice(end);
            target.focus();
            target.selectionStart = start + prefix.length;
            target.selectionEnd = start + prefix.length + selected.length;
        }
    </script>
</x-app-layout>
