<?php

declare(strict_types=1);

namespace App\Services\Messages;

use App\Models\Message;
use App\Models\MessageAuditEvent;
use App\Models\MessageThread;
use Illuminate\Http\Request;

final class MessageAuditLogger
{
    public function record(
        string $action,
        MessageThread $thread,
        ?Message $message = null,
        array $metadata = [],
        ?Request $request = null,
    ): void {
        MessageAuditEvent::query()->create([
            'church_id' => $thread->church_id,
            'message_thread_id' => $thread->id,
            'message_id' => $message?->id,
            'actor_id' => $request?->user()?->id,
            'action' => $action,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata,
        ]);
    }
}
