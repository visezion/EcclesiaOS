<x-app-layout title="{{ $import->reference }}" :breadcrumbs="$breadcrumbs">
    @php
        $editable = in_array($import->status, ['draft', 'ready']);
        $running = in_array($import->status, ['queued', 'processing']);
        $complete = in_array($import->status, ['completed', 'completed_with_errors']);
        $mapping = $import->mapping ?? [];
        $statusTone = [
            'ready' => 'bg-blue-50 text-blue-700', 'duplicate' => 'bg-amber-50 text-amber-700',
            'invalid' => 'bg-rose-50 text-rose-700', 'created' => 'bg-emerald-50 text-emerald-700',
            'updated' => 'bg-violet-50 text-violet-700', 'skipped' => 'bg-slate-100 text-slate-600',
            'failed' => 'bg-rose-50 text-rose-700', 'rolled_back' => 'bg-slate-100 text-slate-600',
            'rollback_conflict' => 'bg-orange-50 text-orange-700',
        ];
    @endphp
    <div
        x-data="{
            progress: @js([
                'status' => $import->status, 'percent' => $import->total_rows ? round(($import->processed_rows / $import->total_rows) * 100, 1) : 0,
                'total' => $import->total_rows, 'processed' => $import->processed_rows, 'created' => $import->created_rows,
                'updated' => $import->updated_rows, 'skipped' => $import->skipped_rows, 'failed' => $import->failed_rows,
            ]),
            timer: null,
            async refreshProgress() {
                const response = await fetch(@js(route('member-imports.progress', $import)), { headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                this.progress = await response.json();
                if (!['queued', 'processing'].includes(this.progress.status)) {
                    clearInterval(this.timer);
                    setTimeout(() => window.location.reload(), 700);
                }
            }
        }"
        x-init="if (['queued', 'processing'].includes(progress.status)) { timer = setInterval(() => refreshProgress(), 2000); refreshProgress(); }"
        class="space-y-4"
    >
        @if(session('status'))<x-alert type="success">{{ session('status') }}</x-alert>@endif
        @if($errors->any())<x-alert type="error">{{ $errors->first() }}</x-alert>@endif

        <header class="dashboard-card">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4"><span class="grid size-12 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="file-search" class="size-6"></i></span><div><div class="flex flex-wrap items-center gap-2"><span class="font-mono text-[10px] font-bold text-violet-600">{{ $import->reference }}</span><span class="rounded-full bg-slate-100 px-2 py-1 text-[9px] font-bold text-slate-700" x-text="progress.status.replaceAll('_', ' ')"></span></div><h1 class="mt-1 text-xl font-black text-slate-950">{{ $import->name }}</h1><p class="mt-1 text-xs text-slate-500">{{ $import->original_filename ?: Str::headline($import->source_type) }} · created by {{ $import->creator?->name ?? 'System' }}</p></div></div>
                <div class="flex flex-wrap gap-2">@if($editable)<form method="POST" action="{{ route('member-imports.start', $import) }}">@csrf<button class="inline-flex h-10 items-center gap-2 rounded-lg bg-violet-600 px-4 text-xs font-bold text-white hover:bg-violet-700"><i data-lucide="play" class="size-4"></i>Start import</button></form>@endif @if($complete)<form method="POST" action="{{ route('member-imports.rollback', $import) }}" onsubmit="return confirm('Roll back member changes from this import? Records changed afterward will be protected.')">@csrf<button class="inline-flex h-10 items-center gap-2 rounded-lg border border-rose-200 bg-white px-4 text-xs font-bold text-rose-700 hover:bg-rose-50"><i data-lucide="undo-2" class="size-4"></i>Roll back import</button></form>@endif<a href="{{ route('member-imports.index') }}" class="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-xs font-bold text-slate-600 hover:bg-slate-50"><i data-lucide="arrow-left" class="size-4"></i>Import center</a></div>
            </div>
        </header>

        <section class="dashboard-card" x-show="['queued', 'processing'].includes(progress.status)" x-cloak>
            <div class="flex items-center justify-between"><div><h2 class="font-black text-slate-950">Import progress</h2><p class="text-xs text-slate-500" x-text="progress.processed + ' of ' + progress.total + ' rows processed'"></p></div><strong class="text-lg text-violet-600" x-text="progress.percent + '%'"></strong></div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-violet-600 transition-all" :style="'width:' + progress.percent + '%'"></div></div>
            <div class="mt-4 grid grid-cols-4 gap-3 text-center text-xs"><div><strong class="block text-lg text-emerald-600" x-text="progress.created"></strong>Created</div><div><strong class="block text-lg text-blue-600" x-text="progress.updated"></strong>Updated</div><div><strong class="block text-lg text-slate-600" x-text="progress.skipped"></strong>Skipped</div><div><strong class="block text-lg text-rose-600" x-text="progress.failed"></strong>Failed</div></div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach([
                ['Total rows', $import->total_rows, 'rows-3', 'bg-slate-100 text-slate-600'],
                ['Ready', $statusCounts['ready'] ?? 0, 'circle-check', 'bg-blue-50 text-blue-600'],
                ['Duplicates', $statusCounts['duplicate'] ?? 0, 'copy-check', 'bg-amber-50 text-amber-600'],
                ['Invalid', $statusCounts['invalid'] ?? 0, 'circle-alert', 'bg-rose-50 text-rose-600'],
                ['Processed', $import->processed_rows, 'badge-check', 'bg-emerald-50 text-emerald-600'],
            ] as [$label, $value, $icon, $tone])
                <div class="dashboard-card flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl {{ $tone }}"><i data-lucide="{{ $icon }}" class="size-5"></i></span><div><strong class="block text-xl text-slate-950">{{ number_format((int) $value) }}</strong><span class="text-xs text-slate-500">{{ $label }}</span></div></div>
            @endforeach
        </section>

        @if($editable)
            <form method="POST" action="{{ route('member-imports.mapping.update', $import) }}" class="dashboard-card">
                @csrf @method('PUT')
                <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-black text-slate-950">Column mapping</h2><p class="text-xs text-slate-500">Automatic matches are preselected. Confirm them before importing.</p></div><button class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 text-xs font-bold text-white"><i data-lucide="refresh-cw" class="size-4"></i>Apply mapping</button></div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach($fields as $field => $label)
                        <label class="text-xs font-bold text-slate-600">{{ $label }}@if(in_array($field, ['first_name','last_name'])) <span class="text-rose-500">*</span>@endif<select name="mapping[{{ $field }}]" class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm"><option value="">Do not import</option>@foreach($headers as $header)<option value="{{ $header }}" @selected(($mapping[$field] ?? null) === $header)>{{ Str::headline($header) }}</option>@endforeach</select></label>
                    @endforeach
                </div>
                <div class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-3">
                    <label class="text-xs font-bold text-slate-600">Duplicate strategy<select name="duplicate_strategy" class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm">@foreach(['skip' => 'Skip duplicates', 'update' => 'Update existing records', 'merge' => 'Fill only missing values', 'create' => 'Create separate records'] as $value => $label)<option value="{{ $value }}" @selected(data_get($import->options, 'duplicate_strategy', 'skip') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label class="text-xs font-bold text-slate-600">Default campus<select name="default_campus_id" required class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm">@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected((int) data_get($import->options, 'default_campus_id') === $campus->id)>{{ $campus->name }}</option>@endforeach</select></label>
                    <label class="flex items-center justify-between gap-3 self-end rounded-lg border border-slate-200 px-3 py-2.5 text-xs font-bold text-slate-700">Create missing families<input type="checkbox" name="create_families" value="1" @checked(data_get($import->options, 'create_families', true)) class="rounded border-slate-300 text-violet-600"></label>
                </div>
            </form>

            <section class="dashboard-card">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div><h2 class="font-black text-slate-950">Reusable mapping profile</h2><p class="mt-1 text-xs text-slate-500">Save this confirmed column mapping and duplicate policy for future files from the same source.</p></div>
                    @if($import->profile)
                        <div class="flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700"><i data-lucide="bookmark" class="size-4"></i>{{ $import->profile->name }}</div>
                    @else
                        <form method="POST" action="{{ route('member-imports.profiles.store', $import) }}" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            @csrf
                            <input name="name" required maxlength="100" class="h-10 rounded-lg border-slate-200 text-sm" placeholder="Profile name">
                            <label class="flex h-10 items-center gap-2 rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-600"><input type="checkbox" name="is_shared" value="1" class="rounded border-slate-300 text-violet-600">Share with admins</label>
                            <button class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 text-xs font-bold text-white"><i data-lucide="save" class="size-4"></i>Save profile</button>
                        </form>
                    @endif
                </div>
            </section>
        @endif

        <section class="dashboard-card overflow-hidden p-0">
            <div class="border-b border-slate-100 px-4 py-3"><h2 class="font-black text-slate-950">Row preview and results</h2><p class="text-xs text-slate-500">Review normalized values, validation problems, and duplicate decisions.</p></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1050px] text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Row</th><th class="px-4 py-3">Member</th><th class="px-4 py-3">Contact</th><th class="px-4 py-3">Campus / Family</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Matched member</th><th class="px-4 py-3">Decision / Error</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($rows as $row)
                            @php($data = $row->normalized_data ?? [])
                            <tr class="align-top hover:bg-slate-50/60">
                                <td class="px-4 py-3 font-mono text-slate-400">{{ $row->row_number }}</td>
                                <td class="px-4 py-3"><strong class="text-slate-800">{{ $data['first_name'] ?? '—' }} {{ $data['last_name'] ?? '' }}</strong><div class="mt-1 text-[10px] text-slate-400">{{ $data['external_id'] ?? 'No external ID' }}</div></td>
                                <td class="px-4 py-3 text-slate-600">{{ $data['email'] ?? 'No email' }}<br>{{ $data['phone'] ?? 'No phone' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $data['campus'] ?? 'Default campus' }}<br>{{ $data['family_name'] ?? 'No family' }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-[9px] font-bold {{ $statusTone[$row->status] ?? 'bg-slate-100 text-slate-600' }}">{{ Str::headline($row->status) }}</span></td>
                                <td class="px-4 py-3">@if($row->matchedMember)<a href="{{ route('members.show', $row->matchedMember) }}" class="font-semibold text-violet-600">{{ $row->matchedMember->first_name }} {{ $row->matchedMember->last_name }}</a>@elseif($row->importedMember)<a href="{{ route('members.show', $row->importedMember) }}" class="font-semibold text-emerald-600">Open imported member</a>@else<span class="text-slate-400">New member</span>@endif</td>
                                <td class="px-4 py-3">@if($editable && $row->status === 'duplicate')<form method="POST" action="{{ route('member-imports.rows.update', [$import, $row->id]) }}" class="flex gap-2">@csrf @method('PUT')<select name="duplicate_action" class="h-8 rounded-lg border-slate-200 text-[10px]">@foreach(['skip' => 'Skip', 'update' => 'Update', 'merge' => 'Fill missing', 'create' => 'Create separate'] as $value => $label)<option value="{{ $value }}" @selected($row->duplicate_action === $value)>{{ $label }}</option>@endforeach</select><button class="h-8 rounded-lg border border-slate-200 px-2 font-bold text-violet-600">Save</button></form>@else<span class="{{ $row->error ? 'text-rose-600' : 'text-slate-500' }}">{{ $row->error ?: Str::headline($row->duplicate_action) }}</span>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-empty-state icon="rows-3" title="No staged rows" message="This import contains no readable member records." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($rows->hasPages())<div class="border-t border-slate-100 p-4">{{ $rows->links() }}</div>@endif
        </section>
    </div>
</x-app-layout>
