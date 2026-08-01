@php
    $activeBibleSection = match (true) {
        request()->routeIs('bible.plans*') => 'plans',
        request()->routeIs('bible.bookmarks*') => 'bookmarks',
        request()->routeIs('bible.notes*') => 'notes',
        request()->routeIs('bible.highlights*') => 'highlights',
        default => 'read',
    };

    $bibleSections = [
        'read' => ['label' => 'Read', 'route' => 'bible.index'],
        'plans' => ['label' => 'Plans', 'route' => 'bible.plans'],
        'bookmarks' => ['label' => 'Bookmarks', 'route' => 'bible.bookmarks'],
        'notes' => ['label' => 'Notes', 'route' => 'bible.notes'],
        'highlights' => ['label' => 'Highlights', 'route' => 'bible.highlights'],
    ];
@endphp

<nav aria-label="Bible sections" class="flex gap-7 overflow-x-auto border-b border-slate-200 px-2 text-sm font-bold text-slate-500">
    @foreach ($bibleSections as $section => $tab)
        <a
            href="{{ route($tab['route']) }}"
            @if ($activeBibleSection === $section) aria-current="page" @endif
            class="whitespace-nowrap border-b-2 px-1 py-3 transition {{ $activeBibleSection === $section ? 'border-violet-600 text-violet-700' : 'border-transparent hover:border-violet-200 hover:text-violet-700' }}"
        >{{ $tab['label'] }}</a>
    @endforeach
</nav>
