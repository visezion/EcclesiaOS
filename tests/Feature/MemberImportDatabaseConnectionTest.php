<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\MemberImport;
use App\Models\MemberImportConnection;
use App\Models\User;
use App\Services\MemberImport\MemberImportDatabaseReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use PDO;
use PDOException;
use Tests\TestCase;

final class MemberImportDatabaseConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sqlite_source_is_encrypted_read_only_browsable_and_staged_for_review(): void
    {
        Storage::fake('local');
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $campus = Campus::query()->where('church_id', $admin->church_id)->firstOrFail();
        $path = tempnam(sys_get_temp_dir(), 'old-members-');
        $source = new PDO('sqlite:'.$path);
        $source->exec('CREATE TABLE old_members (external_id TEXT, first_name TEXT, last_name TEXT, email TEXT, family TEXT)');
        $source->exec("INSERT INTO old_members VALUES ('OLD-501', 'Database', 'Member', 'database.member@example.test', 'Database Family')");
        $source = null;

        try {
            $response = $this->actingAs($admin)->post(route('member-import-connections.store'), [
                'name' => 'Old SQLite Directory',
                'driver' => 'sqlite',
                'sqlite_file' => new UploadedFile($path, 'old-members.sqlite', 'application/vnd.sqlite3', null, true),
            ]);
        } finally {
            @unlink($path);
        }

        $connection = MemberImportConnection::query()->sole();
        $response->assertRedirect(route('member-import-connections.show', $connection));
        $this->assertNotSame('old-members.sqlite', $connection->database_name);
        Storage::disk('local')->assertExists($connection->database_name);

        $this->actingAs($admin)
            ->get(route('member-import-connections.show', $connection))
            ->assertOk()
            ->assertSee('old_members')
            ->assertSee('Read-only database sources');
        $this->actingAs($admin)
            ->post(route('member-import-connections.test', $connection))
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->assertSame('success', $connection->fresh()->last_test_status);

        $reader = app(MemberImportDatabaseReader::class);
        $readOnlyPdo = $reader->connect($connection);
        try {
            $readOnlyPdo->exec("INSERT INTO old_members VALUES ('NO', 'Should', 'Fail', NULL, NULL)");
            $this->fail('The SQLite source accepted a write through a read-only connection.');
        } catch (PDOException) {
            $this->assertTrue(true);
        }

        $this->actingAs($admin)->post(route('member-import-connections.stage', $connection), [
            'table' => 'old_members',
            'name' => 'SQLite review',
            'default_campus_id' => $campus->id,
        ])->assertRedirect();

        $import = MemberImport::query()->sole();
        $this->assertSame('database', $import->source_type);
        $this->assertSame('old_members', $import->source_table);
        $this->assertSame('ready', $import->status);
        $this->assertSame('Database', $import->rows()->sole()->normalized_data['first_name']);
        $this->actingAs($admin)->post(route('member-imports.start', $import))->assertRedirect();
        $this->assertDatabaseHas('members', ['church_id' => $admin->church_id, 'email' => 'database.member@example.test']);
        $this->assertDatabaseHas('families', ['church_id' => $admin->church_id, 'name' => 'Database Family']);
    }

    public function test_remote_passwords_are_stored_encrypted_and_missing_pdo_drivers_are_reported(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $this->actingAs($admin)->post(route('member-import-connections.store'), [
            'name' => 'MySQL Read Replica',
            'driver' => 'mysql',
            'host' => 'database.internal',
            'port' => 3306,
            'database_name' => 'members',
            'username' => 'readonly_importer',
            'password' => 'not-stored-in-plain-text',
        ])->assertRedirect();
        $mysql = MemberImportConnection::query()->where('driver', 'mysql')->sole();
        $this->assertNotSame('not-stored-in-plain-text', $mysql->getRawOriginal('password_encrypted'));
        $this->assertSame('not-stored-in-plain-text', Crypt::decryptString($mysql->password_encrypted));

        if (in_array('pgsql', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('This assertion targets a server without pdo_pgsql.');
        }

        $this->actingAs($admin)->post(route('member-import-connections.store'), [
            'name' => 'PostgreSQL Source',
            'driver' => 'pgsql',
            'host' => 'database.internal',
            'port' => 5432,
            'database_name' => 'members',
            'username' => 'readonly_importer',
            'password' => 'not-stored-in-plain-text',
        ])->assertSessionHasErrors('driver');
        $this->assertDatabaseMissing('member_import_connections', ['name' => 'PostgreSQL Source']);

        $this->actingAs($admin)->get(route('member-import-connections.index'))->assertOk()->assertSee('PDO extension needed');
    }
}
