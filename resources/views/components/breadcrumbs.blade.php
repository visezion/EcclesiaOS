@props(['items' => []])

<nav class="mb-4 flex items-center gap-2 text-sm text-slate-500" aria-label="Breadcrumb">
    @foreach ($items as $item)
        @if (! $loop->first)
            <i data-lucide="chevron-right" class="size-4"></i>
        @endif
        @if ($item['url'])
            <a class="font-medium text-slate-600 hover:text-violet-700" href="{{ $item['url'] }}">{{ $item['label'] }}</a>
        @else
            <span class="font-semibold text-slate-900">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
