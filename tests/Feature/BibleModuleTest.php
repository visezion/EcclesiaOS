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

        $this->actingAs($user)
            ->get(route('bible.index'))
            ->assertOk()
            ->assertSee('Bible', false)
            ->assertSee('Jesus answered and said unto him', false)
            ->assertSee('King James Version', false);
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
        $this->actingAs($user)->get(route('bible.compare'))->assertOk()->assertSee('Verse Comparison', false);
        $this->actingAs($user)->get(route('bible.settings'))->assertOk()->assertSee('Bible Settings', false);

        $translation = BibleTranslation::create(['church_id' => $user->church_id, 'created_by' => $user->id, 'name' => 'Test Version', 'abbreviation' => 'TST', 'language' => 'English', 'status' => 'active']);
        $this->actingAs($user)->post(route('bible.translations.import', $translation), ['file' => UploadedFile::fake()->createWithContent('verses.csv', "book,chapter,verse,text\nJohn,3,16,For God so loved the world." )])->assertRedirect();
        $this->assertDatabaseHas('bible_verses', ['bible_translation_id' => $translation->id, 'verse' => 16]);
    }
}
