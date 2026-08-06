<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Models\CommunicationDelivery;
use App\Models\CommunicationProviderSetting;
use App\Models\Member;
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
        return $this->createDeliveries(
            churchId: (int) $user->church_id,
            name: $user->name,
            email: $user->email,
            phone: $user->phone,
            eventType: $eventType,
            category: $category,
            subject: $subject,
            message: $message,
            channels: $channels,
            metadata: $metadata,
            critical: $critical,
            userId: $user->id,
            memberId: $user->member_id,
        );
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
        $member->loadMissing('userAccount');

        return $this->createDeliveries(
            churchId: (int) $member->church_id,
            name: trim($member->first_name.' '.$member->last_name),
            email: $member->email,
            phone: $member->phone,
            eventType: $eventType,
            category: $category,
            subject: $subject,
            message: $message,
            channels: $channels,
            metadata: $metadata,
            critical: $critical,
            userId: $member->userAccount?->id,
            memberId: $member->id,
        );
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

        $users->each(fn (User $user) => $this->user($user, $eventType, $category, $subject, $message, $channels, $metadata, $critical));
        $members->each(fn (Member $member) => $this->member($member, $eventType, $category, $subject, $message, $channels, $metadata, $critical));

        return $users->count() + $members->count();
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
        $count = 0;
        foreach ($users as $user) {
            $this->user($user, $eventType, $category, $subject, $message, $channels, $metadata, $critical);
            $count++;
        }

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
        return $this->createDeliveries($churchId, $name, $email, $phone, $eventType, $category, $subject, $message, $channels, $metadata, $critical, null, null);
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
        $settings = CommunicationProviderSetting::query()
            ->where('church_id', $churchId)
            ->whereIn('channel', $channels)
            ->get()
            ->keyBy('channel');

        return collect($channels)->unique()->map(function (string $channel) use ($churchId, $name, $email, $phone, $eventType, $category, $subject, $message, $metadata, $critical, $userId, $memberId, $settings): CommunicationDelivery {
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
