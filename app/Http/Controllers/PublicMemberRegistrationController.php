<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Campus;
use App\Models\Church;
use App\Models\Member;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class PublicMemberRegistrationController extends Controller
{
    private const INTERESTS = [
        'membership' => 'Church membership',
        'small_groups' => 'Small groups',
        'serving' => 'Serving & volunteering',
        'baptism' => 'Baptism',
        'prayer' => 'Prayer & pastoral care',
        'children_youth' => 'Children & youth',
    ];

    private const HOW_HEARD = [
        'friend_family' => 'Friend or family',
        'social_media' => 'Social media',
        'website' => 'Church website',
        'community_event' => 'Community event',
        'walk_in' => 'Walked in',
        'other' => 'Other',
    ];

    public function create(): View
    {
        $church = Church::query()->firstOrFail();

        return view('members.self-register', [
            'church' => $church,
            'campuses' => $this->campuses($church),
            'interests' => self::INTERESTS,
            'howHeardOptions' => self::HOW_HEARD,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $church = Church::query()->firstOrFail();
        $emailRules = ['nullable', 'required_without:phone', 'email', 'max:120'];

        if ($request->boolean('create_account')) {
            $emailRules[] = 'required';
            $emailRules[] = Rule::unique('users', 'email');
        }

        $validated = $request->validate([
            'registration_type' => ['required', Rule::in(['new', 'returning'])],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'preferred_name' => ['nullable', 'string', 'max:80'],
            'email' => $emailRules,
            'phone' => ['nullable', 'required_without:email', 'string', 'max:40'],
            'date_of_birth' => ['nullable', 'date', 'after:1900-01-01', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::in(['Male', 'Female', 'Prefer not to say'])],
            'campus_id' => [
                'nullable',
                Rule::exists('campuses', 'id')->where(fn ($query) => $query
                    ->where('church_id', $church->id)
                    ->whereNull('deleted_at')),
            ],
            'address_line' => ['nullable', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:80'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'preferred_contact' => ['required', Rule::in(['email', 'phone'])],
            'interests' => ['nullable', 'array', 'max:6'],
            'interests.*' => [Rule::in(array_keys(self::INTERESTS))],
            'how_heard' => ['nullable', Rule::in(array_keys(self::HOW_HEARD))],
            'support_note' => ['nullable', 'string', 'max:1000'],
            'create_account' => ['sometimes', 'boolean'],
            'password' => [
                'nullable',
                'required_if:create_account,1',
                'confirmed',
                Password::min(8)->mixedCase()->numbers(),
            ],
            'password_confirmation' => ['nullable', 'string'],
            'communications_consent' => ['sometimes', 'accepted'],
            'check_in_today' => ['sometimes', 'accepted'],
            'privacy_consent' => ['required', 'accepted'],
            'website' => ['prohibited'],
        ], [
            'email.required_without' => 'Enter an email address or phone number.',
            'email.required' => 'An email address is required to create your member login.',
            'email.unique' => 'An account already uses this email. Please sign in or reset your password.',
            'phone.required_without' => 'Enter a phone number or email address.',
            'password.required_if' => 'Create a password for your member login.',
            'privacy_consent.accepted' => 'Please confirm that the church may securely use this information for membership and pastoral care.',
        ]);

        $campus = Campus::query()->where('church_id', $church->id)->find($validated['campus_id'] ?? null)
            ?? $this->campuses($church)->first();
        $contactMember = $this->findMemberByContact($church, $validated);
        $existingMember = $contactMember && $this->namesMatch($contactMember, $validated) ? $contactMember : null;

        if ($contactMember && ! $existingMember) {
            return back()
                ->withErrors(['identity' => 'We could not safely match those details. Please check your name and contact information or ask the welcome team for help.'])
                ->withInput();
        }

        if ($validated['registration_type'] === 'returning' && ! $existingMember) {
            return back()
                ->withErrors(['identity' => 'We could not safely match those details. Please check your name and contact information, or choose “I’m new here”.'])
                ->withInput();
        }

        $reference = Str::upper(Str::random(10));
        $isReturning = $existingMember !== null;
        $accountCreated = false;

        DB::transaction(function () use ($request, $church, $campus, $validated, $existingMember, $reference, $isReturning, &$accountCreated): void {
            $member = $existingMember ?? Member::query()->create([
                'church_id' => $church->id,
                'campus_id' => $campus?->id,
                'first_name' => Str::title(trim($validated['first_name'])),
                'last_name' => Str::title(trim($validated['last_name'])),
                'email' => filled($validated['email'] ?? null) ? Str::lower(trim($validated['email'])) : null,
                'phone' => filled($validated['phone'] ?? null) ? trim($validated['phone']) : null,
                'status' => 'new',
                'joined_at' => today(),
            ]);

            $this->syncProfile($member, $validated, $isReturning);

            if ($request->boolean('create_account')) {
                $user = User::query()->create([
                    'church_id' => $church->id,
                    'campus_id' => $campus?->id ?? $member->campus_id,
                    'member_id' => $member->id,
                    'name' => trim($member->first_name.' '.$member->last_name),
                    'email' => Str::lower(trim($validated['email'])),
                    'phone' => filled($validated['phone'] ?? null) ? trim($validated['phone']) : $member->phone,
                    'password' => $validated['password'],
                    'title' => 'Member',
                    'date_joined' => $member->joined_at ?? today(),
                    'status' => 'active',
                    'password_changed_at' => now(),
                    'account_settings' => [
                        'member_portal' => [
                            'created_via' => 'self_registration',
                            'created_at' => now()->toIso8601String(),
                        ],
                    ],
                ]);

                $user->roles()->syncWithoutDetaching([$this->memberRole()->id]);
                $accountCreated = true;
            }

            if ($request->boolean('check_in_today')) {
                AttendanceRecord::query()->updateOrCreate(
                    [
                        'church_id' => $church->id,
                        'member_id' => $member->id,
                        'service_date' => today()->toDateString(),
                    ],
                    [
                        'campus_id' => $campus?->id ?? $member->campus_id,
                        'status' => 'present',
                        'checked_in_at' => now(),
                        'metadata' => [
                            'source' => 'public member registration',
                            'registration_reference' => $reference,
                        ],
                    ],
                );
            }

            ActivityLog::query()->create([
                'church_id' => $church->id,
                'campus_id' => $campus?->id ?? $member->campus_id,
                'user_id' => null,
                'subject_type' => $member->getMorphClass(),
                'subject_id' => $member->id,
                'module' => 'Members',
                'action' => $isReturning ? 'member_self_registration_returned' : 'member_self_registered',
                'description' => ($isReturning ? 'Returning member completed self-registration.' : 'New member completed self-registration.'),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'properties' => [
                    'source' => 'public_self_registration',
                    'reference' => $reference,
                    'registration_type' => $isReturning ? 'returning' : 'new',
                    'selected_path' => $validated['registration_type'],
                    'interests' => $validated['interests'] ?? [],
                    'how_heard' => $validated['how_heard'] ?? null,
                    'follow_up_requested' => filled($validated['support_note'] ?? null),
                    'checked_in_today' => $request->boolean('check_in_today'),
                    'member_login_created' => $accountCreated,
                    'privacy_consent_at' => now()->toIso8601String(),
                ],
            ]);
        });

        return redirect()->route('members.self-register')->with('registration_complete', [
            'reference' => $reference,
            'first_name' => Str::title(trim($validated['preferred_name'] ?? $validated['first_name'])),
            'checked_in' => $request->boolean('check_in_today'),
            'account_created' => $accountCreated,
            'email' => $accountCreated ? Str::lower(trim($validated['email'])) : null,
        ]);
    }

    /**
     * @return Collection<int, Campus>
     */
    private function campuses(Church $church): Collection
    {
        return Campus::query()
            ->where('church_id', $church->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    private function findMemberByContact(Church $church, array $validated): ?Member
    {
        $email = Str::lower(trim((string) ($validated['email'] ?? '')));
        $phone = $this->normalizedPhone($validated['phone'] ?? null);

        if ($email !== '') {
            $emailMember = Member::query()
                ->where('church_id', $church->id)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->with('memberProfile')
                ->first();

            if ($emailMember) {
                return $emailMember;
            }
        }

        if ($phone === '') {
            return null;
        }

        $phoneSuffix = substr($phone, -4);

        return Member::query()
            ->where('church_id', $church->id)
            ->whereNotNull('phone')
            ->where('phone', 'like', '%'.$phoneSuffix.'%')
            ->with('memberProfile')
            ->get()
            ->first(fn (Member $member): bool => $this->normalizedPhone($member->phone) === $phone);
    }

    private function namesMatch(Member $member, array $validated): bool
    {
        return Str::lower(trim($member->first_name)) === Str::lower(trim($validated['first_name']))
            && Str::lower(trim($member->last_name)) === Str::lower(trim($validated['last_name']));
    }

    private function normalizedPhone(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }

    private function syncProfile(Member $member, array $validated, bool $isReturning): void
    {
        $profile = $member->memberProfile()->firstOrNew(['member_id' => $member->id]);
        $communicationPreferences = is_array($profile->communication_preferences) ? $profile->communication_preferences : [];
        $spiritualJourney = is_array($profile->spiritual_journey) ? $profile->spiritual_journey : [];

        $communicationPreferences['preferred_contact'] = $validated['preferred_contact'];
        if (isset($validated['communications_consent'])) {
            $communicationPreferences['email_notifications'] = $validated['preferred_contact'] === 'email';
            $communicationPreferences['sms_notifications'] = $validated['preferred_contact'] === 'phone';
            $communicationPreferences['self_service_consent_at'] = now()->toIso8601String();
        }

        $spiritualJourney['connection_interests'] = $validated['interests'] ?? [];
        $spiritualJourney['how_heard'] = $validated['how_heard'] ?? null;
        $spiritualJourney['last_self_registration_at'] = now()->toIso8601String();

        $profileData = [
            'communication_preferences' => $communicationPreferences,
            'spiritual_journey' => $spiritualJourney,
        ];

        if (! $isReturning) {
            $profileData = array_merge($profileData, Arr::only($validated, [
                'preferred_name',
                'date_of_birth',
                'gender',
                'address_line',
                'city',
                'emergency_contact_name',
                'emergency_contact_phone',
            ]));
        }

        if (filled($validated['support_note'] ?? null)) {
            $note = '['.now()->format('M d, Y').' self-registration] '.trim($validated['support_note']);
            $profileData['care_notes'] = filled($profile->care_notes) ? $profile->care_notes."\n".$note : $note;
        }

        $profile->fill($profileData);
        $profile->save();
    }

    private function memberRole(): Role
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'Member'],
            ['slug' => 'member', 'description' => 'Default access for church members using the self-service portal.'],
        );

        if ($role->wasRecentlyCreated) {
            $permissionIds = collect(['use messages', 'use bible'])
                ->map(fn (string $name): int => Permission::query()->firstOrCreate(
                    ['name' => $name],
                    ['slug' => Str::slug($name), 'description' => 'Allows user to '.$name],
                )->id);

            $role->permissions()->sync($permissionIds);
        }

        return $role;
    }
}
