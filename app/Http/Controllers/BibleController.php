<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BibleHighlight;
use App\Models\BibleNote;
use App\Models\BibleReadingPlan;
use App\Models\BibleTranslation;
use App\Models\BibleVerse;
use App\Support\BibleFreeTranslationInstaller;
use App\Support\BibleTranslationCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class BibleController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeBible($request);
        $this->ensureDefaultBible($request);

        $translations = BibleTranslation::query()
            ->where('church_id', $request->user()->church_id)
            ->where('status', 'active')
            ->orderByDesc('is_default')->orderBy('name')->get();
        $settings = data_get($request->user()->account_settings, 'bible', []);
        $preferredTranslation = $request->query('translation') ?: data_get($settings, 'translation_id');
        $translation = $translations->firstWhere('abbreviation', strtoupper((string) $preferredTranslation)) ?? $translations->firstWhere('id', (int) $preferredTranslation) ?? $translations->first();
        $book = (string) $request->query('book', 'John');
        $chapter = max(1, (int) $request->query('chapter', 3));
        $verses = $translation?->verses()->where('book_slug', Str::slug($book))->where('chapter', $chapter)->orderBy('verse')->get() ?? collect();
        $books = $translation?->verses()->select('book')->distinct()->orderBy('book')->pluck('book') ?? collect();
        $dailyVerse = $translation?->verses()->orderBy('id')->first();
        $recentNote = BibleNote::query()->where('user_id', $request->user()->id)->with('translation')->latest('updated_at')->first();
        $recentHighlights = BibleHighlight::query()->where('user_id', $request->user()->id)->with('translation')->latest()->limit(3)->get();
        $activePlan = BibleReadingPlan::query()->with(['users' => fn ($query) => $query->whereKey($request->user()->id)])->whereHas('users', fn ($query) => $query->whereKey($request->user()->id)->whereNull('completed_at'))->latest('updated_at')->first();

        return view('bible.index', [
            'translation' => $translation,
            'translations' => $translations,
            'book' => $book,
            'chapter' => $chapter,
            'verses' => $verses,
            'books' => $books,
            'dailyVerse' => $dailyVerse,
            'recentNote' => $recentNote,
            'recentHighlights' => $recentHighlights,
            'activePlan' => $activePlan,
            'settings' => $settings,
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Bible', 'url' => null]],
        ]);
    }

    public function placeholder(Request $request): View
    {
        $this->authorizeBible($request);

        return view('bible.placeholder', [
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Bible', 'url' => route('bible.index')], ['label' => (string) $request->route('page'), 'url' => null]],
            'page' => (string) $request->route('page'),
        ]);
    }

    public function search(Request $request): View
    {
        $this->authorizeBible($request);
        $this->ensureDefaultBible($request);
        $query = trim((string) $request->query('q', ''));
        $filters = ['content' => (string) $request->query('content', ''), 'translation_id' => (string) $request->query('translation_id', ''), 'testament' => (string) $request->query('testament', ''), 'book' => (string) $request->query('book', '')];
        $translations = $this->visibleTranslations($request);
        $verseQuery = BibleVerse::query()->with('translation')->whereIn('bible_translation_id', $translations->pluck('id'))->when($query !== '', fn ($builder) => $builder->where('text', 'like', '%'.$query.'%'));
        if ($filters['translation_id'] !== '') {
            $verseQuery->where('bible_translation_id', $filters['translation_id']);
        }
        if ($filters['testament'] !== '') {
            $verseQuery->where('testament', $filters['testament']);
        }
        if ($filters['book'] !== '') {
            $verseQuery->where('book_slug', Str::slug($filters['book']));
        }
        $verses = $query === '' ? collect() : $verseQuery->limit(25)->get();
        $notes = $query === '' ? collect() : BibleNote::query()->where('user_id', $request->user()->id)->where(fn ($builder) => $builder->where('title', 'like', '%'.$query.'%')->orWhere('body', 'like', '%'.$query.'%'))->latest()->limit(10)->get();
        if ($filters['content'] === 'verses') {
            $notes = collect();
        }
        if ($filters['content'] === 'notes') {
            $verses = collect();
        }
        $books = BibleVerse::query()->whereIn('bible_translation_id', $translations->pluck('id'))->select('book')->distinct()->orderBy('book')->pluck('book');
        $recentSearches = collect($request->session()->get('bible_recent_searches', []))->take(5);
        if ($query !== '') {
            $recentSearches = collect([$query])->merge($recentSearches->reject(fn ($recent): bool => $recent === $query))->take(5);
            $request->session()->put('bible_recent_searches', $recentSearches->all());
        }

        return view('bible.search', compact('query', 'verses', 'notes', 'translations', 'books', 'filters', 'recentSearches') + ['breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Bible', 'url' => route('bible.index')], ['label' => 'Search', 'url' => null]]]);
    }

    public function compare(Request $request): View
    {
        $this->authorizeBible($request);
        $this->ensureDefaultBible($request);
        $translations = $this->visibleTranslations($request);
        $book = (string) $request->query('book', 'John');
        $chapter = max(1, (int) $request->query('chapter', 3));
        $verse = max(1, (int) $request->query('verse', 16));
        $verses = BibleVerse::query()->with('translation')->whereIn('bible_translation_id', $translations->pluck('id'))->where('book_slug', Str::slug($book))->where('chapter', $chapter)->where('verse', $verse)->get()->keyBy('bible_translation_id');

        return view('bible.compare', compact('translations', 'verses', 'book', 'chapter', 'verse') + ['breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Bible', 'url' => route('bible.index')], ['label' => 'Verse Comparison', 'url' => null]]]);
    }

    public function settings(Request $request): View
    {
        $this->authorizeBible($request);
        $this->ensureDefaultBible($request);
        $settings = data_get($request->user()->account_settings, 'bible', []);
        $translations = $this->visibleTranslations($request);

        return view('bible.settings', compact('settings', 'translations') + ['breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Bible', 'url' => route('bible.index')], ['label' => 'Settings', 'url' => null]]]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorizeBible($request);
        $data = $request->validate(['translation_id' => ['nullable', Rule::exists('bible_translations', 'id')->where('church_id', $request->user()->church_id)], 'book_view' => ['required', 'in:single,two'], 'font_size' => ['required', 'integer', 'min:12', 'max:28'], 'line_spacing' => ['required', 'in:compact,comfortable,spacious'], 'dark_mode' => ['nullable', 'boolean'], 'verse_of_day' => ['nullable', 'boolean'], 'reading_reminders' => ['nullable', 'boolean'], 'reading_reminder_time' => ['required', 'date_format:H:i'], 'reading_plan_notifications' => ['nullable', 'boolean'], 'autoplay_audio' => ['nullable', 'boolean'], 'open_last_read' => ['nullable', 'boolean'], 'offline_sync' => ['required', 'in:wifi,any'], 'highlight_color' => ['required', 'in:yellow,green,purple,pink,blue'], 'private_notes' => ['nullable', 'boolean']]);
        foreach (['dark_mode', 'verse_of_day', 'reading_reminders', 'reading_plan_notifications', 'autoplay_audio', 'open_last_read', 'private_notes'] as $toggle) {
            $data[$toggle] = (bool) ($data[$toggle] ?? false);
        }
        $account = $request->user()->account_settings ?? [];
        $account['bible'] = array_merge($account['bible'] ?? [], $data);
        $request->user()->forceFill(['account_settings' => $account])->save();

        return back()->with('status', 'Bible preferences saved.');
    }

    private function visibleTranslations(Request $request)
    {
        return BibleTranslation::query()->where('church_id', $request->user()->church_id)->where('status', 'active')->orderByDesc('is_default')->orderBy('name')->get();
    }

    private function authorizeBible(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('use bible'), 403);
    }

    private function ensureDefaultBible(Request $request): void
    {
        BibleTranslationCatalog::ensureFreeDefaults();
        $catalog = BibleTranslation::query()->whereNull('church_id')->where('abbreviation', 'KJV')->firstOrFail();
        $translation = BibleTranslation::query()->firstOrCreate(
            ['church_id' => $request->user()->church_id, 'abbreviation' => 'KJV'],
            $catalog->only(['name', 'abbreviation', 'language', 'description', 'copyright', 'source_url', 'status']) + ['created_by' => $request->user()->id, 'is_default' => true],
        );

        if ($translation->verses()->exists()) {
            return;
        }

        if (is_file(storage_path('app/private/bible/free/KJV.txt'))) {
            BibleFreeTranslationInstaller::install($translation);

            return;
        }

        $verses = [
            [1, 'There was a man of the Pharisees, named Nicodemus, a ruler of the Jews:'],
            [2, 'The same came to Jesus by night, and said unto him, Rabbi, we know that thou art a teacher come from God: for no man can do these miracles that thou doest, except God be with him.'],
            [3, 'Jesus answered and said unto him, Verily, verily, I say unto thee, Except a man be born again, he cannot see the kingdom of God.'],
            [4, 'Nicodemus saith unto him, How can a man be born when he is old?'],
            [5, 'Jesus answered, Verily, verily, I say unto thee, Except a man be born of water and of the Spirit, he cannot enter into the kingdom of God.'],
            [6, 'That which is born of the flesh is flesh; and that which is born of the Spirit is spirit.'],
            [7, 'Marvel not that I said unto thee, Ye must be born again.'],
            [8, 'The wind bloweth where it listeth, and thou hearest the sound thereof, but canst not tell whence it cometh, and whither it goeth.'],
            [9, 'Nicodemus answered and said unto him, How can these things be?'],
            [10, 'Jesus answered and said unto him, Art thou a master of Israel, and knowest not these things?'],
        ];

        $translation->verses()->createMany(collect($verses)->map(fn (array $verse): array => ['book' => 'John', 'book_slug' => 'john', 'testament' => 'new', 'chapter' => 3, 'verse' => $verse[0], 'text' => $verse[1]])->all());
    }
}
