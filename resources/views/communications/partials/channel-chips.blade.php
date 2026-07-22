@props(['selected' => [], 'channels'])

<div class="flex flex-wrap gap-1.5">
    @foreach($selected as $channel)
        @if(isset($channels[$channel]))
            <span class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs ring-1 {{ $channels[$channel]['tone'] }}">
                <i data-lucide="{{ $channels[$channel]['icon'] }}" class="size-3"></i>
                {{ $channels[$channel]['label'] }}
            </span>
        @endif
    @endforeach
</div>
