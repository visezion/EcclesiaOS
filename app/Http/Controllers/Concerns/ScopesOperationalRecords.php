<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Campus;
use App\Models\Church;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait ScopesOperationalRecords
{
    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission($permission), 403);
    }

    private function authorizeScopedRecord(Request $request, mixed $record): void
    {
        $user = $request->user();
        abort_unless($user?->canAccessChurch($record->church_id ?? null) && $user->canAccessCampus($record->campus_id ?? null), 403);
    }

    private function scopeChurchCampus(Builder $query, Request $request): Builder
    {
        $user = $request->user();

        if ($user?->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $user?->church_id);

        if ($user?->campus_id !== null) {
            $query->where(fn (Builder $campusQuery) => $campusQuery
                ->whereNull('campus_id')
                ->orWhere('campus_id', $user->campus_id));
        }

        return $query;
    }

    private function visibleChurches(Request $request): Builder
    {
        $query = Church::query()->orderBy('name');
        $user = $request->user();

        return $user?->isSuperAdministrator()
            ? $query
            : $query->whereKey($user?->church_id);
    }

    private function visibleCampuses(Request $request): Builder
    {
        $query = Campus::query()->orderBy('name');
        $user = $request->user();

        if ($user?->isSuperAdministrator()) {
            return $query;
        }

        $query->where('church_id', $user?->church_id);

        if ($user?->campus_id !== null) {
            $query->whereKey($user->campus_id);
        }

        return $query;
    }

    private function visibleMembers(Request $request): Builder
    {
        return $this->scopeChurchCampus(Member::query(), $request)->orderBy('last_name')->orderBy('first_name');
    }

    private function visibleUsers(Request $request): Builder
    {
        return $this->scopeChurchCampus(User::query(), $request)->orderBy('name');
    }

    private function defaultChurchId(Request $request): int
    {
        $user = $request->user();

        if (! $user?->isSuperAdministrator()) {
            return (int) $user?->church_id;
        }

        return (int) Church::query()->orderBy('id')->value('id');
    }

    private function validatedCampusId(Request $request, mixed $campusId): ?int
    {
        if (! filled($campusId)) {
            return null;
        }

        $id = (int) $campusId;
        abort_unless($this->visibleCampuses($request)->whereKey($id)->exists(), 403);

        return $id;
    }
}
