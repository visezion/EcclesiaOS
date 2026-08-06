<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Models\UserNotificationPreference;
use Illuminate\Support\Carbon;

final class NotificationPreferenceResolver
{
    /**
     * @return array{allowed: bool, reason: ?string, available_at: ?Carbon, contact: ?string}
     */
    public function resolve(
        int $churchId,
        ?int $userId,
        ?int $memberId,
        string $channel,
        ?string $category,
        bool $critical,
        ?array $snapshot = null,
    ): array {
        if ($userId === null && $memberId === null && $snapshot === null) {
            return $this->decision(true, null, null, null);
        }

        $preference = UserNotificationPreference::query()
            ->where('church_id', $churchId)
            ->where(function ($query) use ($userId, $memberId): void {
                if ($userId !== null) {
                    $query->where('user_id', $userId);
                }
                if ($memberId !== null) {
                    $userId !== null
                        ? $query->orWhere('member_id', $memberId)
                        : $query->where('member_id', $memberId);
                }
            })
            ->orderByRaw('user_id IS NULL')
            ->first();

        $data = $preference ? [
            'channels' => $preference->channels,
            'categories' => $preference->categories,
            'category_channels' => $preference->category_channels,
            'digest_mode' => $preference->digest_mode,
            'quiet_hours_start' => $preference->quiet_hours_start,
            'quiet_hours_end' => $preference->quiet_hours_end,
            'critical_alerts' => $preference->critical_alerts,
            'opted_out_at' => $preference->opted_out_at,
            'push_token' => $preference->push_token,
        ] : ($snapshot ?? []);

        if ($data === []) {
            return $this->decision(true, null, null, null);
        }

        $criticalBypass = $critical && (bool) ($data['critical_alerts'] ?? false);
        if (($data['digest_mode'] ?? 'instant') === 'off' && ! $criticalBypass) {
            return $this->decision(false, 'Notifications are turned off.', null, $data['push_token'] ?? null);
        }
        if (filled($data['opted_out_at'] ?? null) && ! $criticalBypass) {
            return $this->decision(false, 'Recipient opted out.', null, $data['push_token'] ?? null);
        }

        $channels = (array) ($data['channels'] ?? []);
        if ($channels !== [] && ! in_array($channel, $channels, true)) {
            return $this->decision(false, 'Channel disabled by recipient.', null, $data['push_token'] ?? null);
        }

        $categories = (array) ($data['categories'] ?? []);
        if ($category !== null && $categories !== [] && ! in_array($category, $categories, true)) {
            return $this->decision(false, 'Category disabled by recipient.', null, $data['push_token'] ?? null);
        }

        $categoryChannels = (array) data_get($data, 'category_channels.'.($category ?? ''), []);
        if ($categoryChannels !== [] && ! in_array($channel, $categoryChannels, true)) {
            return $this->decision(false, 'Channel disabled for this category.', null, $data['push_token'] ?? null);
        }

        $availableAt = null;
        if (! $criticalBypass) {
            $availableAt = $this->quietHoursEnd($data) ?? $this->digestTime((string) ($data['digest_mode'] ?? 'instant'));
        }

        return $this->decision(true, null, $availableAt, $data['push_token'] ?? null);
    }

    private function quietHoursEnd(array $data): ?Carbon
    {
        $start = $data['quiet_hours_start'] ?? null;
        $end = $data['quiet_hours_end'] ?? null;
        if (blank($start) || blank($end)) {
            return null;
        }

        $now = now();
        $startAt = $now->copy()->setTimeFromTimeString((string) $start);
        $endAt = $now->copy()->setTimeFromTimeString((string) $end);
        $inside = $startAt->lte($endAt)
            ? $now->betweenIncluded($startAt, $endAt)
            : $now->gte($startAt) || $now->lte($endAt);
        if (! $inside) {
            return null;
        }

        if ($now->gte($startAt) && $startAt->gt($endAt)) {
            $endAt->addDay();
        }

        return $endAt;
    }

    private function digestTime(string $mode): ?Carbon
    {
        return match ($mode) {
            'daily' => now()->copy()->addDay()->startOfDay()->addHours(8),
            'weekly' => now()->copy()->next('Monday')->startOfDay()->addHours(8),
            default => null,
        };
    }

    private function decision(bool $allowed, ?string $reason, ?Carbon $availableAt, ?string $contact): array
    {
        return compact('allowed', 'reason') + ['available_at' => $availableAt, 'contact' => $contact];
    }
}
