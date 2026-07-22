<x-app-layout title="Built-in Meeting Methods" :breadcrumbs="$breadcrumbs">
    <form id="integration-save-form" method="POST" action="{{ route('meeting-integrations.update') }}">
        @csrf
        @method('PUT')
    </form>

    @foreach($providers as $provider)
        <form id="test-{{ $provider }}" method="POST" action="{{ route('meeting-integrations.test', $provider) }}">
            @csrf
        </form>
    @endforeach

    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-lg bg-violet-100 text-violet-600">
                    <i data-lucide="radio-tower" class="size-7"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-semibold text-slate-950">Built-in Meeting Methods</h1>
                    <p class="text-sm text-slate-500">Configure the internal Zoom, Google Meet, Jitsi, and LiveKit room modes used by EcclesiaOS attendance.</p>
                </div>
            </div>
            <button form="integration-save-form" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-5 py-2.5 text-sm text-white hover:bg-violet-700">
                <i data-lucide="save" class="size-4"></i>
                Save Methods
            </button>
        </div>

        @if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
        @if(session('error'))<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif

        <section class="grid gap-4 md:grid-cols-4">
            @foreach($providers as $provider)
                @php
                    $integration = $integrations[$provider];
                    $settings = $integration->settings ?? [];
                    $meta = $providerMeta[$provider];
                    $complete = $integration->enabled && filled($settings['room_prefix'] ?? null) && ($settings['webhook_secret_configured'] ?? false);
                    $tone = match ($provider) {
                        'zoom' => 'bg-blue-50 text-blue-600 ring-blue-100',
                        'google_meet' => 'bg-emerald-50 text-emerald-600 ring-emerald-100',
                        'jitsi' => 'bg-orange-50 text-orange-600 ring-orange-100',
                        default => 'bg-violet-50 text-violet-600 ring-violet-100',
                    };
                @endphp
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center gap-3">
                        <span class="grid size-11 place-items-center rounded-lg ring-1 {{ $tone }}"><i data-lucide="{{ $meta['icon'] }}" class="size-5"></i></span>
                        <div>
                            <div class="text-sm text-slate-500">{{ $meta['label'] }}</div>
                            <div class="text-xl text-slate-950">{{ $complete ? 'Ready' : 'Needs Setup' }}</div>
                        </div>
                    </div>
                    <div class="mt-3 text-xs {{ ($settings['last_test_status'] ?? '') === 'failed' ? 'text-rose-600' : 'text-emerald-600' }}">
                        {{ $settings['last_test_message'] ?? 'Not tested yet' }}
                    </div>
                </div>
            @endforeach
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            @foreach($providers as $provider)
                @php
                    $integration = $integrations[$provider];
                    $settings = $integration->settings ?? [];
                    $meta = $providerMeta[$provider];
                    $complete = $integration->enabled && filled($settings['room_prefix'] ?? null) && ($settings['webhook_secret_configured'] ?? false);
                    $statusClass = $complete ? 'bg-emerald-50 text-emerald-700 ring-emerald-100' : 'bg-amber-50 text-amber-700 ring-amber-100';
                @endphp
                <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-5 flex items-start justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="grid size-11 place-items-center rounded-lg bg-violet-50 text-violet-600">
                                <i data-lucide="{{ $meta['icon'] }}" class="size-5"></i>
                            </span>
                            <div>
                                <h2 class="text-base text-slate-950">{{ $meta['label'] }}</h2>
                                <p class="text-xs text-slate-500">Last tested: {{ $integration->last_tested_at?->format('M d, Y h:i A') ?? 'Never' }}</p>
                            </div>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input form="integration-save-form" type="checkbox" name="providers[{{ $provider }}][enabled]" value="1" @checked($integration->enabled) class="rounded border-slate-300 text-violet-600">
                            Enabled
                        </label>
                    </div>

                    <div class="mb-4 flex flex-wrap gap-2">
                        <span class="rounded-full px-2.5 py-1 text-xs ring-1 {{ $statusClass }}">{{ $complete ? 'Ready for internal rooms' : 'Configuration required' }}</span>
                        @foreach($meta['required'] as $required)
                            <span class="rounded-full bg-slate-50 px-2.5 py-1 text-xs text-slate-500 ring-1 ring-slate-100">{{ $required }}</span>
                        @endforeach
                    </div>

                    <div class="grid gap-3">
                        <label class="text-sm text-slate-600">
                            Internal Endpoint
                            <input form="integration-save-form" name="providers[{{ $provider }}][internal_endpoint]" value="{{ old("providers.{$provider}.internal_endpoint", $settings['internal_endpoint'] ?? route('meetings.index', absolute: false)) }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">
                        </label>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="text-sm text-slate-600">
                                Room Prefix
                                <input form="integration-save-form" name="providers[{{ $provider }}][room_prefix]" value="{{ old("providers.{$provider}.room_prefix", $settings['room_prefix'] ?? 'kingdomlife') }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">
                            </label>
                            <label class="text-sm text-slate-600">
                                Attendance Secret
                                <input form="integration-save-form" name="providers[{{ $provider }}][webhook_secret]" type="password" placeholder="{{ ($settings['webhook_secret_configured'] ?? false) ? 'Configured. Enter new value to rotate.' : 'Required for internal callbacks' }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">
                            </label>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <label class="text-sm text-slate-600">
                                Identity Field
                                <select form="integration-save-form" name="providers[{{ $provider }}][identity_field]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">
                                    @foreach(['email' => 'Email', 'phone' => 'Phone'] as $key => $label)
                                        <option value="{{ $key }}" @selected(($settings['identity_field'] ?? 'email') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-sm text-slate-600">
                                Callback Event
                                <input form="integration-save-form" name="providers[{{ $provider }}][webhook_event]" value="{{ old("providers.{$provider}.webhook_event", $settings['webhook_event'] ?? $meta['event']) }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">
                            </label>
                            <label class="text-sm text-slate-600">
                                Retention Days
                                <input form="integration-save-form" name="providers[{{ $provider }}][recording_retention_days]" type="number" min="0" max="3650" value="{{ old("providers.{$provider}.recording_retention_days", $settings['recording_retention_days'] ?? 30) }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900">
                            </label>
                        </div>
                    </div>

                    <div class="mt-4 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                        <div class="flex items-center gap-2 text-slate-900"><i data-lucide="webhook" class="size-4 text-violet-600"></i>Internal Attendance Callback</div>
                        <code class="mt-1 block break-all">{{ route('meeting-attendance.webhook', $provider) }}</code>
                        <p class="mt-2">EcclesiaOS rooms can call this endpoint with the attendance session, signed-in member identity, join time, and duration. No external meeting app is required.</p>
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <button form="test-{{ $provider }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                            <i data-lucide="plug-zap" class="size-4"></i>
                            Test Built-in Method
                        </button>
                    </div>
                </article>
            @endforeach
        </section>
    </div>
</x-app-layout>
