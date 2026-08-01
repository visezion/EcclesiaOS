<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BibleTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
            ->assertSee('King James Version', false);
        $response->assertSee('bible/compare?book=John&amp;chapter=3&amp;verse=1', false);
        $response->assertSee('bible/search?q=John&amp;tool=commentaries&amp;book=John&amp;chapter=3', false);
        $response->assertSee('bible/search?q=John&amp;tool=dictionaries&amp;book=John&amp;chapter=3', false);
        $translation = BibleTranslation::query()->where('church_id', $user->church_id)->where('abbreviation', 'KJV')->firstOrFail();
        $this->actingAs($user)->post(route('bible.bookmarks.store'), ['translation_id' => $translation->id, 'reference' => 'John 3:1', 'book' => 'John', 'chapter' => 3, 'verse' => 1, 'preview' => 'There was a man of the Pharisees.'])->assertRedirect();
        $this->actingAs($user)->post(route('bible.notes.store'), ['translation_id' => $translation->id, 'reference' => 'John 3:1', 'title' => 'Reader note', 'body' => 'A note saved from the reader.'])->assertRedirect();
        $this->actingAs($user)->post(route('bible.highlights.store'), ['translation_id' => $translation->id, 'reference' => 'John 3:1', 'snippet' => 'There was a man of the Pharisees.', 'color' => 'yellow', 'meaning' => 'Study'])->assertRedirect();
        $this->assertDatabaseHas('bible_bookmarks', ['user_id' => $user->id, 'reference' => 'John 3:1']);
        $this->assertDatabaseHas('bible_notes', ['user_id' => $user->id, 'reference' => 'John 3:1']);
        $this->assertDatabaseHas('bible_highlights', ['user_id' => $user->id, 'reference' => 'John 3:1']);
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

    public function test_user_without_bible_permission_cannot_read_or_manage_translations(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $user = User::factory()->create(['church_id' => $admin->church_id, 'status' => 'active']);

        $this->actingAs($user)->get(route('bible.index'))->assertForbidden();
        $this->actingAs($user)->get(route('bible.translations.index'))->assertForbidden();
        $this->assertCount(0, BibleTranslation::query()->get());
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
        $this->actingAs($user)->get(route('bible.translations.index'))->assertOk()->assertSee('American Standard Version', false);
        $freeTranslation = BibleTranslation::query()->whereNull('church_id')->where('abbreviation', 'ASV')->firstOrFail();
        $this->actingAs($user)->post(route('bible.translations.install', $freeTranslation))->assertRedirect();
        $this->assertDatabaseHas('bible_translations', ['church_id' => $user->church_id, 'abbreviation' => 'ASV']);
        $this->actingAs($user)->get(route('bible.compare'))->assertOk()->assertSee('Verse Comparison', false);
        $this->actingAs($user)->get(route('bible.settings'))->assertOk()->assertSee('Bible Settings', false);

        $translation = BibleTranslation::create(['church_id' => $user->church_id, 'created_by' => $user->id, 'name' => 'Test Version', 'abbreviation' => 'TST', 'language' => 'English', 'status' => 'active']);
        $this->actingAs($user)->post(route('bible.translations.import', $translation), ['file' => UploadedFile::fake()->createWithContent('verses.csv', "book,chapter,verse,text\nJohn,3,16,For God so loved the world.")])->assertRedirect();
        $this->assertDatabaseHas('bible_verses', ['bible_translation_id' => $translation->id, 'verse' => 16]);
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
