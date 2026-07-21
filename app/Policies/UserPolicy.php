<?php

namespace App\Policies;

use App\Models\User;

final class UserPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdministrator() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage users');
    }

    public function view(User $user, User $target): bool
    {
        if ($target->isSuperAdministrator()) {
            return false;
        }

        return $user->hasPermission('manage users')
            && $user->canAccessChurch($target->church_id)
            && $user->canAccessCampus($target->campus_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage users');
    }

    public function update(User $user, User $target): bool
    {
        if ($target->isSuperAdministrator()) {
            return false;
        }

        return $user->hasPermission('manage users')
            && $user->canAccessChurch($target->church_id)
            && $user->canAccessCampus($target->campus_id);
    }

    public function assignRoles(User $user, User $target): bool
    {
        return $this->update($user, $target) && $user->hasPermission('manage roles');
    }
}
