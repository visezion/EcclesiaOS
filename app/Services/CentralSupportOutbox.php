<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CentralSupportSyncEvent;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use Illuminate\Support\Str;
use Throwable;

final class CentralSupportOutbox
{
    public function enqueueTicket(SupportTicket $ticket): void
    {
        $this->enqueue($ticket, 'ticket.created', [
            'local_ticket_id' => $ticket->opaqueId(),
            'reference' => $ticket->reference,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'progress' => $ticket->progress,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'expected_outcome' => $ticket->expected_outcome,
            'page_url' => $ticket->page_url,
            'browser' => $ticket->browser,
            'reporter' => [
                'local_id' => $ticket->creator?->opaqueId(),
                'name' => $ticket->creator?->name,
                'email' => $ticket->creator?->email,
            ],
            'church' => [
                'local_id' => $ticket->church?->opaqueId(),
                'name' => $ticket->church?->name,
            ],
            'attachments' => $ticket->attachments->map(fn ($attachment): array => [
                'local_id' => $attachment->opaqueId(),
                'name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'sha256' => $attachment->sha256,
            ])->values()->all(),
        ]);
    }

    public function enqueueReply(SupportTicket $ticket, SupportTicketReply $reply): void
    {
        $this->enqueue($ticket, 'ticket.reply.created', [
            'local_ticket_id' => $ticket->opaqueId(),
            'central_ticket_id' => $ticket->central_id,
            'reference' => $ticket->reference,
            'reply' => [
                'local_id' => (string) $reply->id,
                'body' => $reply->body,
                'is_internal' => $reply->is_internal,
                'author_name' => $reply->user?->name,
                'created_at' => $reply->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function enqueueTracking(SupportTicket $ticket): void
    {
        $this->enqueue($ticket, 'ticket.tracking.updated', [
            'local_ticket_id' => $ticket->opaqueId(),
            'central_ticket_id' => $ticket->central_id,
            'reference' => $ticket->reference,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'progress' => $ticket->progress,
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * @return array{sent:int,failed:int,pending:int}
     */
    public function syncPending(CentralSupportClient $client, ?int $churchId = null, int $limit = 25, ?int $ticketId = null): array
    {
        $events = CentralSupportSyncEvent::query()
            ->with(['church', 'ticket'])
            ->whereIn('status', ['pending', 'failed'])
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->when($churchId, fn ($query) => $query->where('church_id', $churchId))
            ->when($ticketId, fn ($query) => $query->where('support_ticket_id', $ticketId))
            ->oldest()
            ->limit($limit)
            ->get();
        $sent = 0;
        $failed = 0;

        foreach ($events as $event) {
            try {
                $response = $client->send($event);
                $event->update(['status' => 'synced', 'attempts' => $event->attempts + 1, 'last_error' => null, 'next_attempt_at' => null, 'synced_at' => now()]);
                $event->ticket->update([
                    'central_id' => $event->ticket->central_id ?: ($response['ticket_id'] ?? null),
                    'sync_status' => 'synced',
                    'sync_error' => null,
                    'synced_at' => now(),
                ]);
                $sent++;
            } catch (Throwable $exception) {
                report($exception);
                $attempts = $event->attempts + 1;
                $message = Str::limit($exception->getMessage(), 1000, '');
                $event->update([
                    'status' => 'failed',
                    'attempts' => $attempts,
                    'last_error' => $message,
                    'next_attempt_at' => now()->addMinutes(min(60, 2 ** min($attempts, 5))),
                ]);
                $event->ticket->update(['sync_status' => 'failed', 'sync_error' => $message]);
                $failed++;
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'pending' => CentralSupportSyncEvent::query()->when($churchId, fn ($query) => $query->where('church_id', $churchId))->whereIn('status', ['pending', 'failed'])->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function enqueue(SupportTicket $ticket, string $eventType, array $payload): void
    {
        CentralSupportSyncEvent::query()->create([
            'church_id' => $ticket->church_id,
            'support_ticket_id' => $ticket->id,
            'event_id' => (string) Str::uuid(),
            'event_type' => $eventType,
            'payload' => $payload,
            'status' => 'pending',
        ]);
        $ticket->update(['sync_status' => 'pending', 'sync_error' => null]);
    }
}
