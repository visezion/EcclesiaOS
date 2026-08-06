<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Member;
use App\Models\MemberImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class MemberImportCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_import_is_reviewed_processed_tracked_and_rolled_back(): void
    {
        Storage::fake('local');
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $campus = Campus::query()->where('church_id', $admin->church_id)->firstOrFail();
        $existing = Member::query()->where('church_id', $admin->church_id)->whereNotNull('email')->firstOrFail();
        $csv = implode("\n", [
            'External ID,First Name,Last Name,Email,Phone,Campus,Family,Date Joined',
            'LEG-1001,Grace,Import,grace.import@example.test,+1 555 010 9191,'.$campus->name.',Import Household,2024-03-15',
            'LEG-1002,'.$existing->first_name.','.$existing->last_name.','.$existing->email.','.$existing->phone.','.$campus->name.',,2023-01-01',
        ])."\n";

        $response = $this->actingAs($admin)->post(route('member-imports.files.store'), [
            'members_file' => UploadedFile::fake()->createWithContent('members.csv', $csv),
            'name' => 'Reviewed member migration',
            'default_campus_id' => $campus->id,
        ]);

        $import = MemberImport::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('member-imports.show', $import));
        $this->assertSame('ready', $import->status);
        $this->assertSame(2, $import->total_rows);
        $this->assertSame(['duplicate' => 1, 'ready' => 1], $import->rows()->orderBy('status')->selectRaw('status, count(*) aggregate')->groupBy('status')->pluck('aggregate', 'status')->all());
        $this->assertSame('first_name', $import->mapping['first_name']);
        $this->assertSame('family', $import->mapping['family_name']);

        $this->actingAs($admin)
            ->get(route('member-imports.show', $import))
            ->assertOk()
            ->assertSee('Column mapping')
            ->assertSee('Grace Import');

        $this->actingAs($admin)->post(route('member-imports.start', $import))->assertRedirect();
        $import->refresh();
        $this->assertSame('completed', $import->status);
        $this->assertSame(2, $import->processed_rows);
        $this->assertSame(1, $import->created_rows);
        $this->assertSame(1, $import->skipped_rows);
        $this->assertDatabaseHas('members', ['church_id' => $admin->church_id, 'email' => 'grace.import@example.test']);
        $this->assertDatabaseHas('families', ['church_id' => $admin->church_id, 'name' => 'Import Household']);
        $this->assertDatabaseHas('member_external_identities', ['church_id' => $admin->church_id, 'external_id' => 'LEG-1001']);

        $this->actingAs($admin)
            ->getJson(route('member-imports.progress', $import))
            ->assertOk()
            ->assertJsonPath('percent', 100)
            ->assertJsonPath('created', 1);

        $this->actingAs($admin)->post(route('member-imports.rollback', $import))->assertRedirect();
        $this->assertSoftDeleted('members', ['email' => 'grace.import@example.test']);
        $this->assertSame('rolled_back', $import->fresh()->status);
    }

    public function test_excel_workbooks_are_staged_with_automatic_mapping(): void
    {
        Storage::fake('local');
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $campus = Campus::query()->where('church_id', $admin->church_id)->firstOrFail();
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['Member ID', 'Full Name', 'Email Address', 'Mobile', 'Branch'],
            ['XLS-42', 'Excel Member', 'excel.member@example.test', '+234 803 000 0042', $campus->name],
        ]);
        $temporaryPath = tempnam(sys_get_temp_dir(), 'member-import-');
        $path = $temporaryPath.'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        try {
            $this->actingAs($admin)->post(route('member-imports.files.store'), [
                'members_file' => new UploadedFile($path, 'members.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
                'name' => 'Excel workbook',
                'default_campus_id' => $campus->id,
            ])->assertRedirect();
        } finally {
            @unlink($path);
            @unlink($temporaryPath);
        }

        $import = MemberImport::query()->latest('id')->firstOrFail();
        $this->assertSame('xlsx', $import->source_type);
        $this->assertSame('full_name', $import->mapping['full_name']);
        $this->assertSame('Excel', $import->rows()->sole()->normalized_data['first_name']);
        $this->assertSame('Member', $import->rows()->sole()->normalized_data['last_name']);
        $this->assertSame('+2348030000042', $import->rows()->sole()->normalized_data['phone']);
    }

    public function test_json_and_xml_records_support_nested_profiles_and_families(): void
    {
        Storage::fake('local');
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $campus = Campus::query()->where('church_id', $admin->church_id)->firstOrFail();
        $sources = [
            'json' => json_encode(['members' => [[
                'external_id' => 'JSON-1',
                'name' => 'Json Member',
                'email' => 'json.member@example.test',
                'family' => ['name' => 'Structured Family'],
                'profile' => ['date_of_birth' => '1990-05-04', 'occupation' => 'Teacher'],
            ]]], JSON_THROW_ON_ERROR),
            'xml' => '<members><member><external_id>XML-1</external_id><first_name>Xml</first_name><last_name>Member</last_name><email>xml.member@example.test</email><profile><city>Lagos</city></profile></member></members>',
        ];

        foreach ($sources as $extension => $contents) {
            $this->actingAs($admin)->post(route('member-imports.files.store'), [
                'members_file' => UploadedFile::fake()->createWithContent('members.'.$extension, $contents),
                'name' => strtoupper($extension).' import',
                'default_campus_id' => $campus->id,
            ])->assertRedirect();
        }

        $json = MemberImport::query()->where('source_type', 'json')->sole();
        $this->assertSame('Structured Family', $json->rows()->sole()->normalized_data['family_name']);
        $this->assertSame('1990-05-04', $json->rows()->sole()->normalized_data['date_of_birth']);
        $this->actingAs($admin)->post(route('member-imports.start', $json))->assertRedirect();
        $member = Member::query()->where('email', 'json.member@example.test')->firstOrFail();
        $this->assertSame('Structured Family', $member->family?->name);
        $this->assertSame('Teacher', $member->memberProfile?->occupation);

        $xml = MemberImport::query()->where('source_type', 'xml')->sole();
        $this->assertSame('Xml', $xml->rows()->sole()->normalized_data['first_name']);
        $this->actingAs($admin)->post(route('member-imports.start', $xml))->assertRedirect();
        $this->assertDatabaseHas('member_profiles', ['member_id' => Member::query()->where('email', 'xml.member@example.test')->value('id'), 'city' => 'Lagos']);
    }

    public function test_zip_imports_verified_profile_images_and_mapping_profiles_are_reusable(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $campus = Campus::query()->where('church_id', $admin->church_id)->firstOrFail();
        $temporaryPath = tempnam(sys_get_temp_dir(), 'member-zip-');
        $zip = new \ZipArchive;
        $zip->open($temporaryPath, \ZipArchive::OVERWRITE);
        $zip->addFromString('data/members.csv', "First Name,Last Name,Email,Profile Photo\nPhoto,Member,photo.member@example.test,photo.png\n");
        $zip->addFromString('images/photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
        $zip->close();

        try {
            $this->actingAs($admin)->post(route('member-imports.files.store'), [
                'members_file' => new UploadedFile($temporaryPath, 'member-package.zip', 'application/zip', null, true),
                'name' => 'ZIP package',
                'default_campus_id' => $campus->id,
            ])->assertRedirect();
        } finally {
            @unlink($temporaryPath);
        }

        $import = MemberImport::query()->where('source_type', 'zip')->sole();
        $this->assertSame('csv', data_get($import->source_options, 'embedded_data_type'));
        $this->assertCount(1, data_get($import->source_options, 'assets'));
        $this->actingAs($admin)->post(route('member-imports.profiles.store', $import), [
            'name' => 'Monthly ZIP export',
            'is_shared' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('member_import_profiles', ['church_id' => $admin->church_id, 'name' => 'Monthly ZIP export', 'is_shared' => true]);

        $this->actingAs($admin)->post(route('member-imports.start', $import))->assertRedirect();
        $this->assertSame('completed', $import->fresh()->status, (string) $import->rows()->whereNotNull('error')->value('error'));
        $member = Member::query()->where('email', 'photo.member@example.test')->firstOrFail();
        $this->assertNotNull($member->profile_photo_path);
        Storage::disk('public')->assertExists($member->profile_photo_path);
        $this->actingAs($admin)->get(route('members.show', $member))->assertOk()->assertSee(asset('storage/'.$member->profile_photo_path), false);

        $photoPath = $member->profile_photo_path;
        $this->actingAs($admin)->post(route('member-imports.rollback', $import))->assertRedirect();
        Storage::disk('public')->assertMissing($photoPath);

        $profile = $import->fresh()->profile;
        $this->actingAs($admin)->post(route('member-imports.files.store'), [
            'members_file' => UploadedFile::fake()->createWithContent('next.csv', "First Name,Last Name,Email,Profile Photo\nNext,Member,next.member@example.test,\n"),
            'name' => 'Profile reuse',
            'profile_id' => $profile->id,
            'default_campus_id' => $campus->id,
        ])->assertRedirect();
        $reused = MemberImport::query()->latest('id')->firstOrFail();
        $this->assertSame($profile->id, $reused->profile_id);
        $this->assertSame($profile->mapping, $reused->mapping);
    }
}
