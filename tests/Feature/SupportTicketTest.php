<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_church_user_can_submit_and_track_a_ticket_with_private_attachment(): void
    {
        Storage::fake('local');
        $this->seed();
        $administrator = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $user = User::factory()->create(['church_id' => $administrator->church_id, 'status' => 'active']);
        $user->roles()->sync([Role::query()->where('name', 'Viewer')->firstOrFail()->id]);

        $this->actingAs($user)
            ->get(route('support.index'))
            ->assertOk()
            ->assertSee('How can we help?', false)
            ->assertSee('Submit a ticket', false);
        $this->actingAs($user)
            ->get(route('support.tickets.create'))
            ->assertOk()
            ->assertSee('Bug or error', false)
            ->assertSee('Expand an existing function', false)
            ->assertSee('Create a new function', false);

        $response = $this->actingAs($user)->post(route('support.tickets.store'), [
            'category' => 'bug',
            'priority' => 'high',
            'subject' => 'Member export fails on Safari',
            'description' => 'The member export button returns an error after selecting the current campus filter.',
            'expected_outcome' => 'The filtered member CSV should download successfully.',
            'page_url' => 'https://example.test/members',
            'browser' => 'Safari on iPad',
            'attachments' => [UploadedFile::fake()->image('export-error.png', 900, 600)],
        ]);

        $ticket = SupportTicket::query()->where('created_by', $user->id)->firstOrFail();
        $response->assertRedirect(route('support.tickets.show', $ticket));
        $this->assertMatchesRegularExpression('/^SUP-\d{8}-[A-Z0-9]{6}$/', $ticket->reference);
        $this->assertSame('new', $ticket->status);
        $this->assertSame(5, $ticket->progress);
        $this->assertDatabaseHas('support_ticket_activities', [
            'support_ticket_id' => $ticket->id,
            'type' => 'created',
        ]);

        $attachment = $ticket->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->actingAs($user)
            ->get(route('support.attachments.download', $attachment))
            ->assertOk()
            ->assertDownload('export-error.png');

        $this->actingAs($user)
            ->get(route('support.tickets.show', $ticket))
            ->assertOk()
            ->assertSee($ticket->reference, false)
            ->assertSee('Delivery tracker', false)
            ->assertSee('Completed', false)
            ->assertSee('Pending', false)
            ->assertSee('5%', false);
    }

    public function test_support_administrator_can_reply_and_update_trackable_progress(): void
    {
        $this->seed();
        $administrator = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $user = User::factory()->create(['church_id' => $administrator->church_id, 'status' => 'active']);
        $user->roles()->sync([Role::query()->where('name', 'Viewer')->firstOrFail()->id]);
        $ticket = $this->ticketFor($user);

        $this->actingAs($administrator)
            ->patch(route('support.tickets.tracking.update', $ticket), [
                'status' => 'in_progress',
                'priority' => 'high',
                'progress' => 45,
                'assigned_to' => $administrator->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Ticket tracking updated.');

        $ticket->refresh();
        $this->assertSame('in_progress', $ticket->status);
        $this->assertSame(45, $ticket->progress);
        $this->assertSame($administrator->id, $ticket->assigned_to);
        $this->assertNotNull($ticket->first_response_at);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id, 'notifiable_type' => $user->getMorphClass()]);

        $this->actingAs($administrator)
            ->post(route('support.tickets.replies.store', $ticket), [
                'body' => 'We reproduced the issue and are preparing a fix.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Reply sent.');

        $this->actingAs($user)
            ->get(route('support.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('We reproduced the issue and are preparing a fix.', false)
            ->assertSee('45%', false)
            ->assertSee('Work underway', false);

        $this->actingAs($administrator)
            ->patch(route('support.tickets.tracking.update', $ticket), [
                'status' => 'resolved',
                'priority' => 'high',
                'progress' => 80,
                'assigned_to' => $administrator->id,
            ])
            ->assertRedirect();
        $ticket->refresh();
        $this->assertSame(100, $ticket->progress);
        $this->assertNotNull($ticket->resolved_at);
    }

    public function test_ticket_visibility_is_isolated_between_churches(): void
    {
        $this->seed();
        $administrator = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $owner = User::factory()->create(['church_id' => $administrator->church_id, 'status' => 'active']);
        $owner->roles()->sync([Role::query()->where('name', 'Viewer')->firstOrFail()->id]);
        $otherChurch = Church::factory()->create();
        $outsider = User::factory()->create(['church_id' => $otherChurch->id, 'status' => 'active']);
        $outsider->roles()->sync([Role::query()->where('name', 'Viewer')->firstOrFail()->id]);
        $ticket = $this->ticketFor($owner);

        $this->actingAs($outsider)->get(route('support.tickets.show', $ticket))->assertNotFound();
        $this->actingAs($outsider)->get(route('support.index'))->assertOk()->assertDontSee($ticket->reference, false);
        $this->actingAs($administrator)->get(route('support.tickets.show', $ticket))->assertOk();
    }

    public function test_internal_notes_and_their_attachments_are_hidden_from_church_users(): void
    {
        Storage::fake('local');
        $this->seed();
        $administrator = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $user = User::factory()->create(['church_id' => $administrator->church_id, 'status' => 'active']);
        $user->roles()->sync([Role::query()->where('name', 'Viewer')->firstOrFail()->id]);
        $ticket = $this->ticketFor($user);

        $this->actingAs($administrator)
            ->post(route('support.tickets.replies.store', $ticket), [
                'body' => 'Internal investigation details that customers must not see.',
                'is_internal' => 1,
                'attachments' => [UploadedFile::fake()->create('private-notes.pdf', 20, 'application/pdf')],
            ])
            ->assertRedirect();

        $attachment = SupportTicketAttachment::query()->firstOrFail();
        $this->actingAs($user)
            ->get(route('support.tickets.show', $ticket))
            ->assertOk()
            ->assertDontSee('Internal investigation details', false)
            ->assertDontSee('private-notes.pdf', false);
        $this->actingAs($user)->get(route('support.attachments.download', $attachment))->assertNotFound();
        $this->actingAs($administrator)->get(route('support.attachments.download', $attachment))->assertOk();
    }

    private function ticketFor(User $user): SupportTicket
    {
        return SupportTicket::query()->create([
            'reference' => 'SUP-20260805-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
            'church_id' => $user->church_id,
            'created_by' => $user->id,
            'category' => 'feature_expansion',
            'priority' => 'normal',
            'status' => 'new',
            'progress' => 5,
            'subject' => 'Expand the reporting dashboard',
            'description' => 'Add clearer ministry comparisons and downloadable summary charts.',
            'last_activity_at' => now(),
        ]);
    }
}
