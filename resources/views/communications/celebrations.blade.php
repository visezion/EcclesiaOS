<x-app-layout title="Celebration Automation" :breadcrumbs="$breadcrumbs">
    @php
        $branding = \App\Support\Branding::current();
        $design = array_replace(['frame' => 'sunrise', 'accent' => data_get($branding->settings, 'primary_color', '#6C4DFF'), 'background' => '#fff7ed', 'footer' => 'With love from your church family'], $setting->design ?? []);
        $brandPrimary = data_get($branding->settings, 'primary_color', '#6C4DFF');
        $brandSecondary = data_get($branding->settings, 'secondary_color', '#A855F7');
        $channels = ['in_app', 'email', 'sms', 'whatsapp'];
        $frames = ['sunrise' => 'Sunrise glow', 'elegant' => 'Elegant gold', 'botanical' => 'Botanical garden', 'royal' => 'Royal celebration'];
    @endphp

    <div class="space-y-6" style="--celebration-primary: {{ $brandPrimary }}; --celebration-secondary: {{ $brandSecondary }}">
        <section class="relative overflow-hidden rounded-3xl bg-slate-950 p-6 text-white shadow-xl sm:p-8">
            <div class="absolute -right-20 -top-24 size-72 rounded-full opacity-30 blur-3xl" style="background: {{ $brandSecondary }}"></div>
            <div class="absolute -bottom-32 left-1/3 size-72 rounded-full opacity-20 blur-3xl" style="background: {{ $brandPrimary }}"></div>
            <div class="relative flex flex-col gap-7 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4">
                    @if ($branding->logo())
                        <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-white p-2 shadow-lg"><img src="{{ $branding->logo() }}" alt="{{ $branding->churchName() }} logo" class="max-h-full max-w-full object-contain"></span>
                    @else
                        <span class="grid size-14 shrink-0 place-items-center rounded-2xl text-white shadow-lg" style="background: var(--celebration-primary)"><i data-lucide="sparkles" class="size-7"></i></span>
                    @endif
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-violet-200"><i data-lucide="sparkles" class="size-3.5"></i> {{ $branding->churchName() }}</div>
                        <h1 class="mt-3 text-2xl font-black tracking-tight sm:text-3xl">Celebrate every life beautifully.</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">Set this up once and {{ $branding->systemName() }} will find birthdays and anniversaries, create a branded card, message the celebrant, and invite your WhatsApp groups to join in.</p>
                    </div>
                </div>
                <div class="shrink-0 rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
                    <div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-emerald-400/20 text-emerald-300"><i data-lucide="clock-3" class="size-5"></i></span><div><p class="text-sm font-bold">Runs automatically</p><p class="mt-1 text-xs text-slate-300">Every minute after {{ substr((string) $setting->send_time, 0, 5) }}</p></div></div>
                </div>
            </div>
        </section>

        @include('communications.partials.flash')
        @include('communications.partials.subnav')

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['Today’s birthdays', $stats['birthday'], 'sparkles', 'from-pink-500 to-rose-500'],
                ['Today’s anniversaries', $stats['anniversary'], 'heart-handshake', 'from-rose-500 to-orange-400'],
                ['Celebrations sent', $stats['sent'], 'send', 'from-emerald-500 to-teal-500'],
                ['Needs attention', $stats['failed'], 'triangle-alert', 'from-amber-500 to-orange-500'],
            ] as [$label, $value, $icon, $gradient])
                <article class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"><div class="flex items-center justify-between"><span class="grid size-11 place-items-center rounded-xl bg-gradient-to-br {{ $gradient }} text-white shadow-sm"><i data-lucide="{{ $icon }}" class="size-5"></i></span><span class="text-xs font-bold text-slate-400">Today</span></div><p class="mt-5 text-xs font-semibold text-slate-500">{{ $label }}</p><p class="mt-1 text-3xl font-black text-slate-950">{{ number_format($value) }}</p></article>
            @endforeach
        </div>

        <form method="POST" action="{{ route('communications.celebrations.update') }}" class="space-y-6">
            @csrf @method('PUT')
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 lg:flex-row lg:items-center lg:justify-between"><div class="flex items-start gap-3"><span class="grid size-10 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="settings" class="size-5"></i></span><div><h2 class="text-base font-black text-slate-950">Automation settings</h2><p class="mt-1 text-sm text-slate-500">Choose what runs and when your church family receives the first message.</p></div></div><label class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-800"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" class="rounded border-emerald-300 text-emerald-600" @checked($setting->enabled)> Enable automation</label></div>
                <div class="mt-5 grid gap-4 md:grid-cols-3"><label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm font-semibold text-slate-700"><input type="hidden" name="birthdays_enabled" value="0"><input type="checkbox" name="birthdays_enabled" value="1" class="rounded border-slate-300 text-violet-600" @checked($setting->birthdays_enabled)><span class="grid size-9 place-items-center rounded-lg bg-pink-50 text-pink-600"><i data-lucide="sparkles" class="size-4"></i></span> Birthday messages</label><label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm font-semibold text-slate-700"><input type="hidden" name="anniversaries_enabled" value="0"><input type="checkbox" name="anniversaries_enabled" value="1" class="rounded border-slate-300 text-violet-600" @checked($setting->anniversaries_enabled)><span class="grid size-9 place-items-center rounded-lg bg-rose-50 text-rose-600"><i data-lucide="heart-handshake" class="size-4"></i></span> Wedding anniversaries</label><label class="text-xs font-semibold text-slate-500">Send after local time<input type="time" name="send_time" value="{{ old('send_time', substr((string) $setting->send_time, 0, 5)) }}" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-white text-sm text-slate-900"></label></div>
                <fieldset class="mt-5"><legend class="text-xs font-bold uppercase tracking-wide text-slate-500">Direct celebrant channels</legend><div class="mt-2 flex flex-wrap gap-2">@foreach($channels as $channel)<label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-violet-300 hover:bg-violet-50"><input type="checkbox" name="celebrant_channels[]" value="{{ $channel }}" class="rounded border-slate-300 text-violet-600" @checked(in_array($channel, $setting->celebrant_channels ?: $channels, true))>{{ Str::headline($channel) }}</label>@endforeach</div></fieldset>
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                @foreach([['birthday', 'Birthday message', 'sparkles', 'Personalize the private message and the WhatsApp group invitation.'], ['anniversary', 'Wedding anniversary message', 'heart-handshake', 'Each spouse receives a personal message; the family group is invited once.']] as [$type, $title, $icon, $help])
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><div class="mb-5 flex items-start gap-3"><span class="grid size-10 place-items-center rounded-xl bg-violet-50 text-violet-600"><i data-lucide="{{ $icon }}" class="size-5"></i></span><div><h2 class="text-base font-black text-slate-950">{{ $title }}</h2><p class="mt-1 text-xs leading-5 text-slate-500">{{ $help }}</p></div></div><div class="space-y-4"><label class="block text-xs font-bold text-slate-500">Personal subject<input name="{{ $type }}_subject" value="{{ old($type.'_subject', $setting->{$type.'_subject'}) }}" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 text-sm text-slate-900"></label><label class="block text-xs font-bold text-slate-500">Personal message<textarea name="{{ $type }}_message" rows="6" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm text-slate-900">{{ old($type.'_message', $setting->{$type.'_message'}) }}</textarea></label><label class="block text-xs font-bold text-slate-500">WhatsApp group message<textarea name="{{ $type }}_group_message" rows="4" class="mt-1.5 w-full rounded-xl border-slate-200 text-sm text-slate-900">{{ old($type.'_group_message', $setting->{$type.'_group_message'}) }}</textarea></label></div></article>
                @endforeach
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><div class="flex items-start gap-3"><span class="grid size-10 place-items-center rounded-xl bg-amber-50 text-amber-600"><i data-lucide="palette" class="size-5"></i></span><div><h2 class="text-base font-black text-slate-950">Branded celebration card</h2><p class="mt-1 text-sm text-slate-500">Use your church colors and logo. The celebrant’s profile image is used automatically; families can share an anniversary photo from their profile.</p></div></div><div class="mt-5 grid gap-4 md:grid-cols-4"><label class="text-xs font-bold text-slate-500 md:col-span-2">Frame style<select name="design[frame]" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-white text-sm text-slate-900">@foreach($frames as $value => $label)<option value="{{ $value }}" @selected($design['frame'] === $value)>{{ $label }}</option>@endforeach</select></label><label class="text-xs font-bold text-slate-500">Accent<input type="color" name="design[accent]" value="{{ $design['accent'] }}" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-white p-1"></label><label class="text-xs font-bold text-slate-500">Card background<input type="color" name="design[background]" value="{{ $design['background'] }}" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 bg-white p-1"></label><label class="text-xs font-bold text-slate-500 md:col-span-4">Footer text<input name="design[footer]" value="{{ $design['footer'] }}" class="mt-1.5 h-11 w-full rounded-xl border-slate-200 text-sm text-slate-900"></label></div><div class="mt-5 flex flex-col gap-4 rounded-2xl p-5 sm:flex-row sm:items-center" style="background: linear-gradient(135deg, {{ $design['background'] }}, #ffffff); border: 2px solid {{ $design['accent'] }}33"><div class="grid size-20 shrink-0 place-items-center rounded-full bg-white text-{{ $design['accent'] }} shadow-md ring-4 ring-white/70"><i data-lucide="image-plus" class="size-8" style="color: {{ $design['accent'] }}"></i></div><div><p class="text-xs font-black uppercase tracking-[0.18em]" style="color: {{ $design['accent'] }}">Live card direction</p><p class="mt-1 text-xl font-black text-slate-950">Photo first. People first.</p><p class="mt-1 text-sm text-slate-600">{{ $branding->churchName() }} · {{ $design['footer'] }}</p></div></div><div class="mt-5 flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between"><p class="flex items-start gap-2 text-xs leading-5 text-slate-500"><i data-lucide="info" class="mt-0.5 size-4 shrink-0 text-violet-500"></i>Available variables: <code>{{ '{{celebrantName}}' }}</code>, <code>{{ '{{familyName}}' }}</code>, <code>{{ '{{years}}' }}</code>, <code>{{ '{{occasionDate}}' }}</code>, <code>{{ '{{imageUrl}}' }}</code></p><button class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:brightness-95" style="background: var(--celebration-primary)"><i data-lucide="save" class="size-4"></i> Save celebration setup</button></div></section>
        </form>
    </div>
</x-app-layout>
