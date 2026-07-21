<x-app-layout title="Search" :breadcrumbs="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Search', 'url' => null]]">
    <div class="mx-auto max-w-5xl">
        <div class="mb-5">
            <h1 class="text-2xl font-bold text-slate-950">Search</h1>
            <p class="mt-1 text-sm text-slate-500">Results for "{{ $query }}" across navigation modules and seeded demonstration records.</p>
        </div>

        <form action="{{ route('search') }}" method="GET" class="dashboard-card mb-4 flex gap-3">
            <input name="q" value="{{ $query }}" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:border-violet-400 focus:ring-4 focus:ring-violet-100" placeholder="Search members, events, assets, modules">
            <button class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-violet-700">
                <i data-lucide="search" class="size-4"></i> Search
            </button>
        </form>

        @if ($query === '')
            <x-empty-state icon="search" title="Enter a search query" message="Search is wired for modules, seeded members, seeded events, and seeded assets." />
        @elseif (count($results) === 0)
            <x-empty-state icon="file-search" title="No results found" message="Try a module name such as members, events, assets, or reports." />
        @else
            <div class="dashboard-card divide-y divide-slate-100 p-0">
                @foreach ($results as $result)
                    <a href="{{ $result['url'] }}" class="flex items-center justify-between gap-4 p-4 hover:bg-slate-50">
                        <div class="min-w-0">
                            <div class="text-xs font-bold uppercase text-violet-600">{{ $result['category'] }}</div>
                            <div class="truncate text-base font-bold text-slate-900">{{ $result['title'] }}</div>
                            <div class="truncate text-sm text-slate-500">{{ $result['description'] }}</div>
                        </div>
                        <i data-lucide="arrow-right" class="size-5 text-slate-400"></i>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
