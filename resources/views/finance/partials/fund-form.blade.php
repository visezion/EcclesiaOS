<label class="space-y-1 text-sm font-medium text-slate-700">
    Fund Name
    <input name="name" value="{{ old('name', $fund?->name) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
</label>

<label class="space-y-1 text-sm font-medium text-slate-700">
    Code
    <input name="code" value="{{ old('code', $fund?->code) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">
</label>

<label class="space-y-1 text-sm font-medium text-slate-700">
    Description
    <textarea name="description" rows="4" class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm">{{ old('description', $fund?->description) }}</textarea>
</label>

<label class="flex items-center gap-2 text-sm font-medium text-slate-700">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $fund?->is_active ?? true)) class="rounded border-slate-300 text-violet-600">
    Active fund
</label>
