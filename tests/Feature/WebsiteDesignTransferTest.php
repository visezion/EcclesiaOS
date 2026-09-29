<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Role;
use App\Models\User;
use App\Services\WebsiteDesignTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

final class WebsiteDesignTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_design_round_trip_moves_media_pages_and_links_without_other_church_data(): void
    {
        Storage::fake('public');
        $source = Church::factory()->create();
        $target = Church::factory()->create(['settings' => ['private_setting' => 'keep']]);
        $path = 'website/'.$source->id.'/hero.png';
        Storage::disk('public')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a2ioAAAAASUVORK5CYII='));
        $settings = [
            'template' => 'main', 'enabled' => true, 'hero_image_url' => $path,
            'navigation' => [['label' => 'About', 'url' => route('website.public', ['church' => $source->slug, 'page' => 'about'])]],
            'custom_sections' => [['id' => 'section-one', 'title' => 'Welcome', 'page_slugs' => ['home'], 'components' => ['type' => 'columns', 'groups' => [['columns' => [['width' => 1, 'components' => [['id' => 'heading-one', 'type' => 'heading', 'text' => 'Hello', 'margin_bottom' => 12], ['type' => 'image', 'url' => url('storage/'.$path)]]]]]]]]],
            'media_library' => [['id' => 'media-one', 'path' => $path, 'name' => 'hero.png']],
        ];
        $source->update(['settings' => ['website' => $settings, 'private_setting' => 'source-secret']]);
        foreach (['home', 'about'] as $slug) {
            $source->websitePages()->create(['title' => ucfirst($slug), 'slug' => $slug, 'status' => 'published', 'sections' => ['section-one', 'contact'], 'design' => ['hero_image_url' => $path]]);
        }
        $old = $target->websitePages()->create(['title' => 'Old', 'slug' => 'old', 'status' => 'published']);
        $trashed = $target->websitePages()->create(['title' => 'Old home', 'slug' => 'home', 'status' => 'draft']);
        $trashed->delete();
        $service = app(WebsiteDesignTransfer::class);
        $file = $service->export($source, $settings);
        try {
            $service->import($target, $file);
        } finally {
            unlink($file);
        }
        $saved = $target->fresh()->settings;
        $this->assertSame('keep', $saved['private_setting']);
        $this->assertStringStartsWith('website/'.$target->id.'/imports/', $saved['website']['hero_image_url']);
        Storage::disk('public')->assertExists($saved['website']['hero_image_url']);
        $this->assertSame($saved['website']['hero_image_url'], $saved['website']['media_library'][0]['path']);
        $this->assertSame(route('website.public', ['church' => $target->slug, 'page' => 'about']), $saved['website']['navigation'][0]['url']);
        $this->assertSame(12, data_get($saved, 'website.custom_sections.0.components.groups.0.columns.0.components.0.margin_bottom'));
        $this->assertSame(['section-one', 'contact'], $target->websitePages()->where('slug', 'home')->first()->sections);
        $this->assertSame(2, $source->websitePages()->count());
        $this->assertSame(2, $target->websitePages()->count());
        $this->assertSoftDeleted($old);
        $this->assertNotSoftDeleted($trashed);
        $this->get(route('website.public', ['church' => $target->slug]))
            ->assertOk()->assertSee('Hello')->assertSee('margin-bottom:12px', false);
        $pageCount = $target->websitePages()->count();
        $secondFile = $service->export($target->fresh(), $saved['website']);
        try {
            $service->import($target, $secondFile);
            $this->assertSame($pageCount, $target->websitePages()->count());
        } finally {
            unlink($secondFile);
        }
    }

    public function test_invalid_archive_is_rejected_without_changing_the_design(): void
    {
        Storage::fake('public');
        $church = Church::factory()->create(['settings' => ['website' => ['site_name' => 'Keep me']]]);
        $file = tempnam(sys_get_temp_dir(), 'bad-design-');
        $zip = new ZipArchive;
        $zip->open($file, ZipArchive::OVERWRITE);
        $zip->addFromString('../outside.php', '<?php echo 1;');
        $zip->close();
        try {
            app(WebsiteDesignTransfer::class)->import($church, $file);
            $this->fail('Unsafe archive should be rejected.');
        } catch (ValidationException) {
            $this->assertSame('Keep me', data_get($church->fresh()->settings, 'website.site_name'));
            $this->assertSame([], Storage::disk('public')->allFiles());
        } finally {
            unlink($file);
        }
    }

    public function test_invalid_media_rolls_back_files_and_keeps_existing_design(): void
    {
        Storage::fake('public');
        $church = Church::factory()->create(['settings' => ['website' => ['site_name' => 'Existing design']]]);
        $file = tempnam(sys_get_temp_dir(), 'invalid-media-');
        $zip = new ZipArchive;
        $zip->open($file, ZipArchive::OVERWRITE);
        $zip->addFromString('design.json', json_encode([
            'format' => 'ecclesiaos-website-design', 'version' => 1,
            'data' => ['settings' => ['template' => 'main', 'hero_image_url' => '@media/media/0.png'], 'pages' => [['title' => 'Home', 'slug' => 'home', 'status' => 'published']]],
        ], JSON_THROW_ON_ERROR));
        $zip->addFromString('media/0.png', '<?php echo "not an image";');
        $zip->close();
        try {
            app(WebsiteDesignTransfer::class)->import($church, $file);
            $this->fail('Invalid media should be rejected.');
        } catch (ValidationException) {
            $this->assertSame('Existing design', data_get($church->fresh()->settings, 'website.site_name'));
            $this->assertSame([], Storage::disk('public')->allFiles());
            $this->assertSame(0, $church->websitePages()->count());
        } finally {
            unlink($file);
        }
    }

    public function test_transfer_routes_require_studio_permission_and_replacement_confirmation(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);
        $this->actingAs($user)->get(route('website-studio.design.export'))->assertForbidden();
        $this->actingAs($user)->post(route('website-studio.design.import'))->assertForbidden();
        $role = Role::query()->create(['name' => 'Super Administrator', 'slug' => 'super-administrator']);
        $user->roles()->attach($role);
        $this->actingAs($user->fresh())->post(route('website-studio.design.import'), ['design_package' => UploadedFile::fake()->create('design.zip', 1, 'application/zip')])->assertSessionHasErrors('replace_design');
        $response = $this->actingAs($user->fresh())->get(route('website-studio.design.export'));
        $response->assertOk()->assertDownload('website-design-'.$church->slug.'.zip');
        $file = $response->baseResponse->getFile()->getPathname();
        try {
            $this->actingAs($user->fresh())->post(route('website-studio.design.import'), [
                'replace_design' => '1',
                'design_package' => new UploadedFile($file, 'design.zip', 'application/zip', null, true),
            ])->assertSessionHasNoErrors()->assertRedirect(route('website-studio.index'));
        } finally {
            @unlink($file);
        }
    }
}
