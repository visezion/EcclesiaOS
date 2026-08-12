@php
    $productName = (string) config('church.product_name', 'EcclesiaOS');
    $version = ltrim((string) config('updater.current_version', '0.0.0'), 'vV');
    $vision = (string) config('church.product_vision', 'Equipping churches to connect people, steward ministry, and serve with clarity.');
@endphp

<footer {{ $attributes->merge(['class' => 'mt-8 border-t border-slate-200/80 py-5']) }}>
    <div class="mx-auto flex max-w-7xl flex-col gap-2 text-center text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:text-left">
        <span class="font-semibold text-slate-600">&copy; {{ now()->year }} {{ $productName }} &middot; v{{ $version }}. All rights reserved.</span>
        <span>{{ $vision }}</span>
    </div>
</footer>
