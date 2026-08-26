<nav id="site-nav" class="site-nav" data-menu>
    @foreach ($navigation as $item)
        @php($children = collect($item['children'] ?? [])->where('visible', true)->values())
        @if (($item['type'] ?? 'link') === 'link' || $children->isEmpty())
            <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
        @else
            <div class="site-nav-item has-submenu is-{{ $item['type'] }}">
                <div class="site-nav-parent">
                    <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                    <button type="button" class="site-submenu-toggle" data-submenu-toggle aria-expanded="false" aria-label="{{ __('Open submenu') }}"><svg viewBox="0 0 12 8" aria-hidden="true" focusable="false"><path d="m1 1 5 5 5-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/></svg></button>
                </div>
                <div class="site-submenu site-submenu-{{ $item['type'] }}" data-submenu>
                    @if (($item['type'] ?? 'dropdown') === 'mega')
                        @foreach ($children->groupBy(fn ($child) => max(1, min(4, (int) ($child['column'] ?? 1)))) as $column => $columnItems)
                            <div class="site-submenu-column">
                                @foreach ($columnItems as $child)
                                    <a href="{{ $child['url'] }}"><strong>{{ $child['label'] }}</strong>@if(filled($child['description'] ?? null))<span>{{ $child['description'] }}</span>@endif</a>
                                @endforeach
                            </div>
                        @endforeach
                    @else
                        @foreach ($children as $child)
                            <a href="{{ $child['url'] }}"><strong>{{ $child['label'] }}</strong>@if(filled($child['description'] ?? null))<span>{{ $child['description'] }}</span>@endif</a>
                        @endforeach
                    @endif
                </div>
            </div>
        @endif
    @endforeach
</nav>
