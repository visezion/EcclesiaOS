<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_renders(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->assertTrue(Storage::disk('public')->exists($user->avatar_url));

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('User Profile')
            ->assertSee('Profile Completion')
            ->assertSee('Edit Profile')
            ->assertSee($user->avatar_src, false);
    }

    public function test_profile_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Updated Person',
                'title' => 'Coordinator',
                'email' => $user->email,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Person']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'profile_updated']);
    }

    public function test_password_can_be_changed_from_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'password',
                'password' => 'NewPassword!234',
                'password_confirmation' => 'NewPassword!234',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('NewPassword!234', $user->fresh()->password));
        $this->assertDatabaseHas('activity_logs', ['action' => 'password_changed']);
    }

    public function test_profile_avatar_can_be_uploaded(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'date_of_birth' => '1978-03-12',
                'gender' => 'Male',
                'avatar' => UploadedFile::fake()->image('avatar.jpg', 300, 300),
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertNotNull($user->avatar_url);
        $this->assertStringStartsWith('avatars/', $user->avatar_url);
        $this->assertStringContainsString('/storage/avatars/', $user->avatar_src);
        $this->assertSame('1978-03-12', $user->date_of_birth?->toDateString());
        Storage::disk('public')->assertExists($user->avatar_url);
    }

    public function test_profile_impersonation_preview_button_sets_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.impersonate'))
            ->assertRedirect()
            ->assertSessionHas('profile_preview_user_id', $user->id);

        $this->assertDatabaseHas('activity_logs', ['action' => 'profile_preview_started']);
    }
}
