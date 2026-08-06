<?php

declare(strict_types=1);

namespace App\Services\MemberImport;

use App\Models\Member;
use App\Models\MemberExternalIdentity;
use App\Models\MemberImport;
use App\Models\MemberImportRow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class MemberImportMapper
{
    private const FIELD_LABELS = [
        'external_id' => 'External member ID',
        'full_name' => 'Full name',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'email' => 'Email',
        'phone' => 'Mobile phone',
        'status' => 'Membership status',
        'joined_at' => 'Date joined',
        'campus' => 'Campus / branch',
        'family_name' => 'Family / household',
        'profile_photo' => 'Profile image filename',
        'preferred_name' => 'Preferred name',
        'date_of_birth' => 'Date of birth',
        'gender' => 'Gender',
        'marital_status' => 'Marital status',
        'anniversary_date' => 'Anniversary date',
        'occupation' => 'Occupation',
        'employer' => 'Employer',
        'address_line' => 'Street address',
        'city' => 'City',
        'state' => 'State / province',
        'postal_code' => 'Postal code',
        'country' => 'Country',
        'alternate_email' => 'Alternate email',
        'home_phone' => 'Home phone',
        'emergency_contact_name' => 'Emergency contact',
        'emergency_contact_phone' => 'Emergency contact phone',
        'care_level' => 'Care level',
        'care_notes' => 'Care notes',
        'skills' => 'Skills',
    ];

    private const ALIASES = [
        'external_id' => ['external_id', 'member_id', 'old_member_id', 'legacy_id', 'contact_id', 'person_id'],
        'full_name' => ['full_name', 'name', 'member_name', 'display_name'],
        'first_name' => ['first_name', 'firstname', 'first', 'fname', 'given_name', 'givenname'],
        'last_name' => ['last_name', 'lastname', 'last', 'lname', 'surname'],
        'email' => ['email', 'email_address', 'primary_email', 'e_mail'],
        'phone' => ['phone', 'phone_number', 'mobile', 'mobile_phone', 'cell', 'telephone'],
        'status' => ['status', 'member_status', 'membership_status'],
        'joined_at' => ['joined_at', 'date_joined', 'join_date', 'member_since', 'membership_date'],
        'campus' => ['campus', 'branch', 'location', 'church', 'site'],
        'family_name' => ['family', 'household', 'household_name'],
        'profile_photo' => ['profile_photo', 'photo', 'avatar', 'image', 'picture', 'photo_filename'],
        'preferred_name' => ['preferred_name', 'nickname', 'known_as'],
        'date_of_birth' => ['date_of_birth', 'birth_date', 'dob', 'birthday'],
        'gender' => ['gender', 'sex'],
        'marital_status' => ['marital_status', 'marriage_status'],
        'anniversary_date' => ['anniversary_date', 'wedding_anniversary', 'anniversary'],
        'occupation' => ['occupation', 'job_title', 'profession'],
        'employer' => ['employer', 'company', 'organization'],
        'address_line' => ['address_line', 'address', 'street', 'street_address'],
        'city' => ['city', 'town'],
        'state' => ['state', 'province', 'region'],
        'postal_code' => ['postal_code', 'zip', 'zip_code', 'postcode'],
        'country' => ['country', 'nation'],
        'alternate_email' => ['alternate_email', 'secondary_email'],
        'home_phone' => ['home_phone', 'telephone_home'],
        'emergency_contact_name' => ['emergency_contact_name', 'emergency_contact'],
        'emergency_contact_phone' => ['emergency_contact_phone', 'emergency_phone'],
        'care_level' => ['care_level', 'pastoral_care_level'],
        'care_notes' => ['care_notes', 'pastoral_notes'],
        'skills' => ['skills', 'talents'],
    ];

    /**
     * @return array<string, string>
     */
    public function fields(): array
    {
        return self::FIELD_LABELS;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<string, string>
     */
    public function autoMap(array $headers): array
    {
        $mapping = [];
        $used = [];
        foreach (self::ALIASES as $field => $aliases) {
            $match = in_array($field, $headers, true)
                ? $field
                : collect($aliases)->first(fn (string $alias): bool => in_array($alias, $headers, true) && ! in_array($alias, $used, true));
            if ($match) {
                $mapping[$field] = $match;
                $used[] = $match;
            }
        }

        return $mapping;
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, string>  $mapping
     * @return array<string, mixed>
     */
    public function normalize(array $source, array $mapping): array
    {
        $data = [];
        foreach ($mapping as $field => $sourceColumn) {
            if (array_key_exists($sourceColumn, $source)) {
                $data[$field] = is_string($source[$sourceColumn]) ? trim($source[$sourceColumn]) : $source[$sourceColumn];
            }
        }

        $fullName = trim((string) ($data['full_name'] ?? ''));
        if (blank($data['first_name'] ?? null) && $fullName !== '') {
            $data['first_name'] = Str::of($fullName)->before(' ')->toString();
        }
        if (blank($data['last_name'] ?? null) && $fullName !== '') {
            $data['last_name'] = Str::of($fullName)->after(' ')->toString();
        }
        $data['email'] = filled($data['email'] ?? null) ? Str::lower((string) $data['email']) : null;
        $data['phone'] = $this->phone($data['phone'] ?? null);
        $data['home_phone'] = $this->phone($data['home_phone'] ?? null);
        $data['emergency_contact_phone'] = $this->phone($data['emergency_contact_phone'] ?? null);
        foreach (['joined_at', 'date_of_birth', 'anniversary_date'] as $dateField) {
            $data[$dateField] = $this->date($data[$dateField] ?? null);
        }
        $status = Str::of((string) ($data['status'] ?? 'active'))->lower()->replace([' ', '_'], '-')->toString();
        $data['status'] = in_array($status, ['active', 'new', 'inactive', 'follow-up', 'archived'], true) ? $status : 'active';
        if (filled($data['gender'] ?? null)) {
            $gender = Str::lower((string) $data['gender']);
            $data['gender'] = match ($gender) {
                'm', 'male' => 'male',
                'f', 'female' => 'female',
                default => $gender,
            };
        }
        if (filled($data['skills'] ?? null) && ! is_array($data['skills'])) {
            $data['skills'] = collect(preg_split('/[,;|]/', (string) $data['skills']))->map(fn ($value) => trim((string) $value))->filter()->values()->all();
        }

        return $data;
    }

    public function analyze(MemberImport $import, MemberImportRow $row, array $mapping, string $duplicateAction = 'skip'): void
    {
        $data = $this->normalize($row->source_data, $mapping);
        $errors = [];
        if (blank($data['first_name'] ?? null)) {
            $errors[] = 'First name is required.';
        }
        if (blank($data['last_name'] ?? null)) {
            $errors[] = 'Last name is required.';
        }
        if (filled($data['email'] ?? null) && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email address is invalid.';
        }

        $match = $errors === [] ? $this->duplicate($import, $data) : null;
        $row->update([
            'normalized_data' => $data,
            'status' => $errors !== [] ? 'invalid' : ($match ? 'duplicate' : 'ready'),
            'duplicate_action' => $match ? $duplicateAction : 'create',
            'matched_member_id' => $match?->id,
            'error' => $errors !== [] ? implode(' ', $errors) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function duplicate(MemberImport $import, array $data): ?Member
    {
        if (filled($data['external_id'] ?? null)) {
            $identity = MemberExternalIdentity::query()
                ->where('church_id', $import->church_id)
                ->where('source', $this->sourceKey($import))
                ->where('external_id', (string) $data['external_id'])
                ->first();
            if ($identity) {
                return Member::query()->find($identity->member_id);
            }
        }
        if (filled($data['email'] ?? null)) {
            $member = Member::withTrashed()->where('church_id', $import->church_id)->whereRaw('LOWER(email) = ?', [Str::lower((string) $data['email'])])->first();
            if ($member) {
                return $member;
            }
        }
        if (filled($data['phone'] ?? null)) {
            $member = Member::withTrashed()->where('church_id', $import->church_id)->where('phone', $data['phone'])->first();
            if ($member) {
                return $member;
            }
        }

        return Member::withTrashed()
            ->where('church_id', $import->church_id)
            ->whereRaw('LOWER(first_name) = ?', [Str::lower((string) ($data['first_name'] ?? ''))])
            ->whereRaw('LOWER(last_name) = ?', [Str::lower((string) ($data['last_name'] ?? ''))])
            ->when(filled($data['date_of_birth'] ?? null), fn ($query) => $query->whereHas('memberProfile', fn ($profile) => $profile->whereDate('date_of_birth', $data['date_of_birth'])))
            ->first();
    }

    public function sourceKey(MemberImport $import): string
    {
        return Str::limit(Str::slug((string) data_get($import->source_options, 'source_name', $import->source_type), '_'), 60, '');
    }

    private function phone(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }
        $value = trim((string) $value);
        $digits = preg_replace('/\D+/', '', $value) ?: '';

        return Str::startsWith($value, '+') ? '+'.$digits : $digits;
    }

    private function date(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }
        if (is_numeric($value) && (float) $value > 20000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }
        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
