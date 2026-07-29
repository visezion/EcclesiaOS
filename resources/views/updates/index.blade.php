<x-app-layout title="System Updates" :breadcrumbs="$breadcrumbs">
    @php
        $statusTones = [
            'detected' => 'bg-blue-50 text-blue-700',
            'pending' => 'bg-amber-50 text-amber-700',
            'installing' => 'bg-violet-50 text-violet-700',
            'completed' => 'bg-emerald-50 text-emerald-700',
            'failed' => 'bg-rose-50 text-rose-700',
            'rolled_back' => 'bg-orange-50 text-orange-700',
            'skipped' => 'bg-slate-100 text-slate-600',
        ];
    @endphp

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{{ session('error') }}</div>
        @endif

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-200 p-6 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <span class="grid size-11 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="refresh-cw" class="size-5"></i></span>
                        <div>
                            <h1 class="text-2xl font-black text-slate-950">System Updates</h1>
                            <p class="mt-1 text-sm text-slate-500">Current version: <span class="font-semibold text-slate-800">v{{ $currentVersion }}</span></p>
                        </div>
                    </div>
                </div>
                <form method="POST" action="{{ route('system-updates.check') }}">
                    @csrf
                    <button class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-950 px-4 py-3 text-sm font-bold text-white hover:bg-slate-800">
                        <i data-lucide="cloud-download" class="size-4"></i>
                        Check GitHub
                    </button>
                </form>
            </div>

            @if ($availableUpdate)
                <div class="grid gap-6 p-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-bold text-violet-700">Update available</span>
                            <span class="text-sm text-slate-500">v{{ $currentVersion }} to v{{ $availableUpdate->version }}</span>
                        </div>
                        <h2 class="mt-4 text-xl font-black text-slate-950">{{ $availableUpdate->name ?: 'Version '.$availableUpdate->version }}</h2>
                        <div class="mt-4 rounded-xl bg-slate-50 p-5">
                            <h3 class="text-sm font-bold text-slate-900">Changelog</h3>
                            <div class="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-600">{{ $availableUpdate->changelog ?: 'No release notes were supplied.' }}</div>
                        </div>
                        @if ($availableUpdate->release_url)
                            <a href="{{ $availableUpdate->release_url }}" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-violet-700 hover:text-violet-800">
                                View GitHub release
                                <i data-lucide="external-link" class="size-4"></i>
                            </a>
                        @endif
                    </div>

                    <aside class="rounded-xl border border-slate-200 p-5">
                        <h3 class="text-sm font-black text-slate-950">Installation readiness</h3>
                        <div class="mt-4 space-y-3">
                            @foreach ($diagnostics['checks'] as $check)
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full {{ $check['ready'] ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600' }}">
                                        <i data-lucide="{{ $check['ready'] ? 'check' : 'triangle-alert' }}" class="size-3.5"></i>
                                    </span>
                                    <div>
                                        <div class="text-sm font-semibold text-slate-800">{{ $check['label'] }}</div>
                                        @unless ($check['ready'])
                                            <div class="mt-0.5 text-xs leading-5 text-slate-500">{{ $check['message'] }}</div>
                                        @endunless
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($diagnostics['ready'] && $availableUpdate->status !== 'pending')
                            <form method="POST" action="{{ route('system-updates.approve', $availableUpdate) }}" class="mt-6 space-y-4">
                                @csrf
                                <label class="block">
                                    <span class="text-xs font-bold text-slate-700">Current password</span>
                                    <input type="password" name="current_password" required autocomplete="current-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-bold text-slate-700">Type UPDATE {{ $availableUpdate->version }}</span>
                                    <input name="confirmation" required autocomplete="off" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                                </label>
                                <button class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-3 text-sm font-bold text-white hover:bg-violet-700">
                                    <i data-lucide="shield-check" class="size-4"></i>
                                    Approve Update
                                </button>
                            </form>
                        @elseif ($availableUpdate->status === 'pending')
                            <div class="mt-6 rounded-lg bg-amber-50 p-4 text-sm font-semibold text-amber-800">Approved and waiting for the background updater.</div>
                        @else
                            <div class="mt-6 rounded-lg bg-slate-100 p-4 text-xs leading-5 text-slate-600">Release checking is active, but installation remains locked until the managed production layout is configured.</div>
                        @endif

                        @if (! in_array($availableUpdate->status, ['pending', 'installing'], true))
                            <form method="POST" action="{{ route('system-updates.skip', $availableUpdate) }}" class="mt-3">
                                @csrf
                                <button class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">Dismiss this version</button>
                            </form>
                        @endif
                    </aside>
                </div>
            @else
                <div class="grid min-h-52 place-items-center p-8 text-center">
                    <div>
                        <span class="mx-auto grid size-14 place-items-center rounded-full bg-emerald-50 text-emerald-600"><i data-lucide="badge-check" class="size-7"></i></span>
                        <h2 class="mt-4 text-lg font-black text-slate-950">No update is waiting</h2>
                        <p class="mt-2 text-sm text-slate-500">Check GitHub to confirm that this installation is current.</p>
                    </div>
                </div>
            @endif
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-black text-slate-950">Update history</h2>
            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-3">Version</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3">Approved by</th>
                            <th class="px-3 py-3">Detected</th>
                            <th class="px-3 py-3">Installed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($updates as $update)
                            <tr>
                                <td class="px-3 py-4 font-bold text-slate-900">v{{ $update->version }}</td>
                                <td class="px-3 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $statusTones[$update->status] ?? 'bg-slate-100 text-slate-600' }}">{{ str($update->status)->headline() }}</span></td>
                                <td class="px-3 py-4 text-slate-600">{{ $update->approver?->name ?? 'Not approved' }}</td>
                                <td class="px-3 py-4 text-slate-600">{{ $update->detected_at?->format('M d, Y H:i') ?? 'Unknown' }}</td>
                                <td class="px-3 py-4 text-slate-600">{{ $update->installed_at?->format('M d, Y H:i') ?? 'Not installed' }}</td>
                            </tr>
                            @if ($update->error)
                                <tr><td colspan="5" class="bg-rose-50 px-3 py-3 text-xs text-rose-700">{{ $update->error }}</td></tr>
                            @endif
                        @empty
                            <tr><td colspan="5" class="px-3 py-8 text-center text-slate-500">No update checks have been recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-5">{{ $updates->links() }}</div>
        </section>
    </div>
</x-app-layout>
