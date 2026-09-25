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

    public function test_admin_can_lock_the_public_website_to_the_selected_theme(): void
    {
        $church = Church::factory()->create([
            'name' => 'Theme Locked Church',
            'settings' => ['website' => [
                'color_scheme' => 'light',
                'theme_switcher_enabled' => false,
            ]],
        ]);

        $this->get(route('website.public', ['church' => $church->slug]))
            ->assertOk()
            ->assertSee('theme-light', false)
            ->assertSee('data-theme-switcher-enabled="false"', false)
            ->assertDontSee('data-theme-toggle', false);

        $javascript = file_get_contents(public_path('js/website/templates/main.js'));
        $this->assertStringContainsString("document.body.dataset.themeSwitcherEnabled !== 'false'", $javascript);
        $this->assertStringContainsString('if (themeSwitcherEnabled)', $javascript);
    }

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

        $this->actingAs($user)
            ->get(route('website-studio.sections.create'))
            ->assertOk()
            ->assertSee('Section details')
            ->assertSee('Design columns and widgets')
            ->assertSee('Publish location')
            ->assertSee('Optional section media')
            ->assertSee('xl:grid-cols-[minmax(0,1fr)_360px]', false)
            ->assertSee('sticky bottom-3', false)
            ->assertDontSee('.section-create-page .bg-gradient-to-br', false);
    }

    public function test_reusable_section_edit_page_uses_the_wide_branded_editor(): void
    {
        $church = Church::factory()->create([
            'name' => 'Editable Sections Church',
            'settings' => [
                'website' => [
                    'custom_sections' => [[
                        'id' => 'editable-section',
                        'title' => 'Welcome section',
                        'components' => [],
                        'page_slugs' => ['home'],
                        'order' => 0,
                    ]],
                ],
            ],
        ]);
        $user = User::factory()->create(['church_id' => $church->id]);
        $adminRole = Role::query()->create(['name' => 'Super Administrator', 'slug' => 'super-administrator']);
        $user->roles()->attach($adminRole);

        $this->actingAs($user)
            ->get(route('website-studio.sections.edit', 'editable-section'))
            ->assertOk()
            ->assertSee('section-edit-page w-full space-y-5', false)
            ->assertSee('Section details')
            ->assertSee('Design columns and widgets')
            ->assertSee('Publish location')
            ->assertSee('Section appearance')
            ->assertSee('Optional section media')
            ->assertSee('xl:grid-cols-[minmax(0,1fr)_360px]', false)
            ->assertSee('sticky bottom-3', false)
            ->assertDontSee('max-w-[1500px]', false)
            ->assertDontSee('aside&gt;.dashboard-card:nth-child(2)', false);
    }

    public function test_section_builder_upload_controls_respond_to_their_column_width(): void
    {
        $css = file_get_contents(public_path('css/website-studio/section-builder.css'));
        $javascript = file_get_contents(public_path('js/website-studio/section-builder.js'));
        $mediaPicker = file_get_contents(public_path('js/website-studio/media-picker.js'));

        $this->assertStringContainsString('container-type: inline-size', $css);
        $this->assertStringContainsString('@container (max-width: 34rem)', $css);
        $this->assertStringContainsString("uploadField.addEventListener('change'", $javascript);
        $this->assertStringContainsString('selectedName', $javascript);
        $this->assertStringContainsString('data-media-url-field="background_image"', $javascript);
        $this->assertStringContainsString('Select or upload image', $javascript);
        $this->assertStringContainsString('data-spacing-preset="flush"', $javascript);
        $this->assertStringContainsString('No spacing', $javascript);
        $this->assertStringContainsString('data-field="divider_justify"', $javascript);
        $this->assertStringContainsString('<option value="center"', $javascript);
        $this->assertStringContainsString('data-field="font_size"', $javascript);
        $this->assertStringContainsString('Font size (px)', $javascript);
        $this->assertStringContainsString('data-field="icon_background_transparent"', $javascript);
        $this->assertStringContainsString('Full screen (edge to edge)', $javascript);
        $this->assertStringContainsString('container.columns.forEach', $javascript);
        $this->assertStringContainsString('explicitColumnField', $mediaPicker);
        $this->assertStringContainsString('urlInput.value = selected.path', $mediaPicker);

        $publicCss = file_get_contents(public_path('css/website/templates/main.css'));
        $this->assertStringContainsString('height: var(--hero-media-height, 560px)', $publicCss);
        $this->assertStringContainsString('overflow-x: clip', $publicCss);
        $this->assertStringContainsString('justify-content: var(--divider-justify, flex-start)', $publicCss);
        $this->assertStringContainsString(".button:hover {\n    transform: translateY(-2px);\n    box-shadow: none;", $publicCss);
        $this->assertStringContainsString(".theme-light .button:hover {\n    box-shadow: none;", $publicCss);
        $this->assertStringContainsString('width: 100vw', $publicCss);
        $this->assertStringContainsString('margin-left: calc(50% - 50vw)', $publicCss);
        $this->assertStringContainsString('.column-full-bleed > .has-column-presentation', $publicCss);
        $this->assertStringContainsString('.reusable-section:has(.column-full-bleed)', $publicCss);
        $this->assertStringContainsString('.theme-dark .public-event-list-item', $publicCss);
        $this->assertStringContainsString('.theme-dark .public-event-aside-card', $publicCss);
        $this->assertStringContainsString('.theme-dark .site-nav', $publicCss);
        $this->assertStringContainsString('.theme-dark .menu-toggle', $publicCss);
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
                'gap' => 0,
                'margin' => 0,
                'columns' => [[
                    'width' => 1,
                    'background_image' => 'website/library/portable-column.webp',
                    'background_position' => 'top',
                    'border_radius' => 36,
                    'padding' => 0,
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
                            'card_border_radius' => 42,
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
        $savedColumn = data_get($section, 'components.groups.0.columns.0');
        $uploadedCard = collect($savedComponents)->firstWhere('id', $uploadedCardId);
        $slider = collect($savedComponents)->firstWhere('id', $sliderId);
        $uploadedCardPath = data_get($uploadedCard, 'background_video');
        $uploadedSliderPath = data_get($slider, 'slides.0.video');

        $this->assertSame($remoteVideo, data_get($linkedCard, 'background_video'));
        $this->assertSame(3, data_get($linkedCard, 'card_border_width'));
        $this->assertSame('#F59E0B', data_get($linkedCard, 'card_border_color'));
        $this->assertSame(42, data_get($linkedCard, 'card_border_radius'));
        $this->assertSame('large', data_get($linkedCard, 'card_shadow'));
        $this->assertSame('top', data_get($savedColumn, 'background_position'));
        $this->assertSame(36, data_get($savedColumn, 'border_radius'));
        $this->assertSame(0, data_get($savedColumn, 'padding'));
        $this->assertSame(0, data_get($section, 'components.groups.0.gap'));
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
            ->assertSee('--card-border-radius:42px', false)
            ->assertSee('--column-border-radius:36px', false)
            ->assertSee('--column-background-position:top', false)
            ->assertSee('--column-group-gap:0px', false)
            ->assertSee('--column-padding:0px', false)
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
                'hero_height' => 640,
                'hero_slides_configured' => '1',
                'hero_slides' => [
                    'library-image' => [
                        'type' => 'image',
                        'url' => 'website/library/portable-hero.webp',
                        'position' => 'right',
                    ],
                ],
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
        $this->assertSame(640, data_get($church->fresh()->settings, 'website.hero_height'));
        $this->assertSame('right', data_get($church->fresh()->settings, 'website.hero_slides.0.position'));
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
            ->assertSee('--hero-media-height:640px', false)
            ->assertSee('--hero-media-position:right', false)
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
