<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BackupRecord;
use App\Models\BackupRestoreRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Backups\BackupArchiveCryptor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class BackupDisasterRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_center_requires_dedicated_permission(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $role = Role::query()->create(['name' => 'No Backup Access', 'slug' => 'no-backup-access']);
        $user = User::factory()->create(['church_id' => $admin->church_id, 'campus_id' => $admin->campus_id]);
        $user->roles()->attach($role);

        $this->actingAs($user)->get(route('backups.index'))->assertForbidden();

        $role->permissions()->attach(Permission::query()->where('name', 'backup.view')->firstOrFail());
        $user->unsetRelation('roles');
        $this->actingAs($user)->get(route('backups.index'))->assertOk()->assertSee('Backup & Disaster Recovery Center', false);
    }

    public function test_database_backup_is_encrypted_portable_and_verifiable(): void
    {
        Storage::fake('local');
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($admin)->post(route('backups.store'), ['type' => 'database'])->assertRedirect();

        $backup = BackupRecord::query()->sole();
        $this->assertSame('completed', $backup->status, (string) $backup->error);
        $this->assertSame(100, $backup->progress);
        $this->assertNotNull($backup->checksum);
        $this->assertGreaterThan(0, $backup->record_count);
        Storage::disk('local')->assertExists($backup->path);
        $stream = Storage::disk('local')->readStream($backup->path);
        $this->assertSame("ECCBACKUP1\n", fread($stream, 11));
        fclose($stream);

        $this->actingAs($admin)->post(route('backups.verify', $backup))->assertRedirect()->assertSessionHas('status');
        $this->assertNotNull($backup->refresh()->verified_at);
    }

    public function test_archive_encryption_streams_and_rejects_tampering(): void
    {
        $source = storage_path('app/backup-work/crypt-source.bin');
        $encrypted = storage_path('app/backup-work/crypt-package.ecbak');
        $decrypted = storage_path('app/backup-work/crypt-output.bin');
        if (! is_dir(dirname($source))) {
            mkdir(dirname($source), 0750, true);
        }
        file_put_contents($source, random_bytes(2 * 1024 * 1024 + 37));
        $cryptor = app(BackupArchiveCryptor::class);

        try {
            $cryptor->encrypt($source, $encrypted);
            $cryptor->decrypt($encrypted, $decrypted);
            $this->assertSame(hash_file('sha256', $source), hash_file('sha256', $decrypted));

            $handle = fopen($encrypted, 'r+b');
            fseek($handle, 100);
            $byte = fread($handle, 1);
            fseek($handle, 100);
            fwrite($handle, chr(ord($byte) ^ 1));
            fclose($handle);

            $this->expectExceptionMessage('Backup authentication failed');
            $cryptor->decrypt($encrypted, $decrypted);
        } finally {
            @unlink($source);
            @unlink($encrypted);
            @unlink($decrypted);
        }
    }

    public function test_large_restore_upload_can_resume_and_validate_without_touching_production(): void
    {
        Storage::fake('local');
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $this->actingAs($admin)->post(route('backups.store'), ['type' => 'database']);
        $backup = BackupRecord::query()->sole();
        $bytes = Storage::disk('local')->get($backup->path);

        $started = $this->actingAs($admin)->postJson(route('backups.uploads.start'), [
            'filename' => 'portable.ecbak', 'total_size' => strlen($bytes), 'chunk_size' => 1048576,
            'checksum' => hash('sha256', $bytes),
        ])->assertCreated();
        $uploadId = $started->json('id');

        $this->post(route('backups.uploads.chunk', $uploadId), [
            'index' => 0, 'chunk' => UploadedFile::fake()->createWithContent('portable.part', $bytes),
        ])->assertOk();
        $this->getJson(route('backups.uploads.status', $uploadId))->assertOk()->assertJsonPath('received_chunks.0', 0);
        $this->postJson(route('backups.uploads.complete', $uploadId))->assertOk()->assertJsonPath('status', 'uploaded');
        $this->post(route('backups.uploads.validate', $uploadId))->assertRedirect()->assertSessionHas('status');

        $restore = BackupRestoreRequest::query()->sole();
        $this->assertSame('validated', $restore->status);
        $this->assertTrue((bool) data_get($restore->validation, 'safe_to_restore'));
        $this->assertDatabaseCount('churches', 1);
    }

    public function test_church_administrator_cannot_authorize_production_restore(): void
    {
        $this->seed();
        $administrator = User::query()->where('email', 'sarah.johnson@klgc.org')->firstOrFail();
        $restore = BackupRestoreRequest::query()->create([
            'uuid' => fake()->uuid(), 'church_id' => $administrator->church_id, 'requested_by' => $administrator->id,
            'mode' => 'validate', 'status' => 'validated', 'stage' => 'compatibility_check', 'progress' => 100,
            'validation' => ['safe_to_restore' => true], 'validated_at' => now(),
        ]);

        $this->actingAs($administrator)->post(route('backups.restores.authorize-production', $restore), [
            'current_password' => 'password', 'confirmation' => 'RESTORE '.strtoupper($administrator->church->name),
        ])->assertForbidden();
    }
}
