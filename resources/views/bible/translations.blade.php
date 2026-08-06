<x-app-layout title="Bible Translations" :breadcrumbs="$breadcrumbs" main-class="px-4 py-5 sm:px-6 lg:px-7">
    @php($churchId = auth()->user()->church_id)
    @php($installed = $translations->where('church_id', $churchId))

    <div x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }" class="space-y-5">
        @include('bible._tabs')

        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-black text-slate-950">Bible Translations</h1>
                <p class="mt-1 text-sm text-slate-500">Manage free and church-specific Bible versions.</p>
            </div>
            <button type="button" @click="open = true" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-5 py-3 text-sm font-bold text-white">
                <i data-lucide="plus" class="size-4"></i>
                Add Translation
            </button>
        </div>

        @if(session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>
        @endif

        <section class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold text-slate-500">Available versions</p>
                <p class="mt-1 text-2xl font-black">{{ $translations->whereNull('church_id')->count() }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold text-slate-500">Installed for church</p>
                <p class="mt-1 text-2xl font-black">{{ $installed->count() }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold text-slate-500">Default</p>
                <p class="mt-1 text-2xl font-black">{{ $installed->firstWhere('is_default', true)?->abbreviation ?? '—' }}</p>
            </div>
        </section>

        <section class="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50/60 p-4 text-sm text-blue-950">
            <i data-lucide="info" class="mt-0.5 size-5 shrink-0 text-blue-600"></i>
            <div>
                <h2 class="font-black">Free catalog included by default</h2>
                <p class="mt-1 text-xs text-slate-600">Install versions for this church or uninstall editions that are no longer needed. The active default translation must be changed before it can be uninstalled.</p>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            @foreach($translations as $translation)
                @php($installedVersion = $translation->church_id === $churchId ? $translation : $installed->firstWhere('abbreviation', $translation->abbreviation))
                @php($isInstalled = $installedVersion !== null)

                <article class="flex min-h-[150px] flex-col justify-between rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <span class="grid size-14 place-items-center rounded-lg bg-violet-50 text-sm font-black text-violet-700">{{ $translation->abbreviation }}</span>
                            <div>
                                <h2 class="font-black text-slate-950">
                                    {{ $translation->name }}
                                    @if($translation->is_default)
                                        <span class="ml-1 rounded bg-emerald-100 px-2 py-1 text-[10px] font-black text-emerald-700">Default</span>
                                    @endif
                                    @if($isInstalled && ! $translation->church_id)
                                        <span class="ml-1 inline-flex items-center gap-1 rounded bg-emerald-100 px-2 py-1 text-[10px] font-black text-emerald-700">
                                            <i data-lucide="circle-check" class="size-3"></i>
                                            Installed
                                        </span>
                                    @endif
                                </h2>
                                <span class="mt-1 inline-block rounded bg-violet-50 px-2 py-1 text-[10px] font-bold text-violet-700">{{ $translation->church_id ? 'Church version' : 'Free catalog' }}</span>
                                <p class="mt-2 text-xs text-slate-500">{{ $translation->language }} · {{ number_format($translation->verses_count) }} verses</p>
                            </div>
                        </div>

                        @if(! $translation->church_id)
                            <div>
                                @if($isInstalled && $installedVersion->is_default)
                                    <span class="inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-700" title="Choose another default translation before uninstalling">
                                        <i data-lucide="lock-keyhole" class="size-3.5"></i>
                                        Active default
                                    </span>
                                @elseif($isInstalled)
                                    <form method="POST" action="{{ route('bible.translations.uninstall', $translation) }}" onsubmit="return confirm('Uninstall {{ addslashes($translation->name) }}? Its verses and related saved Bible study content for this church will be removed.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 transition hover:bg-rose-100">
                                            <i data-lucide="trash-2" class="size-3.5"></i>
                                            Uninstall
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('bible.translations.install', $translation) }}">
                                        @csrf
                                        <button class="inline-flex items-center gap-2 rounded-lg border border-violet-300 px-3 py-2 text-xs font-bold text-violet-700">
                                            <i data-lucide="download" class="size-3.5"></i>
                                            Install for church
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3">
                        <a href="{{ $translation->source_url ?: '#' }}" target="_blank" rel="noopener noreferrer" class="text-xs font-bold text-violet-700">
                            Source and license
                            <i data-lucide="external-link" class="inline size-3"></i>
                        </a>

                        @if($translation->church_id === $churchId)
                            <div class="flex items-center gap-2">
                                <form method="POST" enctype="multipart/form-data" action="{{ route('bible.translations.import', $translation) }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="file" name="file" accept=".csv,.json,.txt" required class="hidden" id="file-{{ $translation->id }}">
                                    <label for="file-{{ $translation->id }}" class="cursor-pointer text-xs font-bold text-slate-500">Choose file</label>
                                    <button class="rounded-lg border border-violet-200 px-2 py-1.5 text-xs font-bold text-violet-700">Import</button>
                                </form>
                                @if(! $translation->is_default)
                                    <form method="POST" action="{{ route('bible.translations.destroy', $translation) }}" onsubmit="return confirm('Uninstall {{ addslashes($translation->name) }}? Its verses and related saved Bible study content will be removed.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-xs font-bold text-rose-600">Uninstall</button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-black">Advanced import</h2>
            <p class="mt-1 text-sm text-slate-500">Import permitted verse files into an installed church translation.</p>
            <form method="POST" enctype="multipart/form-data" action="#" class="mt-4 grid gap-3 md:grid-cols-[1fr_1fr_auto]">
                @csrf
                <select name="translation_target" onchange="this.form.action=this.options[this.selectedIndex].dataset.action" class="h-11 rounded-lg border border-slate-200 px-3 text-sm" required>
                    <option value="">Select installed translation</option>
                    @foreach($installed as $item)
                        <option value="{{ $item->id }}" data-action="{{ route('bible.translations.import', $item) }}">{{ $item->abbreviation }} — {{ $item->name }}</option>
                    @endforeach
                </select>
                <input type="file" name="file" accept=".csv,.json,.txt" required class="h-11 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <button class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-bold text-white">Upload File</button>
            </form>
            <a href="{{ route('bible.translations.sample') }}" class="mt-3 inline-flex items-center gap-2 text-xs font-bold text-violet-700">
                <i data-lucide="download" class="size-3.5"></i>
                Download Sample
            </a>
        </section>

        <div x-cloak x-show="open" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4">
            <form method="POST" action="{{ route('bible.translations.store') }}" class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl">
                @csrf
                <h2 class="text-xl font-black">Add Bible Translation</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="text-sm font-bold">Name<input name="name" value="{{ old('name') }}" required class="mt-1.5 w-full rounded-lg border px-3 py-2.5"></label>
                    <label class="text-sm font-bold">Abbreviation<input name="abbreviation" value="{{ old('abbreviation') }}" required class="mt-1.5 w-full rounded-lg border px-3 py-2.5"></label>
                    <label class="text-sm font-bold">Language<input name="language" value="{{ old('language', 'English') }}" required class="mt-1.5 w-full rounded-lg border px-3 py-2.5"></label>
                    <label class="text-sm font-bold">Source URL<input name="source_url" value="{{ old('source_url') }}" type="url" class="mt-1.5 w-full rounded-lg border px-3 py-2.5"></label>
                    <label class="text-sm font-bold sm:col-span-2">Copyright / License<input name="copyright" value="{{ old('copyright') }}" required class="mt-1.5 w-full rounded-lg border px-3 py-2.5"></label>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="open = false" class="rounded-lg border px-4 py-2.5 text-sm font-bold">Cancel</button>
                    <button class="rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-bold text-white">Add Translation</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
