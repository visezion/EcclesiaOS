<x-app-layout title="Celebration Automation" :breadcrumbs="$breadcrumbs">
    @php
        $branding = \App\Support\Branding::current();
        $brandPrimary = data_get($branding->settings, 'primary_color', '#6C4DFF');
        $brandSecondary = data_get($branding->settings, 'secondary_color', '#A855F7');
        $design = array_replace([
            'frame' => 'sunrise',
            'accent' => $brandPrimary,
            'background' => '#fff7ed',
            'footer' => 'With love from your church family',
        ], $setting->design ?? []);
        $channels = ['in_app', 'email', 'sms', 'whatsapp'];
        $frames = ['sunrise' => 'Sunrise glow', 'elegant' => 'Elegant gold', 'botanical' => 'Botanical garden', 'royal' => 'Royal celebration'];
        $statsCards = [
            ['label' => 'Today’s birthdays', 'value' => $stats['birthday'], 'note' => 'celebrants today', 'icon' => 'sparkles', 'tone' => 'bg-pink-50 text-pink-600'],
            ['label' => 'Today’s anniversaries', 'value' => $stats['anniversary'], 'note' => 'families today', 'icon' => 'heart', 'tone' => 'bg-rose-50 text-rose-600'],
            ['label' => 'Celebrations sent', 'value' => $stats['sent'], 'note' => 'delivered today', 'icon' => 'send', 'tone' => 'bg-emerald-50 text-emerald-600'],
            ['label' => 'Needs attention', 'value' => $stats['failed'], 'note' => 'failed deliveries', 'icon' => 'triangle-alert', 'tone' => 'bg-amber-50 text-amber-600'],
        ];
    @endphp

    <div class="space-y-4" style="--celebration-primary: {{ $brandPrimary }}; --celebration-secondary: {{ $brandSecondary }}">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-violet-600">Communications control center</p>
                <h1 class="mt-1 text-2xl font-semibold text-slate-950">Celebration Automation</h1>
                <p class="mt-1 max-w-3xl text-sm text-slate-500">Set up birthday and wedding anniversary messages once. {{ $branding->systemName() }} will personalize each message and invite the right WhatsApp groups.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700"><i data-lucide="clock-3" class="size-4"></i> Runs after {{ substr((string) $setting->send_time, 0, 5) }}</span>
                <button form="celebration-form" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white shadow-sm hover:bg-violet-700"><i data-lucide="save" class="size-4"></i> Save setup</button>
            </div>
        </div>

        @include('communications.partials.flash')
        @include('communications.partials.subnav')

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($statsCards as $card)
                <article class="dashboard-card flex items-center gap-3 p-4">
                    <span class="grid size-11 shrink-0 place-items-center rounded-full {{ $card['tone'] }}"><i data-lucide="{{ $card['icon'] }}" class="size-5"></i></span>
                    <div class="min-w-0"><p class="truncate text-xs text-slate-500">{{ $card['label'] }}</p><p class="text-2xl font-semibold text-slate-950">{{ number_format($card['value']) }}</p><p class="truncate text-xs text-slate-500">{{ $card['note'] }}</p></div>
                </article>
            @endforeach
        </section>

        <form id="celebration-form" method="POST" action="{{ route('communications.celebrations.update') }}" class="space-y-4">
            @csrf @method('PUT')
            <section class="dashboard-card overflow-hidden">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-start gap-3"><span class="grid size-10 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="settings" class="size-5"></i></span><div><h2 class="font-semibold text-slate-950">Automation settings</h2><p class="text-sm text-slate-500">Choose what runs and when the first message is sent.</p></div></div>
                    <label class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" class="rounded border-emerald-300 text-emerald-600" @checked($setting->enabled)> Enable automation</label>
                </div>
                <div class="grid gap-4 p-5 md:grid-cols-3">
                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-4 text-sm font-medium text-slate-700"><input type="hidden" name="birthdays_enabled" value="0"><input type="checkbox" name="birthdays_enabled" value="1" class="rounded border-slate-300 text-violet-600" @checked($setting->birthdays_enabled)><span class="grid size-9 place-items-center rounded-lg bg-pink-50 text-pink-600"><i data-lucide="sparkles" class="size-4"></i></span> Birthday messages</label>
                    <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-4 text-sm font-medium text-slate-700"><input type="hidden" name="anniversaries_enabled" value="0"><input type="checkbox" name="anniversaries_enabled" value="1" class="rounded border-slate-300 text-violet-600" @checked($setting->anniversaries_enabled)><span class="grid size-9 place-items-center rounded-lg bg-rose-50 text-rose-600"><i data-lucide="heart" class="size-4"></i></span> Wedding anniversaries</label>
                    <label class="text-xs font-semibold text-slate-500">Send after local time<input type="time" name="send_time" value="{{ old('send_time', substr((string) $setting->send_time, 0, 5)) }}" class="mt-1.5 h-11 w-full rounded-lg border-slate-200 bg-white text-sm text-slate-900"></label>
                </div>
                <fieldset class="border-t border-slate-100 px-5 py-4"><legend class="text-xs font-semibold uppercase tracking-wide text-slate-500">Direct celebrant channels</legend><div class="mt-2 flex flex-wrap gap-2">@foreach($channels as $channel)<label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 hover:border-violet-300 hover:bg-violet-50"><input type="checkbox" name="celebrant_channels[]" value="{{ $channel }}" class="rounded border-slate-300 text-violet-600" @checked(in_array($channel, $setting->celebrant_channels ?: $channels, true))>{{ Str::headline($channel) }}</label>@endforeach</div></fieldset>
            </section>

            <section class="grid gap-4 lg:grid-cols-2">
                @foreach([['birthday', 'Birthday message', 'sparkles', 'Personal message plus a WhatsApp group invitation.'], ['anniversary', 'Wedding anniversary message', 'heart', 'Each spouse receives a personal message; the family group is invited once.']] as [$type, $title, $icon, $help])
                    <article class="dashboard-card p-5"><div class="mb-5 flex items-start gap-3"><span class="grid size-10 place-items-center rounded-lg bg-violet-50 text-violet-600"><i data-lucide="{{ $icon }}" class="size-5"></i></span><div><h2 class="font-semibold text-slate-950">{{ $title }}</h2><p class="text-sm text-slate-500">{{ $help }}</p></div></div><div class="space-y-4"><label class="block text-xs font-semibold text-slate-500">Personal subject<input name="{{ $type }}_subject" value="{{ old($type.'_subject', $setting->{$type.'_subject'}) }}" class="mt-1.5 h-11 w-full rounded-lg border-slate-200 text-sm text-slate-900"></label><label class="block text-xs font-semibold text-slate-500">Personal message<textarea name="{{ $type }}_message" rows="6" class="mt-1.5 w-full rounded-lg border-slate-200 text-sm text-slate-900">{{ old($type.'_message', $setting->{$type.'_message'}) }}</textarea></label><label class="block text-xs font-semibold text-slate-500">WhatsApp group message<textarea name="{{ $type }}_group_message" rows="4" class="mt-1.5 w-full rounded-lg border-slate-200 text-sm text-slate-900">{{ old($type.'_group_message', $setting->{$type.'_group_message'}) }}</textarea></label></div></article>
                @endforeach
            </section>

            <section class="dashboard-card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-4"><div class="flex items-start gap-3"><span class="grid size-10 place-items-center rounded-lg bg-amber-50 text-amber-600"><i data-lucide="palette" class="size-5"></i></span><div><h2 class="font-semibold text-slate-950">Branded celebration card</h2><p class="text-sm text-slate-500">Use your church colors, logo, and the celebrant’s profile image automatically.</p></div></div></div>
                <div class="grid gap-4 p-5 md:grid-cols-4"><label class="text-xs font-semibold text-slate-500 md:col-span-2">Frame style<select name="design[frame]" class="mt-1.5 h-11 w-full rounded-lg border-slate-200 bg-white text-sm text-slate-900">@foreach($frames as $value => $label)<option value="{{ $value }}" @selected($design['frame'] === $value)>{{ $label }}</option>@endforeach</select></label><label class="text-xs font-semibold text-slate-500">Accent<input type="color" name="design[accent]" value="{{ $design['accent'] }}" class="mt-1.5 h-11 w-full rounded-lg border-slate-200 bg-white p-1"></label><label class="text-xs font-semibold text-slate-500">Card background<input type="color" name="design[background]" value="{{ $design['background'] }}" class="mt-1.5 h-11 w-full rounded-lg border-slate-200 bg-white p-1"></label><label class="text-xs font-semibold text-slate-500 md:col-span-4">Footer text<input name="design[footer]" value="{{ $design['footer'] }}" class="mt-1.5 h-11 w-full rounded-lg border-slate-200 text-sm text-slate-900"></label></div>
                <div class="mx-5 mb-5 flex items-center gap-4 rounded-xl border p-4" style="background: linear-gradient(135deg, {{ $design['background'] }}, #ffffff); border-color: {{ $design['accent'] }}55"><span class="grid size-14 place-items-center rounded-full bg-white shadow-sm" style="color: {{ $design['accent'] }}"><i data-lucide="image" class="size-6"></i></span><div><p class="text-xs font-semibold uppercase tracking-wide" style="color: {{ $design['accent'] }}">Preview</p><p class="font-semibold text-slate-950">Photo first. People first.</p><p class="text-sm text-slate-500">{{ $branding->churchName() }} · {{ $design['footer'] }}</p></div></div>
                <div class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs leading-5 text-slate-500">Variables: <code>@{{celebrantName}}</code>, <code>@{{familyName}}</code>, <code>@{{years}}</code>, <code>@{{occasionDate}}</code>, <code>@{{imageUrl}}</code></p><button class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm text-white shadow-sm hover:bg-violet-700"><i data-lucide="save" class="size-4"></i> Save celebration setup</button></div>
            </section>
        </form>
    </div>
</x-app-layout>
