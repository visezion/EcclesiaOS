<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BootstrapAdministratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_bootstraps_a_production_administrator_without_demo_data(): void
    {
        $this->artisan('app:bootstrap-admin', [
            '--name' => 'Deployment Admin',
            '--email' => 'admin@example.org',
            '--password' => 'StrongPassword123',
            '--no-interaction' => true,
        ])->assertSuccessful();

        $administrator = User::query()->where('email', 'admin@example.org')->firstOrFail();

        $this->assertTrue(Hash::check('StrongPassword123', $administrator->password));
        $this->assertTrue($administrator->isSuperAdministrator());
        $this->assertSame('active', $administrator->status);
        $this->assertNotNull($administrator->email_verified_at);
        $this->assertSame(1, Church::query()->count());
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('donations', 0);
    }

    public function test_it_does_not_replace_an_existing_administrator_without_force(): void
    {
        $options = [
            '--name' => 'Deployment Admin',
            '--email' => 'admin@example.org',
            '--password' => 'StrongPassword123',
            '--no-interaction' => true,
        ];

        $this->artisan('app:bootstrap-admin', $options)->assertSuccessful();
        $this->artisan('app:bootstrap-admin', [
            '--name' => 'Replacement Admin',
            '--email' => 'replacement@example.org',
            '--password' => 'OtherPassword456',
            '--no-interaction' => true,
        ])->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'replacement@example.org']);
        $this->assertDatabaseHas('users', ['email' => 'admin@example.org']);
    }
}
