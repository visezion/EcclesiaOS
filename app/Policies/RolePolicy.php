<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

final class RolePolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdministrator() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage roles');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage roles');
    }

    public function update(User $user, Role $role): bool
    {
        if ($role->name === 'Super Administrator') {
            return false;
        }

        return $user->hasPermission('manage roles');
    }
}
