<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Member;
use App\Models\Ministry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class AccessScope
{
    public static function isChurchAdministrator(?User $user): bool
    {
        return $user?->hasAnyRole(['Super Administrator', 'Church Administrator']) ?? false;
    }

    public static function isBranchPastor(?User $user): bool
    {
        return $user?->hasAnyRole(['Branch Pastor']) ?? false;
    }

    public static function isMinistryLeader(?User $user): bool
    {
        return $user?->hasAnyRole(['Ministry Leader']) ?? false;
    }

    public static function ministryIds(?User $user): array
    {
        if (! $user?->member_id) {
            return [];
        }

        return Ministry::query()
            ->where('leader_id', $user->member_id)
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    public static function scope(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $user->church_id);

        if (self::isChurchAdministrator($user)) {
            return $query;
        }

        if ($user->campus_id !== null) {
            $query->where(fn (Builder $campusQuery) => $campusQuery
                ->whereNull('campus_id')
                ->orWhere('campus_id', $user->campus_id));
        }

        if (! self::isMinistryLeader($user)) {
            return $query;
        }

        $ministryIds = self::ministryIds($user);
        $model = $query->getModel();

        if ($model instanceof Ministry) {
            return $ministryIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereKey($ministryIds);
        }

        if ($model instanceof Member) {
            return self::scopeMembers($user, $query);
        }

        $table = $model->getTable();
        $hasMinistry = Schema::hasColumn($table, 'ministry_id') && $ministryIds !== [];
        $hasMember = Schema::hasColumn($table, 'member_id');
        $hasCreator = Schema::hasColumn($table, 'created_by');
        $hasUserCreator = Schema::hasColumn($table, 'created_by_user_id');
        $hasAssignee = Schema::hasColumn($table, 'assigned_user_id');

        if (! $hasMinistry && ! $hasMember && ! $hasCreator && ! $hasUserCreator && ! $hasAssignee) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $ownershipQuery) use ($hasMinistry, $hasMember, $hasCreator, $hasUserCreator, $hasAssignee, $ministryIds, $user): void {
            $method = 'where';

            if ($hasMinistry) {
                $ownershipQuery->whereIn('ministry_id', $ministryIds);
                $method = 'orWhere';
            }

            if ($hasMember) {
                $ownershipQuery->{$method.'In'}('member_id', self::scopeMembers($user)->select('id'));
                $method = 'orWhere';
            }

            foreach ([
                'created_by' => $hasCreator,
                'created_by_user_id' => $hasUserCreator,
                'assigned_user_id' => $hasAssignee,
            ] as $column => $available) {
                if ($available) {
                    $ownershipQuery->{$method}($column, $user->id);
                    $method = 'orWhere';
                }
            }
        });
    }

    public static function scopeMembers(?User $user, ?Builder $query = null, bool $restrictToMinistry = true): Builder
    {
        $query ??= Member::query();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $user->church_id);

        if (self::isChurchAdministrator($user)) {
            return $query;
        }

        if ($user->campus_id !== null) {
            $query->where(fn (Builder $campusQuery) => $campusQuery
                ->whereNull('campus_id')
                ->orWhere('campus_id', $user->campus_id));
        }

        if ($restrictToMinistry && self::isMinistryLeader($user)) {
            $query->whereHas('volunteers', fn (Builder $volunteerQuery) => $volunteerQuery->whereIn('ministry_id', self::ministryIds($user)));
        }

        return $query;
    }

    public static function canAccessRecord(?User $user, mixed $record): bool
    {
        if (! $user || ! $record) {
            return false;
        }

        return self::scope($record::query(), $user)->whereKey($record->getKey())->exists();
    }
}
