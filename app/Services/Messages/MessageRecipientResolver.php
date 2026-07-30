<?php

declare(strict_types=1);

namespace App\Services\Messages;

use App\Models\Campus;
use App\Models\Ministry;
use App\Models\Role;
use App\Models\User;
use App\Support\OpaqueId;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class MessageRecipientResolver
{
    /**
     * @return array{users: Collection<int, User>, recipients: array<int, array<string, int|string>>}
     */
    public function resolve(User $sender, array $tokens): array
    {
        $users = collect();
        $recipientRows = [];

        foreach (array_unique($tokens) as $token) {
            [$type, $opaqueId] = str_contains($token, ':') ? explode(':', $token, 2) : ['user', $token];
            [$resolved, $label, $id] = match ($type) {
                'user' => $this->users($sender, $opaqueId),
                'role' => $this->roleUsers($sender, $opaqueId),
                'campus' => $this->campusUsers($sender, $opaqueId),
                'ministry' => $this->ministryUsers($sender, $opaqueId),
                'leadership' => $this->leadershipUsers($sender),
                default => throw ValidationException::withMessages(['recipients' => 'An unsupported recipient type was selected.']),
            };

            $users = $users->merge($resolved);
            $recipientRows[] = [
                'recipient_type' => $type,
                'recipient_id' => $id,
                'label' => $label,
                'resolved_count' => $resolved->count(),
            ];
        }

        $users = $users
            ->where('id', '!=', $sender->id)
            ->unique('id')
            ->values();
        if (! config('messages.allow_cross_campus', true)
            && ! $sender->isSuperAdministrator()
            && ! $sender->hasPermission('administer messages')) {
            $users = $users->where('campus_id', $sender->campus_id)->values();
        }

        if ($users->isEmpty()) {
            throw ValidationException::withMessages(['recipients' => 'No active recipients could be resolved from the selection.']);
        }

        if ($users->count() > 500) {
            throw ValidationException::withMessages(['recipients' => 'A conversation cannot include more than 500 resolved users.']);
        }

        return ['users' => $users, 'recipients' => $recipientRows];
    }

    private function users(User $sender, string $opaqueId): array
    {
        $id = OpaqueId::decode($opaqueId, User::class);
        $user = User::query()->where('church_id', $sender->church_id)->where('status', 'active')->find($id);
        if (! $user) {
            throw ValidationException::withMessages(['recipients' => 'A selected user is unavailable.']);
        }

        return [collect([$user]), $user->name, $user->id];
    }

    private function roleUsers(User $sender, string $opaqueId): array
    {
        $id = OpaqueId::decode($opaqueId, Role::class);
        $role = Role::query()->find($id);
        if (! $role) {
            throw ValidationException::withMessages(['recipients' => 'A selected role is unavailable.']);
        }

        $users = $role->users()->where('church_id', $sender->church_id)->where('status', 'active')->get();

        return [$users, $role->name, $role->id];
    }

    private function campusUsers(User $sender, string $opaqueId): array
    {
        $id = OpaqueId::decode($opaqueId, Campus::class);
        $campus = Campus::query()->where('church_id', $sender->church_id)->find($id);
        if (! $campus) {
            throw ValidationException::withMessages(['recipients' => 'A selected campus is unavailable.']);
        }

        return [$campus->users()->where('status', 'active')->get(), $campus->name, $campus->id];
    }

    private function ministryUsers(User $sender, string $opaqueId): array
    {
        $id = OpaqueId::decode($opaqueId, Ministry::class);
        $ministry = Ministry::query()->where('church_id', $sender->church_id)->find($id);
        if (! $ministry) {
            throw ValidationException::withMessages(['recipients' => 'A selected ministry is unavailable.']);
        }

        $emails = $ministry->volunteers()->with('member:id,email')->get()->pluck('member.email')->filter();
        $users = User::query()->where('church_id', $sender->church_id)->where('status', 'active')->whereIn('email', $emails)->get();

        return [$users, $ministry->name, $ministry->id];
    }

    private function leadershipUsers(User $sender): array
    {
        $users = User::query()
            ->where('church_id', $sender->church_id)
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['Super Administrator', 'Church Administrator', 'Senior Pastor', 'Branch Pastor']))
            ->get();

        return [$users, 'Leadership group', $sender->church_id];
    }
}
