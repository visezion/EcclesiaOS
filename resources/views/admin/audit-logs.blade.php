<x-app-layout title="Audit Logs" :breadcrumbs="$breadcrumbs">
    <div class="space-y-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-2xl bg-violet-100 text-violet-600"><i data-lucide="shield-check" class="size-7"></i></div>
                <div>
                    <h1 class="text-2xl font-black text-slate-950">Audit Logs</h1>
                    <p class="text-sm text-slate-500">Monitor system activity, authentication events, and access policies to ensure security and compliance.</p>
                </div>
            </div>
            <select class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option>May 19 - May 25, 2024</option><option>This Month</option></select>
        </div>

        <div class="grid gap-4 xl:grid-cols-[1fr_360px]">
            <section class="space-y-4">
                <div class="dashboard-card">
                    <div class="mb-4 grid gap-3 md:grid-cols-3 xl:grid-cols-5">
                        <select class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option>All Users</option></select>
                        <select class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option>All Roles</option></select>
                        <select class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option>All Churches</option></select>
                        <select class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option>All Campuses</option></select>
                        <select class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option>All Actions</option></select>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold">More Filters</button>
                        <button class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-bold text-white">Apply Filters</button>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-base font-black text-slate-950">Activity Logs</h2>
                        <button class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold">Export</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table-compact min-w-[1050px]">
                            <thead><tr><th>Time & Date</th><th>User</th><th>Action</th><th>Resource</th><th>Details</th><th>IP Address</th><th>Risk Level</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($logs as $log)
                                    @php($risk = $log->properties['risk'] ?? 'low')
                                    @php($status = $log->properties['status'] ?? 'success')
                                    <tr>
                                        <td>{{ $log->created_at->format('M d, Y') }}<div class="text-xs text-slate-500">{{ $log->created_at->format('h:i:s A') }}</div></td>
                                        <td class="font-bold">{{ $log->user?->name ?? 'Unknown User' }}<div class="text-xs font-normal text-slate-500">{{ $log->user?->email ?? 'system' }}</div></td>
                                        <td><span class="{{ $status === 'failed' ? 'text-rose-600' : 'text-emerald-600' }} font-bold">{{ str($log->action)->headline() }}</span></td>
                                        <td>{{ $log->properties['resource'] ?? $log->module }}</td>
                                        <td>{{ $log->description }}</td>
                                        <td>{{ $log->ip_address ?? 'local' }}</td>
                                        <td><x-status-badge :status="$risk" /></td>
                                        <td><x-status-badge :status="$status" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <aside class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <x-dashboard-card title="Security Score"><div class="text-3xl font-black">{{ $stats['security_score'] }}<span class="text-sm">/100</span></div><div class="mt-1 text-sm font-bold text-emerald-600">Excellent</div></x-dashboard-card>
                    <x-dashboard-card title="Failed Logins"><div class="text-3xl font-black">{{ $stats['failed_logins'] }}</div><div class="mt-1 text-sm font-bold text-rose-600">from DB logs</div></x-dashboard-card>
                    <x-dashboard-card title="Suspicious Activity"><div class="text-3xl font-black">{{ $stats['suspicious'] }}</div><div class="mt-1 text-sm font-bold text-orange-600">high risk</div></x-dashboard-card>
                    <x-dashboard-card title="MFA Adoption"><div class="text-3xl font-black">{{ $stats['mfa'] }}</div><div class="mt-1 text-sm font-bold text-violet-600">enabled events</div></x-dashboard-card>
                </div>
                <x-dashboard-card title="Security & Access Overview">
                    <div class="space-y-4 text-sm">
                        <div>
                            <div class="mb-2 font-black">Authentication Middleware</div>
                            <div class="flex justify-between"><span>Multi-Factor Authentication</span><strong class="text-emerald-600">Enabled</strong></div>
                            <div class="flex justify-between"><span>Password Policy</span><strong class="text-emerald-600">Strong</strong></div>
                            <div class="flex justify-between"><span>Session Management</span><strong class="text-emerald-600">Active</strong></div>
                            <div class="flex justify-between"><span>Audit Logging</span><strong class="text-emerald-600">Enabled</strong></div>
                        </div>
                        <div>
                            <div class="mb-2 font-black">Authorization Policies</div>
                            <div class="flex justify-between"><span>Role-Based Access Control</span><strong class="text-emerald-600">Enforced</strong></div>
                            <div class="flex justify-between"><span>Campus Scoping</span><strong class="text-emerald-600">Enabled</strong></div>
                            <div class="flex justify-between"><span>Session Timeout</span><strong>120 minutes</strong></div>
                        </div>
                    </div>
                </x-dashboard-card>
            </aside>
        </div>
    </div>
</x-app-layout>
