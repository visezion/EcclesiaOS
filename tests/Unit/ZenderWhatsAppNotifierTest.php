<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Campus;
use App\Models\Church;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationProviderSetting;
use App\Models\CommunicationWhatsAppGroup;
use App\Models\Ministry;
use App\Services\Communications\ZenderWhatsAppNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ZenderWhatsAppNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_ministry_scoped_whatsapp_groups_receive_zender_notifications(): void
    {
        $church = Church::factory()->create();
        $campus = Campus::factory()->create(['church_id' => $church->id]);
        $ministry = Ministry::query()->create([
            'church_id' => $church->id,
            'campus_id' => $campus->id,
            'name' => 'Media Ministry',
            'status' => 'active',
        ]);

        CommunicationProviderSetting::query()->create([
            'church_id' => $church->id,
            'channel' => 'whatsapp',
            'provider' => 'Zender WhatsApp Gateway',
            'enabled' => true,
            'settings' => [
                'endpoint_url' => 'https://zender.example.test',
                'account_id' => 'wa-account-1',
                'api_key_encrypted' => Crypt::encryptString('zender-secret'),
            ],
        ]);

        CommunicationWhatsAppGroup::query()->create([
            'church_id' => $church->id,
            'campus_id' => $campus->id,
            'ministry_id' => $ministry->id,
            'provider' => 'zender',
            'provider_group_id' => '120363025551111111@g.us',
            'name' => 'Media Ministry Group',
            'target_scope' => 'ministry',
            'participant_count' => 24,
            'enabled' => true,
        ]);

        Http::fake([
            'https://zender.example.test/api/send/whatsapp' => Http::response(['status' => 200, 'data' => ['id' => 'wa-1']], 200),
        ]);

        $result = app(ZenderWhatsAppNotifier::class)->notify(
            $church->id,
            'A new ministry report is ready for review.',
            'LeadershipReportCreated',
            $campus->id,
            $ministry->id,
            'Leadership report created',
        );

        $this->assertSame(['sent' => 1, 'failed' => 0, 'skipped' => 0], $result);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://zender.example.test/api/send/whatsapp'
                && $request['secret'] === 'zender-secret'
                && $request['account'] === 'wa-account-1'
                && $request['recipient'] === '120363025551111111@g.us'
                && $request['type'] === 'text'
                && $request['message'] === 'A new ministry report is ready for review.';
        });

        $this->assertDatabaseHas('communication_deliveries', [
            'church_id' => $church->id,
            'communication_whatsapp_group_id' => $ministry->id,
            'channel' => 'whatsapp',
            'provider' => 'zender',
            'event_type' => 'LeadershipReportCreated',
            'status' => 'delivered',
        ]);

        $this->assertSame(1, CommunicationDelivery::query()->count());
    }
}
