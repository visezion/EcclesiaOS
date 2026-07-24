<x-app-layout :title="$terminology['ministry_plural']" :breadcrumbs="$breadcrumbs">
    <div class="space-y-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-950">{{ $terminology['ministry_plural'] }}</h1>
                <p class="text-sm text-slate-500">Create and manage {{ Str::lower($terminology['ministry_plural']) }} for the {{ Str::lower($terminology['campus_singular']) }} you are assigned to.</p>
            </div>
            <button form="create-ministry-form" class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                <i data-lucide="plus" class="size-4"></i>
                Add {{ $terminology['ministry_singular'] }}
            </button>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>
        @endif

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => 'Total '.$terminology['ministry_plural'], 'value' => $stats['total'], 'icon' => 'landmark', 'tone' => 'bg-violet-50 text-violet-600 ring-violet-100'],
                ['label' => 'Active', 'value' => $stats['active'], 'icon' => 'check-circle-2', 'tone' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
                ['label' => $terminology['campus_plural'].' Covered', 'value' => $stats['campuses'], 'icon' => 'map-pin', 'tone' => 'bg-blue-50 text-blue-600 ring-blue-100'],
                ['label' => 'Volunteers', 'value' => $stats['volunteers'], 'icon' => 'users-round', 'tone' => 'bg-orange-50 text-orange-600 ring-orange-100'],
            ] as $card)
                <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center gap-3">
                        <span class="grid size-11 place-items-center rounded-lg ring-1 {{ $card['tone'] }}"><i data-lucide="{{ $card['icon'] }}" class="size-5"></i></span>
                        <div>
                            <div class="text-xs text-slate-500">{{ $card['label'] }}</div>
                            <div class="mt-1 text-2xl font-semibold text-slate-950">{{ number_format($card['value']) }}</div>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="grid gap-4 xl:grid-cols-[360px_1fr]">
            <form id="create-ministry-form" method="POST" action="{{ route('ministries.store') }}" class="h-fit rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                @csrf
                <h2 class="flex items-center gap-2 text-base font-semibold text-slate-950"><i data-lucide="landmark" class="size-4 text-violet-600"></i>New {{ $terminology['ministry_singular'] }}</h2>
                <div class="mt-4 grid gap-3">
                    <label class="space-y-1 text-xs font-medium text-slate-500">{{ $terminology['ministry_singular'] }} Name
                        <input name="name" value="{{ old('name') }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-900" placeholder="Example: Worship {{ $terminology['ministry_singular'] }}">
                    </label>
                    <label class="space-y-1 text-xs font-medium text-slate-500">{{ $terminology['campus_singular'] }}
                        <select name="campus_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-900">
                            @foreach ($campuses as $campus)
                                <option value="{{ $campus->id }}" @selected((string) old('campus_id') === (string) $campus->id)>{{ $campus->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="space-y-1 text-xs font-medium text-slate-500">Leader
                        <select name="leader_id" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-900">
                            <option value="">No leader assigned</option>
                            @foreach ($leaders as $leader)
                                <option value="{{ $leader->id }}" @selected((string) old('leader_id') === (string) $leader->id)>{{ $leader->first_name }} {{ $leader->last_name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="space-y-1 text-xs font-medium text-slate-500">Status
                        <select name="status" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-900">
                            <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                            <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                        </select>
                    </label>
                    <label class="space-y-1 text-xs font-medium text-slate-500">Description
                        <textarea name="description" rows="4" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-900" placeholder="Purpose, responsibilities, and serving focus.">{{ old('description') }}</textarea>
                    </label>
                </div>
            </form>

            <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 p-4">
                    <h2 class="text-base font-semibold text-slate-950">{{ $terminology['ministry_plural'] }} by {{ $terminology['campus_singular'] }}</h2>
                    <p class="mt-1 text-sm text-slate-500">Leaders only see and manage records inside their assigned {{ Str::lower($terminology['campus_singular']) }}.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="table-compact min-w-[920px]">
                        <thead>
                            <tr>
                                <th>{{ $terminology['ministry_singular'] }}</th>
                                <th>{{ $terminology['campus_singular'] }}</th>
                                <th>Leader</th>
                                <th class="text-center">Volunteers</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ministries as $ministry)
                                <tr>
                                    <td>
                                        <div class="font-semibold text-slate-900">{{ $ministry->name }}</div>
                                        <div class="max-w-sm truncate text-xs text-slate-500">{{ $ministry->description ?: 'No description recorded.' }}</div>
                                    </td>
                                    <td>{{ $ministry->campus?->name ?? 'Unassigned' }}</td>
                                    <td>{{ $ministry->leader ? $ministry->leader->first_name.' '.$ministry->leader->last_name : 'Unassigned' }}</td>
                                    <td class="text-center font-semibold text-slate-900">{{ number_format($ministry->volunteers_count) }}</td>
                                    <td><x-status-badge :status="$ministry->status" /></td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <button form="update-ministry-{{ $ministry->id }}" class="inline-flex items-center gap-1 rounded-lg border border-violet-200 px-3 py-1.5 text-xs font-semibold text-violet-700 hover:bg-violet-50"><i data-lucide="save" class="size-3.5"></i>Save</button>
                                            <button form="delete-ministry-{{ $ministry->id }}" class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50"><i data-lucide="archive" class="size-3.5"></i>Archive</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="6" class="bg-slate-50/70">
                                        <form id="update-ministry-{{ $ministry->id }}" method="POST" action="{{ route('ministries.update', $ministry) }}" class="grid gap-3 py-2 md:grid-cols-[1.1fr_1fr_1fr_140px]">
                                            @csrf
                                            @method('PUT')
                                            <input name="name" value="{{ old('name', $ministry->name) }}" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                            <select name="leader_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                                <option value="">No leader assigned</option>
                                                @foreach ($leaders as $leader)
                                                    <option value="{{ $leader->id }}" @selected($ministry->leader_id === $leader->id)>{{ $leader->first_name }} {{ $leader->last_name }}</option>
                                                @endforeach
                                            </select>
                                            <select name="campus_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                                @foreach ($campuses as $campus)
                                                    <option value="{{ $campus->id }}" @selected($ministry->campus_id === $campus->id)>{{ $campus->name }}</option>
                                                @endforeach
                                            </select>
                                            <select name="status" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                                <option value="active" @selected($ministry->status === 'active')>Active</option>
                                                <option value="inactive" @selected($ministry->status === 'inactive')>Inactive</option>
                                            </select>
                                            <textarea name="description" rows="2" class="md:col-span-4 rounded-lg border border-slate-200 px-3 py-2 text-sm">{{ $ministry->description }}</textarea>
                                        </form>
                                        <form id="delete-ministry-{{ $ministry->id }}" method="POST" action="{{ route('ministries.destroy', $ministry) }}" class="hidden">@csrf @method('DELETE')</form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center">
                                        <x-empty-state icon="landmark" title="No ministries found" message="Create the first ministry for this campus." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
