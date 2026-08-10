<x-app-layout title="Celebration Automation" :breadcrumbs="$breadcrumbs">
    @php
        $design = array_replace(['frame' => 'sunrise', 'accent' => '#7c3aed', 'background' => '#fff7ed', 'footer' => 'With love from your church family'], $setting->design ?? []);
        $channels = ['in_app', 'email', 'sms', 'whatsapp'];
        $frames = ['sunrise' => 'Sunrise glow', 'elegant' => 'Elegant gold', 'botanical' => 'Botanical garden', 'royal' => 'Royal celebration'];
    @endphp
    <div class="space-y-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-violet-600">Communications control center</p>
                <h1 class="mt-1 text-2xl font-semibold text-slate-950">Birthday & Anniversary Celebrations</h1>
                <p class="mt-1 max-w-3xl text-sm text-slate-500">Set this up once. EcclesiaOS will find today’s celebrations, personalize the message, create a beautiful card from the person’s photo, notify the celebrant, and invite your WhatsApp groups to celebrate with them.</p>
            </div>
            <div class="rounded-2xl border border-violet-100 bg-gradient-to-br from-violet-50 to-amber-50 px-4 py-3 text-sm text-violet-900"><span class="font-semibold">Runs automatically</span><br><span class="text-xs text-violet-700">Every minute after the chosen send time</span></div>
        </div>
        @include('communications.partials.flash')
        @include('communications.partials.subnav')

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['Today’s birthdays', $stats['birthday'], 'cake', 'bg-pink-50 text-pink-600'],
                ['Today’s anniversaries', $stats['anniversary'], 'heart-handshake', 'bg-rose-50 text-rose-600'],
                ['Celebrations sent', $stats['sent'], 'send', 'bg-emerald-50 text-emerald-600'],
                ['Needs attention', $stats['failed'], 'triangle-alert', 'bg-amber-50 text-amber-600'],
            ] as [$label, $value, $icon, $tone])
                <article class="dashboard-card flex items-center gap-3 p-4"><span class="grid size-11 place-items-center rounded-full {{ $tone }}"><i data-lucide="{{ $icon }}" class="size-5"></i></span><div><p class="text-xs text-slate-500">{{ $label }}</p><p class="text-2xl font-semibold text-slate-950">{{ number_format($value) }}</p></div></article>
            @endforeach
        </div>

        <form method="POST" action="{{ route('communications.celebrations.update') }}" class="space-y-5">
            @csrf @method('PUT')
            <section class="dashboard-card space-y-5 p-5">
                <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 md:flex-row md:items-center md:justify-between"><div><h2 class="font-semibold text-slate-950">Automation settings</h2><p class="text-sm text-slate-500">Use member profile birthdays and anniversaries. Family anniversary cards use the family photo first, then the primary member photo.</p></div><label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" class="rounded border-slate-300 text-violet-600" @checked($setting->enabled)> Enable celebrations</label></div>
                <div class="grid gap-4 md:grid-cols-3"><label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm text-slate-700"><input type="hidden" name="birthdays_enabled" value="0"><input type="checkbox" name="birthdays_enabled" value="1" class="rounded border-slate-300 text-violet-600" @checked($setting->birthdays_enabled)> Birthday messages</label><label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm text-slate-700"><input type="hidden" name="anniversaries_enabled" value="0"><input type="checkbox" name="anniversaries_enabled" value="1" class="rounded border-slate-300 text-violet-600" @checked($setting->anniversaries_enabled)> Wedding anniversaries</label><label class="text-xs text-slate-500">Send after local time<input type="time" name="send_time" value="{{ old('send_time', substr((string) $setting->send_time, 0, 5)) }}" class="mt-1 w-full rounded-lg border-slate-200 bg-white text-sm"></label></div>
                <fieldset><legend class="text-xs text-slate-500">Send directly to the celebrant</legend><div class="mt-2 flex flex-wrap gap-2">@foreach($channels as $channel)<label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"><input type="checkbox" name="celebrant_channels[]" value="{{ $channel }}" class="rounded border-slate-300 text-violet-600" @checked(in_array($channel, $setting->celebrant_channels ?: $channels, true))>{{ Str::headline($channel) }}</label>@endforeach</div></fieldset>
            </section>

            <section class="grid gap-5 lg:grid-cols-2">
                <article class="dashboard-card space-y-4 p-5"><div><h2 class="font-semibold text-slate-950">Birthday message</h2><p class="text-xs text-slate-500">Available variables: <code>{{ '{{celebrantName}}' }}</code>, <code>{{ '{{familyName}}' }}</code>, <code>{{ '{{years}}' }}</code>, <code>{{ '{{occasionDate}}' }}</code>, <code>{{ '{{imageUrl}}' }}</code></p></div><label class="text-xs text-slate-500">Personal subject<input name="birthday_subject" value="{{ old('birthday_subject', $setting->birthday_subject) }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm"></label><label class="text-xs text-slate-500">Personal message<textarea name="birthday_message" rows="6" class="mt-1 w-full rounded-lg border-slate-200 text-sm">{{ old('birthday_message', $setting->birthday_message) }}</textarea></label><label class="text-xs text-slate-500">WhatsApp group message<textarea name="birthday_group_message" rows="4" class="mt-1 w-full rounded-lg border-slate-200 text-sm">{{ old('birthday_group_message', $setting->birthday_group_message) }}</textarea></label></article>
                <article class="dashboard-card space-y-4 p-5"><div><h2 class="font-semibold text-slate-950">Wedding anniversary message</h2><p class="text-xs text-slate-500">Family groups are announced once; each matching spouse receives the personal message.</p></div><label class="text-xs text-slate-500">Personal subject<input name="anniversary_subject" value="{{ old('anniversary_subject', $setting->anniversary_subject) }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm"></label><label class="text-xs text-slate-500">Personal message<textarea name="anniversary_message" rows="6" class="mt-1 w-full rounded-lg border-slate-200 text-sm">{{ old('anniversary_message', $setting->anniversary_message) }}</textarea></label><label class="text-xs text-slate-500">WhatsApp group message<textarea name="anniversary_group_message" rows="4" class="mt-1 w-full rounded-lg border-slate-200 text-sm">{{ old('anniversary_group_message', $setting->anniversary_group_message) }}</textarea></label></article>
            </section>

            <section class="dashboard-card space-y-4 p-5"><div><h2 class="font-semibold text-slate-950">Celebration card design</h2><p class="text-sm text-slate-500">The card is generated automatically using the celebrant’s profile photo. Add <code>{{ '{{imageUrl}}' }}</code> to messages to share the card link.</p></div><div class="grid gap-4 md:grid-cols-4"><label class="text-xs text-slate-500 md:col-span-2">Frame style<select name="design[frame]" class="mt-1 w-full rounded-lg border-slate-200 bg-white text-sm">@foreach($frames as $value => $label)<option value="{{ $value }}" @selected($design['frame'] === $value)>{{ $label }}</option>@endforeach</select></label><label class="text-xs text-slate-500">Accent<input type="color" name="design[accent]" value="{{ $design['accent'] }}" class="mt-1 h-10 w-full rounded-lg border-slate-200 bg-white p-1"></label><label class="text-xs text-slate-500">Background<input type="color" name="design[background]" value="{{ $design['background'] }}" class="mt-1 h-10 w-full rounded-lg border-slate-200 bg-white p-1"></label><label class="text-xs text-slate-500 md:col-span-4">Footer text<input name="design[footer]" value="{{ $design['footer'] }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm"></label></div><div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-violet-50 to-amber-50 p-5 text-center"><div class="mx-auto grid size-20 place-items-center rounded-full bg-white text-4xl shadow-sm">🎉</div><p class="mt-3 text-xs font-semibold uppercase tracking-[0.18em] text-violet-600">Preview frame</p><p class="mt-1 text-xl font-semibold text-slate-900">Happy Birthday</p><p class="text-sm text-slate-600">Your celebrant’s name and photo appear here automatically.</p></div><div class="flex justify-end"><button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-violet-700"><i data-lucide="save" class="size-4"></i> Save celebration setup</button></div></section>
        </form>
    </div>
</x-app-layout>
