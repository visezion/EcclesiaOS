<x-app-layout title="Database Import Sources" :breadcrumbs="$breadcrumbs">
    <div class="space-y-4">
        @if(session('status'))<x-alert type="success">{{ session('status') }}</x-alert>@endif
        @if($errors->any())<x-alert type="error">{{ $errors->first() }}</x-alert>@endif

        <header class="dashboard-card">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4">
                    <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="database" class="size-6"></i></span>
                    <div><p class="text-[10px] font-black uppercase tracking-widest text-violet-600">Member Import Center</p><h1 class="text-xl font-black text-slate-950">Read-only database sources</h1><p class="mt-1 max-w-3xl text-xs leading-5 text-slate-500">Securely copy members into the review area from MySQL, PostgreSQL, SQL Server, or an uploaded SQLite database. EcclesiaOS never sends update or delete statements to these sources.</p></div>
                </div>
                <a href="{{ route('member-imports.index') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-xs font-bold text-slate-600"><i data-lucide="arrow-left" class="size-4"></i>Import center</a>
            </div>
        </header>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($drivers as $key => $driver)
                <div class="dashboard-card flex items-center justify-between gap-3"><div><strong class="text-sm text-slate-900">{{ $driver['label'] }}</strong><p class="mt-1 text-[10px] text-slate-500">{{ $key === 'sqlite' ? 'Uploaded database file' : 'Remote read-only account' }}</p></div><span class="rounded-full px-2 py-1 text-[9px] font-bold {{ $driver['available'] ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $driver['available'] ? 'Driver ready' : 'PDO extension needed' }}</span></div>
            @endforeach
        </section>

        <section class="grid gap-4 xl:grid-cols-[390px_minmax(0,1fr)]">
            <form method="POST" action="{{ route('member-import-connections.store') }}" enctype="multipart/form-data" class="dashboard-card self-start" x-data="{ driver: @js(old('driver', 'mysql')) }">
                @csrf
                <div class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-lg bg-blue-50 text-blue-600"><i data-lucide="plug-zap" class="size-4"></i></span><div><h2 class="font-black text-slate-950">Add a data source</h2><p class="text-xs text-slate-500">Use a database account with SELECT-only permission.</p></div></div>
                <div class="mt-4 space-y-3">
                    <label class="block text-xs font-bold text-slate-700">Connection name<input name="name" value="{{ old('name') }}" required class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm" placeholder="Old membership database"></label>
                    <label class="block text-xs font-bold text-slate-700">Database type<select name="driver" x-model="driver" class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm">@foreach($drivers as $key => $driver)<option value="{{ $key }}" @disabled(!$driver['available'])>{{ $driver['label'] }}{{ $driver['available'] ? '' : ' — server driver unavailable' }}</option>@endforeach</select></label>
                    <div x-show="driver !== 'sqlite'" class="space-y-3">
                        <div class="grid grid-cols-[minmax(0,1fr)_100px] gap-2"><label class="block text-xs font-bold text-slate-700">Host<input name="host" value="{{ old('host') }}" :required="driver !== 'sqlite'" class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm" placeholder="127.0.0.1"></label><label class="block text-xs font-bold text-slate-700">Port<input name="port" value="{{ old('port') }}" type="number" class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm" placeholder="Default"></label></div>
                        <label class="block text-xs font-bold text-slate-700">Database name<input name="database_name" value="{{ old('database_name') }}" :required="driver !== 'sqlite'" class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm"></label>
                        <label class="block text-xs font-bold text-slate-700">Username<input name="username" value="{{ old('username') }}" :required="driver !== 'sqlite'" autocomplete="off" class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm"></label>
                        <label class="block text-xs font-bold text-slate-700">Password<input name="password" type="password" autocomplete="new-password" class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm"></label>
                        <label x-show="driver === 'pgsql'" class="block text-xs font-bold text-slate-700">Schema<input name="schema" value="{{ old('schema', 'public') }}" class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm"></label>
                        <label class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2.5 text-xs font-bold text-slate-600">Require encrypted transport<input type="checkbox" name="encrypt" value="1" checked class="rounded border-slate-300 text-violet-600"></label>
                    </div>
                    <label x-show="driver === 'sqlite'" class="block rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 p-4 text-center text-xs font-bold text-slate-700">Upload SQLite database<input name="sqlite_file" type="file" accept=".sqlite,.sqlite3,.db" :required="driver === 'sqlite'" class="mt-3 block w-full text-[10px] font-normal text-slate-500"></label>
                    <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-3 text-[10px] leading-5 text-emerald-800"><strong class="flex items-center gap-2 text-xs"><i data-lucide="shield-check" class="size-4"></i>Source protection</strong>Credentials are encrypted. Table names are verified against database metadata, queries are capped at 25,000 rows, and no custom SQL is accepted.</div>
                    <button class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 text-xs font-bold text-white"><i data-lucide="save" class="size-4"></i>Save data source</button>
                </div>
            </form>

            <div class="space-y-4">
                @if($selected)
                    <section class="dashboard-card">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><div class="flex items-center gap-2"><h2 class="font-black text-slate-950">{{ $selected->name }}</h2><span class="rounded-full px-2 py-1 text-[9px] font-bold {{ $selected->last_test_status === 'success' ? 'bg-emerald-50 text-emerald-700' : ($selected->last_test_status === 'failed' ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600') }}">{{ $selected->last_test_status ? Str::headline($selected->last_test_status) : 'Not tested' }}</span></div><p class="mt-1 text-xs text-slate-500">{{ $drivers[$selected->driver]['label'] }} · {{ $selected->driver === 'sqlite' ? 'Private uploaded file' : $selected->host.':'.$selected->port.'/'.$selected->database_name }}</p></div><div class="flex gap-2"><form method="POST" action="{{ route('member-import-connections.test', $selected) }}">@csrf<button class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-700"><i data-lucide="activity" class="size-4"></i>Test connection</button></form><form method="POST" action="{{ route('member-import-connections.destroy', $selected) }}" onsubmit="return confirm('Remove this data source? Existing staged imports will remain.')">@csrf @method('DELETE')<button class="grid size-9 place-items-center rounded-lg border border-rose-200 text-rose-600"><i data-lucide="trash-2" class="size-4"></i></button></form></div></div>
                        @if($connectionError)<div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700">{{ $connectionError }}</div>@elseif($tables === [])<div class="mt-4 rounded-lg bg-slate-50 p-3 text-xs text-slate-500">No importable tables were found.</div>@else
                            <form method="POST" action="{{ route('member-import-connections.stage', $selected) }}" class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2">
                                @csrf
                                <label class="text-xs font-bold text-slate-700">Source table<select name="table" required class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm"><option value="">Select a table</option>@foreach($tables as $table)<option value="{{ $table }}">{{ $table }}</option>@endforeach</select></label>
                                <label class="text-xs font-bold text-slate-700">Default campus<select name="default_campus_id" required class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm">@foreach($campuses as $campus)<option value="{{ $campus->id }}" @selected(auth()->user()->campus_id === $campus->id)>{{ $campus->name }}</option>@endforeach</select></label>
                                <label class="text-xs font-bold text-slate-700 sm:col-span-2">Import name<input name="name" class="mt-1.5 h-10 w-full rounded-lg border-slate-200 text-sm" placeholder="{{ $selected->name }} member migration"></label>
                                <button class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 text-xs font-bold text-white sm:col-span-2"><i data-lucide="scan-search" class="size-4"></i>Copy rows to review area</button>
                            </form>
                        @endif
                    </section>
                @endif

                <section class="dashboard-card overflow-hidden p-0">
                    <div class="border-b border-slate-100 px-4 py-3"><h2 class="font-black text-slate-950">Saved data sources</h2><p class="text-xs text-slate-500">{{ $connections->count() }} secure connection{{ $connections->count() === 1 ? '' : 's' }}</p></div>
                    <div class="divide-y divide-slate-100">
                        @forelse($connections as $connection)
                            <a href="{{ route('member-import-connections.show', $connection) }}" class="flex items-center justify-between gap-4 px-4 py-3 hover:bg-slate-50"><div class="flex min-w-0 items-center gap-3"><span class="grid size-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-600"><i data-lucide="database" class="size-4"></i></span><div class="min-w-0"><strong class="block truncate text-xs text-slate-900">{{ $connection->name }}</strong><span class="text-[10px] text-slate-400">{{ $drivers[$connection->driver]['label'] }} · added {{ $connection->created_at->diffForHumans() }}</span></div></div><i data-lucide="chevron-right" class="size-4 text-slate-400"></i></a>
                        @empty
                            <x-empty-state icon="database" title="No database sources" message="Add a read-only connection or upload an SQLite database." />
                        @endforelse
                    </div>
                </section>
            </div>
        </section>
    </div>
</x-app-layout>
