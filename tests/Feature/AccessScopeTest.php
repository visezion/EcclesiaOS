<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Family;
use App\Models\Member;
use App\Models\Ministry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ministry_leaders_only_see_and_create_members_in_their_led_ministry(): void
    {
        $this->seed();
        $leaderUser = User::query()->where('email', 'emily.davis@klgc.org')->firstOrFail();
        $manageMembers = Permission::query()->where('name', 'manage members')->firstOrFail();
        Role::query()->where('name', 'Ministry Leader')->firstOrFail()->permissions()->syncWithoutDetaching([$manageMembers->id]);

        $leaderMember = Member::query()->create([
            'church_id' => $leaderUser->church_id,
            'campus_id' => $leaderUser->campus_id,
            'first_name' => 'Ministry',
            'last_name' => 'Leader',
            'email' => 'ministry.leader@klgc.org',
            'status' => 'active',
            'joined_at' => today(),
        ]);
        $leaderUser->forceFill(['member_id' => $leaderMember->id])->save();

        $ministry = Ministry::query()->create([
            'church_id' => $leaderUser->church_id,
            'campus_id' => $leaderUser->campus_id,
            'name' => 'Leader Ministry',
            'leader_id' => $leaderMember->id,
            'status' => 'active',
        ]);
        $otherMember = Member::query()->create([
            'church_id' => $leaderUser->church_id,
            'campus_id' => $leaderUser->campus_id,
            'first_name' => 'Other',
            'last_name' => 'Ministry',
            'email' => 'other.ministry@klgc.org',
            'status' => 'active',
            'joined_at' => today(),
        ]);
        $otherMinistry = Ministry::query()->create([
            'church_id' => $leaderUser->church_id,
            'campus_id' => $leaderUser->campus_id,
            'name' => 'Other Ministry',
            'leader_id' => $otherMember->id,
            'status' => 'active',
        ]);
        Volunteer::query()->create(['church_id' => $leaderUser->church_id, 'campus_id' => $leaderUser->campus_id, 'member_id' => $leaderMember->id, 'ministry_id' => $ministry->id, 'role' => 'Leader', 'status' => 'active']);
        Volunteer::query()->create(['church_id' => $leaderUser->church_id, 'campus_id' => $leaderUser->campus_id, 'member_id' => $otherMember->id, 'ministry_id' => $otherMinistry->id, 'role' => 'Leader', 'status' => 'active']);

        $this->actingAs($leaderUser)
            ->get(route('members.index'))
            ->assertOk()
            ->assertSee('Ministry Leader')
            ->assertDontSee('Other Ministry');

        $this->actingAs($leaderUser)
            ->post(route('members.store'), [
                'first_name' => 'Created',
                'last_name' => 'In Ministry',
                'status' => 'active',
                'church_id' => $leaderUser->church_id,
                'campus_id' => $leaderUser->campus_id,
                'ministry_id' => $ministry->id,
            ])
            ->assertRedirect();

        $created = Member::query()->where('email', null)->where('first_name', 'Created')->firstOrFail();
        $this->assertTrue($created->volunteers()->where('ministry_id', $ministry->id)->exists());

        $this->actingAs($leaderUser)
            ->post(route('members.store'), [
                'first_name' => 'Blocked',
                'last_name' => 'Other Ministry',
                'status' => 'active',
                'church_id' => $leaderUser->church_id,
                'campus_id' => $leaderUser->campus_id,
                'ministry_id' => $otherMinistry->id,
            ])
            ->assertForbidden();
    }

    public function test_branch_pastor_sees_their_branch_and_church_administrator_sees_the_whole_church(): void
    {
        $this->seed();
        $branchPastor = User::query()->where('email', 'david.wilson@klgc.org')->firstOrFail();
        $churchAdministrator = User::query()->where('email', 'sarah.johnson@klgc.org')->firstOrFail();
        $sameBranch = Member::query()->create(['church_id' => $branchPastor->church_id, 'campus_id' => $branchPastor->campus_id, 'first_name' => 'Same', 'last_name' => 'Branch', 'email' => 'same.branch@klgc.org', 'status' => 'active', 'joined_at' => today()]);
        $otherBranch = Member::query()->create(['church_id' => $branchPastor->church_id, 'campus_id' => $churchAdministrator->campus_id, 'first_name' => 'Other', 'last_name' => 'Branch', 'email' => 'other.branch@klgc.org', 'status' => 'active', 'joined_at' => today()]);

        $this->actingAs($branchPastor)->get(route('members.index'))->assertOk()->assertSee('Same Branch')->assertDontSee('Other Branch');
        $this->actingAs($churchAdministrator)->get(route('members.index'))->assertOk()->assertSee('Same Branch')->assertSee('Other Branch');
    }

    public function test_ministry_leader_household_search_and_updates_stay_inside_their_ministry(): void
    {
        $this->seed();
        $leader = User::query()->where('email', 'emily.davis@klgc.org')->firstOrFail();
        $permission = Permission::query()->where('name', 'manage members')->firstOrFail();
        Role::query()->where('name', 'Ministry Leader')->firstOrFail()->permissions()->syncWithoutDetaching([$permission->id]);

        $leaderMember = Member::query()->create(['church_id' => $leader->church_id, 'campus_id' => $leader->campus_id, 'first_name' => 'Household', 'last_name' => 'Leader', 'status' => 'active']);
        $otherMember = Member::query()->create(['church_id' => $leader->church_id, 'campus_id' => $leader->campus_id, 'first_name' => 'Hidden', 'last_name' => 'Household', 'status' => 'active']);
        $leader->forceFill(['member_id' => $leaderMember->id])->save();
        $ministry = Ministry::query()->create(['church_id' => $leader->church_id, 'campus_id' => $leader->campus_id, 'name' => 'Household Ministry', 'leader_id' => $leaderMember->id, 'status' => 'active']);
        $otherMinistry = Ministry::query()->create(['church_id' => $leader->church_id, 'campus_id' => $leader->campus_id, 'name' => 'Hidden Household Ministry', 'leader_id' => $otherMember->id, 'status' => 'active']);
        Volunteer::query()->create(['church_id' => $leader->church_id, 'campus_id' => $leader->campus_id, 'member_id' => $leaderMember->id, 'ministry_id' => $ministry->id, 'role' => 'Leader', 'status' => 'active']);
        Volunteer::query()->create(['church_id' => $leader->church_id, 'campus_id' => $leader->campus_id, 'member_id' => $otherMember->id, 'ministry_id' => $otherMinistry->id, 'role' => 'Leader', 'status' => 'active']);
        $allowedFamily = Family::query()->create(['church_id' => $leader->church_id, 'campus_id' => $leader->campus_id, 'name' => 'Allowed Household', 'address' => 'Allowed Address']);
        $hiddenFamily = Family::query()->create(['church_id' => $leader->church_id, 'campus_id' => $leader->campus_id, 'name' => 'Hidden Household', 'address' => 'Secret Search Address']);
        $leaderMember->update(['family_id' => $allowedFamily->id]);
        $otherMember->update(['family_id' => $hiddenFamily->id]);

        $this->actingAs($leader)
            ->get(route('families.index', ['q' => 'Secret Search Address']))
            ->assertOk()
            ->assertDontSee('Hidden Household');

        $this->actingAs($leader)
            ->put(route('families.update', $allowedFamily), ['name' => 'Updated Allowed Household', 'campus_id' => $leader->campus_id, 'address' => 'Allowed Address'])
            ->assertRedirect();
        $this->assertDatabaseHas('families', ['id' => $allowedFamily->id, 'name' => 'Updated Allowed Household']);

        $this->actingAs($leader)
            ->put(route('families.update', $hiddenFamily), ['name' => 'Blocked Update', 'campus_id' => $leader->campus_id])
            ->assertForbidden();
    }

    public function test_ministry_leader_created_assets_are_owned_and_visible_to_the_creator(): void
    {
        $this->seed();
        $leader = User::query()->where('email', 'emily.davis@klgc.org')->firstOrFail();
        $permission = Permission::query()->where('name', 'manage assets')->firstOrFail();
        Role::query()->where('name', 'Ministry Leader')->firstOrFail()->permissions()->syncWithoutDetaching([$permission->id]);

        $this->actingAs($leader)->post(route('assets.store'), [
            'name' => 'Leader Owned Asset',
            'campus_id' => $leader->campus_id,
            'status' => 'available',
            'condition' => 'good',
        ])->assertRedirect();

        $asset = Asset::query()->where('name', 'Leader Owned Asset')->firstOrFail();
        $this->assertSame($leader->id, $asset->created_by_user_id);
        $this->actingAs($leader)->get(route('assets.index'))->assertOk()->assertSee('Leader Owned Asset');
    }
}
