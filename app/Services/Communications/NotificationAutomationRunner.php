<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Models\CommunicationDelivery;
use App\Models\EventSession;
use App\Models\NotificationAutomationRule;
use Carbon\Carbon;

final class NotificationAutomationRunner
{
    public function __construct(private readonly DomainNotificationService $notifications) {}

    public function runDueReminders(int $limit = 100): int
    {
        $sent = 0;

        NotificationAutomationRule::query()
            ->where('event_type', 'EventReminderDue')
            ->where('enabled', true)
            ->whereNotNull('reminder_minutes')
            ->get()
            ->each(function (NotificationAutomationRule $rule) use (&$sent, $limit): void {
                if ($sent >= $limit) {
                    return;
                }

                $now = now();
                $latest = $now->copy()->addMinutes((int) $rule->reminder_minutes);
                $alreadySent = CommunicationDelivery::query()
                    ->where('church_id', $rule->church_id)
                    ->where('event_type', 'EventReminderDue')
                    ->get(['metadata'])
                    ->pluck('metadata')
                    ->map(fn (?array $metadata) => (int) ($metadata['event_session_id'] ?? 0))
                    ->filter()
                    ->all();

                EventSession::query()
                    ->where('church_id', $rule->church_id)
                    ->where('status', 'scheduled')
                    ->whereDate('session_date', '>=', $now->toDateString())
                    ->whereDate('session_date', '<=', $latest->toDateString())
                    ->whereNotIn('id', $alreadySent)
                    ->orderBy('session_date')
                    ->limit($limit - $sent)
                    ->get()
                    ->each(function (EventSession $session) use ($rule, $now, $latest, &$sent): void {
                        $startsAt = Carbon::parse($session->session_date->toDateString().' '.$session->starts_at, $session->timezone ?: config('app.timezone'));
                        if ($startsAt->isBefore($now) || $startsAt->isAfter($latest)) {
                            return;
                        }

                        $recipientCount = $this->notifications->audience(
                            (int) $session->church_id,
                            $session->campus_id ? (int) $session->campus_id : null,
                            'EventReminderDue',
                            'events',
                            'Upcoming: '.$session->title,
                            $session->title.' begins '.$startsAt->diffForHumans().'.',
                            $rule->channels ?: ['in_app'],
                            [
                                'event_session_id' => $session->id,
                                'event_title' => $session->title,
                                'event_date' => $startsAt->format('M d, Y'),
                                'event_time' => $startsAt->format('g:i A'),
                                'url' => route('event-sessions.meeting', $session),
                            ],
                            (bool) $rule->critical,
                        );

                        if ($recipientCount > 0) {
                            $sent++;
                        }
                    });
            });

        return $sent;
    }
}
