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

    public function test_admin_can_manage_navigation_and_apply_a_public_menu_style(): void
    {
        $church = Church::factory()->create(['name' => 'Navigation Church']);
        $user = User::factory()->create(['church_id' => $church->id]);
        $adminRole = Role::query()->create(['name' => 'Super Administrator', 'slug' => 'super-administrator']);
        $user->roles()->attach($adminRole);

        $this->actingAs($user)
            ->get(route('website-studio.navigation'))
            ->assertOk()
            ->assertSee('Navigation Builder')
            ->assertSee('Floating glass')
            ->assertSee('Bold brand');

        $this->actingAs($user)
            ->put(route('website-studio.navigation.update'), [
                'menu_style' => 'accent',
                'navigation' => [
                    ['label' => 'Welcome', 'url' => '#welcome', 'visible' => '1'],
                    ['label' => 'Private link', 'url' => '#private', 'visible' => '0'],
                    ['label' => 'Visit', 'url' => '#contact', 'visible' => '1'],
                ],
            ])
            ->assertRedirect();

        $settings = data_get($church->fresh()->settings, 'website');
        $this->assertSame('accent', data_get($settings, 'menu_style'));
        $this->assertCount(3, data_get($settings, 'navigation'));

        $this->get(route('website.public', ['church' => $church->slug]))
            ->assertOk()
            ->assertSee('menu-style-accent', false)
            ->assertSee('Welcome')
            ->assertSee('Visit')
            ->assertDontSee('Private link');
    }

    public function test_admin_can_create_dropdown_and_mega_navigation_items(): void
    {
        $church = Church::factory()->create(['name' => 'Nested Navigation Church']);
        $user = User::factory()->create(['church_id' => $church->id]);
        $adminRole = Role::query()->create(['name' => 'Super Administrator', 'slug' => 'super-administrator']);
        $user->roles()->attach($adminRole);

        $this->actingAs($user)->put(route('website-studio.navigation.update'), [
            'menu_style' => 'classic',
            'navigation' => [
                ['label' => 'About', 'url' => '#about', 'type' => 'dropdown', 'visible' => '1', 'children' => [
                    ['label' => 'Our story', 'url' => '/story', 'description' => 'Learn who we are.', 'column' => '1', 'visible' => '1'],
                    ['label' => 'Hidden child', 'url' => '/hidden', 'visible' => '0'],
                ]],
                ['label' => 'Explore', 'url' => '#explore', 'type' => 'mega', 'visible' => '1', 'children' => [
                    ['label' => 'Ministries', 'url' => '/ministries', 'column' => '2', 'visible' => '1'],
                ]],
            ],
        ])->assertRedirect();

        $settings = data_get($church->fresh()->settings, 'website.navigation');
        $this->assertSame('dropdown', data_get($settings, '0.type'));
        $this->assertSame('Our story', data_get($settings, '0.children.0.label'));
        $this->assertSame(2, data_get($settings, '1.children.0.column'));

        $this->get(route('website.public', ['church' => $church->slug]))
            ->assertOk()
            ->assertSee('site-submenu-dropdown', false)
            ->assertSee('site-submenu-mega', false)
            ->assertSee('Our story')
            ->assertSee('Ministries')
            ->assertDontSee('Hidden child');
    }

    public function test_website_studio_emits_canonical_media_urls(): void
    {
        $church = Church::factory()->create(['name' => 'Media URL Church']);
        $user = User::factory()->create(['church_id' => $church->id]);
        $adminRole = Role::query()->create(['name' => 'Super Administrator', 'slug' => 'super-administrator']);
        $user->roles()->attach($adminRole);

        $this->actingAs($user)
            ->get(route('website-studio.index'))
            ->assertOk()
            ->assertSee('window.ecclesiaMediaConfig', false)
            ->assertSee(route('website-studio.media'), false)
            ->assertSee(route('website-studio.media.upload'), false)
            ->assertSee('storageUrl', false);
    }

    public function test_media_library_uses_the_shared_website_studio_header(): void
    {
        $church = Church::factory()->create(['name' => 'Media Design Church']);
        $user = User::factory()->create(['church_id' => $church->id]);
        $adminRole = Role::query()->create(['name' => 'Super Administrator', 'slug' => 'super-administrator']);
        $user->roles()->attach($adminRole);

        $this->actingAs($user)
            ->get(route('website-studio.media'))
            ->assertOk()
            ->assertSee('Website Studio · Media library')
            ->assertSee('Manage your website media')
            ->assertSee('Back to Website Studio')
            ->assertSee(route('website-studio.index'), false)
            ->assertSee('website-studio-admin w-full space-y-5', false)
            ->assertDontSee('max-w-[1500px]', false)
            ->assertDontSee('media-library-hero', false);
    }

    public function test_reusable_section_pages_use_the_full_website_studio_width(): void
    {
        $church = Church::factory()->create(['name' => 'Wide Sections Church']);
        $user = User::factory()->create(['church_id' => $church->id]);
        $adminRole = Role::query()->create(['name' => 'Super Administrator', 'slug' => 'super-administrator']);
        $user->roles()->attach($adminRole);

        foreach ([route('website-studio.sections'), route('website-studio.sections.create')] as $url) {
            $this->actingAs($user)
                ->get($url)
                ->assertOk()
                ->assertSee('w-full space-y-5', false)
                ->assertDontSee('max-w-[1500px]', false);
        }
    }

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
                            'card_border_width' => 3,
                            'card_border_color' => '#F59E0B',
                            'card_shadow' => 'large',
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
        $this->assertSame(3, data_get($linkedCard, 'card_border_width'));
        $this->assertSame('#F59E0B', data_get($linkedCard, 'card_border_color'));
        $this->assertSame('large', data_get($linkedCard, 'card_shadow'));
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
            ->assertSee('content-card-shadow-large', false)
            ->assertSee('--card-border-width: 3px', false)
            ->assertSee('--card-border-color: #F59E0B', false)
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

    public function test_page_sections_are_saved_in_the_dragged_order(): void
    {
        $church = Church::factory()->create(['name' => 'Section Order Church']);
        $user = User::factory()->create(['church_id' => $church->id]);
        $adminRole = Role::query()->create(['name' => 'Super Administrator', 'slug' => 'super-administrator']);
        $user->roles()->attach($adminRole);

        $this->actingAs($user)->get(route('website-studio.index'))->assertOk();

        $page = WebsitePage::query()
            ->where('church_id', $church->id)
            ->where('slug', 'about')
            ->firstOrFail();

        $this->actingAs($user)
            ->put(route('website-studio.pages.update', $page), [
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => 'published',
                'body' => $page->body,
                'section_types' => ['hero', 'welcome', 'contact'],
                'section_order' => ['contact', 'hero', 'welcome'],
                'page_template' => 'inherit',
            ])
            ->assertRedirect();

        $this->assertSame(['contact', 'hero', 'welcome'], $page->fresh()->sections);
    }

    public function test_legacy_public_landing_toggle_no_longer_hides_the_church_homepage(): void
    {
        $church = Church::factory()->create([
            'name' => 'Always Open Church',
            'settings' => [
                'website' => [
                    'enabled' => true,
                    'landing_page_enabled' => false,
                ],
            ],
        ]);
        $user = User::factory()->create(['church_id' => $church->id]);
        $adminRole = Role::query()->create(['name' => 'Super Administrator', 'slug' => 'super-administrator']);
        $user->roles()->attach($adminRole);

        $this->actingAs($user)
            ->get(route('website-studio.index'))
            ->assertOk()
            ->assertDontSee('Landing page enabled');

        $this->get(route('website.public', ['church' => $church->slug]))
            ->assertOk()
            ->assertSee('Always Open Church');
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
            ->assertSee('Harbour Light Church');

        $this->assertSame(
            ['home', 'ministries', 'about', 'our-sermons', 'our-locations', 'events', 'contact', 'store'],
            WebsitePage::query()->where('church_id', $church->id)->orderBy('id')->pluck('slug')->all(),
        );

        $this->actingAs($user)
            ->put(route('website-studio.settings.update'), [
                'enabled' => '1',
                'template' => 'main',
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
                'navigation_configured' => '1',
                'navigation' => [
                    ['label' => 'Welcome', 'url' => '#welcome', 'visible' => '1'],
                    ['label' => 'Visit us', 'url' => '#contact', 'visible' => '1'],
                ],
                'contact_email' => 'hello@harbour.test',
                'contact_phone' => '+1 555 0100',
                'contact_address' => '1 Harbour Lane',
                'seo_description' => 'Harbour Light Church online.',
            ])
            ->assertRedirect();

        $homepage = WebsitePage::query()->where('church_id', $church->id)->where('slug', 'home')->firstOrFail();
        $this->assertSame('published', $homepage->status);
        $this->assertSame('main', data_get($church->fresh()->settings, 'website.template'));
        $this->assertSame('main', data_get(WebsitePage::query()->where('church_id', $church->id)->where('slug', 'about')->firstOrFail()->design, 'starter_template'));

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
            ->assertSee('Page-level design')
            ->assertSee('Edit Website Page')
            ->assertSee('app-shell');

        $this->actingAs($user)->get(route('website-studio.preview', $page))->assertOk()->assertSee('Our story starts here.');
        $this->get(route('website.public', ['church' => $church->slug]))
            ->assertOk()
            ->assertSee('A place to belong.')
            ->assertSee('Harbour Light Church')
            ->assertSee('href="#welcome"', false)
            ->assertSee('Welcome', false)
            ->assertSee('Visit us', false);
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
                'page_template' => 'main',
                'page_primary_color' => '#234567',
                'page_accent_color' => '#F59E0B',
                'page_hero_heading' => 'Our story, told together.',
            ])
            ->assertRedirect();

        $this->assertSame('main', data_get($page->fresh()->design, 'template'));

        $this->get(route('website.public', ['church' => $church->slug, 'page' => 'our-story']))
            ->assertOk()
            ->assertSee('Our story starts here.');

        $this->actingAs($user)
            ->put(route('website-studio.settings.update'), [
                'enabled' => '1',
                'template' => 'main',
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
        $this->assertSame('main', data_get(WebsitePage::query()->where('church_id', $church->id)->where('slug', 'about')->firstOrFail()->design, 'starter_template'));

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
            ->assertSee('Grace for the road')
            ->assertSee('Harbour Light Study Guide');

        $this->actingAs($user)
            ->get(route('sermons.index'))
            ->assertOk()
            ->assertSee('Grace for the road');
    }
}
