<?php

declare(strict_types=1);

namespace App\Services\MemberImport;

use App\Models\Campus;
use App\Models\Family;
use App\Models\Member;
use App\Models\MemberExternalIdentity;
use App\Models\MemberHistoryEntry;
use App\Models\MemberImport;
use App\Models\MemberImportRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class MemberImportProcessor
{
    private const MEMBER_FIELDS = ['first_name', 'last_name', 'email', 'phone', 'status', 'joined_at'];

    private const PROFILE_FIELDS = [
        'preferred_name', 'date_of_birth', 'gender', 'marital_status', 'anniversary_date',
        'occupation', 'employer', 'place_of_birth', 'nationality', 'address_line', 'city', 'state', 'postal_code', 'country',
        'alternate_email', 'home_phone', 'emergency_contact_name', 'emergency_contact_relationship',
        'emergency_contact_phone', 'emergency_contact_alt_phone', 'care_level', 'care_notes',
        'communication_preferences', 'spiritual_journey', 'skills', 'documents', 'volunteer_hours',
    ];

    public function __construct(private readonly MemberImportMapper $mapper) {}

    public function process(MemberImport $import): void
    {
        $import->update([
            'status' => 'processing',
            'started_at' => now(),
            'processed_rows' => 0,
            'created_rows' => 0,
            'updated_rows' => 0,
            'skipped_rows' => 0,
            'failed_rows' => 0,
            'error' => null,
        ]);
        $counts = ['created_rows' => 0, 'updated_rows' => 0, 'skipped_rows' => 0, 'failed_rows' => 0];

        $import->rows()->orderBy('row_number')->lazyById(100)->each(function (MemberImportRow $row) use ($import, &$counts): void {
            try {
                $result = $this->processRow($import, $row);
                $counts[$result.'_rows']++;
            } catch (Throwable $exception) {
                report($exception);
                $row->update(['status' => 'failed', 'error' => Str::limit($exception->getMessage(), 2000)]);
                $counts['failed_rows']++;
            }
            $import->update([...$counts, 'processed_rows' => array_sum($counts)]);
        });

        $import->update([
            ...$counts,
            'processed_rows' => array_sum($counts),
            'status' => $counts['failed_rows'] > 0 ? 'completed_with_errors' : 'completed',
            'completed_at' => now(),
            'summary' => [
                ...($import->summary ?? []),
                'message' => 'Member import finished.',
                'rollback_available' => true,
                'history_entries' => MemberHistoryEntry::query()->where('member_import_id', $import->id)->count(),
            ],
        ]);
    }

    public function rollback(MemberImport $import, int $userId): array
    {
        $restored = 0;
        $conflicts = 0;
        $import->rows()->whereIn('status', ['created', 'updated'])->orderByDesc('id')->get()->each(function (MemberImportRow $row) use (&$restored, &$conflicts): void {
            $member = Member::withTrashed()->find($row->imported_member_id);
            if (! $member || ! hash_equals((string) $row->post_import_checksum, $this->checksum($member))) {
                $row->update(['status' => 'rollback_conflict', 'error' => 'This member changed after import and was not rolled back.']);
                $conflicts++;

                return;
            }
            DB::transaction(function () use ($row, $member): void {
                $snapshot = $row->rollback_snapshot ?? [];
                $importedPhotoPath = $member->profile_photo_path;
                MemberHistoryEntry::query()->where('member_import_id', $row->member_import_id)->where('member_id', $member->id)->delete();
                if ($snapshot['created'] ?? false) {
                    $member->delete();
                } else {
                    $member->restore();
                    $member->update($snapshot['member'] ?? []);
                    if ($snapshot['profile_exists'] ?? false) {
                        $member->memberProfile()->withTrashed()->first()?->restore();
                        $member->memberProfile()->updateOrCreate(['member_id' => $member->id], $snapshot['profile'] ?? []);
                    } else {
                        $member->memberProfile()?->delete();
                    }
                }
                if ($importedPhotoPath && $importedPhotoPath !== data_get($snapshot, 'member.profile_photo_path')) {
                    Storage::disk('public')->delete($importedPhotoPath);
                }
                if (filled($snapshot['created_family_id'] ?? null)) {
                    Family::query()->find($snapshot['created_family_id'])?->delete();
                }
                $row->update(['status' => 'rolled_back']);
            });
            $restored++;
        });
        $import->update([
            'status' => $conflicts > 0 ? 'rollback_with_conflicts' : 'rolled_back',
            'rolled_back_at' => now(),
            'rolled_back_by' => $userId,
            'summary' => [...($import->summary ?? []), 'rollback_restored' => $restored, 'rollback_conflicts' => $conflicts],
        ]);

        return compact('restored', 'conflicts');
    }

    private function processRow(MemberImport $import, MemberImportRow $row): string
    {
        if ($row->status === 'invalid' || ($row->status === 'duplicate' && $row->duplicate_action === 'skip')) {
            $row->update(['status' => 'skipped']);

            return 'skipped';
        }

        return DB::transaction(function () use ($import, $row): string {
            $data = $row->normalized_data ?? [];
            $member = $row->matched_member_id ? Member::withTrashed()->find($row->matched_member_id) : null;
            $creating = ! $member || $row->duplicate_action === 'create';
            if ($creating) {
                $member = new Member;
            } else {
                $member->restore();
            }

            $snapshot = $creating ? ['created' => true] : [
                'created' => false,
                'member' => collect(self::MEMBER_FIELDS)->mapWithKeys(fn (string $field) => [$field => $member->{$field}])->all() + ['campus_id' => $member->campus_id, 'family_id' => $member->family_id, 'profile_photo_path' => $member->profile_photo_path],
                'profile_exists' => $member->memberProfile()->withTrashed()->exists(),
                'profile' => $member->memberProfile()->withTrashed()->first()?->only(self::PROFILE_FIELDS) ?? [],
            ];
            $campusId = $this->campusId($import, $data);
            [$familyId, $familyCreated] = $this->familyResolution($import, $data, $campusId);
            if ($familyCreated) {
                $snapshot['created_family_id'] = $familyId;
            }
            $memberValues = collect(self::MEMBER_FIELDS)->mapWithKeys(fn (string $field) => [$field => $data[$field] ?? null])->all();
            $memberValues['status'] = $data['status'] ?? 'active';
            $memberValues['joined_at'] = $data['joined_at'] ?? now()->toDateString();
            $memberValues['campus_id'] = $campusId;
            $memberValues['family_id'] = $familyId;
            $memberValues['church_id'] = $import->church_id;
            $canImportPhoto = $creating || $row->duplicate_action !== 'merge' || blank($member->profile_photo_path);
            $photoPath = $canImportPhoto ? $this->profilePhotoPath($import, $data) : null;
            if ($photoPath) {
                $memberValues['profile_photo_path'] = $photoPath;
            }
            if (! $creating && $row->duplicate_action === 'merge') {
                $memberValues = collect($memberValues)->filter(fn ($value, string $field) => filled($value) && blank($member->{$field}))->all();
            }
            $member->fill($memberValues)->save();

            $profileValues = collect(self::PROFILE_FIELDS)->filter(fn (string $field) => array_key_exists($field, $data) && filled($data[$field]))->mapWithKeys(fn (string $field) => [$field => $data[$field]])->all();
            if ($profileValues !== []) {
                if (! $creating && $row->duplicate_action === 'merge') {
                    $profile = $member->memberProfile;
                    $profileValues = collect($profileValues)->filter(fn ($value, string $field) => blank($profile?->{$field}))->all();
                }
                if ($profileValues !== []) {
                    $member->memberProfile()->updateOrCreate(['member_id' => $member->id], $profileValues);
                }
            }
            if (filled($data['external_id'] ?? null)) {
                MemberExternalIdentity::query()->updateOrCreate([
                    'church_id' => $import->church_id,
                    'source' => $this->mapper->sourceKey($import),
                    'external_id' => (string) $data['external_id'],
                ], ['member_id' => $member->id, 'member_import_id' => $import->id]);
            }
            $this->importHistory($import, $row, $member);
            $row->update([
                'status' => $creating ? 'created' : 'updated',
                'imported_member_id' => $member->id,
                'rollback_snapshot' => $snapshot,
                'post_import_checksum' => $this->checksum($member->fresh()),
                'error' => null,
            ]);

            return $creating ? 'created' : 'updated';
        });
    }

    private function campusId(MemberImport $import, array $data): int
    {
        if (filled($data['campus'] ?? null)) {
            $campus = Campus::query()->where('church_id', $import->church_id)->whereRaw('LOWER(name) = ?', [Str::lower((string) $data['campus'])])->first();
            if ($campus) {
                return $campus->id;
            }
        }

        return (int) (data_get($import->options, 'default_campus_id')
            ?: Campus::query()->where('church_id', $import->church_id)->where('status', 'active')->value('id'));
    }

    /**
     * @return array{0: ?int, 1: bool}
     */
    private function familyResolution(MemberImport $import, array $data, int $campusId): array
    {
        if (blank($data['family_name'] ?? null) || ! data_get($import->options, 'create_families', true)) {
            return [null, false];
        }

        $family = Family::query()->firstOrCreate([
            'church_id' => $import->church_id,
            'campus_id' => $campusId,
            'name' => trim((string) $data['family_name']),
        ]);

        return [$family->id, $family->wasRecentlyCreated];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function profilePhotoPath(MemberImport $import, array $data): ?string
    {
        if (blank($data['profile_photo'] ?? null)) {
            return null;
        }
        $assets = (array) data_get($import->source_options, 'assets', []);
        $asset = $assets[Str::lower(basename((string) $data['profile_photo']))] ?? null;
        if (! is_string($asset) || ! Storage::disk('local')->exists($asset)) {
            return null;
        }
        $contents = Storage::disk('local')->get($asset);
        $image = @getimagesizefromstring($contents);
        $extension = match ($image['mime'] ?? null) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };
        if (! $extension) {
            return null;
        }
        $path = 'member-profile-photos/'.$import->church_id.'/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $contents);

        return $path;
    }

    private function importHistory(MemberImport $import, MemberImportRow $row, Member $member): void
    {
        $history = $row->source_data['_legacy_history'] ?? [];
        if (! is_array($history)) {
            return;
        }
        foreach ($history as $event) {
            if (! is_array($event) || blank($event['event_type'] ?? null)) {
                continue;
            }
            MemberHistoryEntry::query()->updateOrCreate([
                'church_id' => $import->church_id,
                'member_id' => $member->id,
                'event_type' => Str::limit((string) $event['event_type'], 80, ''),
                'source_reference' => filled($event['source_reference'] ?? null) ? Str::limit((string) $event['source_reference'], 255, '') : null,
            ], [
                'member_import_id' => $import->id,
                'status' => filled($event['status'] ?? null) ? Str::limit((string) $event['status'], 60, '') : null,
                'occurred_at' => $event['occurred_at'] ?? null,
                'description' => filled($event['description'] ?? null) ? Str::limit((string) $event['description'], 4000, '') : null,
                'metadata' => $event['metadata'] ?? null,
            ]);
        }
    }

    private function checksum(Member $member): string
    {
        $member->load('memberProfile');
        $payload = $member->only([...self::MEMBER_FIELDS, 'campus_id', 'family_id', 'profile_photo_path', 'deleted_at']);
        $payload['profile'] = $member->memberProfile?->only(self::PROFILE_FIELDS);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
