<x-app-layout title="Developer Hub" :breadcrumbs="$breadcrumbs">
    <div class="space-y-5">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-950">Developer Hub</h1>
                <p class="mt-1 max-w-3xl text-sm text-slate-500">Technical documentation for contributing to KingdomHub, adding modules, extending workflows, and keeping the application enterprise-ready.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="#new-server-deployment" class="inline-flex items-center justify-center gap-2 rounded-lg border border-violet-200 bg-white px-4 py-2.5 text-sm font-semibold text-violet-700 hover:bg-violet-50">
                    <i data-lucide="cloud-download" class="size-4"></i>
                    Deploy Server
                </a>
                <a href="#module-process" class="inline-flex items-center justify-center gap-2 rounded-lg border border-violet-200 bg-white px-4 py-2.5 text-sm font-semibold text-violet-700 hover:bg-violet-50">
                    <i data-lucide="layout-grid" class="size-4"></i>
                    Add Module
                </a>
                <a href="#quality" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                    <i data-lucide="check-circle-2" class="size-4"></i>
                    Quality Gates
                </a>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <section class="dashboard-card">
                <div class="flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-xl bg-violet-50 text-violet-600 ring-1 ring-violet-100"><i data-lucide="braces" class="size-5"></i></span>
                    <div>
                        <div class="text-xs font-semibold text-slate-500">Framework</div>
                        <div class="text-xl font-bold text-slate-950">Laravel 12</div>
                    </div>
                </div>
                <p class="mt-3 text-sm text-slate-500">Blade, Eloquent, middleware, policies, services, Vite, Tailwind, Alpine, Chart.js, and Lucide icons.</p>
            </section>
            <section class="dashboard-card">
                <div class="flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100"><i data-lucide="shield-check" class="size-5"></i></span>
                    <div>
                        <div class="text-xs font-semibold text-slate-500">Access Model</div>
                        <div class="text-xl font-bold text-slate-950">RBAC</div>
                    </div>
                </div>
                <p class="mt-3 text-sm text-slate-500">Users, roles, permissions, policies, church/campus scope, activity logging, and module availability checks.</p>
            </section>
            <section class="dashboard-card">
                <div class="flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-xl bg-blue-50 text-blue-600 ring-1 ring-blue-100"><i data-lucide="database" class="size-5"></i></span>
                    <div>
                        <div class="text-xs font-semibold text-slate-500">Persistence</div>
                        <div class="text-xl font-bold text-slate-950">Eloquent</div>
                    </div>
                </div>
                <p class="mt-3 text-sm text-slate-500">Models own casts and relationships. Controllers validate requests and delegate repeatable business logic to services.</p>
            </section>
            <section class="dashboard-card">
                <div class="flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-xl bg-orange-50 text-orange-600 ring-1 ring-orange-100"><i data-lucide="git-branch" class="size-5"></i></span>
                    <div>
                        <div class="text-xs font-semibold text-slate-500">Contribution Flow</div>
                        <div class="text-xl font-bold text-slate-950">Tested PRs</div>
                    </div>
                </div>
                <p class="mt-3 text-sm text-slate-500">Keep changes scoped, database-backed, permission-aware, and covered by feature tests.</p>
            </section>
        </div>

        <div class="grid gap-5 xl:grid-cols-[300px_1fr]">
            <aside class="dashboard-card h-fit xl:sticky xl:top-20">
                <h2 class="mb-3 text-base font-semibold text-slate-950">Documentation Index</h2>
                <nav class="space-y-1 text-sm">
                    @foreach ([
                        ['id' => 'quick-links', 'label' => 'Project Files', 'icon' => 'file-text'],
                        ['id' => 'new-server-deployment', 'label' => 'New Server Deploy', 'icon' => 'cloud-download'],
                        ['id' => 'architecture', 'label' => 'Architecture', 'icon' => 'network'],
                        ['id' => 'layout', 'label' => 'Layout System', 'icon' => 'layout-dashboard'],
                        ['id' => 'module-process', 'label' => 'Add A Module', 'icon' => 'layout-grid'],
                        ['id' => 'data-security', 'label' => 'Data & Security', 'icon' => 'shield-check'],
                        ['id' => 'quality', 'label' => 'Testing & Release', 'icon' => 'check-circle-2'],
                        ['id' => 'production-updates', 'label' => 'Production Updates', 'icon' => 'upload'],
                        ['id' => 'contributing', 'label' => 'Contribution Rules', 'icon' => 'git-branch'],
                    ] as $link)
                        <a href="#{{ $link['id'] }}" class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium text-slate-600 hover:bg-violet-50 hover:text-violet-700">
                            <i data-lucide="{{ $link['icon'] }}" class="size-4"></i>
                            <span>{{ $link['label'] }}</span>
                        </a>
                    @endforeach
                </nav>
                <div class="mt-5 rounded-lg bg-slate-50 p-3 text-xs leading-5 text-slate-500">
                    Repo doc: <span class="font-semibold text-slate-700">docs/DEVELOPER_HUB.md</span>
                </div>
            </aside>

            <main class="space-y-5">
                <section id="quick-links" class="dashboard-card">
                    <div class="mb-4">
                        <h2 class="text-lg font-semibold text-slate-950">Project Files</h2>
                        <p class="mt-1 text-sm text-slate-500">Start here when onboarding or extending the codebase.</p>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        @foreach ($quickLinks as $link)
                            <div class="rounded-lg border border-slate-200 p-4">
                                <div class="flex items-center gap-3">
                                    <span class="grid size-10 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="{{ $link['icon'] }}" class="size-5"></i></span>
                                    <div class="min-w-0">
                                        <div class="font-semibold text-slate-950">{{ $link['label'] }}</div>
                                        <div class="mt-0.5 truncate text-xs text-slate-500">{{ $link['path'] }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section id="new-server-deployment" class="dashboard-card">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="flex items-center gap-2 text-lg font-semibold text-slate-950">
                                <span class="grid size-9 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="cloud-download" class="size-5"></i></span>
                                Ubuntu New Server Deployment
                            </h2>
                            <p class="mt-2 text-sm text-slate-500">Install Docker, clone EcclesiaOS, and start the production-oriented Compose stack.</p>
                        </div>
                        <span class="inline-flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">
                            <i data-lucide="shield-check" class="size-4"></i>
                            Ubuntu quick start
                        </span>
                    </div>

                    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                        <span class="font-semibold">Important:</span> if the login account is not named <code class="rounded bg-white/70 px-1.5 py-0.5 font-mono text-xs">ubuntu</code>, replace it with the actual username. Sign out and reconnect between the two stages so Docker can run without <code class="rounded bg-white/70 px-1.5 py-0.5 font-mono text-xs">sudo</code>.
                    </div>

                    <div class="grid gap-4 xl:grid-cols-2">
                        @foreach ($ubuntuDeploymentSteps as $deploymentStep)
                            <article x-data="{ copied: false }" class="overflow-hidden rounded-xl border border-slate-200 bg-slate-950">
                                <div class="flex items-start justify-between gap-3 border-b border-slate-800 bg-slate-900 px-4 py-3">
                                    <div>
                                        <h3 class="font-semibold text-white">{{ $deploymentStep['title'] }}</h3>
                                        <p class="mt-1 text-xs leading-5 text-slate-400">{{ $deploymentStep['description'] }}</p>
                                    </div>
                                    <button type="button" @click="navigator.clipboard.writeText($refs.commands.innerText); copied = true; setTimeout(() => copied = false, 1800)" class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-slate-700 px-3 py-2 text-xs font-semibold text-slate-200 hover:border-violet-400 hover:text-white">
                                        <i data-lucide="copy" class="size-4"></i>
                                        <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                                    </button>
                                </div>
                                <pre class="overflow-x-auto p-4 text-sm leading-7 text-emerald-300"><code x-ref="commands">{{ $deploymentStep['commands'] }}</code></pre>
                            </article>
                        @endforeach
                    </div>
                    <p class="mt-4 text-xs leading-5 text-slate-500">The setup script creates <code class="font-mono text-slate-700">.env.docker</code>, generates application and database secrets, starts the services, runs migrations, and guides initial administrator setup.</p>
                </section>

                <section id="architecture" class="dashboard-card">
                    <div class="mb-4">
                        <h2 class="text-lg font-semibold text-slate-950">Architecture Design</h2>
                        <p class="mt-1 text-sm text-slate-500">KingdomHub is a Laravel monolith with modular navigation, database-backed features, and permission-controlled pages.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="grid gap-3 text-sm md:grid-cols-3">
                            <div class="rounded-lg bg-white p-3 shadow-sm"><span class="font-semibold text-slate-950">Browser</span><div class="mt-1 text-slate-500">Blade, Tailwind, Alpine, charts, Lucide</div></div>
                            <div class="rounded-lg bg-white p-3 shadow-sm"><span class="font-semibold text-slate-950">Laravel HTTP</span><div class="mt-1 text-slate-500">Routes, middleware, controllers, policies</div></div>
                            <div class="rounded-lg bg-white p-3 shadow-sm"><span class="font-semibold text-slate-950">Domain/Data</span><div class="mt-1 text-slate-500">Services, Eloquent models, migrations, seeders</div></div>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        @foreach ($architectureLayers as $layer)
                            <article class="rounded-lg border border-slate-200 p-4">
                                <div class="mb-3 flex items-center gap-3">
                                    <span class="grid size-9 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="{{ $layer['icon'] }}" class="size-4"></i></span>
                                    <h3 class="font-semibold text-slate-950">{{ $layer['name'] }}</h3>
                                </div>
                                <ul class="space-y-2 text-sm text-slate-600">
                                    @foreach ($layer['items'] as $item)
                                        <li class="flex gap-2"><i data-lucide="check" class="mt-0.5 size-4 shrink-0 text-emerald-600"></i>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section id="layout" class="dashboard-card">
                    <h2 class="text-lg font-semibold text-slate-950">Layout System</h2>
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        @foreach ($layoutRules as $rule)
                            <div class="flex gap-3 rounded-lg border border-slate-200 p-3 text-sm text-slate-600">
                                <i data-lucide="layout-dashboard" class="mt-0.5 size-4 shrink-0 text-violet-600"></i>
                                <span>{{ $rule }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section id="module-process" class="dashboard-card">
                    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">How To Add A New Module</h2>
                            <p class="mt-1 text-sm text-slate-500">Use this procedure for any new church-management module or major feature area.</p>
                        </div>
                        <a href="{{ route('modules.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-violet-200 px-3 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50">
                            <i data-lucide="layout-grid" class="size-4"></i>
                            Module Manager
                        </a>
                    </div>
                    <div class="space-y-3">
                        @foreach ($moduleSteps as $step)
                            <div class="grid gap-3 rounded-lg border border-slate-200 p-3 text-sm sm:grid-cols-[40px_1fr]">
                                <span class="grid size-9 place-items-center rounded-full bg-violet-600 text-sm font-bold text-white">{{ $loop->iteration }}</span>
                                <div class="self-center text-slate-700">{{ $step }}</div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section id="data-security" class="dashboard-card">
                    <h2 class="text-lg font-semibold text-slate-950">Data, Security, And Permissions</h2>
                    <div class="mt-4 grid gap-4 lg:grid-cols-3">
                        <div class="rounded-lg border border-slate-200 p-4">
                            <h3 class="flex items-center gap-2 font-semibold text-slate-950"><i data-lucide="database" class="size-4 text-blue-600"></i> Data Rules</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">Persist real records in migrations and Eloquent models. Avoid hardcoded page data once a module is implemented. Use eager loading and pagination for tables.</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 p-4">
                            <h3 class="flex items-center gap-2 font-semibold text-slate-950"><i data-lucide="shield-check" class="size-4 text-emerald-600"></i> Access Rules</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">Every page needs an auth route, permission in config/access.php, sidebar permission, and policy or controller authorization for sensitive records.</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 p-4">
                            <h3 class="flex items-center gap-2 font-semibold text-slate-950"><i data-lucide="clipboard-list" class="size-4 text-violet-600"></i> Audit Rules</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">Log user-visible changes, imports, deletes, approvals, settings changes, authentication events, and failed security-sensitive actions.</p>
                        </div>
                    </div>
                </section>

                <section id="quality" class="dashboard-card">
                    <h2 class="text-lg font-semibold text-slate-950">Testing And Release Gates</h2>
                    <div class="mt-4 overflow-hidden rounded-lg border border-slate-200">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr><th class="px-4 py-3">Command</th><th class="px-4 py-3">Purpose</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($qualityGates as $gate)
                                    <tr>
                                        <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-900">{{ $gate['command'] }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ $gate['purpose'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="production-updates" class="dashboard-card">
                    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-950">Safe Production Update Path</h2>
                            <p class="mt-1 text-sm text-slate-500">Use this path when shipping new features or updating live modules.</p>
                        </div>
                        <span class="inline-flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">
                            <i data-lucide="shield-check" class="size-4"></i>
                            Production gate
                        </span>
                    </div>
                    <div class="space-y-3">
                        @foreach ($releaseSteps as $step)
                            <div class="grid gap-3 rounded-lg border border-slate-200 p-3 text-sm sm:grid-cols-[40px_1fr]">
                                <span class="grid size-9 place-items-center rounded-full bg-slate-900 text-sm font-bold text-white">{{ $loop->iteration }}</span>
                                <div class="self-center text-slate-700">{{ $step }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 rounded-lg bg-slate-50 p-4 text-sm leading-6 text-slate-600">
                        Recommended production command order: <span class="font-mono text-xs font-semibold text-slate-900">composer install --no-dev --optimize-autoloader</span>, <span class="font-mono text-xs font-semibold text-slate-900">npm ci && npm run build</span>, <span class="font-mono text-xs font-semibold text-slate-900">php artisan migrate --force</span>, <span class="font-mono text-xs font-semibold text-slate-900">php artisan config:cache</span>, <span class="font-mono text-xs font-semibold text-slate-900">php artisan route:cache</span>, <span class="font-mono text-xs font-semibold text-slate-900">php artisan view:cache</span>.
                    </div>
                </section>

                <section id="contributing" class="dashboard-card">
                    <h2 class="text-lg font-semibold text-slate-950">Contribution Procedure</h2>
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        @foreach ([
                            'Read README.md and docs/DEVELOPER_HUB.md before starting a feature.',
                            'Keep migrations reversible and seed enough data for local verification.',
                            'Use existing controllers, services, view components, icons, and route naming patterns.',
                            'Do not leave dead buttons, fake exports, or placeholder actions in implemented pages.',
                            'Run focused tests while developing, then run the relevant feature suite before handoff.',
                            'Document new module routes, permissions, settings, and operational risks in the Developer Hub docs.',
                        ] as $item)
                            <div class="flex gap-3 rounded-lg border border-slate-200 p-3 text-sm text-slate-600">
                                <i data-lucide="git-branch" class="mt-0.5 size-4 shrink-0 text-violet-600"></i>
                                <span>{{ $item }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            </main>
        </div>
    </div>
</x-app-layout>
