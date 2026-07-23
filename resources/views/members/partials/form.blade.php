@php
    $isEdit = $method === 'PUT';
    $value = fn (string $key, mixed $fallback = '') => old($key, $member[$key] ?? $member[Str::camel($key)] ?? $fallback);
    $preferences = $member['communicationPreferences'] ?? [];
@endphp

<form method="POST" action="{{ $action }}" class="space-y-5">
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

    <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-950"><i data-lucide="user-round" class="size-4 text-violet-600"></i>Profile Details</div>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="space-y-1 text-sm font-medium text-slate-600">Preferred Name
                <input name="preferred_name" value="{{ $value('preferred_name') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600">Date of Birth
                <input name="date_of_birth" type="date" value="{{ $value('date_of_birth', $member['dateOfBirthInput'] ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600">Gender
                <select name="gender" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
                    <option value="">Not specified</option>
                    @foreach (['Male', 'Female', 'Prefer not to say'] as $option)
                        <option value="{{ $option }}" @selected($value('gender') === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600">Marital Status
                <select name="marital_status" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
                    <option value="">Not specified</option>
                    @foreach (['Single', 'Married', 'Widowed', 'Divorced'] as $option)
                        <option value="{{ $option }}" @selected($value('marital_status', $member['marital'] ?? '') === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600">Anniversary Date
                <input name="anniversary_date" type="date" value="{{ $value('anniversary_date', $member['anniversaryInput'] ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600">Volunteer Hours
                <input name="volunteer_hours" type="number" min="0" value="{{ $value('volunteer_hours', $member['volunteerHours'] ?? 0) }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600">Occupation
                <input name="occupation" value="{{ $value('occupation') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600">Employer
                <input name="employer" value="{{ $value('employer') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            </label>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-950"><i data-lucide="map-pin" class="size-4 text-violet-600"></i>Address & Contact</div>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="space-y-1 text-sm font-medium text-slate-600 sm:col-span-2">Address
                <input name="address_line" value="{{ $value('address_line', $member['addressLine'] ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600">City
                <input name="city" value="{{ $value('city') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600">State
                <input name="state" value="{{ $value('state') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600">Postal Code
                <input name="postal_code" value="{{ $value('postal_code', $member['postalCode'] ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600">Country
                <input name="country" value="{{ $value('country') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600">Alternate Email
                <input name="alternate_email" type="email" value="{{ $value('alternate_email', $member['alternateEmail'] ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600">Home Phone
                <input name="home_phone" value="{{ $value('home_phone', $member['homePhone'] ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            </label>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-950"><i data-lucide="briefcase-medical" class="size-4 text-violet-600"></i>Emergency & Care</div>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="space-y-1 text-sm font-medium text-slate-600">Contact Name
                <input name="emergency_contact_name" value="{{ $value('emergency_contact_name', $member['emergencyContactName'] ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600">Relationship
                <input name="emergency_contact_relationship" value="{{ $value('emergency_contact_relationship', $member['emergencyContactRelationship'] ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600">Emergency Phone
                <input name="emergency_contact_phone" value="{{ $value('emergency_contact_phone', $member['emergencyContactPhone'] ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600">Alternate Phone
                <input name="emergency_contact_alt_phone" value="{{ $value('emergency_contact_alt_phone', $member['emergencyContactAltPhone'] ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600">Care Level
                <select name="care_level" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
                    @foreach (['standard' => 'Standard Care', 'follow-up' => 'Follow-up Needed', 'high-touch' => 'High Touch', 'crisis' => 'Crisis Care'] as $level => $label)
                        <option value="{{ $level }}" @selected($value('care_level', $member['careLevel'] ?? 'standard') === $level)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600 sm:col-span-2">Care Notes
                <textarea name="care_notes" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">{{ $value('care_notes', $member['careNotes'] ?? '') }}</textarea>
            </label>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-950"><i data-lucide="sparkles" class="size-4 text-violet-600"></i>Spiritual, Skills & Preferences</div>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="space-y-1 text-sm font-medium text-slate-600">Salvation Date
                <input name="salvation_date" type="date" value="{{ $value('salvation_date', $member['spiritualJourney']['salvation_date'] ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600">Baptism Date
                <input name="baptism_date" type="date" value="{{ $value('baptism_date', $member['spiritualJourney']['baptism_date'] ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600">Discipleship Class
                <input name="discipleship_class" value="{{ $value('discipleship_class', $member['spiritualJourney']['discipleship_class'] ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600">Membership Class
                <input name="membership_class" value="{{ $value('membership_class', $member['spiritualJourney']['membership_class'] ?? '') }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600 sm:col-span-2">Skills & Talents
                <input name="skills" value="{{ old('skills', $member['skillsText'] ?? '') }}" placeholder="Singing, Team Leader, Event Planning" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
            </label>
            <label class="space-y-1 text-sm font-medium text-slate-600">Preferred Contact
                <select name="preferred_contact" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 focus:border-violet-400 focus:ring-4 focus:ring-violet-100">
                    @foreach (['email' => 'Email', 'phone' => 'Phone', 'mail' => 'Mail'] as $contact => $label)
                        <option value="{{ $contact }}" @selected(old('preferred_contact', $preferences['preferred_contact'] ?? 'email') === $contact)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="grid gap-2 pt-6 text-sm text-slate-600">
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="email_notifications" value="1" @checked(old('email_notifications', $preferences['email_notifications'] ?? true)) class="rounded border-slate-300 text-violet-600">Email notifications</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="sms_notifications" value="1" @checked(old('sms_notifications', $preferences['sms_notifications'] ?? false)) class="rounded border-slate-300 text-violet-600">SMS notifications</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="mailing_mail" value="1" @checked(old('mailing_mail', $preferences['mailing_mail'] ?? true)) class="rounded border-slate-300 text-violet-600">Mailing list</label>
            </div>
        </div>
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
