<?php

declare(strict_types=1);

namespace App\Services\Backups;

use App\Models\Church;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PortableSnapshotExporter
{
    /**
     * @return array{tables: array<string, array<int, array<string, mixed>>>, record_count: int}
     */
    public function export(Church $church): array
    {
        $schema = Schema::getConnection()->getSchemaBuilder();
        $excluded = array_merge((array) config('backups.excluded_tables', []), [
            'migrations', 'backup_records', 'backup_events', 'backup_restore_requests', 'backup_restore_uploads',
        ]);
        $tableNames = collect($schema->getTables())
            ->map(fn (array $table): string => (string) ($table['name'] ?? $table['table'] ?? ''))
            ->filter()
            ->reject(fn (string $table): bool => in_array($table, $excluded, true))
            ->values();
        $tables = [];

        DB::transaction(function () use ($church, $schema, $tableNames, $excluded, &$tables): void {
            foreach ($tableNames as $table) {
                $columns = collect($schema->getColumns($table))->pluck('name');
                if ($table === 'churches') {
                    $tables[$table] = DB::table($table)->where('id', $church->id)->get()->map(fn ($row): array => (array) $row)->all();
                } elseif ($columns->contains('church_id')) {
                    $tables[$table] = DB::table($table)->where('church_id', $church->id)->orderBy($columns->contains('id') ? 'id' : $columns->first())->get()->map(fn ($row): array => (array) $row)->all();
                }
            }

            $userIds = collect($tables['users'] ?? [])->pluck('id')->filter()->all();
            if ($userIds !== [] && Schema::hasTable('role_user')) {
                $tables['role_user'] = DB::table('role_user')->whereIn('user_id', $userIds)->get()->map(fn ($row): array => (array) $row)->all();
                $roleIds = collect($tables['role_user'])->pluck('role_id')->filter()->unique()->all();
                $tables['roles'] = DB::table('roles')->whereIn('id', $roleIds)->get()->map(fn ($row): array => (array) $row)->all();
                $tables['permission_role'] = DB::table('permission_role')->whereIn('role_id', $roleIds)->get()->map(fn ($row): array => (array) $row)->all();
                $permissionIds = collect($tables['permission_role'])->pluck('permission_id')->filter()->unique()->all();
                $tables['permissions'] = DB::table('permissions')->whereIn('id', $permissionIds)->get()->map(fn ($row): array => (array) $row)->all();

                if (Schema::hasTable('notifications')) {
                    $tables['notifications'] = DB::table('notifications')
                        ->where('notifiable_type', 'App\\Models\\User')
                        ->whereIn('notifiable_id', $userIds)
                        ->get()->map(fn ($row): array => (array) $row)->all();
                }
            }

            $this->includeDependentRows($schema, $tableNames->all(), $tables, $excluded);
        }, 3);

        ksort($tables);

        return [
            'tables' => $tables,
            'record_count' => collect($tables)->sum(fn (array $rows): int => count($rows)),
        ];
    }

    /** @param array<int, string> $tableNames @param array<string, array<int, array<string, mixed>>> $tables @param array<int, string> $excluded */
    private function includeDependentRows(object $schema, array $tableNames, array &$tables, array $excluded): void
    {
        $blocked = array_merge($excluded, ['roles', 'permissions', 'permission_role', 'role_user', 'notifications']);
        for ($pass = 0; $pass < 5; $pass++) {
            $changed = false;
            foreach ($tableNames as $table) {
                if (isset($tables[$table]) || in_array($table, $blocked, true)) {
                    continue;
                }
                $foreignKeys = collect($schema->getForeignKeys($table));
                foreach ($foreignKeys as $foreignKey) {
                    $parent = (string) ($foreignKey['foreign_table'] ?? '');
                    $column = $foreignKey['columns'][0] ?? null;
                    $parentColumn = $foreignKey['foreign_columns'][0] ?? 'id';
                    if ($column === null || ! isset($tables[$parent])) {
                        continue;
                    }
                    $parentIds = collect($tables[$parent])->pluck($parentColumn)->filter(fn ($id): bool => $id !== null)->unique()->all();
                    if ($parentIds === []) {
                        continue;
                    }
                    $rows = DB::table($table)->whereIn($column, $parentIds)->get()->map(fn ($row): array => (array) $row)->all();
                    if ($rows !== []) {
                        $tables[$table] = $rows;
                        $changed = true;
                        break;
                    }
                }
            }
            if (! $changed) {
                break;
            }
        }
    }
}
