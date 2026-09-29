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

    public function test_card_widget_can_link_the_whole_card_or_only_its_button(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);
        $role = Role::query()->create(['name' => 'Super Administrator', 'slug' => 'super-administrator']);
        $user->roles()->attach($role);
        $this->actingAs($user)->get(route('website-studio.index'))->assertOk();

        $cards = [
            ['type' => 'card', 'title' => 'Whole card', 'body' => 'Open the page', 'link' => '/visit', 'card_link_enabled' => true, 'card_button_enabled' => false],
            ['type' => 'card', 'title' => 'Button only', 'body' => 'Read more', 'link' => 'https://example.org/info', 'card_link_enabled' => false, 'card_button_enabled' => true],
            ['type' => 'card', 'title' => 'Older card', 'link' => '/legacy', 'card_button_enabled' => false],
            ['type' => 'card', 'title' => 'Unsafe card', 'link' => 'javascript:alert(1)', 'card_link_enabled' => true],
        ];
        $this->actingAs($user)->post(route('website-studio.sections.store'), [
            'title' => 'Linked cards', 'page_slugs' => ['home'], 'components' => json_encode($cards),
        ])->assertSessionHasNoErrors();

        $saved = data_get($church->fresh()->settings, 'website.custom_sections.0.components');
        $this->assertTrue($saved[0]['card_link_enabled']);
        $this->assertFalse($saved[1]['card_link_enabled']);
        $this->assertTrue($saved[2]['card_link_enabled']);
        $this->assertSame('', $saved[3]['link']);

        $html = $this->get(route('website.public', ['church' => $church->slug]))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'class="content-card-widget-link"'));
        $this->assertStringContainsString('class="content-card-widget-link" href="/visit"', $html);
        $this->assertStringContainsString('class="content-card-widget-link" href="/legacy"', $html);
        $this->assertStringContainsString('href="https://example.org/info" class="button button-light card-action-button"', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
    }

    public function test_heading_and_text_widgets_can_be_linked_in_flat_and_nested_sections(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);
        $role = Role::query()->create(['name' => 'Super Administrator', 'slug' => 'super-administrator']);
        $user->roles()->attach($role);
        $this->actingAs($user)->get(route('website-studio.index'))->assertOk();

        $flat = [
            ['type' => 'heading', 'text' => 'Visit our events', 'link_enabled' => true, 'link_url' => '/events'],
            ['type' => 'text', 'text' => 'Read our story', 'link_enabled' => true, 'link_url' => 'https://example.org/story'],
            ['type' => 'text', 'text' => 'Plain text', 'link_enabled' => false, 'link_url' => '/unused'],
            ['type' => 'heading', 'text' => 'Unsafe link stays plain', 'link_enabled' => true, 'link_url' => 'javascript:alert(1)'],
        ];
        $nested = ['type' => 'columns', 'columns' => [[
            'width' => 1, 'components' => [['type' => 'columns', 'columns' => [[
                'width' => 1, 'components' => [['type' => 'text', 'text' => 'Email us', 'link_enabled' => true, 'link_url' => 'mailto:team@example.org']],
            ]]]],
        ]]];
        foreach ([$flat, $nested] as $index => $components) {
            $this->post(route('website-studio.sections.store'), [
                'title' => 'Linked typography '.$index, 'page_slugs' => ['home'], 'components' => json_encode($components),
            ])->assertSessionHasNoErrors()->assertRedirect();
        }

        $sections = data_get($church->fresh()->settings, 'website.custom_sections');
        $this->assertTrue(data_get($sections, '0.components.0.link_enabled'));
        $this->assertSame('/events', data_get($sections, '0.components.0.link_url'));
        $this->assertSame('', data_get($sections, '0.components.3.link_url'));
        $this->assertSame('mailto:team@example.org', data_get($sections, '1.components.columns.0.components.0.columns.0.components.0.link_url'));

        $html = $this->get(route('website.public', ['church' => $church->slug]))->assertOk()->getContent();
        $this->assertStringContainsString('class="typography-widget-link" href="/events"', $html);
        $this->assertStringContainsString('class="typography-widget-link" href="https://example.org/story"', $html);
        $this->assertStringContainsString('class="typography-widget-link" href="mailto:team@example.org"', $html);
        $this->assertStringNotContainsString('href="/unused"', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
        $this->assertSame(3, substr_count($html, 'class="typography-widget-link"'));
    }

    public function test_faq_widget_saves_and_renders_nested_accordion_safely(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);
        $role = Role::query()->create(['name' => 'Super Administrator', 'slug' => 'super-administrator']);
        $user->roles()->attach($role);
        $this->actingAs($user)->get(route('website-studio.index'))->assertOk();

        $faq = [
            'id' => 'visitor-faq', 'type' => 'faq', 'faq_title' => 'Visitor questions',
            'faq_open' => 'first', 'faq_mode' => 'single',
            'faq_items' => [
                ['question' => 'When are services?', 'answer' => "Sunday at 10.\nEveryone is welcome."],
                ['question' => 'Can I ask for prayer?', 'answer' => 'Yes, use our prayer form.'],
                ['question' => '', 'answer' => 'Incomplete item is omitted.'],
            ],
        ];
        $components = ['type' => 'columns', 'columns' => [[
            'width' => 1, 'components' => [['type' => 'columns', 'columns' => [[
                'width' => 1, 'components' => [$faq],
            ]]]],
        ]]];
        $this->actingAs($user)->post(route('website-studio.sections.store'), [
            'title' => 'Visitor FAQs', 'page_slugs' => ['home'], 'components' => json_encode($components),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $section = data_get($church->fresh()->settings, 'website.custom_sections.0');
        $saved = data_get($section, 'components.columns.0.components.0.columns.0.components.0');
        $this->assertSame('faq', $saved['type']);
        $this->assertCount(2, $saved['faq_items']);
        $this->get(route('website-studio.sections.edit', $section['id']))->assertOk()->assertSee('Visitor questions');
        $response = $this->get(route('website.public', ['church' => $church->slug]))
            ->assertOk()->assertSee('Visitor questions')->assertSee('When are services?')
            ->assertSee('Sunday at 10.')->assertSee('Can I ask for prayer?')
            ->assertDontSee('Incomplete item is omitted.');
        $this->assertSame(2, substr_count($response->getContent(), 'class="website-faq-item"'));
        $this->assertStringContainsString('name="faq-', $response->getContent());
        $this->assertStringContainsString(' open', $response->getContent());
    }

    public function test_faq_widget_can_start_closed_and_keep_answers_on_the_public_page(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);
        $role = Role::query()->create(['name' => 'Super Administrator', 'slug' => 'super-administrator']);
        $user->roles()->attach($role);
        $this->actingAs($user)->get(route('website-studio.index'))->assertOk();
        $this->actingAs($user)->post(route('website-studio.sections.store'), [
            'title' => 'FAQs', 'page_slugs' => ['home'],
            'components' => json_encode([['type' => 'faq', 'faq_title' => 'FAQs', 'faq_open' => 'closed', 'faq_mode' => 'multiple', 'faq_items' => [
                ['question' => 'What time?', 'answer' => 'At ten.'],
                ['question' => 'Where?', 'answer' => 'At church.'],
            ]]]),
        ])->assertSessionHasNoErrors();

        $response = $this->get(route('website.public', ['church' => $church->slug]))->assertOk()->assertSee('What time?')->assertSee('At ten.');
        $this->assertSame(2, substr_count($response->getContent(), 'class="website-faq-item"'));
        $this->assertStringNotContainsString('name="faq-', $response->getContent());
    }

    public function test_section_saves_collapsed_widgets_and_columns_and_can_expand_them_again(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);
        $role = Role::query()->create(['name' => 'Super Administrator', 'slug' => 'super-administrator']);
        $user->roles()->attach($role);
        $this->actingAs($user);
        $components = ['type' => 'columns', 'groups' => [[
            'type' => 'columns', 'columns' => [[
                'id' => 'outer', 'editor_collapsed' => true, 'components' => [
                    ['id' => 'heading', 'type' => 'heading', 'text' => 'Always visible publicly', 'editor_collapsed' => true],
                    ['type' => 'columns', 'columns' => [[
                        'id' => 'inner', 'editor_collapsed' => true, 'components' => [
                            ['id' => 'text', 'type' => 'text', 'text' => 'Nested message', 'editor_collapsed' => false],
                        ],
                    ]]],
                ],
            ]],
        ]]];
        $this->post(route('website-studio.sections.store'), ['title' => 'Remember editor state', 'components' => json_encode($components)])
            ->assertSessionHasNoErrors()->assertRedirect();
        $section = data_get($church->fresh()->settings, 'website.custom_sections.0');
        $column = data_get($section, 'components.groups.0.columns.0');
        $this->assertTrue($column['editor_collapsed']);
        $this->assertTrue($column['components'][0]['editor_collapsed']);
        $this->assertTrue($column['components'][1]['columns'][0]['editor_collapsed']);
        $this->assertFalse($column['components'][1]['columns'][0]['components'][0]['editor_collapsed']);
        $this->get(route('website-studio.sections.edit', $section['id']))->assertOk();
        $this->get(route('website.public', ['church' => $church->slug]))->assertOk()->assertSee('Always visible publicly')->assertSee('Nested message');
        $components['groups'][0]['columns'][0]['editor_collapsed'] = false;
        $components['groups'][0]['columns'][0]['components'][0]['editor_collapsed'] = false;
        $this->put(route('website-studio.sections.update', $section['id']), ['title' => 'Remember editor state', 'components' => json_encode($components)])
            ->assertSessionHasNoErrors();
        $column = data_get($church->fresh()->settings, 'website.custom_sections.0.components.groups.0.columns.0');
        $this->assertFalse($column['editor_collapsed']);
        $this->assertFalse($column['components'][0]['editor_collapsed']);
    }

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
        // The duplicate handler requires a matching button in every widget toolbar.
        $this->assertStringContainsString('<button type="button" data-duplicate title="Duplicate this widget">Duplicate</button>', $javascript);
        $this->assertStringContainsString('data-field="font_size"', $javascript);
        $this->assertStringContainsString('data-field="text_color"', $javascript);
        $this->assertStringContainsString('typography-controls', $javascript);
        $this->assertStringContainsString("['heading', 'text', 'quote'].includes(item.type)", $javascript);
        $this->assertStringContainsString('Font size (px)', $javascript);
        $this->assertStringContainsString('data-field="icon_background_transparent"', $javascript);
        $this->assertStringContainsString('Full screen (edge to edge)', $javascript);
        $this->assertStringContainsString('container.columns.forEach', $javascript);
        $this->assertStringContainsString('dataset.deleteGroup', $javascript);
        $this->assertStringContainsString('dataset.deleteColumn', $javascript);
        $this->assertStringContainsString('Delete column group ${groupIndex + 1} and all widgets inside it?', $javascript);
        $this->assertStringContainsString('container.columns.splice(columnIndex, 1)', $javascript);
        $this->assertStringContainsString('tree.groups.splice(groupIndex, 1)', $javascript);
        $this->assertStringNotContainsString('deleteColumnButton.disabled', $javascript);
        $this->assertStringNotContainsString('deleteGroupButton.disabled', $javascript);
        $this->assertStringContainsString('Column gap', $javascript);
        $this->assertStringContainsString('- Last column', $javascript);
        $this->assertStringContainsString('explicitColumnField', $mediaPicker);
        $this->assertStringContainsString('urlInput.value = selected.path', $mediaPicker);

        $publicCss = file_get_contents(public_path('css/website/templates/main.css'));
        $publicTemplate = file_get_contents(resource_path('views/website/templates/main/index.blade.php'));
        $this->assertStringContainsString("['1200px', 'auto', null, null, 'transparent']", $publicTemplate);
        $this->assertStringContainsString('var(--section-content-width, 1200px)', $publicCss);
        $this->assertStringContainsString('[style*="--column-content-width:100%"]', $publicCss);
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
        $this->assertStringContainsString('.content-gallery-slider-viewport', $publicCss);
        $this->assertStringContainsString('minmax(0, 4.25fr)', $publicCss);
        $this->assertStringContainsString('.content-gallery-slider .content-gallery-item.is-active', $publicCss);
        $this->assertStringContainsString('.content-gallery-slider .content-gallery-item.is-prev', $publicCss);
        $this->assertStringContainsString('border-radius: 0 1rem 1rem 0', $publicCss);
        $this->assertStringContainsString('border-radius: 1rem 0 0 1rem', $publicCss);
        $this->assertStringContainsString('height: clamp(20rem, 38vw, 38rem)', $publicCss);
        $this->assertStringContainsString('.content-gallery-caption', $publicCss);

        $publicJavascript = file_get_contents(public_path('js/website/templates/main.js'));
        $this->assertStringContainsString("gallery.querySelector('[data-gallery-prev]')", $publicJavascript);
        $this->assertStringContainsString("slide.setAttribute('aria-hidden'", $publicJavascript);
        $this->assertStringContainsString("slide.classList.remove('is-prev', 'is-active', 'is-next')", $publicJavascript);
        $this->assertStringContainsString('track.append(previousDisplay, activeSlide, nextSlide)', $publicJavascript);
        $this->assertStringContainsString('previousSlide.cloneNode(true)', $publicJavascript);

        $galleryView = file_get_contents(resource_path('views/website/templates/main/_gallery.blade.php'));
        $this->assertStringContainsString('data-gallery-prev', $galleryView);
        $this->assertStringContainsString('data-gallery-next', $galleryView);

        $builderCss = file_get_contents(public_path('css/website-studio/section-builder.css'));
        $this->assertStringContainsString('.typography-controls', $builderCss);
        $this->assertStringContainsString('@container (max-width: 42rem)', $builderCss);
        $this->assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr))', $builderCss);
        $this->assertStringContainsString('.widget-fields > .carousel-editor', $builderCss);
        $this->assertStringContainsString('.card-editor > label:has(textarea)', $builderCss);
        $this->assertStringContainsString('.gallery-image-fields', $builderCss);
        $this->assertStringContainsString('.column-group-shell > .nested-column-group > .nested-group-toolbar', $builderCss);
    }

    public function test_column_background_removal_preserves_unselected_columns_and_reaches_nested_columns(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);
        $role = Role::query()->create(['name' => 'Super Administrator', 'slug' => 'super-administrator']);
        $user->roles()->attach($role);
        $column = fn (string $id): array => ['id' => $id, 'width' => 1, 'background_image' => 'https://example.com/'.$id.'.jpg', 'background_video' => 'https://example.com/'.$id.'.mp4', 'components' => []];
        $outer = $column('remove-image');
        $outer['components'] = [['type' => 'columns', 'columns' => [$column('nested')]]];
        $components = ['type' => 'columns', 'groups' => [['type' => 'columns', 'columns' => [$outer, $column('keep')]]]];
        $this->actingAs($user)->post(route('website-studio.sections.store'), [
            'title' => 'Removal regression',
            'components' => json_encode($components, JSON_THROW_ON_ERROR),
            'remove_column_background_images' => ['remove-image' => '1', 'keep' => '0', 'nested' => '1'],
            'remove_column_background_videos' => ['remove-image' => '0', 'keep' => '0', 'nested' => '1'],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $section = collect(data_get($church->fresh()->settings, 'website.custom_sections'))->firstWhere('title', 'Removal regression');
        $columns = data_get($section, 'components.groups.0.columns');
        $this->assertSame('', $columns[0]['background_image']);
        $this->assertSame('https://example.com/remove-image.mp4', $columns[0]['background_video']);
        $this->assertSame('https://example.com/keep.jpg', $columns[1]['background_image']);
        $this->assertSame('https://example.com/keep.mp4', $columns[1]['background_video']);
        $this->assertSame('', data_get($columns, '0.components.0.columns.0.background_image'));
        $this->assertSame('', data_get($columns, '0.components.0.columns.0.background_video'));
    }

    public function test_heading_text_and_quote_widgets_save_and_render_custom_colours(): void
    {
        $church = Church::factory()->create(['name' => 'Colour Widget Church']);
        $user = User::factory()->create(['church_id' => $church->id]);
        $adminRole = Role::query()->create(['name' => 'Super Administrator', 'slug' => 'super-administrator']);
        $user->roles()->attach($adminRole);

        $this->actingAs($user)->get(route('website-studio.index'))->assertOk();

        $components = [
            'id' => 'root-columns',
            'type' => 'columns',
            'groups' => [[
                'id' => 'group-one',
                'type' => 'columns',
                'columns' => [[
                    'width' => 1,
                    'components' => [
                        ['id' => 'coloured-heading', 'type' => 'heading', 'text' => 'Mission and Vision', 'text_color' => '#F59E0B', 'margin_top' => 0, 'margin_bottom' => 12, 'padding_top' => 6, 'padding_bottom' => 8],
                        ['id' => 'coloured-text', 'type' => 'text', 'text' => 'A visible supporting message.', 'text_color' => '#38BDF8', 'margin_top' => -5, 'padding_bottom' => 999],
                        ['id' => 'coloured-quote', 'type' => 'quote', 'text' => 'Faith makes room for hope.', 'font_size' => 28, 'text_color' => '#A78BFA', 'align' => 'center'],
                    ],
                ]],
            ]],
        ];

        $this->actingAs($user)
            ->post(route('website-studio.sections.store'), [
                'title' => 'Colour controls section',
                'page_slugs' => ['about'],
                'components' => json_encode($components, JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect();

        $section = collect(data_get($church->fresh()->settings, 'website.custom_sections'))->firstWhere('title', 'Colour controls section');
        $savedComponents = data_get($section, 'components.groups.0.columns.0.components');
        $this->assertSame(0, data_get($savedComponents, '0.margin_top'));
        $this->assertSame(12, data_get($savedComponents, '0.margin_bottom'));
        $this->assertSame(6, data_get($savedComponents, '0.padding_top'));
        $this->assertSame(8, data_get($savedComponents, '0.padding_bottom'));
        $this->assertSame(0, data_get($savedComponents, '1.margin_top'));
        $this->assertSame(120, data_get($savedComponents, '1.padding_bottom'));
        $this->assertSame('#F59E0B', data_get($savedComponents, '0.text_color'));
        $this->assertSame('#38BDF8', data_get($savedComponents, '1.text_color'));
        $this->assertSame('#A78BFA', data_get($savedComponents, '2.text_color'));
        $this->assertSame(28, data_get($savedComponents, '2.font_size'));
        $this->assertSame('center', data_get($savedComponents, '2.align'));

        $this->get(route('website.public', ['church' => $church->slug, 'page' => 'about']))
            ->assertOk()
            ->assertSee('Mission and Vision')
            ->assertSee('color:#F59E0B;', false)
            ->assertSee('margin-top:0px;margin-bottom:12px;padding-top:6px;padding-bottom:8px;', false)
            ->assertSee('A visible supporting message.')
            ->assertSee('color:#38BDF8;', false)
            ->assertSee('Faith makes room for hope.')
            ->assertSee('text-align: center;font-size:28px;color:#A78BFA;', false);
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
                            'card_title_color' => '#FACC15',
                            'card_title_size' => 32,
                            'card_button_label' => 'Join our volunteer team',
                            'link' => '/volunteer',
                            'card_button_enabled' => true,
                            'card_button_color' => '#2563EB',
                            'card_background_type' => 'video',
                            'card_button_text_color' => '#FEF08A',
                            'card_description_color' => '#38BDF8',
                            'card_description_size' => 18,
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
                        [
                            'id' => 'gallery-slider',
                            'type' => 'gallery',
                            'style' => 'slider',
                            'images' => [
                                ['id' => 'gallery-one', 'url' => 'https://cdn.example.test/gallery-one.jpg', 'alt' => 'First gallery image', 'title' => 'Invite Your Friends and Family', 'text' => 'Join us for food and fun'],
                                ['id' => 'gallery-two', 'url' => 'https://cdn.example.test/gallery-two.jpg', 'alt' => 'Second gallery image', 'title' => 'Worship With Us', 'text' => 'Everyone is welcome'],
                            ],
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
        $gallerySlider = collect($savedComponents)->firstWhere('id', 'gallery-slider');
        $uploadedCardPath = data_get($uploadedCard, 'background_video');
        $uploadedSliderPath = data_get($slider, 'slides.0.video');

        $this->assertSame($remoteVideo, data_get($linkedCard, 'background_video'));
        $this->assertSame(3, data_get($linkedCard, 'card_border_width'));
        $this->assertSame('#F59E0B', data_get($linkedCard, 'card_border_color'));
        $this->assertSame(42, data_get($linkedCard, 'card_border_radius'));
        $this->assertSame('#FACC15', $linkedCard['card_title_color']);
        $this->assertSame(32, $linkedCard['card_title_size']);
        $this->assertSame('Join our volunteer team', $linkedCard['card_button_label']);
        $this->assertTrue($linkedCard['card_button_enabled']);
        $this->assertSame('#2563EB', $linkedCard['card_button_color']);
        $this->assertSame('video', $linkedCard['card_background_type']);
        $this->assertSame('#FEF08A', $linkedCard['card_button_text_color']);
        $this->assertSame('#38BDF8', $linkedCard['card_description_color']);
        $this->assertSame(18, $linkedCard['card_description_size']);
        $this->assertSame('large', data_get($linkedCard, 'card_shadow'));
        $this->assertSame('top', data_get($savedColumn, 'background_position'));
        $this->assertSame(36, data_get($savedColumn, 'border_radius'));
        $this->assertSame(0, data_get($savedColumn, 'padding'));
        $this->assertSame(0, data_get($section, 'components.groups.0.gap'));
        $this->assertNotEmpty($uploadedCardPath);
        $this->assertNotEmpty($uploadedSliderPath);
        $this->assertSame('Invite Your Friends and Family', data_get($gallerySlider, 'images.0.title'));
        $this->assertSame('Join us for food and fun', data_get($gallerySlider, 'images.0.text'));
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
            ->assertSee('Join our volunteer team')
            ->assertSee('background-color:#2563EB;color:#FEF08A;', false)
            ->assertSee('color:#FACC15;font-size:32px;', false)
            ->assertSee('color:#38BDF8;font-size:18px;', false)
            ->assertSee('--card-border-width: 3px', false)
            ->assertSee('--card-border-color: #F59E0B', false)
            ->assertSee('--card-border-radius:42px', false)
            ->assertSee('--column-border-radius:36px', false)
            ->assertSee('--column-background-position:top', false)
            ->assertSee('--column-group-gap:0px', false)
            ->assertSee('--column-padding:0px', false)
            ->assertSee('data-background-video', false)
            ->assertSee('autoplay muted loop', false)
            ->assertSee('content-gallery-slider-viewport', false)
            ->assertSee('data-gallery-prev', false)
            ->assertSee('data-gallery-next', false)
            ->assertSee('First gallery image')
            ->assertSee('Second gallery image');
        $publicPage->assertSee('Invite Your Friends and Family')
            ->assertSee('Join us for food and fun')
            ->assertSee('Worship With Us')
            ->assertSee('Everyone is welcome');
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
