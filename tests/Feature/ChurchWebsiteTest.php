<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BookstoreProduct;
use App\Models\Church;
use App\Models\Role;
use App\Models\Sermon;
use App\Models\User;
use App\Models\WebsitePage;
use App\Services\WebsiteStarterContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ChurchWebsiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_and_video_slider_support_uploaded_and_linked_videos_end_to_end(): void
    {
        Storage::fake('public');

        $church = Church::factory()->create(['name' => 'Video Test Church']);
        $user = User::factory()->create(['church_id' => $church->id]);
        $adminRole = Role::query()->create(['name' => 'Super Administrator', 'slug' => 'super-administrator']);
        $user->roles()->attach($adminRole);

        $this->actingAs($user)->get(route('website-studio.index'))->assertOk();

        $linkedCardId = 'linked-card';
        $uploadedCardId = 'uploaded-card';
        $sliderId = 'video-slider';
        $sliderVideoId = 'uploaded-slider-video';
        $remoteVideo = 'https://cdn.example.test/church-intro.mp4';
        $components = [
            'id' => 'root-columns',
            'type' => 'columns',
            'groups' => [[
                'id' => 'group-one',
                'type' => 'columns',
                'columns' => [[
                    'width' => 1,
                    'components' => [
                        [
                            'id' => $linkedCardId,
                            'type' => 'card',
                            'title' => 'Linked video card',
                            'body' => 'Direct URL video',
                            'background_video' => $remoteVideo,
                            'background_color' => '#6d4aff',
                        ],
                        [
                            'id' => $uploadedCardId,
                            'type' => 'card',
                            'title' => 'Uploaded video card',
                            'body' => 'Uploaded background video',
                            'background_color' => '#6d4aff',
                        ],
                        [
                            'id' => $sliderId,
                            'type' => 'video-slider',
                            'autoplay' => true,
                            'slides' => [[
                                'id' => $sliderVideoId,
                                'video' => '',
                                'title' => 'Uploaded slider video',
                                'text' => '',
                                'link' => '',
                            ]],
                        ],
                    ],
                ]],
            ]],
        ];

        $this->actingAs($user)
            ->post(route('website-studio.sections.store'), [
                'title' => 'Video end-to-end section',
                'page_slugs' => ['about'],
                'components' => json_encode($components, JSON_THROW_ON_ERROR),
                'component_video_files' => [
                    $uploadedCardId => UploadedFile::fake()->create('card-background.mp4', 256, 'video/mp4'),
                    $sliderVideoId => UploadedFile::fake()->create('slider-video.mp4', 256, 'video/mp4'),
                ],
            ])
            ->assertRedirect();

        $section = collect(data_get($church->fresh()->settings, 'website.custom_sections'))->firstWhere('title', 'Video end-to-end section');
        $this->assertNotNull($section);
        $savedComponents = data_get($section, 'components.groups.0.columns.0.components');
        $linkedCard = collect($savedComponents)->firstWhere('id', $linkedCardId);
        $uploadedCard = collect($savedComponents)->firstWhere('id', $uploadedCardId);
        $slider = collect($savedComponents)->firstWhere('id', $sliderId);
        $uploadedCardPath = data_get($uploadedCard, 'background_video');
        $uploadedSliderPath = data_get($slider, 'slides.0.video');

        $this->assertSame($remoteVideo, data_get($linkedCard, 'background_video'));
        $this->assertNotEmpty($uploadedCardPath);
        $this->assertNotEmpty($uploadedSliderPath);
        Storage::disk('public')->assertExists($uploadedCardPath);
        Storage::disk('public')->assertExists($uploadedSliderPath);

        $publicPage = $this->get(route('website.public', ['church' => $church->slug, 'page' => 'about']));
        $publicPage->assertOk()
            ->assertHeaderContains('Content-Security-Policy', "media-src 'self' blob: https:")
            ->assertSee($remoteVideo, false)
            ->assertSee(asset('storage/'.$uploadedCardPath), false)
            ->assertSee(asset('storage/'.$uploadedSliderPath), false)
            ->assertSee('content-card-widget-background', false)
            ->assertSee('data-background-video', false)
            ->assertSee('autoplay muted loop', false);
    }

    public function test_every_supported_template_has_complete_starter_content(): void
    {
        $templates = [
            'modern', 'classic', 'community', 'crepa', 'elevation', 'austin-stone',
            'motivation', 'vous', 'river-valley', 'city', 'anchor', 'meeting-house',
            'bay-hope', 'brooklake',
        ];

        foreach ($templates as $template) {
            $pages = app(WebsiteStarterContent::class)->pages($template, 'Test Church');

            $this->assertCount(8, $pages, $template);
            foreach ($pages as $page) {
                $this->assertNotEmpty($page['body'], $template.' body');
                if ($page['slug'] !== 'home') {
                    $this->assertNotEmpty(data_get($page, 'design.hero_heading'), $template.' hero heading');
                }
            }
        }
    }

    public function test_church_admin_can_configure_publish_and_preview_a_church_website(): void
    {
        $church = Church::factory()->create(['name' => 'Harbour Light Church']);
        $user = User::factory()->create(['church_id' => $church->id]);
        $adminRole = Role::query()->create(['name' => 'Super Administrator', 'slug' => 'super-administrator']);
        $user->roles()->attach($adminRole);

        $this->actingAs($user)
            ->get(route('website-studio.index'))
            ->assertOk()
            ->assertSee('Build a website that feels like your church.')
            ->assertSee('Abundant Grace');

        $this->assertSame(
            ['home', 'ministries', 'about', 'our-sermons', 'our-locations', 'events', 'contact', 'store'],
            WebsitePage::query()->where('church_id', $church->id)->orderBy('id')->pluck('slug')->all(),
        );

        $this->actingAs($user)
            ->put(route('website-studio.settings.update'), [
                'enabled' => '1',
                'template' => 'community',
                'site_name' => 'Harbour Light Church',
                'tagline' => 'A warm place to belong.',
                'primary_color' => '#123456',
                'accent_color' => '#F59E0B',
                'font' => 'Manrope',
                'hero_eyebrow' => 'Welcome home',
                'hero_heading' => 'A place to belong.',
                'hero_body' => 'Come worship with us.',
                'hero_button_label' => 'Plan a visit',
                'hero_button_url' => '#visit',
                'welcome_heading' => 'You have a place here.',
                'welcome_body' => 'We are glad you are here.',
                'contact_email' => 'hello@harbour.test',
                'contact_phone' => '+1 555 0100',
                'contact_address' => '1 Harbour Lane',
                'seo_description' => 'Harbour Light Church online.',
            ])
            ->assertRedirect();

        $homepage = WebsitePage::query()->where('church_id', $church->id)->where('slug', 'home')->firstOrFail();
        $this->assertSame('published', $homepage->status);
        $this->assertSame('community', data_get($church->fresh()->settings, 'website.template'));
        $this->assertSame('community', data_get(WebsitePage::query()->where('church_id', $church->id)->where('slug', 'about')->firstOrFail()->design, 'starter_template'));

        $this->actingAs($user)
            ->post(route('website-studio.pages.store'), [
                'title' => 'Our Story',
                'slug' => 'our-story',
                'status' => 'draft',
                'body' => 'Our story starts here.',
                'section_types' => ['welcome', 'contact'],
            ])
            ->assertRedirect();

        $page = WebsitePage::query()->where('church_id', $church->id)->where('slug', 'our-story')->firstOrFail();

        $this->actingAs($user)
            ->get(route('website-studio.pages.edit', $page))
            ->assertOk()
            ->assertSee('Page-level design');

        $this->actingAs($user)->get(route('website-studio.preview', $page))->assertOk()->assertSee('Our story starts here.');
        $this->get(route('website.public', ['church' => $church->slug]))
            ->assertOk()
            ->assertSee('A place to belong.')
            ->assertSee('Harbour Light Church');
        $this->get(route('website.public', ['church' => $church->slug, 'page' => 'about']))
            ->assertOk()
            ->assertSee('Learn about our mission');

        $this->actingAs($user)
            ->put(route('website-studio.pages.update', $page), [
                'title' => 'Our Story',
                'slug' => 'our-story',
                'status' => 'published',
                'body' => 'Our story starts here.',
                'section_types' => ['hero', 'sermons', 'store', 'contact'],
                'page_template' => 'crepa',
                'page_primary_color' => '#234567',
                'page_accent_color' => '#F59E0B',
                'page_hero_heading' => 'Our story, told together.',
            ])
            ->assertRedirect();

        $this->assertSame('crepa', data_get($page->fresh()->design, 'template'));

        $this->get(route('website.public', ['church' => $church->slug, 'page' => 'our-story']))
            ->assertOk()
            ->assertSee('Our story starts here.');

        $this->actingAs($user)
            ->put(route('website-studio.settings.update'), [
                'enabled' => '1',
                'template' => 'crepa',
                'site_name' => 'Harbour Light Church',
                'tagline' => 'A warm place to belong.',
                'primary_color' => '#123456',
                'accent_color' => '#F59E0B',
                'font' => 'Manrope',
                'hero_heading' => 'A place to belong.',
                'welcome_heading' => 'You have a place here.',
                'welcome_body' => 'We are glad you are here.',
            ])
            ->assertRedirect();
        $this->assertSame('crepa', data_get(WebsitePage::query()->where('church_id', $church->id)->where('slug', 'about')->firstOrFail()->design, 'starter_template'));

        $this->actingAs($user)
            ->post(route('sermons.store'), [
                'title' => 'Grace for the road',
                'speaker' => 'Pastor Grace',
                'scripture' => 'Psalm 23',
                'summary' => 'A message for the next step.',
                'preached_at' => now()->toDateString(),
                'status' => 'published',
            ])
            ->assertRedirect();
        $this->assertTrue(Sermon::query()->where('church_id', $church->id)->where('slug', 'grace-for-the-road')->exists());
        BookstoreProduct::query()->create([
            'church_id' => $church->id,
            'name' => 'Harbour Light Study Guide',
            'category' => 'Study resources',
            'format' => 'hardcopy',
            'price' => 12.50,
            'stock_quantity' => 4,
            'reorder_level' => 1,
            'status' => 'active',
        ]);

        $this->get(route('website.public', ['church' => $church->slug]))
            ->assertOk()
            ->assertSee('crepa-title', false)
            ->assertSee('Be part of our', false)
            ->assertSee('Grace for the road')
            ->assertSee('Harbour Light Study Guide');

        $this->actingAs($user)
            ->get(route('sermons.index'))
            ->assertOk()
            ->assertSee('Grace for the road');
    }
}
