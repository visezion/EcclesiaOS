<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CentralSupportSession;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\CentralSupportSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CentralSupportConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_church_administrator_can_configure_and_test_the_fixed_central_server(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('central-support.update'), [
                'enabled' => '1',
                'remote_access_enabled' => '1',
                'api_token' => 'central-installation-token-123456',
            ])
            ->assertRedirect();

        $stored = Setting::query()->where('church_id', $admin->church_id)->where('key', 'central_support.connection')->firstOrFail();
        $this->assertTrue((bool) data_get($stored->value, 'enabled'));
        $this->assertTrue((bool) data_get($stored->value, 'remote_access_enabled'));
        $this->assertNotSame('central-installation-token-123456', data_get($stored->value, 'api_token_encrypted'));
        $this->assertNotEmpty(data_get($stored->value, 'installation_id'));

        Http::fake([
            'https://ecclesiaos.vicezion.com/api/v1/installations/ping' => Http::response(['message' => 'Installation connected.']),
        ]);
        $this->actingAs($admin)->post(route('central-support.test'))->assertRedirect()->assertSessionHas('status', 'Installation connected.');
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer central-installation-token-123456'));

        $this->actingAs($admin)
            ->get(route('central-support.index'))
            ->assertOk()
            ->assertSee('https://ecclesiaos.vicezion.com', false)
            ->assertSee('Create temporary access', false);
    }

    public function test_new_tickets_enter_the_retryable_central_sync_outbox(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($user)->post(route('support.tickets.store'), [
            'category' => 'bug',
            'priority' => 'normal',
            'subject' => 'Central support synchronization test',
            'description' => 'This ticket confirms that church requests enter the reliable synchronization outbox.',
        ])->assertRedirect();

        $ticket = SupportTicket::query()->sole();
        $this->assertSame('pending', $ticket->sync_status);
        $this->assertDatabaseHas('central_support_sync_events', [
            'support_ticket_id' => $ticket->id,
            'event_type' => 'ticket.created',
            'status' => 'pending',
        ]);

        app(CentralSupportSettings::class)->save($user->church, [
            'enabled' => true,
            'remote_access_enabled' => false,
            'api_token' => 'central-installation-token-123456',
        ]);
        $centralTicketId = (string) Str::uuid();
        Http::fake([
            'https://ecclesiaos.vicezion.com/api/v1/church/events' => Http::response(['ticket_id' => $centralTicketId]),
        ]);

        $this->actingAs($user)->post(route('central-support.sync'))->assertRedirect()->assertSessionHas('status');

        $this->assertSame('synced', $ticket->fresh()->sync_status);
        $this->assertSame($centralTicketId, $ticket->fresh()->central_id);
        $this->assertDatabaseHas('central_support_sync_events', [
            'support_ticket_id' => $ticket->id,
            'status' => 'synced',
        ]);
        Http::assertSent(fn ($request): bool => $request['event_type'] === 'ticket.created');
    }

    public function test_new_ticket_is_delivered_immediately_when_central_support_is_configured(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        app(CentralSupportSettings::class)->save($user->church, [
            'enabled' => true,
            'remote_access_enabled' => false,
            'api_token' => 'central-installation-token-123456',
        ]);
        Http::fake([
            'https://ecclesiaos.vicezion.com/api/v1/church/events' => Http::response(['ticket_id' => (string) Str::uuid()]),
        ]);

        $this->actingAs($user)->post(route('support.tickets.store'), [
            'category' => 'bug',
            'priority' => 'normal',
            'subject' => 'Immediate central support delivery',
            'description' => 'This ticket should be sent to central support as soon as it is created.',
        ])->assertRedirect();

        $this->assertSame('synced', SupportTicket::query()->sole()->sync_status);
        Http::assertSent(fn ($request): bool => $request['event_type'] === 'ticket.created');
    }

    public function test_one_time_grant_creates_an_expiring_named_remote_support_session(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $settings = app(CentralSupportSettings::class);
        $settings->save($admin->church, [
            'enabled' => true,
            'remote_access_enabled' => true,
            'api_token' => 'central-installation-token-123456',
        ]);

        $grantResponse = $this->actingAs($admin)->post(route('central-support.grants.store'), [
            'duration' => 30,
            'reason' => 'Investigate support ticket SUP-TEST safely.',
        ])->assertRedirect();
        $grantToken = (string) $grantResponse->getSession()->get('central_support_grant_token');
        $this->assertSame(64, strlen($grantToken));
        $access = CentralSupportSession::query()->sole();
        $this->assertNotSame($grantToken, $access->grant_token_hash);

        $connection = $settings->forChurch($admin->church);
        $exchange = $this->withToken('central-installation-token-123456')
            ->postJson(route('central-support.remote.exchange'), [
                'grant_token' => $grantToken,
                'agent_id' => 'agent-100',
                'agent_name' => 'Developer Support',
                'agent_email' => 'developer@vicezion.com',
            ])
            ->assertOk()
            ->assertJsonPath('church', $admin->church->name);
        $this->withToken('central-installation-token-123456')
            ->postJson(route('central-support.remote.exchange'), [
                'grant_token' => $grantToken,
                'agent_id' => 'agent-100',
                'agent_name' => 'Developer Support',
                'agent_email' => 'developer@vicezion.com',
            ])
            ->assertGone();

        $this->assertStringContainsString('/support/central-access/login/', $exchange->json('login_url'));
        $this->get($exchange->json('login_url'))->assertRedirect(route('dashboard'));

        $remoteUser = User::query()->where('email', 'remote-support-'.$access->id.'@ecclesiaos.invalid')->firstOrFail();
        $this->assertAuthenticatedAs($remoteUser);
        $this->assertTrue($remoteUser->isSuperAdministrator());
        $this->assertSame('active', $access->fresh()->status);
        $this->assertSame('agent-100', $access->fresh()->central_agent_id);

        $access->update(['expires_at' => now()->subMinute()]);
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertSame('inactive', $remoteUser->fresh()->status);
    }

    public function test_authenticated_central_events_update_the_local_ticket_once(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $settings = app(CentralSupportSettings::class);
        $settings->save($admin->church, [
            'enabled' => true,
            'remote_access_enabled' => false,
            'api_token' => 'central-installation-token-123456',
        ]);
        $connection = $settings->forChurch($admin->church);
        $ticket = SupportTicket::query()->create([
            'church_id' => $admin->church_id,
            'created_by' => $admin->id,
            'reference' => 'SUP-CENTRAL-1',
            'category' => 'bug',
            'priority' => 'normal',
            'status' => 'new',
            'progress' => 5,
            'subject' => 'Receive central reply',
            'description' => 'The central support server should be able to return a reply safely.',
            'last_activity_at' => now(),
        ]);
        $eventId = (string) Str::uuid();
        $payload = [
            'event_id' => $eventId,
            'event_type' => 'ticket.reply.created',
            'ticket_id' => (string) Str::uuid(),
            'reference' => $ticket->reference,
            'payload' => [
                'body' => 'The central support agent has reviewed this issue.',
                'is_internal' => false,
                'agent_name' => 'Developer Support',
            ],
        ];
        $headers = [
            'Authorization' => 'Bearer central-installation-token-123456',
            'X-EcclesiaOS-Installation' => $connection['installation_id'],
        ];

        $this->withHeaders($headers)->postJson(route('central-support.events.receive'), $payload)->assertOk()->assertJsonPath('received', true);
        $this->withHeaders($headers)->postJson(route('central-support.events.receive'), $payload)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('support_ticket_replies', 1);
        $this->assertDatabaseHas('support_ticket_replies', ['support_ticket_id' => $ticket->id, 'body' => 'The central support agent has reviewed this issue.']);
        $this->assertSame('triaged', $ticket->fresh()->status);
        $this->assertSame(20, $ticket->fresh()->progress);
        $this->assertDatabaseCount('central_support_inbound_events', 1);
    }

    public function test_community_solutions_reads_and_publishes_through_the_central_api(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        app(CentralSupportSettings::class)->save($admin->church, [
            'enabled' => true,
            'remote_access_enabled' => false,
            'api_token' => 'central-installation-token-123456',
        ]);
        Http::fake(function ($request) {
            if ($request->method() === 'GET') {
                return Http::response([
                    'data' => [[
                        'id' => 'question-1',
                        'category' => 'how_to',
                        'status' => 'solved',
                        'title' => 'How to resolve duplicate member imports',
                        'excerpt' => 'Use the duplicate review screen before confirming the import.',
                        'church_name' => 'Community Church',
                        'answers_count' => 3,
                        'helpful_count' => 12,
                    ]],
                ]);
            }

            return Http::response(['id' => 'question-2'], 201);
        });

        $this->actingAs($admin)
            ->get(route('support.community'))
            ->assertOk()
            ->assertSee('How to resolve duplicate member imports', false)
            ->assertSee('Accepted solution', false);

        $this->actingAs($admin)
            ->post(route('support.community.store'), [
                'category' => 'how_to',
                'title' => 'How should we correct an imported member?',
                'body' => 'We need the recommended steps for safely correcting a member imported with the wrong campus.',
                'consent' => '1',
            ])
            ->assertRedirect(route('support.community'))
            ->assertSessionHas('status');

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'https://ecclesiaos.vicezion.com/api/v1/community/questions'
            && $request['author']['display_name'] === $admin->name);
    }

    public function test_support_workspace_knowledge_and_live_pages_use_central_data_safely(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($admin)->get(route('support.tickets.index'))->assertOk()->assertSee('My Tickets', false);
        $this->actingAs($admin)->get(route('support.knowledge'))->assertOk()->assertSee('Knowledge service is not connected', false);
        $this->actingAs($admin)->get(route('support.live'))->assertOk()->assertSee('Live support is not connected', false);

        app(CentralSupportSettings::class)->save($admin->church, [
            'enabled' => true,
            'remote_access_enabled' => false,
            'api_token' => 'central-installation-token-123456',
        ]);
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/knowledge/articles')) {
                return Http::response([
                    'data' => [[
                        'title' => 'Configure attendance imports',
                        'excerpt' => 'A verified guide for importing attendance safely.',
                        'category_name' => 'Attendance',
                        'read_time' => '4 minutes',
                        'helpful_percent' => 98,
                    ]],
                    'categories' => [['slug' => 'attendance', 'name' => 'Attendance', 'articles_count' => 1]],
                ]);
            }
            if ($request->method() === 'POST') {
                return Http::response(['message_id' => 'message-1'], 201);
            }

            return Http::response([
                'online' => true,
                'agents_online' => 2,
                'queue_position' => 1,
                'average_response' => '2 minutes',
                'messages' => [],
            ]);
        });

        $this->actingAs($admin)->get(route('support.knowledge'))->assertOk()->assertSee('Configure attendance imports', false);
        $this->actingAs($admin)->get(route('support.live'))->assertOk()->assertSee('Central Support is online', false);
        $this->actingAs($admin)->post(route('support.live.messages'), ['message' => 'Please help us diagnose our current support issue.'])->assertRedirect()->assertSessionHas('status');
    }

    public function test_church_can_read_a_full_knowledge_article_and_rate_it(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        app(CentralSupportSettings::class)->save($admin->church, [
            'enabled' => true,
            'remote_access_enabled' => false,
            'api_token' => 'central-installation-token-123456',
        ]);
        Http::fake(function ($request) {
            if ($request->method() === 'GET') {
                return Http::response([
                    'data' => [
                        'id' => 'article-1',
                        'title' => 'Configure attendance imports',
                        'content' => 'Follow these steps to configure attendance imports.',
                    ],
                ]);
            }

            return Http::response(['helpful' => true]);
        });

        $this->actingAs($admin)
            ->get(route('support.knowledge.article', ['article' => 'article-1']))
            ->assertOk()
            ->assertSee('Follow these steps to configure attendance imports.', false)
            ->assertSee('Was this article helpful?', false);

        $this->actingAs($admin)
            ->post(route('support.knowledge.article.helpful', ['article' => 'article-1']), ['helpful' => '1'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/v1/knowledge/articles/article-1/helpful')
            && $request['helpful'] === true);
    }
}
