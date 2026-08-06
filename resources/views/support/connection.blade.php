<x-app-layout title="Central Support Connection" :breadcrumbs="$breadcrumbs">
    @php
        $statusTone = match($connection['last_test_status']) {
            'success' => 'bg-emerald-50 text-emerald-700',
            'failed' => 'bg-rose-50 text-rose-700',
            default => 'bg-slate-100 text-slate-600',
        };
    @endphp

    <div class="space-y-5">
        <header class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-center gap-4">
                <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-violet-50 text-violet-600"><i data-lucide="radio-tower" class="size-7"></i></span>
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wide text-violet-600">Support Center</p>
                    <h1 class="text-2xl font-black text-slate-950">Central Support Connection</h1>
                    <p class="mt-0.5 text-sm text-slate-500">Connect {{ $church->name }} securely to the EcclesiaOS support network.</p>
                </div>
            </div>
            <a href="{{ route('support.index') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"><i data-lucide="arrow-left" class="size-4"></i>Support Center</a>
        </header>

        <x-support-nav />

        @if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>@endif
        @if(session('error_status'))<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{ session('error_status') }}</div>@endif
        @if($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>@endif

        @if($grantToken)
            <section x-data="{ copied: false }" class="dashboard-card border-l-4" style="border-left-color: var(--brand-primary);">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="key-round" class="size-5"></i></span>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-black text-slate-950">One-time support grant</h2>
                        <p class="mt-1 text-xs text-slate-500">Copy this code into the central support server. It is stored only as a hash and cannot be displayed again.</p>
                        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                            <code class="min-w-0 flex-1 break-all rounded-lg bg-slate-950 px-3 py-2.5 text-xs text-white">{{ $grantToken }}</code>
                            <button type="button" x-on:click="navigator.clipboard.writeText(@js($grantToken)); copied = true; setTimeout(() => copied = false, 1800)" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 text-xs font-bold text-white"><i data-lucide="copy" class="size-4"></i><span x-text="copied ? 'Copied' : 'Copy code'">Copy code</span></button>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="dashboard-card"><div class="flex items-start gap-3"><span class="grid size-9 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="settings" class="size-4"></i></span><div class="min-w-0"><h2 class="text-xs font-black text-slate-900">Installation</h2><p class="mt-2 truncate font-mono text-[10px] text-slate-500">{{ $connection['installation_id'] ?: 'Not registered' }}</p><p class="mt-1 text-[10px] font-bold text-slate-600">{{ $church->name }}</p></div></div></div>
            <div class="dashboard-card"><div class="flex items-start gap-3"><span class="grid size-9 place-items-center rounded-lg bg-blue-50 text-blue-600"><i data-lucide="globe-2" class="size-4"></i></span><div class="min-w-0"><h2 class="text-xs font-black text-slate-900">Central server</h2><p class="mt-2 truncate text-[10px] text-slate-500">{{ $connection['endpoint'] }}</p><p class="mt-1 text-[10px] font-bold text-slate-600">HTTPS · Production</p></div></div></div>
            <div class="dashboard-card"><div class="flex items-start gap-3"><span class="grid size-9 place-items-center rounded-lg bg-amber-50 text-amber-600"><i data-lucide="key-round" class="size-4"></i></span><div><h2 class="text-xs font-black text-slate-900">API credentials</h2><p class="mt-2 text-[10px] text-slate-500">{{ $connection['api_token_configured'] ? 'Encrypted token saved' : 'Token required' }}</p><p class="mt-1 text-[10px] font-bold {{ $connection['api_token_configured'] ? 'text-emerald-600' : 'text-amber-600' }}">{{ $connection['api_token_configured'] ? 'Configured' : 'Not configured' }}</p></div></div></div>
            <div class="dashboard-card"><div class="flex items-start gap-3"><span class="grid size-9 place-items-center rounded-lg {{ $connection['last_test_status'] === 'success' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500' }}"><i data-lucide="radio-tower" class="size-4"></i></span><div><h2 class="text-xs font-black text-slate-900">Connection status</h2><p class="mt-2 text-[10px] text-slate-500">{{ $connection['last_test_message'] ?: 'Run a connection test' }}</p><p class="mt-1 text-[10px] font-bold {{ $connection['last_test_status'] === 'success' ? 'text-emerald-600' : 'text-slate-500' }}">{{ $connection['last_test_status'] ? str($connection['last_test_status'])->headline() : 'Not tested' }}</p></div></div></div>
        </section>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
            <main class="space-y-5">
                <section class="dashboard-card">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-base font-black text-slate-950">Connection settings</h2>
                            <p class="mt-1 text-xs text-slate-500">The destination is fixed to the official support server and cannot be redirected from this page.</p>
                        </div>
                        <span class="inline-flex items-center gap-2 self-start rounded-full px-3 py-1 text-xs font-bold {{ $statusTone }}"><span class="size-2 rounded-full bg-current"></span>{{ $connection['last_test_status'] ? str($connection['last_test_status'])->headline() : 'Not tested' }}</span>
                    </div>

                    <form method="POST" action="{{ route('central-support.update') }}" class="mt-5 space-y-4">
                        @csrf
                        @method('PUT')
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="text-sm font-bold text-slate-700">Official support server
                                <input value="{{ $connection['endpoint'] }}" readonly class="mt-1.5 h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-600">
                            </label>
                            <label class="text-sm font-bold text-slate-700">Installation ID
                                <input value="{{ $connection['installation_id'] ?: 'Created when settings are saved' }}" readonly class="mt-1.5 h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 font-mono text-xs text-slate-600">
                            </label>
                        </div>
                        <label class="block text-sm font-bold text-slate-700">Installation API token
                            <input name="api_token" type="password" autocomplete="new-password" class="mt-1.5 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm" placeholder="{{ $connection['api_token_configured'] ? 'Saved securely · ending '.$connection['api_token_last_four'] : 'Paste the token issued by the central server' }}">
                            <span class="mt-1 block text-[10px] font-normal text-slate-500">Leave blank to keep the existing encrypted token.</span>
                        </label>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4">
                                <input type="hidden" name="enabled" value="0">
                                <input type="checkbox" name="enabled" value="1" @checked($connection['enabled']) class="mt-0.5 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                                <span><span class="block text-sm font-bold text-slate-800">Central ticket synchronization</span><span class="mt-1 block text-xs leading-5 text-slate-500">Queue tickets and updates for secure delivery to the central server.</span></span>
                            </label>
                            <label class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50/50 p-4">
                                <input type="hidden" name="remote_access_enabled" value="0">
                                <input type="checkbox" name="remote_access_enabled" value="1" @checked($connection['remote_access_enabled']) class="mt-0.5 rounded border-amber-300 text-violet-600 focus:ring-violet-500">
                                <span><span class="block text-sm font-bold text-slate-800">Allow approved remote support</span><span class="mt-1 block text-xs leading-5 text-slate-500">Access still requires a new one-time, expiring grant from a local administrator.</span></span>
                            </label>
                        </div>
                        <div class="flex flex-wrap justify-end gap-2">
                            <button class="inline-flex h-10 items-center gap-2 rounded-lg bg-violet-600 px-4 text-sm font-bold text-white hover:bg-violet-700"><i data-lucide="save" class="size-4"></i>Save connection</button>
                        </div>
                    </form>
                </section>

                <section class="dashboard-card">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div><h2 class="font-black text-slate-950">Synchronization queue</h2><p class="mt-1 text-xs text-slate-500">Tickets remain safely queued when the central server is unavailable.</p></div>
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('central-support.test') }}">@csrf<button class="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-200 px-4 text-xs font-bold text-slate-700"><i data-lucide="plug-zap" class="size-4"></i>Test connection</button></form>
                            <form method="POST" action="{{ route('central-support.sync') }}">@csrf<button class="inline-flex h-10 items-center gap-2 rounded-lg bg-slate-950 px-4 text-xs font-bold text-white"><i data-lucide="refresh-cw" class="size-4"></i>Sync now</button></form>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        @foreach([['Pending', $syncStats['pending'], 'text-amber-700 bg-amber-50'], ['Synchronized', $syncStats['synced'], 'text-emerald-700 bg-emerald-50'], ['Failed', $syncStats['failed'], 'text-rose-700 bg-rose-50']] as [$label, $value, $tone])
                            <div class="rounded-xl border border-slate-200 p-3"><div class="text-2xl font-black text-slate-950">{{ number_format($value) }}</div><span class="mt-2 inline-flex rounded-full px-2 py-1 text-[10px] font-bold {{ $tone }}">{{ $label }}</span></div>
                        @endforeach
                    </div>
                </section>

                <section class="dashboard-card">
                    <div><h2 class="font-black text-slate-950">Remote support access history</h2><p class="mt-1 text-xs text-slate-500">Every grant, agent, expiry and revocation is recorded locally.</p></div>
                    <div class="mt-4 overflow-x-auto">
                        <table class="table-compact min-w-[760px]">
                            <thead><tr><th>Agent</th><th>Approved by</th><th>Status</th><th>Expires</th><th>Last activity</th><th class="text-right">Action</th></tr></thead>
                            <tbody>
                                @forelse($sessions as $access)
                                    <tr>
                                        <td><div class="font-bold text-slate-800">{{ $access->agent_name ?: 'Awaiting central server' }}</div><div class="text-[10px] text-slate-500">{{ $access->agent_email ?: 'One-time grant not exchanged' }}</div></td>
                                        <td>{{ $access->approver?->name ?? 'Former admin' }}</td>
                                        <td><span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600">{{ str($access->status)->headline() }}</span></td>
                                        <td>{{ $access->expires_at->format('M d, Y H:i') }}</td>
                                        <td>{{ $access->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                                        <td class="text-right">
                                            @if($access->isUsable())
                                                <form method="POST" action="{{ route('central-support.grants.revoke', $access) }}">@csrf @method('DELETE')<button class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-bold text-rose-600">Revoke</button></form>
                                            @else
                                                <span class="text-xs text-slate-400">Ended</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="py-8 text-center text-sm text-slate-500">No remote support access has been granted.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>

            <aside class="space-y-4 xl:sticky xl:top-24 xl:h-fit">
                <section class="dashboard-card border-t-4" style="border-top-color: var(--brand-primary);">
                    <span class="grid size-10 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="shield-check" class="size-5"></i></span>
                    <h2 class="mt-3 font-black text-slate-950">Create temporary access</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Only create a grant while actively working with an identified central support agent.</p>
                    <form method="POST" action="{{ route('central-support.grants.store') }}" class="mt-4 space-y-3">
                        @csrf
                        <label class="block text-xs font-bold text-slate-700">Access duration
                            <select name="duration" class="mt-1.5 h-10 w-full rounded-lg border border-slate-200 px-3 text-sm">
                                <option value="30">30 minutes</option><option value="60">1 hour</option><option value="120">2 hours</option><option value="240">4 hours</option><option value="15">15 minutes</option>
                            </select>
                        </label>
                        <label class="block text-xs font-bold text-slate-700">Reason for access
                            <textarea name="reason" required rows="3" class="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Ticket reference and problem being investigated"></textarea>
                        </label>
                        <button class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 text-xs font-bold text-white hover:bg-violet-700"><i data-lucide="key-round" class="size-4"></i>Create one-time grant</button>
                    </form>
                </section>

                <section class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <h3 class="flex items-center gap-2 text-sm font-black text-amber-900"><i data-lucide="triangle-alert" class="size-4"></i>Security rules</h3>
                    <ul class="mt-3 space-y-2 text-xs leading-5 text-amber-800">
                        <li>Access is never permanent.</li><li>The grant works only once.</li><li>The session ends automatically at expiry.</li><li>A local administrator can revoke it immediately.</li><li>All actions use a named support-agent audit identity.</li>
                    </ul>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
