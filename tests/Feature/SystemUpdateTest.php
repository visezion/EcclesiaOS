<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SystemUpdate;
use App\Models\User;
use App\Services\Updates\UpdateManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SystemUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $churchAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'updater.enabled' => true,
            'updater.install_enabled' => false,
            'updater.current_version' => '1.0.0',
            'updater.repository' => 'example/ecclesiaos',
            'updater.channel' => 'stable',
            'updater.check_ttl_seconds' => 3600,
            'updater.github_api_url' => 'https://api.github.test',
            'updater.require_immutable' => true,
            'updater.manifest_asset' => 'update-manifest.json',
            'updater.max_download_bytes' => 10_000_000,
        ]);

        Cache::flush();

        $superRole = Role::query()->create([
            'name' => 'Super Administrator',
            'slug' => 'super-administrator',
        ]);
        $churchRole = Role::query()->create([
            'name' => 'Church Administrator',
            'slug' => 'church-administrator',
        ]);

        $this->admin = User::factory()->create([
            'email' => 'updater-admin@example.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $this->admin->roles()->attach($superRole);

        $this->churchAdmin = User::factory()->create([
            'email' => 'church-admin@example.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $this->churchAdmin->roles()->attach($churchRole);
    }

    public function test_super_administrator_can_detect_and_review_an_immutable_release(): void
    {
        $this->fakeRelease();

        $this->actingAs($this->admin)
            ->post(route('system-updates.check'))
            ->assertRedirect()
            ->assertSessionHas('status', 'Version 2.1.0 is available.');

        $update = SystemUpdate::query()->sole();

        $this->assertSame('detected', $update->status);
        $this->assertSame('sha256:'.str_repeat('a', 64), $update->asset_digest);
        $this->assertTrue((bool) data_get($update->metadata, 'immutable'));
        $this->assertDatabaseCount('notifications', 1);

        $this->actingAs($this->admin)
            ->get(route('system-updates.index'))
            ->assertOk()
            ->assertSee('Update available')
            ->assertSee('v1.0.0 to v2.1.0')
            ->assertSee('Safer upgrades and faster pages.')
            ->assertSee('installation remains locked', false);
    }

    public function test_rechecking_a_release_does_not_duplicate_notifications_or_erase_operational_metadata(): void
    {
        $this->fakeRelease();
        $manager = app(UpdateManager::class);
        $update = $manager->check(force: true);

        $this->assertNotNull($update);
        $update->forceFill([
            'metadata' => array_merge($update->metadata ?? [], [
                'backup' => ['database' => '/secure/backups/database.sql'],
                'previous_release' => '/var/www/ecclesiaos/releases/v1.0.0',
            ]),
        ])->save();

        $this->fakeRelease();
        $refreshed = $manager->check(force: true);

        $this->assertSame('/secure/backups/database.sql', data_get($refreshed?->metadata, 'backup.database'));
        $this->assertSame('/var/www/ecclesiaos/releases/v1.0.0', data_get($refreshed?->metadata, 'previous_release'));
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('system_updates', 1);
    }

    public function test_non_super_administrators_cannot_access_or_check_updates(): void
    {
        $this->actingAs($this->churchAdmin)
            ->get(route('system-updates.index'))
            ->assertForbidden();

        $this->actingAs($this->churchAdmin)
            ->post(route('system-updates.check'))
            ->assertForbidden();
    }

    public function test_approval_requires_confirmation_and_a_managed_server(): void
    {
        $update = $this->createDetectedUpdate();

        $this->actingAs($this->admin)
            ->post(route('system-updates.approve', $update), [
                'current_password' => 'incorrect',
                'confirmation' => 'UPDATE 2.1.0',
            ])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($this->admin)
            ->post(route('system-updates.approve', $update), [
                'current_password' => 'password',
                'confirmation' => 'UPDATE 2.1.0',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'The server is not configured for safe one-click installation.');

        $this->assertSame('detected', $update->fresh()->status);
        $this->assertNull($update->fresh()->approved_by);
    }

    public function test_a_detected_release_can_be_dismissed_but_a_pending_release_cannot(): void
    {
        $detected = $this->createDetectedUpdate();

        $this->actingAs($this->admin)
            ->post(route('system-updates.skip', $detected))
            ->assertRedirect()
            ->assertSessionHas('status', 'Version 2.1.0 was dismissed.');

        $this->assertSame('skipped', $detected->fresh()->status);
        $this->assertNull(app(UpdateManager::class)->available());

        $pending = $this->createDetectedUpdate('2.2.0', 'v2.2.0', 'pending');

        $this->actingAs($this->admin)
            ->post(route('system-updates.skip', $pending))
            ->assertRedirect()
            ->assertSessionHas('error', 'This update can no longer be dismissed.');

        $this->assertSame('pending', $pending->fresh()->status);
    }

    public function test_mutable_releases_are_rejected(): void
    {
        $this->fakeRelease(immutable: false);

        $this->actingAs($this->admin)
            ->post(route('system-updates.check'))
            ->assertRedirect()
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'not immutable'));

        $this->assertDatabaseCount('system_updates', 0);
    }

    public function test_legacy_releases_without_a_manifest_can_still_be_detected(): void
    {
        $this->fakeRelease(withManifest: false);

        $this->actingAs($this->admin)
            ->post(route('system-updates.check'))
            ->assertRedirect()
            ->assertSessionHas('status', 'Version 2.1.0 is available.');

        $update = SystemUpdate::query()->sole();

        $this->assertSame('detected', $update->status);
        $this->assertSame('ecclesiaos-v2.1.0.zip', $update->asset_name);
        $this->assertSame('1.0.0', data_get($update->metadata, 'manifest.minimum_version'));
    }

    public function test_package_assets_are_detected_even_when_the_filename_is_not_lowercase_zip(): void
    {
        $this->fakeRelease(packageName: 'EcclesiaOS-v2.1.0.ZIP');

        $this->actingAs($this->admin)
            ->post(route('system-updates.check'))
            ->assertRedirect()
            ->assertSessionHas('status', 'Version 2.1.0 is available.');

        $update = SystemUpdate::query()->sole();

        $this->assertSame('detected', $update->status);
        $this->assertSame('EcclesiaOS-v2.1.0.ZIP', $update->asset_name);
    }

    public function test_releases_without_a_package_still_create_an_update_notification(): void
    {
        $this->fakeRelease(withPackage: false, withManifest: false);

        $this->actingAs($this->admin)
            ->post(route('system-updates.check'))
            ->assertRedirect()
            ->assertSessionHas('status', 'Version 2.1.0 is available.');

        $update = SystemUpdate::query()->sole();

        $this->assertSame('detected', $update->status);
        $this->assertNull($update->asset_name);
        $this->assertFalse((bool) data_get($update->metadata, 'installable'));
    }

    public function test_digest_mismatched_releases_are_rejected(): void
    {
        $this->fakeRelease(apiDigest: str_repeat('b', 64));

        $this->actingAs($this->admin)
            ->post(route('system-updates.check'))
            ->assertRedirect()
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'digest does not match'));

        $this->assertDatabaseCount('system_updates', 0);
    }

    public function test_install_command_refuses_to_modify_an_unmanaged_host(): void
    {
        $update = $this->createDetectedUpdate(status: 'pending');

        $this->artisan('app:update --pending')
            ->expectsOutputToContain('managed update environment is not ready')
            ->assertFailed();

        $this->assertSame('pending', $update->fresh()->status);
        $this->assertNull($update->fresh()->started_at);
    }

    public function test_rollback_command_refuses_to_modify_an_unmanaged_host(): void
    {
        $update = $this->createDetectedUpdate(status: 'completed');
        $update->forceFill([
            'installed_at' => now(),
            'metadata' => array_merge($update->metadata ?? [], [
                'previous_release' => base_path(),
                'new_release' => base_path(),
            ]),
        ])->save();

        $this->artisan('app:update-rollback 2.1.0')
            ->expectsOutputToContain('managed update environment is not ready for rollback')
            ->assertFailed();

        $this->assertSame('completed', $update->fresh()->status);
        $this->assertNull($update->fresh()->rolled_back_at);
    }

    private function fakeRelease(
        bool $immutable = true,
        ?string $apiDigest = null,
        bool $withManifest = true,
        bool $withPackage = true,
        string $packageName = 'ecclesiaos-v2.1.0.zip',
    ): void {
        $digest = str_repeat('a', 64);
        $artifactUrl = 'https://api.github.test/repos/example/ecclesiaos/releases/assets/100';
        $manifestUrl = 'https://api.github.test/repos/example/ecclesiaos/releases/assets/200';

        Http::fake([
            'https://api.github.test/repos/example/ecclesiaos/releases/latest' => Http::response([
                'tag_name' => 'v2.1.0',
                'name' => 'EcclesiaOS v2.1.0',
                'body' => 'Safer upgrades and faster pages.',
                'html_url' => 'https://github.com/example/ecclesiaos/releases/tag/v2.1.0',
                'published_at' => '2026-07-29T08:00:00Z',
                'draft' => false,
                'prerelease' => false,
                'immutable' => $immutable,
                'zipball_url' => 'https://api.github.test/repos/example/ecclesiaos/zipball/v2.1.0',
                'assets' => [
                    ...($withPackage ? [[
                        'name' => $packageName,
                        'url' => $artifactUrl,
                        'browser_download_url' => 'https://github.test/download/'.$packageName,
                        'size' => 2048,
                        'digest' => 'sha256:'.($apiDigest ?? $digest),
                    ]] : []),
                    ...($withManifest ? [[
                        'name' => 'update-manifest.json',
                        'url' => $manifestUrl,
                        'size' => 512,
                    ]] : []),
                ],
            ]),
            ...($withManifest ? [
                $manifestUrl => Http::response([
                    'version' => '2.1.0',
                    'minimum_version' => '1.0.0',
                    'minimum_php' => '8.2.0',
                    'channel' => 'stable',
                    'artifact' => $packageName,
                    'sha256' => $digest,
                    'commit' => str_repeat('c', 40),
                    'has_migrations' => true,
                    'requires_backup' => true,
                ]),
            ] : []),
        ]);
    }

    private function createDetectedUpdate(
        string $version = '2.1.0',
        string $tag = 'v2.1.0',
        string $status = 'detected',
    ): SystemUpdate {
        return SystemUpdate::query()->create([
            'version' => $version,
            'tag' => $tag,
            'name' => "EcclesiaOS v{$version}",
            'status' => $status,
            'current_version' => '1.0.0',
            'release_url' => "https://github.com/example/ecclesiaos/releases/tag/v{$version}",
            'asset_name' => "ecclesiaos-v{$version}.zip",
            'asset_api_url' => 'https://api.github.test/repos/example/ecclesiaos/releases/assets/100',
            'asset_download_url' => "https://github.test/download/ecclesiaos-v{$version}.zip",
            'asset_digest' => 'sha256:'.str_repeat('a', 64),
            'asset_size' => 2048,
            'changelog' => 'Release notes.',
            'detected_at' => now(),
            'metadata' => [
                'immutable' => true,
                'manifest' => [
                    'minimum_version' => '1.0.0',
                    'minimum_php' => '8.2.0',
                ],
            ],
        ]);
    }
}
