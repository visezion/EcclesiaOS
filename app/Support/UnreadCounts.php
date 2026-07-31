<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\MessageThread;
use App\Models\User;

final class UnreadCounts
{
    /** @return array{notifications: int, messages: int} */
    public function for(User $user): array
    {
        $messages = MessageThread::query()
            ->where('church_id', $user->church_id)
            ->whereHas('participants', function ($query) use ($user): void {
                $query
                    ->where('users.id', $user->id)
                    ->where(function ($query): void {
                        $query
                            ->whereNull('message_thread_user.last_read_at')
                            ->orWhereColumn('message_thread_user.last_read_at', '<', 'message_threads.last_message_at');
                    });
            })
            ->when(! $user->isSuperAdministrator() && ! $user->hasPermission('view sensitive messages'), function ($query) use ($user): void {
                $query->where(function ($query) use ($user): void {
                    $query
                        ->whereNotIn('permission_scope', ['leadership', 'restricted'])
                        ->orWhere('created_by', $user->id);
                });
            })
            ->count();

        return [
            'notifications' => $user->unreadNotifications()->count(),
            'messages' => $messages,
        ];
    }
}
