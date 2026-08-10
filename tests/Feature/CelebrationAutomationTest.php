<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CelebrationDispatch;
use App\Models\CelebrationSetting;
use App\Models\Church;
use App\Models\Member;
use App\Models\MemberProfile;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Notifications\CommunicationDeliveryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CelebrationAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_birthday_is_personalized_and_idempotent(): void
    {
        Notification::fake();
        $church = Church::factory()->create();
        CelebrationSetting::query()->create([
            'church_id' => $church->id,
            'send_time' => '00:00',
            'celebrant_channels' => ['in_app'],
        ]);
        $member = Member::factory()->create([
            'church_id' => $church->id,
            'first_name' => 'Grace',
            'last_name' => 'Adams',
        ]);
        MemberProfile::query()->create([
            'member_id' => $member->id,
            'date_of_birth' => today()->subYears(31),
        ]);
        $user = User::factory()->create([
            'church_id' => $church->id,
            'member_id' => $member->id,
            'status' => 'active',
            'email' => 'grace@example.test',
        ]);
        UserNotificationPreference::query()->create([
            'church_id' => $church->id,
            'user_id' => $user->id,
            'member_id' => $member->id,
            'channels' => ['in_app'],
            'categories' => ['celebrations'],
            'category_channels' => ['celebrations' => ['in_app']],
            'digest_mode' => 'instant',
            'language' => 'en',
            'critical_alerts' => true,
        ]);

        $this->artisan('celebrations:dispatch', ['--church' => $church->id, '--date' => today()->toDateString()])->assertSuccessful();
        $this->artisan('celebrations:dispatch', ['--church' => $church->id, '--date' => today()->toDateString()])->assertSuccessful();

        $this->assertDatabaseHas('celebration_dispatches', [
            'church_id' => $church->id,
            'member_id' => $member->id,
            'occasion_type' => 'birthday',
            'status' => 'sent',
        ]);
        $this->assertDatabaseCount('celebration_dispatches', 2);
        $this->assertDatabaseHas('communication_deliveries', [
            'user_id' => $user->id,
            'event_type' => 'BirthdayCelebration',
            'category' => 'celebrations',
            'subject' => 'Happy Birthday, Grace Adams!',
        ]);
        $card = (string) CelebrationDispatch::query()->where('member_id', $member->id)->where('occasion_type', 'birthday')->value('image_path');
        $this->assertStringContainsString($church->name, Storage::disk('public')->get($card));
        Notification::assertSentTo($user, CommunicationDeliveryNotification::class);
    }
}
