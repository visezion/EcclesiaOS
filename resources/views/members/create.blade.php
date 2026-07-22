<x-app-layout title="Add New Member" :breadcrumbs="$breadcrumbs">
    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="grid size-14 place-items-center rounded-2xl bg-violet-100 text-violet-600"><i data-lucide="user-plus" class="size-7"></i></div>
                <div><h1 class="text-2xl font-semibold text-slate-950">Add New Member</h1><p class="text-sm text-slate-500">Create a new member profile and assign to your church community.</p></div>
            </div>
            <div class="flex gap-2"><a href="{{ route('members.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700"><i data-lucide="x" class="size-4"></i>Cancel</a></div>
        </div>

        @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('members.store') }}" class="grid gap-5 xl:grid-cols-[1fr_340px]">
            @csrf
            <main class="space-y-5">
                <section class="dashboard-card">
                    <div class="grid gap-4 md:grid-cols-7">
                        @foreach ([1 => ['Personal Details', 'Basic information'], 2 => ['Contact & Address', 'Contact details'], 3 => ['Family & Household', 'Family relationships'], 4 => ['Church Assignment', 'Campus & ministries'], 5 => ['Spiritual Profile', 'Faith journey'], 6 => ['Documents & Preferences', 'Uploads & settings'], 7 => ['Review & Save', 'Confirm & create']] as $step => [$title, $caption])
                            <div class="text-center">
                                <div class="{{ $step === 1 ? 'bg-violet-600 text-white' : 'bg-slate-100 text-slate-500' }} mx-auto grid size-9 place-items-center rounded-full text-sm font-semibold">{{ $step }}</div>
                                <div class="mt-2 text-xs font-medium {{ $step === 1 ? 'text-violet-600' : 'text-slate-600' }}">{{ $title }}</div>
                                <div class="mt-1 text-[11px] text-slate-400">{{ $caption }}</div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="mb-4 flex items-center justify-between"><div><h2 class="text-base font-semibold text-slate-950">Personal Details</h2><p class="mt-1 text-sm text-slate-500">Provide basic information about the new member.</p></div><span class="text-xs text-rose-500">* Required fields</span></div>
                    <div class="grid gap-4 lg:grid-cols-[1fr_190px]">
                        <div class="grid gap-4">
                            <div class="grid gap-3 md:grid-cols-3"><label class="space-y-1 text-sm font-medium text-slate-600">First Name *<input name="first_name" required value="{{ old('first_name') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label><label class="space-y-1 text-sm font-medium text-slate-600">Preferred Name<input name="preferred_name" value="{{ old('preferred_name') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label><label class="space-y-1 text-sm font-medium text-slate-600">Last Name *<input name="last_name" required value="{{ old('last_name') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label></div>
                            <div class="grid gap-3 md:grid-cols-4"><label class="space-y-1 text-sm font-medium text-slate-600">Gender<select name="gender" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">Not specified</option><option>Male</option><option>Female</option><option>Prefer not to say</option></select></label><label class="space-y-1 text-sm font-medium text-slate-600">Date of Birth<input name="date_of_birth" type="date" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label><label class="space-y-1 text-sm font-medium text-slate-600">Marital Status<select name="marital_status" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">Not specified</option><option>Single</option><option>Married</option><option>Widowed</option><option>Divorced</option></select></label><label class="space-y-1 text-sm font-medium text-slate-600">Status *<select name="status" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="active">Active</option><option value="new">New Member</option><option value="follow-up">Follow-up</option><option value="inactive">Inactive</option></select></label></div>
                            <div class="grid gap-3 md:grid-cols-2"><label class="space-y-1 text-sm font-medium text-slate-600">Phone Number<input name="phone" value="{{ old('phone') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label><label class="space-y-1 text-sm font-medium text-slate-600">Email Address<input name="email" type="email" value="{{ old('email') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label></div>
                            <div class="grid gap-3 md:grid-cols-2"><label class="space-y-1 text-sm font-medium text-slate-600">Occupation<input name="occupation" value="{{ old('occupation') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label><label class="space-y-1 text-sm font-medium text-slate-600">Employer<input name="employer" value="{{ old('employer') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></label></div>
                            <label class="space-y-1 text-sm font-medium text-slate-600">Address<textarea name="address_line" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">{{ old('address_line') }}</textarea></label>
                        </div>
                        <div class="rounded-lg border border-dashed border-slate-300 p-4 text-center"><div class="mx-auto grid size-16 place-items-center rounded-full bg-violet-100 text-violet-600"><i data-lucide="upload" class="size-7"></i></div><div class="mt-3 text-sm font-medium text-slate-700">Profile Photo</div><div class="mt-1 text-xs text-slate-400">Photo upload can be added when member media storage is enabled.</div></div>
                    </div>
                </section>

                <section class="grid gap-4 lg:grid-cols-3">
                    <div class="dashboard-card"><h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-950"><i data-lucide="phone" class="size-4 text-violet-600"></i> Emergency Contact</h3><div class="grid gap-3"><input name="emergency_contact_name" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Contact name"><input name="emergency_contact_relationship" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Relationship"><input name="emergency_contact_phone" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Phone number"></div></div>
                    <div class="dashboard-card"><h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-950"><i data-lucide="users-round" class="size-4 text-violet-600"></i> Household Assignment</h3><div class="grid gap-3"><select name="family_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">Assign to existing household</option>@foreach($families as $family)<option value="{{ $family->id }}">{{ $family->name }}</option>@endforeach</select><input name="family_name" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Or create new household"></div></div>
                    <div class="dashboard-card"><h3 class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-950"><i data-lucide="landmark" class="size-4 text-violet-600"></i> Church Assignment</h3><div class="grid gap-3"><select name="church_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">@foreach($churches as $church)<option value="{{ $church->id }}">{{ $church->name }}</option>@endforeach</select><select name="campus_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">Select campus</option>@foreach($campuses as $campus)<option value="{{ $campus->id }}">{{ $campus->name }}</option>@endforeach</select><select name="ministry_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm"><option value="">Assign ministry</option>@foreach($ministries as $ministry)<option value="{{ $ministry->id }}">{{ $ministry->name }}</option>@endforeach</select></div></div>
                </section>

                <div class="dashboard-card flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><a href="{{ route('members.index') }}" class="rounded-lg border border-slate-200 px-4 py-2.5 text-center text-sm font-medium text-slate-700">Cancel</a><div class="flex gap-2"><button name="status" value="new" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700"><i data-lucide="save" class="mr-2 inline size-4"></i>Save Draft</button><button class="rounded-lg bg-violet-600 px-5 py-2.5 text-sm font-medium text-white">Create Member</button></div></div>
            </main>

            <aside class="space-y-4">
                <section class="dashboard-card"><h2 class="mb-3 text-base font-semibold text-slate-950">Onboarding Progress</h2><div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full w-[14%] rounded-full bg-violet-600"></div></div><div class="mt-3 space-y-2 text-sm">@foreach(['Personal Details','Contact & Address','Family & Household','Church Assignment','Spiritual Profile','Documents & Preferences','Review & Save'] as $item)<div class="flex items-center gap-2 text-slate-600"><span class="size-2 rounded-full bg-violet-500"></span>{{ $item }}</div>@endforeach</div></section>
                <section class="dashboard-card"><h2 class="mb-3 text-base font-semibold text-slate-950">Data Validation</h2><p class="text-sm text-slate-500">Required fields are validated before the record is created.</p><span class="mt-3 inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">Ready for production validation</span></section>
                <section class="dashboard-card"><h2 class="mb-3 text-base font-semibold text-slate-950">Next Steps After Creation</h2><div class="space-y-2 text-sm text-slate-600"><div class="flex gap-2"><i data-lucide="check-circle-2" class="size-4 text-emerald-600"></i>Member record is saved to the database.</div><div class="flex gap-2"><i data-lucide="check-circle-2" class="size-4 text-emerald-600"></i>Household and ministry links are assigned.</div><div class="flex gap-2"><i data-lucide="check-circle-2" class="size-4 text-emerald-600"></i>Activity is available in audit logs.</div></div></section>
            </aside>
        </form>
    </div>
</x-app-layout>
