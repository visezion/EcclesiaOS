<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\MessageAuditEvent;
use App\Models\MessageThread;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class DispatchScheduledMessages extends Command
{
    protected $signature = 'messages:dispatch-scheduled';

    protected $description = 'Send due internal messages and awaiting-response reminders';

    public function handle(): int
    {
        Message::query()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->with(['thread', 'sender'])
            ->orderBy('scheduled_at')
            ->chunkById(100, function ($messages): void {
                foreach ($messages as $message) {
                    $this->dispatch($message);
                }
            });

        $this->sendAwaitingResponseReminders();

        return self::SUCCESS;
    }

    private function dispatch(Message $message): void
    {
        $recipientIds = collect($message->thread->metadata['scheduled_recipient_ids'] ?? [])->filter()->unique();
        $recipients = User::query()
            ->where('church_id', $message->thread->church_id)
            ->where('status', 'active')
            ->whereIn('id', $recipientIds)
            ->get();

        $dispatched = DB::transaction(function () use ($message, $recipients): bool {
            $locked = Message::query()->lockForUpdate()->findOrFail($message->id);
            if ($locked->status !== 'scheduled') {
                return false;
            }
            foreach ($recipients as $recipient) {
                $message->thread->participants()->syncWithoutDetaching([
                    $recipient->id => ['participant_role' => 'member', 'joined_at' => now()],
                ]);
            }
            $locked->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
            $metadata = $message->thread->metadata ?? [];
            unset($metadata['scheduled_recipient_ids']);
            $message->thread->forceFill(['metadata' => $metadata, 'last_message_at' => now()])->save();

            return true;
        });

        if (! $dispatched) {
            return;
        }

        $recipients->each->notify(new NewMessageNotification($message->thread, $message->sender->name));
        MessageAuditEvent::query()->create([
            'church_id' => $message->thread->church_id,
            'message_thread_id' => $message->thread->id,
            'message_id' => $message->id,
            'actor_id' => $message->sender_id,
            'action' => 'scheduled_message_dispatched',
            'metadata' => ['recipient_count' => $recipients->count()],
        ]);
    }

    private function sendAwaitingResponseReminders(): void
    {
        MessageThread::query()
            ->where('status', 'active')
            ->where('last_message_at', '<=', now()->subDay())
            ->with(['latestMessage.sender', 'participants'])
            ->chunkById(100, function ($threads): void {
                foreach ($threads as $thread) {
                    $metadata = $thread->metadata ?? [];
                    if (isset($metadata['last_response_reminder_at']) && now()->diffInHours($metadata['last_response_reminder_at']) < 24) {
                        continue;
                    }
                    $sender = $thread->latestMessage?->sender;
                    if (! $sender || ! $thread->participants->contains('id', $sender->id)) {
                        continue;
                    }
                    $sender->notify(new NewMessageNotification($thread, customMessage: 'Your conversation is still awaiting a response.'));
                    $metadata['last_response_reminder_at'] = now()->toIso8601String();
                    $thread->forceFill(['metadata' => $metadata])->save();
                }
            });
    }
}
