<?php

declare(strict_types=1);

namespace App\Services\MemberImport;

use App\Models\MemberImportConnection;
use Illuminate\Support\Carbon;
use RuntimeException;

final class LegacyEcclesiaMigrationReader
{
    private const PROFILE_FIELDS = [
        'preferred_name', 'date_of_birth', 'gender', 'marital_status', 'anniversary_date',
        'occupation', 'employer', 'place_of_birth', 'nationality', 'address_line', 'city', 'state', 'postal_code', 'country',
        'alternate_email', 'home_phone', 'emergency_contact_name', 'emergency_contact_relationship',
        'emergency_contact_phone', 'emergency_contact_alt_phone', 'care_level', 'care_notes',
        'communication_preferences', 'spiritual_journey', 'skills', 'documents', 'volunteer_hours',
    ];

    public function __construct(private readonly MemberImportDatabaseReader $reader) {}

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function read(MemberImportConnection $connection): array
    {
        $tables = $this->reader->tables($connection);
        $memberTable = $this->table($tables, 'members');
        if (! $memberTable) {
            throw new RuntimeException('This source does not contain an EcclesiaOS members table.');
        }
        $members = $this->reader->read($connection, $memberTable)['rows'];
        $profiles = $this->indexed($this->optionalRows($connection, $tables, 'member_profiles'), 'member_id');
        $families = $this->indexed($this->optionalRows($connection, $tables, 'families'), 'id');
        $campuses = $this->indexed($this->optionalRows($connection, $tables, 'campuses'), 'id');
        $ministries = $this->indexed($this->optionalRows($connection, $tables, 'ministries'), 'id');
        $history = [];
        $historyCounts = [];
        $this->appendHistory($history, $historyCounts, 'attendance', $this->optionalRows($connection, $tables, 'attendance_records'));
        $this->appendHistory($history, $historyCounts, 'giving', $this->optionalRows($connection, $tables, 'donations'));
        $this->appendHistory($history, $historyCounts, 'care', $this->optionalRows($connection, $tables, 'care_tasks'));
        $this->appendHistory($history, $historyCounts, 'volunteer', $this->optionalRows($connection, $tables, 'volunteers'), $ministries);

        $rows = collect($members)->map(function (array $member) use ($profiles, $families, $campuses, $history): array {
            $oldId = (string) ($member['id'] ?? $member['member_id'] ?? '');
            $profile = $profiles[$oldId] ?? [];
            foreach (self::PROFILE_FIELDS as $field) {
                if (array_key_exists($field, $profile)) {
                    $member[$field] = $this->decoded($profile[$field]);
                }
            }
            $familyId = (string) ($member['family_id'] ?? '');
            $campusId = (string) ($member['campus_id'] ?? '');
            $member['external_id'] = $oldId;
            $member['family_name'] = $families[$familyId]['name'] ?? null;
            $member['campus'] = $campuses[$campusId]['name'] ?? null;
            $member['_legacy_history'] = $history[$oldId] ?? [];

            return $member;
        })->all();
        $headers = collect($rows)->flatMap(fn (array $row) => array_keys($row))
            ->reject(fn (string $header) => str_starts_with($header, '_'))
            ->unique()->values()->all();

        return [
            'headers' => $headers,
            'rows' => $rows,
            'summary' => [
                'members' => count($rows),
                'profiles' => count($profiles),
                'families' => count($families),
                'campuses' => count($campuses),
                'history' => $historyCounts,
                'source_tables' => $tables,
            ],
        ];
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $history
     * @param  array<string, int>  $counts
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array<string, mixed>>  $ministries
     */
    private function appendHistory(array &$history, array &$counts, string $type, array $rows, array $ministries = []): void
    {
        foreach ($rows as $row) {
            $memberId = (string) ($row['member_id'] ?? '');
            if ($memberId === '') {
                continue;
            }
            $event = match ($type) {
                'attendance' => [
                    'event_type' => 'attendance',
                    'status' => $row['status'] ?? 'present',
                    'occurred_at' => $row['checked_in_at'] ?? $row['service_date'] ?? $row['created_at'] ?? null,
                    'description' => 'Attendance recorded on '.($row['service_date'] ?? 'an imported service date').'.',
                ],
                'giving' => [
                    'event_type' => 'giving',
                    'status' => 'received',
                    'occurred_at' => $row['received_at'] ?? $row['created_at'] ?? null,
                    'description' => trim(($row['currency'] ?? '').' '.($row['amount'] ?? '0').' contribution imported from the old installation.'),
                ],
                'care' => [
                    'event_type' => 'care',
                    'status' => $row['status'] ?? null,
                    'occurred_at' => $row['resolved_at'] ?? $row['due_at'] ?? $row['created_at'] ?? null,
                    'description' => trim((string) ($row['type'] ?? 'Care task').': '.($row['next_action'] ?? $row['notes'] ?? 'Imported care record')),
                ],
                'volunteer' => [
                    'event_type' => 'volunteer',
                    'status' => $row['status'] ?? null,
                    'occurred_at' => $row['created_at'] ?? null,
                    'description' => trim('Volunteer assignment'.(isset($ministries[(string) ($row['ministry_id'] ?? '')]['name']) ? ' in '.$ministries[(string) $row['ministry_id']]['name'] : '').(filled($row['role'] ?? null) ? ' as '.$row['role'] : '').'.'),
                ],
            };
            $event['occurred_at'] = $this->date($event['occurred_at']);
            $event['source_reference'] = $type.':'.($row['id'] ?? count($history[$memberId] ?? []));
            $event['metadata'] = $row;
            $history[$memberId][] = $event;
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function indexed(array $rows, string $key): array
    {
        return collect($rows)->filter(fn (array $row) => filled($row[$key] ?? null))
            ->keyBy(fn (array $row) => (string) $row[$key])->all();
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<int, array<string, mixed>>
     */
    private function optionalRows(MemberImportConnection $connection, array $tables, string $name): array
    {
        $table = $this->table($tables, $name);
        if (! $table) {
            return [];
        }
        try {
            return $this->reader->read($connection, $table)['rows'];
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'contains no rows')) {
                return [];
            }
            throw $exception;
        }
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function table(array $tables, string $name): ?string
    {
        return collect($tables)->first(fn (string $table) => $table === $name || str_ends_with($table, '.'.$name));
    }

    private function decoded(mixed $value): mixed
    {
        if (! is_string($value) || ! str_starts_with(trim($value), '[') && ! str_starts_with(trim($value), '{')) {
            return $value;
        }
        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function date(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }
        try {
            return Carbon::parse((string) $value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}
