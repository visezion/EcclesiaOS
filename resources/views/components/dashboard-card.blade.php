@props(['title', 'action' => null, 'class' => ''])

<section {{ $attributes->merge(['class' => 'dashboard-card '.$class]) }}>
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="text-base font-bold text-slate-900">{{ $title }}</h2>
        @if ($action)
            <a href="{{ $action['url'] }}" class="text-xs font-semibold text-violet-600 hover:text-violet-800">{{ $action['label'] }}</a>
        @endif
    </div>
    {{ $slot }}
</section>
