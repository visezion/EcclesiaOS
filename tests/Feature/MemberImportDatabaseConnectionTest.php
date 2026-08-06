<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Member;
use App\Models\MemberHistoryEntry;
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

    public function test_old_ecclesiaos_database_migrates_profiles_families_and_related_history(): void
    {
        Storage::fake('local');
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $campus = Campus::query()->where('church_id', $admin->church_id)->firstOrFail();
        $path = tempnam(sys_get_temp_dir(), 'legacy-ecclesia-');
        $source = new PDO('sqlite:'.$path);
        foreach ([
            'CREATE TABLE members (id INTEGER PRIMARY KEY, campus_id INTEGER, family_id INTEGER, first_name TEXT, last_name TEXT, email TEXT, phone TEXT, status TEXT, joined_at TEXT)',
            'CREATE TABLE member_profiles (id INTEGER PRIMARY KEY, member_id INTEGER, date_of_birth TEXT, occupation TEXT, skills TEXT)',
            'CREATE TABLE families (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE campuses (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE attendance_records (id INTEGER PRIMARY KEY, member_id INTEGER, service_date TEXT, status TEXT, checked_in_at TEXT)',
            'CREATE TABLE donations (id INTEGER PRIMARY KEY, member_id INTEGER, amount NUMERIC, currency TEXT, received_at TEXT, reference TEXT)',
            'CREATE TABLE care_tasks (id INTEGER PRIMARY KEY, member_id INTEGER, type TEXT, status TEXT, next_action TEXT, due_at TEXT)',
            'CREATE TABLE ministries (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE volunteers (id INTEGER PRIMARY KEY, member_id INTEGER, ministry_id INTEGER, role TEXT, status TEXT, created_at TEXT)',
        ] as $statement) {
            $source->exec($statement);
        }
        $source->exec("INSERT INTO members VALUES (77, 4, 9, 'Legacy', 'Person', 'legacy.person@example.test', '+2348000000077', 'active', '2018-02-03')");
        $source->exec("INSERT INTO member_profiles VALUES (1, 77, '1985-06-07', 'Engineer', '[\"Teaching\",\"Music\"]')");
        $source->exec("INSERT INTO families VALUES (9, 'Legacy Household')");
        $source->exec("INSERT INTO campuses VALUES (4, 'Former Main Campus')");
        $source->exec("INSERT INTO attendance_records VALUES (11, 77, '2025-01-05', 'present', '2025-01-05 10:00:00')");
        $source->exec("INSERT INTO donations VALUES (12, 77, 125.50, 'USD', '2025-01-10 11:00:00', 'OLD-GIFT-12')");
        $source->exec("INSERT INTO care_tasks VALUES (13, 77, 'follow_up', 'resolved', 'Called member', '2025-02-01 09:00:00')");
        $source->exec("INSERT INTO ministries VALUES (6, 'Choir')");
        $source->exec("INSERT INTO volunteers VALUES (14, 77, 6, 'Singer', 'active', '2024-01-01 08:00:00')");
        $source = null;

        try {
            $this->actingAs($admin)->post(route('member-import-connections.store'), [
                'name' => 'Legacy EcclesiaOS',
                'driver' => 'sqlite',
                'sqlite_file' => new UploadedFile($path, 'legacy.sqlite', 'application/vnd.sqlite3', null, true),
            ])->assertRedirect();
        } finally {
            @unlink($path);
        }
        $connection = MemberImportConnection::query()->sole();
        $this->actingAs($admin)->get(route('member-import-connections.show', $connection))->assertOk()->assertSee('Old EcclesiaOS installation detected');

        $this->actingAs($admin)->post(route('member-import-connections.legacy', $connection), [
            'name' => 'Full legacy migration',
            'default_campus_id' => $campus->id,
        ])->assertRedirect();
        $import = MemberImport::query()->sole();
        $this->assertSame('ecclesiaos', $import->source_type);
        $this->assertSame(4, array_sum(data_get($import->source_options, 'legacy_summary.history')));
        $this->assertSame('Legacy Household', $import->rows()->sole()->normalized_data['family_name']);
        $this->assertSame('Engineer', $import->rows()->sole()->normalized_data['occupation']);

        $this->actingAs($admin)->post(route('member-imports.start', $import))->assertRedirect();
        $member = Member::query()->where('email', 'legacy.person@example.test')->firstOrFail();
        $this->assertSame('Legacy Household', $member->family?->name);
        $this->assertSame(['Teaching', 'Music'], $member->memberProfile?->skills);
        $this->assertSame(4, MemberHistoryEntry::query()->where('member_id', $member->id)->count());
        $this->actingAs($admin)->get(route('members.show', $member))->assertOk()->assertSee('Imported Member History')->assertSee('contribution imported');

        $this->actingAs($admin)->post(route('member-imports.rollback', $import))->assertRedirect();
        $this->assertDatabaseMissing('member_history_entries', ['member_id' => $member->id]);
        $this->assertSoftDeleted('members', ['id' => $member->id]);
    }
}
