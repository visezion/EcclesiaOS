@php
    $isEdit = $method === 'PUT';
    $value = fn (string $key, mixed $fallback = '') => old($key, $member[$key] ?? $fallback);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid gap-3 sm:grid-cols-2">
        <label class="space-y-1 text-sm font-medium text-slate-600">First Name
            <input name="first_name" value="{{ $value('firstName') }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
        </label>
        <label class="space-y-1 text-sm font-medium text-slate-600">Last Name
            <input name="last_name" value="{{ $value('lastName') }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
        </label>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <label class="space-y-1 text-sm font-medium text-slate-600">Email
            <input name="email" type="email" value="{{ $value('email') !== 'No email' ? $value('email') : '' }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
        </label>
        <label class="space-y-1 text-sm font-medium text-slate-600">Phone
            <input name="phone" value="{{ $value('phone') !== 'No phone' ? $value('phone') : '' }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
        </label>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <label class="space-y-1 text-sm font-medium text-slate-600">Status
            <select name="status" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
                @foreach (['active' => 'Active', 'new' => 'New Member', 'inactive' => 'Inactive', 'follow-up' => 'Follow-up', 'archived' => 'Archived'] as $status => $label)
                    <option value="{{ $status }}" @selected($value('status', 'active') === $status)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="space-y-1 text-sm font-medium text-slate-600">Joined Date
            <input name="joined_at" type="date" value="{{ $value('joinedInput', now()->toDateString()) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
        </label>
    </div>

    <label class="space-y-1 text-sm font-medium text-slate-600">Church
        <select name="church_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            @foreach ($churches as $church)
                <option value="{{ $church->id }}" @selected((string) $value('churchId', $churches->first()?->id) === (string) $church->id)>{{ $church->name }}</option>
            @endforeach
        </select>
    </label>

    <div class="grid gap-3 sm:grid-cols-2">
        <label class="space-y-1 text-sm font-medium text-slate-600">Campus
            <select name="campus_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
                <option value="">Unassigned</option>
                @foreach ($campuses as $campus)
                    <option value="{{ $campus->id }}" @selected((string) $value('campusId') === (string) $campus->id)>{{ $campus->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="space-y-1 text-sm font-medium text-slate-600">Ministry
            <select name="ministry_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
                <option value="">No ministry</option>
                @foreach ($ministries as $ministry)
                    <option value="{{ $ministry->id }}" @selected((string) $value('ministryId') === (string) $ministry->id)>{{ $ministry->name }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <label class="space-y-1 text-sm font-medium text-slate-600">Household
            <select name="family_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
                <option value="">No household</option>
                @foreach ($families as $family)
                    <option value="{{ $family->id }}" @selected((string) $value('familyId') === (string) $family->id)>{{ $family->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="space-y-1 text-sm font-medium text-slate-600">New Household
            <input name="family_name" value="{{ old('family_name') }}" placeholder="Optional household name" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
        </label>
    </div>

    <div class="flex gap-2 pt-2">
        <button class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-violet-700">
            <i data-lucide="{{ $isEdit ? 'save' : 'user-plus' }}" class="size-4"></i>
            {{ $isEdit ? 'Save Member' : 'Create Member' }}
        </button>
        <a href="{{ route('members.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</a>
    </div>
</form>
