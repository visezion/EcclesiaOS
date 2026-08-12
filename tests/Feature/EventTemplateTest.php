<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\Church;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\EventTemplate;
use App\Models\Program;
use App\Models\ProgramSection;
use App\Models\ProgramSectionAssignment;
use App\Models\Role;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_template_reuse_it_and_clone_an_event(): void
    {
        $admin = $this->administrator();
        $program = Program::query()->create([
            'church_id' => $admin->church_id,
            'name' => 'Sunday Worship',
            'status' => 'ongoing',
        ]);
        $event = Event::query()->create([
            'church_id' => $admin->church_id,
            'program_id' => $program->id,
            'title' => 'Sunday Service',
            'description' => 'Weekly worship gathering.',
            'event_type' => 'Service',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'venue' => 'Main Hall',
            'status' => 'scheduled',
        ]);
        $session = EventSession::query()->create([
            'church_id' => $admin->church_id,
            'event_id' => $event->id,
            'title' => 'Sunday Service Session',
            'session_date' => now()->addDay()->toDateString(),
            'starts_at' => '09:00',
            'ends_at' => '11:00',
            'meeting_type' => 'physical',
            'venue' => 'Main Hall',
            'status' => 'scheduled',
        ]);
        $section = ProgramSection::query()->create([
            'church_id' => $admin->church_id,
            'program_id' => $program->id,
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'title' => 'Opening Prayer',
            'section_type' => 'prayer',
            'position' => 1,
            'planned_duration_minutes' => 10,
            'status' => 'active',
        ]);
        ProgramSectionAssignment::query()->create([
            'church_id' => $admin->church_id,
            'program_section_id' => $section->id,
            'user_id' => $admin->id,
            'role_title' => 'Prayer leader',
            'status' => 'assigned',
        ]);

        $this->actingAs($admin)
            ->post(route('programs.events.template.store', [$program, $event]), ['name' => 'Sunday Service Template'])
            ->assertRedirect();

        $template = EventTemplate::query()->where('name', 'Sunday Service Template')->firstOrFail();
        $this->assertCount(1, $template->agenda);

        $this->actingAs($admin)
            ->post(route('programs.events.store', $program), [
                'title' => 'Christmas Service',
                'starts_at' => now()->addDays(10)->format('Y-m-d\\TH:i'),
                'status' => 'draft',
                'template_id' => $template->id,
            ])
            ->assertRedirect();

        $newEvent = Event::query()->where('title', 'Christmas Service')->firstOrFail();
        $this->assertDatabaseHas('program_sections', ['event_id' => $newEvent->id, 'title' => 'Opening Prayer']);
        $this->assertSame('Weekly worship gathering.', $newEvent->description);

        $this->actingAs($admin)
            ->post(route('programs.events.clone', [$program, $event]), ['title' => 'Sunday Service Copy'])
            ->assertRedirect();

        $clone = Event::query()->where('title', 'Sunday Service Copy')->firstOrFail();
        $this->assertSame('draft', $clone->status);
        $this->assertSame(1, $clone->sessions()->count());
        $this->assertDatabaseHas('program_sections', ['event_id' => $clone->id, 'title' => 'Opening Prayer']);
    }

    public function test_admin_can_create_an_event_without_a_program(): void
    {
        $admin = $this->administrator();

        $response = $this->actingAs($admin)
            ->post(route('events.store'), [
                'title' => 'Community Prayer Night',
                'starts_at' => now()->addDays(3)->format('Y-m-d\\TH:i'),
                'status' => 'scheduled',
            ])
            ->assertRedirect();

        $event = Event::query()->where('title', 'Community Prayer Night')->firstOrFail();
        $session = $event->sessions()->firstOrFail();
        $this->assertNull($event->program_id);
        $this->assertSame(1, $event->sessions()->count());
        $response->assertRedirect(route('event-sessions.meeting', $session));
    }

    public function test_event_and_meeting_follow_workflow_approval_before_publishing(): void
    {
        $admin = $this->administrator();
        Workflow::query()->create([
            'church_id' => $admin->church_id,
            'name' => 'Configured Event Approval',
            'module' => 'events',
            'status' => 'active',
            'steps' => [
                'approval_type' => 'sequential',
                'steps' => [['position' => 1, 'label' => 'Administrator Review', 'role' => 'Super Administrator', 'mode' => 'required', 'required' => true]],
            ],
        ]);

        $this->actingAs($admin)->post(route('events.store'), [
            'title' => 'Approval Required Event',
            'starts_at' => now()->addDays(3)->format('Y-m-d\\TH:i'),
            'status' => 'scheduled',
        ])->assertRedirect();

        $event = Event::query()->where('title', 'Approval Required Event')->firstOrFail();
        $session = $event->sessions()->firstOrFail();
        $this->assertSame('draft', $event->status);
        $this->assertSame('draft', $session->status);

        $this->actingAs($admin)
            ->post(route('event-sessions.submit-approval', $session))
            ->assertRedirect();

        $approval = Approval::query()->where('approvable_type', EventSession::class)->where('approvable_id', $session->id)->firstOrFail();
        $this->assertSame('pending', $approval->status);

        $this->actingAs($admin)
            ->post(route('workflows.approvals.approve', $approval), ['notes' => 'Ready to publish.'])
            ->assertRedirect();

        $this->assertSame('scheduled', $session->fresh()->status);
        $this->assertSame('draft', $event->fresh()->status);
        $this->assertSame('approved', $approval->fresh()->status);
    }

    private function administrator(): User
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id, 'status' => 'active']);
        $role = Role::query()->create([
            'name' => 'Super Administrator',
            'slug' => 'super-administrator-'.uniqid(),
            'description' => 'Full test access',
        ]);
        $user->roles()->attach($role);

        return $user;
    }
}
