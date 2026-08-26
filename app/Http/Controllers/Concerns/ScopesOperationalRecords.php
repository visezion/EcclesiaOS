<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Campus;
use App\Models\Church;
use App\Models\Member;
use App\Models\User;
use App\Support\AccessScope;
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
        abort_unless(AccessScope::canAccessRecord($request->user(), $record), 403);
    }

    private function scopeChurchCampus(Builder $query, Request $request): Builder
    {
        return AccessScope::scope($query, $request->user());
    }

    private function visibleChurches(Request $request): Builder
    {
        $query = Church::query()->orderBy('name');
        $user = $request->user();

        return AccessScope::isChurchAdministrator($user)
            ? $query
            : $query->whereKey($user?->church_id);
    }

    private function visibleCampuses(Request $request): Builder
    {
        $query = Campus::query()->orderBy('name');
        $user = $request->user();

        if (AccessScope::isChurchAdministrator($user)) {
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
        return AccessScope::scope(Member::query(), $request->user())->orderBy('last_name')->orderBy('first_name');
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
