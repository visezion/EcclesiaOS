<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Models\CommunicationDelivery;
use App\Models\CommunicationProviderSetting;
use App\Models\Member;
use App\Models\NotificationAutomationRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class DomainNotificationService
{
    public function __construct(
        private readonly CommunicationDeliveryDispatcher $dispatcher,
        private readonly NotificationPreferenceResolver $preferences,
    ) {}

    /**
     * @param  array<int, string>  $channels
     * @param  array<string, mixed>  $metadata
     * @return Collection<int, CommunicationDelivery>
     */
    public function user(
        User $user,
        string $eventType,
        string $category,
        string $subject,
        string $message,
        array $channels = ['in_app'],
        array $metadata = [],
        bool $critical = false,
    ): Collection {
        $rule = $this->rule((int) $user->church_id, $eventType);
        if ($this->isDisabled($rule, $metadata)) {
            $this->recordRun($rule, 0, 'skipped');

            return collect();
        }
        [$subject, $message, $channels, $critical] = $this->applyRule($rule, $subject, $message, $channels, $critical, $metadata);
        $deliveries = $this->createDeliveriesForUser($user, $eventType, $category, $subject, $message, $channels, $metadata, $critical);
        $this->recordRun($rule, 1, $this->deliveryStatus($deliveries), $this->deliveryError($deliveries));

        return $deliveries;
    }

    /**
     * @param  array<int, string>  $channels
     * @param  array<string, mixed>  $metadata
     * @return Collection<int, CommunicationDelivery>
     */
    public function member(
        Member $member,
        string $eventType,
        string $category,
        string $subject,
        string $message,
        array $channels = ['in_app'],
        array $metadata = [],
        bool $critical = false,
    ): Collection {
        $rule = $this->rule((int) $member->church_id, $eventType);
        if ($this->isDisabled($rule, $metadata)) {
            $this->recordRun($rule, 0, 'skipped');

            return collect();
        }
        [$subject, $message, $channels, $critical] = $this->applyRule($rule, $subject, $message, $channels, $critical, $metadata);
        $deliveries = $this->createDeliveriesForMember($member, $eventType, $category, $subject, $message, $channels, $metadata, $critical);
        $this->recordRun($rule, 1, $this->deliveryStatus($deliveries), $this->deliveryError($deliveries));

        return $deliveries;
    }

    /**
     * Notify every active user and member account in a church/campus without duplicating linked members.
     *
     * @param  array<int, string>  $channels
     * @param  array<string, mixed>  $metadata
     */
    public function audience(
        int $churchId,
        ?int $campusId,
        string $eventType,
        string $category,
        string $subject,
        string $message,
        array $channels = ['in_app'],
        array $metadata = [],
        bool $critical = false,
    ): int {
        $rule = $this->rule($churchId, $eventType);
        if ($this->isDisabled($rule, $metadata)) {
            $this->recordRun($rule, 0, 'skipped');

            return 0;
        }

        [$subject, $message, $channels, $critical] = $this->applyRule($rule, $subject, $message, $channels, $critical, $metadata);

        $users = User::query()
            ->where('church_id', $churchId)
            ->where('status', 'active')
            ->when($campusId, fn ($query) => $query->where(fn ($scope) => $scope->whereNull('campus_id')->orWhere('campus_id', $campusId)))
            ->get();

        $members = Member::query()
            ->where('church_id', $churchId)
            ->where('status', 'active')
            ->when($campusId, fn ($query) => $query->where('campus_id', $campusId))
            ->whereDoesntHave('userAccount')
            ->get();

        if ($rule?->audience === 'all_users') {
            $users = User::query()->where('church_id', $churchId)->where('status', 'active')->get();
            $members = new EloquentCollection;
        } elseif ($rule?->audience === 'all_members') {
            $users = new EloquentCollection;
            $members = Member::query()->where('church_id', $churchId)->where('status', 'active')->get();
        } elseif ($rule?->audience === 'administrators') {
            $users = User::query()
                ->where('church_id', $churchId)
                ->where('status', 'active')
                ->whereHas('roles', fn ($query) => $query
                    ->where('name', 'Super Administrator')
                    ->orWhereHas('permissions', fn ($permissions) => $permissions->where('name', 'manage communications')))
                ->get();
            $members = new EloquentCollection;
        }

        $deliveries = collect();
        $users->each(fn (User $user) => $deliveries->push(...$this->createDeliveriesForUser($user, $eventType, $category, $subject, $message, $channels, $metadata, $critical)));
        $members->each(fn (Member $member) => $deliveries->push(...$this->createDeliveriesForMember($member, $eventType, $category, $subject, $message, $channels, $metadata, $critical)));
        $recipientCount = $users->count() + $members->count();
        $this->recordRun($rule, $recipientCount, $this->deliveryStatus($deliveries), $this->deliveryError($deliveries));

        return $recipientCount;
    }

    /**
     * @param  EloquentCollection<int, User>|Collection<int, User>  $users
     * @param  array<int, string>  $channels
     * @param  array<string, mixed>  $metadata
     */
    public function users(
        iterable $users,
        string $eventType,
        string $category,
        string $subject,
        string $message,
        array $channels = ['in_app'],
        array $metadata = [],
        bool $critical = false,
    ): int {
        $users = collect($users);
        $first = $users->first();
        if (! $first instanceof User) {
            return 0;
        }
        $rule = $this->rule((int) $first->church_id, $eventType);
        if ($this->isDisabled($rule, $metadata)) {
            $this->recordRun($rule, 0, 'skipped');

            return 0;
        }
        [$subject, $message, $channels, $critical] = $this->applyRule($rule, $subject, $message, $channels, $critical, $metadata);

        $count = 0;
        $deliveries = collect();
        foreach ($users as $user) {
            $deliveries->push(...$this->createDeliveriesForUser($user, $eventType, $category, $subject, $message, $channels, $metadata, $critical));
            $count++;
        }
        $this->recordRun($rule, $count, $this->deliveryStatus($deliveries), $this->deliveryError($deliveries));

        return $count;
    }

    /**
     * @param  array<int, string>  $channels
     * @param  array<string, mixed>  $metadata
     * @return Collection<int, CommunicationDelivery>
     */
    public function contact(
        int $churchId,
        string $name,
        ?string $email,
        ?string $phone,
        string $eventType,
        string $category,
        string $subject,
        string $message,
        array $channels,
        array $metadata = [],
        bool $critical = false,
    ): Collection {
        $rule = $this->rule($churchId, $eventType);
        if ($this->isDisabled($rule, $metadata)) {
            $this->recordRun($rule, 0, 'skipped');

            return collect();
        }
        [$subject, $message, $channels, $critical] = $this->applyRule($rule, $subject, $message, $channels, $critical, $metadata);
        $deliveries = $this->createDeliveries($churchId, $name, $email, $phone, $eventType, $category, $subject, $message, $channels, $metadata, $critical, null, null);
        $this->recordRun($rule, 1, $this->deliveryStatus($deliveries), $this->deliveryError($deliveries));

        return $deliveries;
    }

    private function createDeliveriesForUser(User $user, string $eventType, string $category, string $subject, string $message, array $channels, array $metadata, bool $critical): Collection
    {
        return $this->createDeliveries((int) $user->church_id, $user->name, $user->email, $user->phone, $eventType, $category, $subject, $message, $channels, $metadata, $critical, $user->id, $user->member_id);
    }

    private function createDeliveriesForMember(Member $member, string $eventType, string $category, string $subject, string $message, array $channels, array $metadata, bool $critical): Collection
    {
        $member->loadMissing('userAccount');

        return $this->createDeliveries((int) $member->church_id, trim($member->first_name.' '.$member->last_name), $member->email, $member->phone, $eventType, $category, $subject, $message, $channels, $metadata, $critical, $member->userAccount?->id, $member->id);
    }

    private function rule(int $churchId, string $eventType): ?NotificationAutomationRule
    {
        return NotificationAutomationRule::query()
            ->with('template')
            ->where('church_id', $churchId)
            ->where('event_type', $eventType)
            ->first();
    }

    private function isDisabled(?NotificationAutomationRule $rule, array $metadata): bool
    {
        return $rule !== null && ! $rule->enabled && ! ($metadata['_automation_test'] ?? false);
    }

    /**
     * @return array{string, string, array<int, string>, bool}
     */
    private function applyRule(?NotificationAutomationRule $rule, string $subject, string $message, array $channels, bool $critical, array $metadata): array
    {
        if ($rule === null) {
            return [$subject, $message, $channels, $critical];
        }

        $variables = collect($metadata)
            ->mapWithKeys(fn ($value, $key) => ['{{'.$key.'}}' => is_scalar($value) ? (string) $value : ''])
            ->all();
        if ($rule->template !== null) {
            $subject = strtr($rule->template->subject ?: $subject, $variables);
            $message = strtr($rule->template->body ?: $message, $variables);
            $rule->template->increment('usage_count');
            $rule->template->forceFill(['last_used_at' => now()])->save();
        }

        return [$subject, $message, $rule->channels ?: $channels, $critical || $rule->critical];
    }

    private function deliveryStatus(Collection $deliveries): string
    {
        if ($deliveries->isEmpty() || $deliveries->every(fn (CommunicationDelivery $delivery): bool => $delivery->status === 'skipped')) {
            return 'skipped';
        }

        return $deliveries->contains('status', 'failed') ? 'failed' : 'success';
    }

    private function deliveryError(Collection $deliveries): ?string
    {
        $error = $deliveries->pluck('error')->filter()->unique()->join('; ');

        return $error !== '' ? $error : null;
    }

    private function recordRun(?NotificationAutomationRule $rule, int $recipients, string $status, ?string $error = null): void
    {
        $rule?->update([
            'last_run_at' => now(),
            'last_status' => $status,
            'last_recipient_count' => $recipients,
            'last_error' => $error,
        ]);
    }

    /**
     * @param  array<int, string>  $channels
     * @param  array<string, mixed>  $metadata
     * @return Collection<int, CommunicationDelivery>
     */
    private function createDeliveries(
        int $churchId,
        string $name,
        ?string $email,
        ?string $phone,
        string $eventType,
        string $category,
        string $subject,
        string $message,
        array $channels,
        array $metadata,
        bool $critical,
        ?int $userId,
        ?int $memberId,
    ): Collection {
        unset($metadata['_automation_test']);
        $settings = CommunicationProviderSetting::query()
            ->where('church_id', $churchId)
            ->whereIn('channel', $channels)
            ->get()
            ->keyBy('channel');

        return collect($channels)->unique()->map(function (string $channel) use ($churchId, $name, $email, $phone, $eventType, $category, $subject, $message, $metadata, $critical, $userId, $memberId, $settings): CommunicationDelivery {
            if (is_string($metadata['_dedupe_key'] ?? null) && $metadata['_dedupe_key'] !== '') {
                $duplicate = CommunicationDelivery::query()
                    ->where('church_id', $churchId)
                    ->where('event_type', $eventType)
                    ->where('channel', $channel)
                    ->whereIn('status', ['queued', 'sent', 'delivered', 'skipped'])
                    ->whereJsonContains('metadata->'.'_dedupe_key', $metadata['_dedupe_key'])
                    ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
                    ->when($memberId !== null, fn ($query) => $query->where('member_id', $memberId))
                    ->first();

                if ($duplicate) {
                    return $duplicate;
                }
            }

            $preference = $this->preferences->resolve($churchId, $userId, $memberId, $channel, $category, $critical);
            $contact = match ($channel) {
                'sms', 'whatsapp' => $phone,
                'push' => $preference['contact'],
                default => $email,
            };
            $delivery = CommunicationDelivery::query()->create([
                'church_id' => $churchId,
                'member_id' => $memberId,
                'user_id' => $userId,
                'channel' => $channel,
                'provider' => $settings[$channel]?->provider ?? ($channel === 'in_app' ? 'EcclesiaOS' : Str::headline($channel)),
                'recipient_name' => $name ?: 'Recipient',
                'recipient_contact' => $contact,
                'subject' => $subject,
                'body_excerpt' => Str::limit(strip_tags($message), 180),
                'body' => $message,
                'event_type' => $eventType,
                'category' => $category,
                'critical' => $critical,
                'metadata' => $metadata,
                'available_at' => $preference['available_at'],
                'status' => $preference['allowed'] ? 'queued' : 'skipped',
                'retry_status' => $preference['allowed'] ? 'queued' : 'none',
                'attempt' => 1,
                'error' => $preference['reason'],
            ]);

            return $preference['allowed'] && $preference['available_at'] === null
                ? $this->dispatcher->dispatch($delivery)
                : $delivery;
        });
    }
}
