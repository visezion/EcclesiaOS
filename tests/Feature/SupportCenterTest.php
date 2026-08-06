<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SupportCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_church_user_can_submit_and_track_a_ticket_with_private_attachment(): void
    {
        Storage::fake('local');
        $this->seed();
        $administrator = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $reporter = $this->memberUser($administrator->church_id);

        $this->actingAs($reporter)
            ->get(route('support.index'))
            ->assertOk()
            ->assertSee('How can we help?', false)
            ->assertSee(route('support.tickets.create'), false);

        $this->actingAs($reporter)
            ->get(route('support.tickets.create'))
            ->assertOk()
            ->assertSee('Bug or error', false)
            ->assertSee('Idea or suggestion', false)
            ->assertSee('Expand an existing function', false)
            ->assertSee('Create a new function', false)
            ->assertSee('Security concern', false);

        $this->actingAs($reporter)
            ->post(route('support.tickets.store'), [
                'category' => 'bug',
                'priority' => 'high',
                'subject' => 'Member export displays an error',
                'description' => 'Opening the member export from the directory displays an unexpected server error for our church.',
                'expected_outcome' => 'The member CSV should download without an error.',
                'page_url' => 'https://church.example.test/members',
                'attachments' => [UploadedFile::fake()->image('member-export-error.png', 1200, 700)],
            ])
            ->assertRedirect();

        $ticket = SupportTicket::query()->where('created_by', $reporter->id)->firstOrFail();
        $this->assertStringStartsWith('SUP-', $ticket->reference);
        $this->assertSame('new', $ticket->status);
        $this->assertSame(5, $ticket->progress);
        $this->assertDatabaseHas('support_ticket_activities', [
            'support_ticket_id' => $ticket->id,
            'type' => 'created',
        ]);
        $attachment = $ticket->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($reporter)
            ->get(route('support.tickets.show', $ticket))
            ->assertOk()
            ->assertSee($ticket->reference, false)
            ->assertSee('Delivery tracker', false)
            ->assertSee('Member export displays an error', false);
        $this->actingAs($reporter)
            ->get(route('support.attachments.download', $attachment))
            ->assertOk()
            ->assertDownload('member-export-error.png');

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => $administrator->getMorphClass(),
            'notifiable_id' => $administrator->id,
        ]);
    }

    public function test_ticket_visibility_and_attachments_are_isolated_between_churches(): void
    {
        Storage::fake('local');
        $this->seed();
        $administrator = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $reporter = $this->memberUser($administrator->church_id);
        $churchManager = User::factory()->create([
            'church_id' => $administrator->church_id,
            'status' => 'active',
        ]);
        $churchManager->roles()->sync([Role::query()->where('name', 'Church Administrator')->firstOrFail()->id]);

        $otherChurch = Church::factory()->create();
        $outsider = $this->memberUser($otherChurch->id);

        $this->actingAs($reporter)->post(route('support.tickets.store'), [
            'category' => 'new_feature',
            'priority' => 'normal',
            'subject' => 'Add a volunteer skills planner',
            'description' => 'We would like a new planning function that matches volunteer skills with upcoming service needs.',
            'attachments' => [UploadedFile::fake()->createWithContent('requirements.txt', 'Skills, availability, service date')],
        ])->assertRedirect();

        $ticket = SupportTicket::query()->where('created_by', $reporter->id)->firstOrFail();
        $attachment = $ticket->attachments()->firstOrFail();

        $this->actingAs($churchManager)
            ->get(route('support.index'))
            ->assertOk()
            ->assertSee('Add a volunteer skills planner', false);
        $this->actingAs($churchManager)->get(route('support.tickets.show', $ticket))->assertOk();

        $this->actingAs($outsider)->get(route('support.tickets.show', $ticket))->assertNotFound();
        $this->actingAs($outsider)->get(route('support.attachments.download', $attachment))->assertNotFound();
        $this->actingAs($outsider)
            ->get(route('support.index'))
            ->assertOk()
            ->assertDontSee('Add a volunteer skills planner', false);
    }

    public function test_support_administrator_can_track_reply_and_keep_internal_notes_private(): void
    {
        $this->seed();
        $supportAdministrator = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $reporter = $this->memberUser($supportAdministrator->church_id);

        $this->actingAs($reporter)->post(route('support.tickets.store'), [
            'category' => 'feature_expansion',
            'priority' => 'normal',
            'subject' => 'Expand the attendance dashboard',
            'description' => 'Please expand attendance analytics with a comparison across campuses and reporting periods.',
        ])->assertRedirect();
        $ticket = SupportTicket::query()->where('created_by', $reporter->id)->firstOrFail();

        $this->actingAs($supportAdministrator)
            ->patch(route('support.tickets.tracking.update', $ticket), [
                'status' => 'in_progress',
                'priority' => 'high',
                'progress' => 55,
                'assigned_to' => $supportAdministrator->id,
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'status' => 'in_progress',
            'priority' => 'high',
            'progress' => 55,
            'assigned_to' => $supportAdministrator->id,
        ]);

        $this->actingAs($supportAdministrator)
            ->post(route('support.tickets.replies.store', $ticket), [
                'body' => 'Internal estimate and implementation notes must stay private.',
                'is_internal' => 1,
            ])
            ->assertRedirect();
        $this->actingAs($reporter)
            ->get(route('support.tickets.show', $ticket))
            ->assertOk()
            ->assertDontSee('Internal estimate and implementation notes', false)
            ->assertDontSee('An internal support note was added.', false);
        $this->actingAs($supportAdministrator)
            ->get(route('support.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Internal estimate and implementation notes', false)
            ->assertSee('Internal note', false);

        $this->actingAs($supportAdministrator)
            ->post(route('support.tickets.replies.store', $ticket), [
                'body' => 'We have started reviewing the attendance comparison design.',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => $reporter->getMorphClass(),
            'notifiable_id' => $reporter->id,
        ]);

        $this->actingAs($reporter)
            ->post(route('support.tickets.replies.store', $ticket), [
                'body' => 'Thank you. A monthly and quarterly comparison would be most useful.',
            ])
            ->assertRedirect();

        $this->actingAs($supportAdministrator)
            ->patch(route('support.tickets.tracking.update', $ticket), [
                'status' => 'resolved',
                'priority' => 'high',
                'progress' => 85,
                'assigned_to' => $supportAdministrator->id,
            ])
            ->assertRedirect();
        $ticket->refresh();
        $this->assertSame(100, $ticket->progress);
        $this->assertNotNull($ticket->resolved_at);
    }

    public function test_non_support_user_cannot_update_ticket_tracking(): void
    {
        $this->seed();
        $administrator = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $reporter = $this->memberUser($administrator->church_id);
        $ticket = SupportTicket::query()->create([
            'reference' => 'SUP-TEST-ACCESS',
            'church_id' => $reporter->church_id,
            'created_by' => $reporter->id,
            'category' => 'idea',
            'priority' => 'normal',
            'status' => 'new',
            'progress' => 5,
            'subject' => 'An idea that must remain scoped',
            'description' => 'This ticket verifies that ordinary users cannot alter support tracking.',
            'last_activity_at' => now(),
        ]);

        $this->actingAs($reporter)
            ->patch(route('support.tickets.tracking.update', $ticket), [
                'status' => 'resolved',
                'priority' => 'low',
                'progress' => 100,
            ])
            ->assertForbidden();
        $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'status' => 'new', 'progress' => 5]);
    }

    private function memberUser(?int $churchId): User
    {
        $user = User::factory()->create([
            'church_id' => $churchId,
            'status' => 'active',
        ]);
        $user->roles()->sync([Role::query()->where('name', 'Member')->firstOrFail()->id]);

        return $user;
    }
}
