<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Church;
use App\Models\FinanceTransaction;
use App\Models\FinancialAssistanceRequest;
use App\Models\Fund;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class FinancialAssistanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        $this->seed();
    }

    public function test_ministry_leader_can_submit_to_assigned_campus_with_private_evidence(): void
    {
        $leader = User::query()->where('email', 'emily.davis@klgc.org')->firstOrFail();

        $this->actingAs($leader)
            ->get(route('financial-assistance.index'))
            ->assertOk()
            ->assertSee('Financial Assistance', false);

        $this->actingAs($leader)
            ->get(route('financial-assistance.create'))
            ->assertOk()
            ->assertSee('Request financial assistance', false)
            ->assertSee($leader->campus->name, false)
            ->assertDontSee('Headquarters — HQ', false);

        $response = $this->actingAs($leader)->post(route('financial-assistance.store'), $this->validPayload($leader->campus_id));
        $assistance = FinancialAssistanceRequest::query()->where('requester_id', $leader->id)->sole();

        $response->assertRedirect(route('financial-assistance.show', $assistance));
        $this->assertMatchesRegularExpression('/^FAR-\d{8}-[A-Z0-9]{6}$/', $assistance->reference);
        $this->assertSame('submitted', $assistance->status);
        $this->assertSame('campus_review', $assistance->current_stage);
        $this->assertDatabaseHas('approvals', [
            'approvable_type' => FinancialAssistanceRequest::class,
            'approvable_id' => $assistance->id,
            'status' => 'pending',
        ]);
        $attachment = $assistance->attachments()->sole();
        Storage::disk('local')->assertExists($attachment->path);
        $this->actingAs($leader)->get(route('financial-assistance.attachments.download', $attachment))->assertOk();
        $this->assertDatabaseHas('communication_deliveries', [
            'event_type' => 'FinancialAssistanceApprovalRequired',
            'category' => 'approvals',
            'channel' => 'in_app',
        ]);
    }

    public function test_regular_leader_cannot_route_request_to_another_campus(): void
    {
        $leader = User::query()->where('email', 'emily.davis@klgc.org')->firstOrFail();
        $otherCampus = Campus::query()->where('church_id', $leader->church_id)->whereKeyNot($leader->campus_id)->where('status', 'active')->firstOrFail();

        $this->actingAs($leader)
            ->post(route('financial-assistance.store'), $this->validPayload($otherCampus->id))
            ->assertForbidden();

        $this->assertDatabaseCount('financial_assistance_requests', 0);
    }

    public function test_branch_pastor_can_route_to_headquarters_and_complete_two_stage_workflow(): void
    {
        $pastor = User::query()->where('email', 'david.wilson@klgc.org')->firstOrFail();
        $headquarters = Campus::query()->where('church_id', $pastor->church_id)->where('slug', 'headquarters')->firstOrFail();
        $campusApprover = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $financeOfficer = User::query()->where('email', 'michael.thompson@klgc.org')->firstOrFail();
        $fund = Fund::query()->where('church_id', $pastor->church_id)->where('is_active', true)->firstOrFail();

        $this->actingAs($pastor)->post(route('financial-assistance.store'), $this->validPayload($headquarters->id))->assertRedirect();
        $assistance = FinancialAssistanceRequest::query()->where('requester_id', $pastor->id)->sole();

        $this->actingAs($campusApprover)
            ->post(route('financial-assistance.decide', $assistance), [
                'decision' => 'approve',
                'notes' => 'Need and evidence confirmed by headquarters.',
            ])
            ->assertRedirect();

        $assistance->refresh();
        $this->assertSame('under_review', $assistance->status);
        $this->assertSame('finance_review', $assistance->current_stage);
        $this->assertSame('pending', $assistance->approval->status);

        $this->actingAs($financeOfficer)
            ->post(route('financial-assistance.decide', $assistance), [
                'decision' => 'approve',
                'approved_amount' => '1450.00',
                'notes' => 'Authorized from the care fund.',
            ])
            ->assertRedirect();

        $assistance->refresh();
        $this->assertSame('approved', $assistance->status);
        $this->assertSame('1450.00', $assistance->approved_amount);
        $this->assertSame('approved', $assistance->approval->status);

        $this->actingAs($financeOfficer)
            ->post(route('financial-assistance.disburse', $assistance), [
                'fund_id' => $fund->id,
                'disbursement_reference' => 'BANK-20260806-1042',
                'disbursement_notes' => 'Paid directly to the service provider.',
                'disbursed_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $assistance->refresh();
        $this->assertSame('disbursed', $assistance->status);
        $this->assertSame('BANK-20260806-1042', $assistance->disbursement_reference);
        $this->assertSame($fund->id, $assistance->fund_id);
        $expense = FinanceTransaction::query()->findOrFail($assistance->finance_transaction_id);
        $this->assertSame('expense', $expense->type);
        $this->assertSame('benevolence', $expense->category);
        $this->assertSame('posted', $expense->status);
        $this->assertSame('1450.00', $expense->amount);
        $this->assertSame($fund->id, $expense->fund_id);
        $this->assertSame($headquarters->id, $expense->campus_id);
        $this->assertSame('BANK-20260806-1042', $expense->reference);
        $this->assertDatabaseHas('financial_assistance_activities', [
            'financial_assistance_request_id' => $assistance->id,
            'type' => 'disbursed',
        ]);
        $this->assertDatabaseHas('communication_deliveries', [
            'user_id' => $pastor->id,
            'event_type' => 'FinancialAssistanceStatusChanged',
            'category' => 'financial_assistance',
            'channel' => 'whatsapp',
        ]);

        $this->actingAs($financeOfficer)
            ->post(route('financial-assistance.disburse', $assistance), [
                'fund_id' => $fund->id,
                'disbursement_reference' => 'BANK-DUPLICATE',
                'disbursed_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertStatus(403);
        $this->assertDatabaseMissing('finance_transactions', ['reference' => 'BANK-DUPLICATE']);
        $this->assertSame($expense->id, $assistance->fresh()->finance_transaction_id);
    }

    public function test_requester_cannot_approve_own_request_and_other_church_cannot_view_it(): void
    {
        $pastor = User::query()->where('email', 'david.wilson@klgc.org')->firstOrFail();
        $this->actingAs($pastor)->post(route('financial-assistance.store'), $this->validPayload($pastor->campus_id));
        $assistance = FinancialAssistanceRequest::query()->where('requester_id', $pastor->id)->sole();

        $this->actingAs($pastor)
            ->post(route('financial-assistance.decide', $assistance), ['decision' => 'approve'])
            ->assertStatus(422);

        $otherChurch = Church::factory()->create();
        $outsider = User::factory()->create(['church_id' => $otherChurch->id, 'status' => 'active']);
        $outsider->roles()->sync([Role::query()->where('name', 'Finance Officer')->firstOrFail()->id]);

        $this->actingAs($outsider)->get(route('financial-assistance.show', $assistance))->assertNotFound();
        $this->actingAs($outsider)->get(route('financial-assistance.attachments.download', $assistance->attachments()->sole()))->assertNotFound();
    }

    public function test_approver_can_request_changes_and_requester_can_resubmit(): void
    {
        $pastor = User::query()->where('email', 'david.wilson@klgc.org')->firstOrFail();
        $this->actingAs($pastor)->post(route('financial-assistance.store'), $this->validPayload($pastor->campus_id));
        $assistance = FinancialAssistanceRequest::query()->where('requester_id', $pastor->id)->sole();
        $approver = User::factory()->create(['church_id' => $pastor->church_id, 'campus_id' => $pastor->campus_id, 'status' => 'active']);
        $approver->roles()->sync([Role::query()->where('name', 'Church Administrator')->firstOrFail()->id]);

        $this->actingAs($approver)
            ->post(route('financial-assistance.decide', $assistance), [
                'decision' => 'request_changes',
                'notes' => 'Please attach a second vendor quotation.',
            ])
            ->assertRedirect();
        $this->assertSame('changes_requested', $assistance->fresh()->status);

        $this->actingAs($pastor)
            ->post(route('financial-assistance.resubmit', $assistance), [
                'response' => 'A second quotation has now been attached.',
                'evidence' => [UploadedFile::fake()->create('second-quote.pdf', 90, 'application/pdf')],
            ])
            ->assertRedirect();

        $assistance->refresh();
        $this->assertSame('submitted', $assistance->status);
        $this->assertSame('campus_review', $assistance->current_stage);
        $this->assertCount(2, $assistance->attachments);
    }

    public function test_global_workflow_actions_keep_financial_assistance_status_in_sync(): void
    {
        $pastor = User::query()->where('email', 'david.wilson@klgc.org')->firstOrFail();
        $administrator = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $this->actingAs($pastor)->post(route('financial-assistance.store'), $this->validPayload($pastor->campus_id));
        $assistance = FinancialAssistanceRequest::query()->where('requester_id', $pastor->id)->sole();
        $approval = $assistance->approval;

        $this->actingAs($administrator)
            ->post(route('workflows.approvals.approve', $approval), ['notes' => 'Campus review completed.'])
            ->assertRedirect();
        $this->assertSame('finance_review', $assistance->fresh()->current_stage);
        $this->assertSame('pending', $approval->fresh()->status);

        $this->actingAs($administrator)
            ->post(route('workflows.approvals.approve', $approval), ['notes' => 'Finance authorization completed.'])
            ->assertRedirect();

        $assistance->refresh();
        $this->assertSame('approved', $assistance->status);
        $this->assertSame('disbursement', $assistance->current_stage);
        $this->assertSame($assistance->amount, $assistance->approved_amount);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(int $campusId): array
    {
        return [
            'target_campus_id' => $campusId,
            'category' => 'facility_repairs',
            'beneficiary_type' => 'member',
            'beneficiary_name' => 'Grace Williams',
            'title' => 'Emergency surgery assistance',
            'purpose' => 'Cover the outstanding hospital deposit required before emergency surgery can begin.',
            'justification' => 'The family has exhausted insurance and personal savings, and treatment cannot be delayed.',
            'amount' => '1750.00',
            'needed_by' => now()->addDays(5)->toDateString(),
            'urgency' => 'urgent',
            'preferred_payment_method' => 'vendor_payment',
            'payee_name' => 'Kingdom Community Hospital',
            'evidence' => [UploadedFile::fake()->create('hospital-invoice.pdf', 120, 'application/pdf')],
        ];
    }
}
