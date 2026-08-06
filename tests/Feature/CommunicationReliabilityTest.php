<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Church;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationDelivery;
use App\Models\User;
use App\Notifications\CommunicationDeliveryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class CommunicationReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_queued_in_app_delivery_reaches_the_recipient_account(): void
    {
        Notification::fake();
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id, 'status' => 'active']);
        $delivery = CommunicationDelivery::query()->create([
            'church_id' => $church->id,
            'user_id' => $user->id,
            'channel' => 'in_app',
            'provider' => 'EcclesiaOS',
            'recipient_name' => $user->name,
            'recipient_contact' => $user->email,
            'subject' => 'Assignment ready',
            'body_excerpt' => 'You have a new assignment.',
            'body' => 'You have a new assignment.',
            'event_type' => 'ProgramSectionAssigned',
            'category' => 'volunteers',
            'status' => 'queued',
            'retry_status' => 'queued',
            'attempt' => 1,
        ]);

        $this->artisan('communications:dispatch')->assertSuccessful();

        $this->assertSame('delivered', $delivery->fresh()->status);
        Notification::assertSentTo($user, CommunicationDeliveryNotification::class);
    }

    public function test_due_campaign_is_dispatched_once_by_the_scheduler_command(): void
    {
        Notification::fake();
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id, 'status' => 'active']);
        $campaign = CommunicationCampaign::query()->create([
            'church_id' => $church->id,
            'created_by' => $user->id,
            'name' => 'Due campaign',
            'segment_name' => 'Active users',
            'channels' => ['in_app'],
            'subject' => 'Service reminder',
            'body' => 'Service begins soon.',
            'send_mode' => 'scheduled',
            'scheduled_at' => now()->subMinute(),
            'status' => 'scheduled',
            'recipient_count' => 1,
        ]);
        $campaign->recipients()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'preferences' => ['channels' => ['in_app']],
            'status' => 'scheduled',
        ]);

        $this->artisan('communications:dispatch')->assertSuccessful();
        $this->artisan('communications:dispatch')->assertSuccessful();

        $this->assertSame('sent', $campaign->fresh()->status);
        $this->assertSame(1, $campaign->deliveries()->count());
        Notification::assertSentToTimes($user, CommunicationDeliveryNotification::class, 1);
    }
}
