<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BibleReadingPlan;
use App\Models\BibleTranslation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class BibleModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_read_the_bible(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $response = $this->actingAs($user)
            ->get(route('bible.index'))
            ->assertOk()
            ->assertSee('Bible', false)
            ->assertSee('Jesus answered and said unto him', false)
            ->assertSee('King James Version', false)
            ->assertSee('id="bible-translation"', false)
            ->assertSee('id="bible-book"', false)
            ->assertSee('id="bible-chapter"', false)
            ->assertSee(':aria-pressed="isSelected(', false)
            ->assertDontSee('grid size-5 shrink-0 place-items-center rounded border', false)
            ->assertSee('data-lucide="bookmark-plus"', false)
            ->assertSee('data-lucide="notebook-pen"', false)
            ->assertSee('data-lucide="highlighter"', false);
        $response->assertSee('bible/compare?book=John&amp;chapter=3&amp;verse=1', false);
        $response->assertSee('bible/search?q=John&amp;tool=commentaries&amp;book=John&amp;chapter=3', false);
        $response->assertSee('bible/search?q=John&amp;tool=dictionaries&amp;book=John&amp;chapter=3', false);
        $translation = BibleTranslation::query()->where('church_id', $user->church_id)->where('abbreviation', 'KJV')->firstOrFail();
        $this->actingAs($user)->post(route('bible.bookmarks.store'), ['translation_id' => $translation->id, 'reference' => 'John 3:1', 'book' => 'John', 'chapter' => 3, 'verse' => 1, 'preview' => 'There was a man of the Pharisees.'])->assertRedirect();
        $this->actingAs($user)->post(route('bible.notes.store'), ['translation_id' => $translation->id, 'reference' => 'John 3:1', 'title' => 'Reader note', 'body' => 'A note saved from the reader.'])->assertRedirect();
        $this->actingAs($user)->post(route('bible.highlights.store'), ['translation_id' => $translation->id, 'reference' => 'John 3:1', 'snippet' => 'There was a man of the Pharisees.', 'color' => 'yellow', 'meaning' => 'Study'])->assertRedirect();
        $this->actingAs($user)->get(route('bible.index'))->assertOk()->assertSee('bg-yellow-100 text-yellow-950', false)->assertSee('border-yellow-400 bg-yellow-50/40', false);
        $this->actingAs($user)->get(route('bible.highlights', ['book' => 'John', 'date' => 'year']))
            ->assertOk()
            ->assertSee('Highlights by Color', false)
            ->assertSee('Create Collection', false)
            ->assertSee('All Dates', false)
            ->assertSee('There was a man of the Pharisees.', false);
        $this->assertDatabaseHas('bible_bookmarks', ['user_id' => $user->id, 'reference' => 'John 3:1']);
        $this->assertDatabaseHas('bible_notes', ['user_id' => $user->id, 'reference' => 'John 3:1']);
        $this->assertDatabaseHas('bible_highlights', ['user_id' => $user->id, 'reference' => 'John 3:1']);
    }

    public function test_search_and_compare_reference_selectors_load_valid_chapters_and_verses(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($user)
            ->getJson(route('bible.reference-options', ['book' => 'John', 'chapter' => 3]))
            ->assertOk()
            ->assertJsonPath('book', 'John')
            ->assertJsonPath('chapter', 3)
            ->assertJsonPath('chapters.2', 3)
            ->assertJsonPath('verses.0', 1);

        $this->actingAs($user)
            ->get(route('bible.search', ['book' => 'John', 'chapter' => 3, 'verse' => 1]))
            ->assertOk()
            ->assertSee('id="search-reference-picker"', false)
            ->assertSee('data-bible-book', false)
            ->assertSee('data-bible-chapter', false)
            ->assertSee('data-bible-verse', false)
            ->assertSee('John 3:1', false);

        $this->actingAs($user)
            ->get(route('bible.compare', ['book' => 'John', 'chapter' => 3, 'verse' => 1]))
            ->assertOk()
            ->assertSee('id="compare-reference-picker"', false)
            ->assertSee('<option value="1" selected>Verse 1</option>', false);
    }

    public function test_church_administrator_can_add_a_translation(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($user)
            ->post(route('bible.translations.store'), [
                'name' => 'English Standard Version',
                'abbreviation' => 'esv',
                'language' => 'English',
                'copyright' => 'Used by permission',
                'source_url' => 'https://example.com/esv',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bible_translations', [
            'church_id' => $user->church_id,
            'abbreviation' => 'ESV',
            'name' => 'English Standard Version',
        ]);
    }

    public function test_only_authorized_administrators_can_create_church_bible_plans(): void
    {
        Storage::fake('public');
        $this->seed();
        $administrator = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        $this->actingAs($administrator)
            ->post(route('bible.admin.plans.store'), [
                'name' => 'Unsafe Cover Plan',
                'description' => 'This plan must reject a non-image cover.',
                'category' => 'Topical',
                'schedule' => 'Day one | John 1',
                'image' => UploadedFile::fake()->createWithContent('cover.svg', '<svg><script>alert(1)</script></svg>'),
            ])
            ->assertSessionHasErrors('image');
        $this->assertDatabaseMissing('bible_reading_plans', ['name' => 'Unsafe Cover Plan']);

        $this->actingAs($administrator)
            ->get(route('bible.admin.plans.index'))
            ->assertOk()
            ->assertSee('Manage Reading Plans', false)
            ->assertSee('New Reading Plan', false);

        $this->actingAs($administrator)
            ->post(route('bible.admin.plans.store'), [
                'name' => 'Two Days in Psalms',
                'description' => 'Read and reflect on selected Psalms for two days.',
                'category' => 'Psalms',
                'schedule' => "Songs of trust | Psalms 1-2 | Trust God in every season.\nSongs of worship | Psalms 3-4 | Respond to God with worship.",
                'is_recommended' => 1,
                'image' => UploadedFile::fake()->image('psalms.jpg', 1280, 720),
            ])
            ->assertRedirect(route('bible.admin.plans.index'));

        $this->assertDatabaseHas('bible_reading_plans', [
            'church_id' => $administrator->church_id,
            'name' => 'Two Days in Psalms',
            'duration_days' => 2,
            'is_recommended' => true,
        ]);
        $plan = BibleReadingPlan::query()->where('name', 'Two Days in Psalms')->firstOrFail();
        $this->assertIsString($plan->image_path);
        $this->assertStringStartsWith('bible/plans/', $plan->image_path);
        Storage::disk('public')->assertExists($plan->image_path);
        $originalImagePath = $plan->image_path;
        $this->assertDatabaseHas('bible_reading_plan_days', ['bible_reading_plan_id' => $plan->id, 'day_number' => 1, 'passages' => 'Psalms 1-2']);
        $this->assertDatabaseHas('bible_reading_plan_days', ['bible_reading_plan_id' => $plan->id, 'day_number' => 2, 'passages' => 'Psalms 3-4']);
        $this->actingAs($administrator)->put(route('bible.admin.plans.update', $plan), [
            'name' => 'Two Days in Psalms',
            'description' => 'An updated two-day journey through selected Psalms.',
            'category' => 'Psalms',
            'schedule' => "Trust | Psalms 1-2 | Trust in the Lord.\nWorship | Psalms 3-4 | Worship the Lord.",
            'is_recommended' => 1,
            'image' => UploadedFile::fake()->image('updated-psalms.webp', 1280, 720),
        ])->assertRedirect(route('bible.admin.plans.index'));
        $plan->refresh();
        $this->assertNotSame($originalImagePath, $plan->image_path);
        Storage::disk('public')->assertMissing($originalImagePath);
        Storage::disk('public')->assertExists($plan->image_path);
        $this->actingAs($administrator)
            ->get(route('bible.admin.plans.index'))
            ->assertOk()
            ->assertSee('storage/'.$plan->image_path, false)
            ->assertSee('data-plan-image-preview="create"', false)
            ->assertSee('data-plan-image-preview="edit"', false)
            ->assertSee('x-data="planImagePreview"', false)
            ->assertSee(':src="previewUrl"', false)
            ->assertSee('Clear selected image', false);
        $this->assertDatabaseHas('bible_reading_plan_days', ['bible_reading_plan_id' => $plan->id, 'day_number' => 2, 'title' => 'Worship', 'reflection' => 'Worship the Lord.']);

        $this->actingAs($administrator)->post(route('bible.admin.plans.store'), [
            'name' => 'Temporary Plan',
            'description' => 'A plan created to verify deletion.',
            'category' => 'Topical',
            'schedule' => 'Only day | John 1',
            'image' => UploadedFile::fake()->image('temporary.png', 640, 360),
        ])->assertRedirect(route('bible.admin.plans.index'));
        $temporaryPlan = BibleReadingPlan::query()->where('name', 'Temporary Plan')->firstOrFail();
        $temporaryImagePath = $temporaryPlan->image_path;
        Storage::disk('public')->assertExists($temporaryImagePath);
        $this->actingAs($administrator)->delete(route('bible.admin.plans.destroy', $temporaryPlan))->assertRedirect(route('bible.admin.plans.index'));
        $this->assertDatabaseMissing('bible_reading_plans', ['id' => $temporaryPlan->id]);
        Storage::disk('public')->assertMissing($temporaryImagePath);

        $viewer = User::factory()->create(['church_id' => $administrator->church_id, 'status' => 'active']);
        $viewer->roles()->sync([Role::query()->where('name', 'Viewer')->firstOrFail()->id]);

        $this->actingAs($viewer)->get(route('bible.plans'))->assertOk()->assertSee('Two Days in Psalms', false)->assertSee('storage/'.$plan->image_path, false)->assertDontSee('Manage Plans', false);
        $this->actingAs($viewer)->get(route('bible.admin.plans.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('bible.admin.plans.store'), [
            'name' => 'Unauthorized Plan',
            'description' => 'This should not be created.',
            'category' => 'Topical',
            'schedule' => 'Day one | John 1',
        ])->assertForbidden();

        $this->assertDatabaseMissing('bible_reading_plans', ['name' => 'Unauthorized Plan']);

        $this->actingAs($viewer)->post(route('bible.plans.start', $plan))->assertRedirect();
        $this->actingAs($viewer)->post(route('bible.plans.complete-day', $plan), ['day' => 1])->assertRedirect();
        $this->assertDatabaseHas('bible_reading_plan_user', ['bible_reading_plan_id' => $plan->id, 'user_id' => $viewer->id, 'current_day' => 2, 'current_streak' => 1, 'completed_at' => null]);
        $this->actingAs($viewer)->get(route('bible.plans'))->assertOk()->assertSee('Psalms 3-4', false);
        $this->actingAs($viewer)->post(route('bible.plans.complete-day', $plan), ['day' => 2])->assertRedirect();
        $this->assertDatabaseHas('bible_reading_plan_user', ['bible_reading_plan_id' => $plan->id, 'user_id' => $viewer->id, 'current_day' => 2, 'current_streak' => 1]);
        $this->assertNotNull(DB::table('bible_reading_plan_user')->where('bible_reading_plan_id', $plan->id)->where('user_id', $viewer->id)->value('completed_at'));
        $this->actingAs($viewer)
            ->get(route('bible.plans'))
            ->assertOk()
            ->assertSee('Completed Plans', false)
            ->assertSee('Two Days in Psalms', false)
            ->assertSee('1 completed', false)
            ->assertSee('data-lucide="trophy"', false);
    }

    public function test_authenticated_users_can_use_bible_but_cannot_access_management_pages_without_permission(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $user = User::factory()->create(['church_id' => $admin->church_id, 'status' => 'active']);

        foreach (['bible.index', 'bible.plans', 'bible.bookmarks', 'bible.notes', 'bible.highlights', 'bible.search', 'bible.compare', 'bible.settings'] as $routeName) {
            $this->actingAs($user)->get(route($routeName))->assertOk();
        }

        $this->actingAs($user)->get(route('bible.index'))
            ->assertOk()
            ->assertSee(route('bible.plans'), false)
            ->assertDontSee(route('bible.admin.plans.index'), false)
            ->assertDontSee(route('bible.translations.index'), false);
        $this->actingAs($user)->get(route('bible.admin.plans.index'))->assertForbidden();
        $this->actingAs($user)->get(route('bible.translations.index'))->assertForbidden();
        $this->actingAs($user)->post(route('bible.admin.plans.store'), [
            'name' => 'Unauthorized Plan',
            'description' => 'This plan must not be created.',
            'category' => 'Topical',
            'schedule' => 'Day one | John 1',
        ])->assertForbidden();
        $this->actingAs($user)->post(route('bible.translations.store'), [
            'name' => 'Unauthorized Translation',
            'abbreviation' => 'NOPE',
            'language' => 'English',
            'copyright' => 'Not permitted',
        ])->assertForbidden();
        $this->assertDatabaseMissing('bible_reading_plans', ['name' => 'Unauthorized Plan']);
        $this->assertDatabaseMissing('bible_translations', ['abbreviation' => 'NOPE']);
    }

    public function test_bible_study_pages_and_translation_import_are_available(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $this->actingAs($user)->get(route('bible.plans'))->assertOk()->assertSee('Reading Plans', false);
        $this->actingAs($user)->get(route('bible.bookmarks'))->assertOk();
        $this->actingAs($user)->get(route('bible.notes'))->assertOk();
        $this->actingAs($user)->get(route('bible.highlights'))->assertOk();
        $this->actingAs($user)->get(route('bible.search', ['q' => 'Nicodemus']))->assertOk()->assertSee('Nicodemus', false);
        $this->actingAs($user)->get(route('bible.translations.index'))
            ->assertOk()
            ->assertSee('Berean Standard Bible', false)
            ->assertSee('World English Bible Updated', false)
            ->assertSee('World English Bible Protestant Edition', false)
            ->assertSee('Noah Webster Bible', false)
            ->assertSee('World Messianic Bible', false);
        $freeTranslation = BibleTranslation::query()->whereNull('church_id')->where('abbreviation', 'BSB')->firstOrFail();
        $this->actingAs($user)->post(route('bible.translations.install', $freeTranslation))->assertRedirect();
        $installedTranslation = BibleTranslation::query()->where('church_id', $user->church_id)->where('abbreviation', 'BSB')->firstOrFail();
        $this->assertGreaterThan(30_000, $installedTranslation->verses()->count());
        $this->actingAs($user)->get(route('bible.compare'))
            ->assertOk()
            ->assertSee('Verse Comparison', false)
            ->assertSee('Highlight Differences', false)
            ->assertSee('role="switch"', false)
            ->assertSee('data-lucide="highlighter"', false)
            ->assertSee('Baseline', false)
            ->assertSee('Different wording', false);
        $this->actingAs($user)->get(route('bible.settings'))->assertOk()->assertSee('Bible Settings', false);

        $translation = BibleTranslation::create(['church_id' => $user->church_id, 'created_by' => $user->id, 'name' => 'Test Version', 'abbreviation' => 'TST', 'language' => 'English', 'status' => 'active']);
        $this->actingAs($user)->post(route('bible.translations.import', $translation), ['file' => UploadedFile::fake()->createWithContent('verses.csv', "book,chapter,verse,text\nJohn,3,16,For God so loved the world.")])->assertRedirect();
        $this->actingAs($user)->post(route('bible.translations.import', $translation), ['file' => UploadedFile::fake()->createWithContent('verses.json', json_encode([['book' => 'John', 'chapter' => 3, 'verse' => 17, 'content' => 'God sent his Son into the world.']], JSON_THROW_ON_ERROR))])->assertRedirect();
        $this->assertDatabaseHas('bible_verses', ['bible_translation_id' => $translation->id, 'verse' => 16]);
        $this->assertDatabaseHas('bible_verses', ['bible_translation_id' => $translation->id, 'verse' => 17, 'testament' => 'new']);
    }

    public function test_every_bible_page_has_the_shared_section_navigation(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $pages = [
            route('bible.index'),
            route('bible.plans'),
            route('bible.admin.plans.index'),
            route('bible.bookmarks'),
            route('bible.notes'),
            route('bible.highlights'),
            route('bible.search'),
            route('bible.compare'),
            route('bible.settings'),
            route('bible.translations.index'),
        ];

        foreach ($pages as $page) {
            $response = $this->actingAs($user)
                ->get($page)
                ->assertOk()
                ->assertSee('aria-label="Bible sections"', false);

            foreach (['bible.index', 'bible.plans', 'bible.bookmarks', 'bible.notes', 'bible.highlights'] as $tabRoute) {
                $response->assertSee(route($tabRoute), false);
            }
        }
    }

    public function test_bible_settings_are_saved_and_applied_to_the_reader(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $this->actingAs($user)->get(route('bible.settings'))->assertOk();
        $translation = BibleTranslation::query()->where('church_id', $user->church_id)->where('abbreviation', 'KJV')->firstOrFail();

        $this->actingAs($user)->put(route('bible.settings.update'), [
            'translation_id' => $translation->id,
            'book_view' => 'two',
            'font_size' => 20,
            'line_spacing' => 'spacious',
            'dark_mode' => 1,
            'verse_of_day' => 0,
            'reading_reminders' => 1,
            'reading_reminder_time' => '18:00',
            'reading_plan_notifications' => 0,
            'autoplay_audio' => 1,
            'open_last_read' => 0,
            'offline_sync' => 'any',
            'highlight_color' => 'purple',
            'private_notes' => 1,
        ])->assertRedirect();

        $this->assertSame(20, data_get($user->fresh()->account_settings, 'bible.font_size'));
        $this->assertSame('spacious', data_get($user->fresh()->account_settings, 'bible.line_spacing'));
        $this->actingAs($user)->get(route('bible.index'))->assertOk()->assertSee('font-size: 20px', false)->assertSee('line-height: 2.25', false);
    }
}
