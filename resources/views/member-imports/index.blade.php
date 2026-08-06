<x-app-layout title="Member Import Center" :breadcrumbs="$breadcrumbs">
    @php
        $statusTone = [
            'draft' => 'bg-slate-100 text-slate-700',
            'ready' => 'bg-blue-50 text-blue-700',
            'queued' => 'bg-amber-50 text-amber-700',
            'processing' => 'bg-violet-50 text-violet-700',
            'completed' => 'bg-emerald-50 text-emerald-700',
            'completed_with_errors' => 'bg-orange-50 text-orange-700',
            'rolled_back' => 'bg-slate-100 text-slate-600',
            'rollback_with_conflicts' => 'bg-rose-50 text-rose-700',
        ];
    @endphp

    <div class="space-y-4">
        @if(session('status'))<x-alert type="success">{{ session('status') }}</x-alert>@endif
        @if($errors->any())<x-alert type="error">{{ $errors->first() }}</x-alert>@endif

        <header class="relative overflow-hidden rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="absolute -right-10 -top-20 size-56 rounded-full bg-violet-50"></div>
            <div class="relative flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4">
                    <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-violet-50 text-violet-600"><i data-lucide="database-zap" class="size-7"></i></span>
                    <div><p class="text-xs font-bold uppercase tracking-wide text-violet-600">Members Management</p><h1 class="text-2xl font-black text-slate-950">Member Import Center</h1><p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">Map, preview, validate, deduplicate, import, and safely roll back member data from files and external systems.</p></div>
                </div>
                <a href="{{ route('members.index') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-xs font-bold text-slate-600 hover:bg-slate-50"><i data-lucide="arrow-left" class="size-4"></i>Member directory</a>
            </div>
        </header>

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['CSV & Excel', 'Automatic column mapping and row preview', 'file-spreadsheet', 'bg-emerald-50 text-emerald-600', true],
                ['JSON, XML & ZIP', 'Structured data, families and profile images', 'archive', 'bg-blue-50 text-blue-600', true],
                ['Databases', 'Read-only MySQL, PostgreSQL, SQL Server and SQLite', 'database', 'bg-violet-50 text-violet-600', true],
                ['Legacy EcclesiaOS', 'Members, profiles, families and history', 'history', 'bg-amber-50 text-amber-600', true],
            ] as [$title, $description, $icon, $tone, $enabled])
                <article class="dashboard-card"><div class="flex items-start gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-xl {{ $tone }}"><i data-lucide="{{ $icon }}" class="size-5"></i></span><div><h2 class="text-sm font-black text-slate-900">{{ $title }}</h2><p class="mt-1 text-xs leading-5 text-slate-500">{{ $description }}</p></div></div></article>
            @endforeach
        </section>

        <section class="grid gap-4 xl:grid-cols-[420px_minmax(0,1fr)]">
            <form method="POST" action="{{ route('member-imports.files.store') }}" enctype="multipart/form-data" class="dashboard-card self-start">
                @csrf
                <div class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="upload" class="size-4"></i></span><div><h2 class="font-black text-slate-950">Start a file import</h2><p class="text-xs text-slate-500">CSV, XLSX or XLS up to 50 MB</p></div></div>
                <div class="mt-4 space-y-3">
                    <label class="block text-xs font-bold text-slate-700">Import name<input name="name" value="{{ old('name') }}" class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm" placeholder="Example: Legacy database export"></label>
                    <label class="block text-xs font-bold text-slate-700">Default campus *<select name="default_campus_id" required class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm"><option value="">Select campus</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected((string) old('default_campus_id', auth()->user()->campus_id) === (string) $campus->id)>{{ $campus->name }}</option>@endforeach</select></label>
                    <label class="block text-xs font-bold text-slate-700">Saved mapping profile<select name="profile_id" class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm"><option value="">Use automatic mapping</option>@foreach($profiles as $profile)<option value="{{ $profile->id }}" @selected((string) old('profile_id') === (string) $profile->id)>{{ $profile->name }}</option>@endforeach</select></label>
                    <label class="flex min-h-36 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-5 py-6 text-center hover:border-violet-400 hover:bg-violet-50/30"><span class="grid size-10 place-items-center rounded-full bg-white text-violet-600 shadow-sm"><i data-lucide="file-up" class="size-5"></i></span><span class="mt-2 text-xs font-black text-slate-800">Choose a member data file</span><span class="mt-1 text-[10px] text-slate-400">CSV, Excel workbook, or tabular text</span><input name="members_file" type="file" accept=".csv,.txt,.xlsx,.xls" required class="mt-3 max-w-full text-[10px] text-slate-500"></label>
                    <button class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 text-xs font-bold text-white hover:bg-violet-700"><i data-lucide="scan-search" class="size-4"></i>Analyze and preview</button>
                </div>
            </form>

            <section class="dashboard-card overflow-hidden p-0">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3"><div><h2 class="font-black text-slate-950">Import history</h2><p class="text-xs text-slate-500">{{ number_format($imports->total()) }} staged and completed imports</p></div></div>
                <div class="divide-y divide-slate-100">
                    @forelse($imports as $import)
                        <a href="{{ route('member-imports.show', $import) }}" class="grid gap-3 px-4 py-4 transition hover:bg-slate-50 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                            <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="font-mono text-[10px] font-bold text-violet-600">{{ $import->reference }}</span><span class="rounded-full px-2 py-1 text-[9px] font-bold {{ $statusTone[$import->status] ?? 'bg-slate-100 text-slate-600' }}">{{ Str::headline($import->status) }}</span></div><h3 class="mt-1 truncate text-sm font-bold text-slate-900">{{ $import->name }}</h3><p class="mt-1 text-[10px] text-slate-400">{{ Str::upper($import->source_type) }} · {{ number_format($import->total_rows) }} rows · {{ $import->created_at->format('M d, Y g:i A') }}</p></div>
                            <div class="grid grid-cols-4 gap-3 text-center text-[10px]"><div><strong class="block text-sm text-emerald-600">{{ $import->created_rows }}</strong>Created</div><div><strong class="block text-sm text-blue-600">{{ $import->updated_rows }}</strong>Updated</div><div><strong class="block text-sm text-slate-600">{{ $import->skipped_rows }}</strong>Skipped</div><div><strong class="block text-sm text-rose-600">{{ $import->failed_rows }}</strong>Failed</div></div>
                        </a>
                    @empty
                        <x-empty-state icon="database-zap" title="No member imports yet" message="Upload a CSV or Excel file to begin a reviewed import." />
                    @endforelse
                </div>
                @if($imports->hasPages())<div class="border-t border-slate-100 p-4">{{ $imports->links() }}</div>@endif
            </section>
        </section>
    </div>
</x-app-layout>
