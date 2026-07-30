<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Message;
use App\Models\MessageDraft;
use App\Models\MessageThread;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MessageModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_send_and_reply_to_internal_messages(): void
    {
        $this->seed();
        $sender = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $recipient = User::query()->where('email', 'sarah.johnson@klgc.org')->firstOrFail();

        $this->actingAs($sender)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertSee('Message Center', false)
            ->assertSee('No messages yet', false);

        $this->actingAs($sender)
            ->post(route('messages.store'), [
                'recipients' => [$recipient->opaqueId()],
                'subject' => 'Sunday service planning',
                'body' => 'Please review the service plan before Friday.',
            ])
            ->assertRedirect();

        $thread = MessageThread::query()->latest('id')->firstOrFail();

        $this->actingAs($sender)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertSee('x-model="search"', false)
            ->assertSee('Sunday service planning', false);

        $firstMessage = Message::query()
            ->where('message_thread_id', $thread->id)
            ->where('sender_id', $sender->id)
            ->firstOrFail();
        $this->assertSame('Please review the service plan before Friday.', $firstMessage->body);
        $this->assertNotSame(
            'Please review the service plan before Friday.',
            DB::table('messages')->whereKey($firstMessage->id)->value('body'),
        );
        $this->assertSame(1, $recipient->fresh()->unreadNotifications()->count());

        $this->actingAs($recipient)
            ->get(route('messages.show', $thread))
            ->assertOk()
            ->assertSee('Sunday service planning', false)
            ->assertSee('Please review the service plan before Friday.', false);

        $this->actingAs($sender)
            ->post(route('messages.reply', $thread), ['body' => 'I will review it today.'])
            ->assertRedirect();

        $this->assertSame(
            'I will review it today.',
            Message::query()
                ->where('message_thread_id', $thread->id)
                ->where('sender_id', $sender->id)
                ->latest('id')
                ->firstOrFail()
                ->body,
        );

        $this->actingAs($recipient)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertSee('Unread messages', false)
            ->assertSeeInOrder(['Unread messages', '1'], false)
            ->assertSee('I will review it today.', false);

        $this->actingAs($recipient)
            ->postJson(route('messages.read', $thread))
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertNotNull(DB::table('message_thread_user')
            ->where('message_thread_id', $thread->id)
            ->where('user_id', $recipient->id)
            ->value('last_read_at'));

        $this->actingAs($recipient)
            ->postJson(route('messages.state', $thread), ['state' => 'starred', 'enabled' => true])
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertNotNull(DB::table('message_thread_user')
            ->where('message_thread_id', $thread->id)
            ->where('user_id', $recipient->id)
            ->value('starred_at'));

        $this->actingAs($recipient)
            ->post(route('messages.reply', $thread), [
                'body' => 'Leadership-only follow-up note.',
                'is_internal_note' => true,
            ])
            ->assertRedirect();

        $internalNote = Message::query()
            ->where('message_thread_id', $thread->id)
            ->where('is_internal_note', true)
            ->firstOrFail();
        $this->assertSame('Leadership-only follow-up note.', $internalNote->body);
    }

    public function test_role_conversations_sanitize_rich_content_and_protect_attachments(): void
    {
        Storage::fake('local');
        $this->seed();
        $sender = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $recipient = User::query()->where('email', 'sarah.johnson@klgc.org')->firstOrFail();
        $outsider = User::query()->where('email', 'jessica.lee@klgc.org')->firstOrFail();
        $role = Role::query()->where('name', 'Church Administrator')->firstOrFail();

        $this->actingAs($sender)
            ->post(route('messages.store'), [
                'recipients' => ['role:'.$role->opaqueId()],
                'subject' => 'Administrator update',
                'body_html' => '<p>Hello<script>alert(1)</script><a href="javascript:alert(2)">unsafe</a><strong>team</strong></p>',
                'conversation_type' => 'role',
                'attachments' => [
                    UploadedFile::fake()->createWithContent('agenda.txt', 'Private agenda'),
                ],
            ])
            ->assertRedirect();

        $thread = MessageThread::query()->latest('id')->firstOrFail();
        $message = Message::query()->where('message_thread_id', $thread->id)->firstOrFail();
        $attachment = $message->attachments()->firstOrFail();

        $this->assertTrue($thread->participants->contains('id', $recipient->id));
        $this->assertFalse($thread->participants->contains('id', $outsider->id));
        $this->assertSame('role', $thread->recipients()->firstOrFail()->recipient_type);
        $this->assertStringNotContainsString('<script', $message->body_html);
        $this->assertStringNotContainsString('javascript:', $message->body_html);
        $this->assertStringContainsString('<strong>team</strong>', $message->body_html);
        Storage::disk('local')->assertExists($attachment->path);

        $download = $this->actingAs($recipient)
            ->get(route('messages.attachments.download', $attachment))
            ->assertOk();
        $this->assertSame('Private agenda', $download->streamedContent());

        $this->actingAs($outsider)
            ->get(route('messages.attachments.download', $attachment))
            ->assertNotFound();
    }

    public function test_drafts_are_encrypted_and_scheduled_messages_dispatch_once(): void
    {
        $this->seed();
        $sender = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $recipient = User::query()->where('email', 'sarah.johnson@klgc.org')->firstOrFail();

        $draftResponse = $this->actingAs($sender)
            ->postJson(route('messages.drafts.store'), [
                'recipients' => ['user:'.$recipient->opaqueId()],
                'subject' => 'Future service',
                'body' => 'Private draft content',
                'body_html' => '<p>Private draft content</p>',
            ])
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $draft = MessageDraft::query()->firstOrFail();
        $this->assertSame($draft->opaqueId(), $draftResponse->json('id'));
        $this->assertSame('Private draft content', $draft->body);
        $this->assertNotSame('Private draft content', DB::table('message_drafts')->whereKey($draft->id)->value('body'));

        $this->actingAs($sender)
            ->post(route('messages.store'), [
                'recipients' => ['user:'.$recipient->opaqueId()],
                'subject' => 'Scheduled service reminder',
                'body' => 'This should be delivered once.',
                'scheduled_at' => now()->addMinute()->toDateTimeString(),
                'draft_id' => $draft->opaqueId(),
            ])
            ->assertRedirect();

        $thread = MessageThread::query()->latest('id')->firstOrFail();
        $message = Message::query()->where('message_thread_id', $thread->id)->firstOrFail();
        $this->assertDatabaseMissing('message_drafts', ['id' => $draft->id]);
        $this->assertSame('scheduled', $message->status);
        $this->assertFalse($thread->participants()->whereKey($recipient->id)->exists());

        $this->travel(2)->minutes();
        $this->artisan('messages:dispatch-scheduled')->assertSuccessful();
        $this->artisan('messages:dispatch-scheduled')->assertSuccessful();

        $this->assertSame('sent', $message->fresh()->status);
        $this->assertTrue($thread->participants()->whereKey($recipient->id)->exists());
        $this->assertSame(1, $recipient->fresh()->unreadNotifications()->count());
        $this->assertSame(
            1,
            DB::table('message_audit_events')
                ->where('message_id', $message->id)
                ->where('action', 'scheduled_message_dispatched')
                ->count(),
        );
    }

    public function test_sensitive_conversations_require_authorized_recipients(): void
    {
        $this->seed();
        $sender = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $viewer = User::query()->where('email', 'jessica.lee@klgc.org')->firstOrFail();

        $this->actingAs($sender)
            ->post(route('messages.store'), [
                'recipients' => ['user:'.$viewer->opaqueId()],
                'subject' => 'Restricted leadership matter',
                'body' => 'Sensitive content',
                'permission_scope' => 'restricted',
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('message_threads', ['subject' => 'Restricted leadership matter']);
    }

    public function test_every_active_user_can_access_messages_without_a_role(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $user = User::factory()->create([
            'church_id' => $admin->church_id,
            'campus_id' => $admin->campus_id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertSee('Message Center', false)
            ->assertSee(route('messages.index'), false)
            ->assertDontSee('New message', false);

        $this->actingAs($user)
            ->get(route('messages.create'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('messages.store'), [
                'recipients' => ['user:'.$admin->opaqueId()],
                'body' => 'This should remain permission controlled.',
            ])
            ->assertForbidden();
    }
}
