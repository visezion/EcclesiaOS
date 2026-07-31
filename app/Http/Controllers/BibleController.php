<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BibleTranslation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class BibleController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeBible($request);
        $this->ensureDefaultBible($request);

        $translations = BibleTranslation::query()
            ->where(fn ($query) => $query->whereNull('church_id')->orWhere('church_id', $request->user()->church_id))
            ->where('status', 'active')
            ->orderByDesc('is_default')->orderBy('name')->get();
        $translation = $translations->firstWhere('abbreviation', strtoupper((string) $request->query('translation'))) ?? $translations->first();
        $book = (string) $request->query('book', 'John');
        $chapter = max(1, (int) $request->query('chapter', 3));
        $verses = $translation?->verses()->where('book_slug', Str::slug($book))->where('chapter', $chapter)->orderBy('verse')->get() ?? collect();

        return view('bible.index', [
            'translation' => $translation,
            'translations' => $translations,
            'book' => $book,
            'chapter' => $chapter,
            'verses' => $verses,
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

    private function authorizeBible(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('use bible'), 403);
    }

    private function ensureDefaultBible(Request $request): void
    {
        $translation = BibleTranslation::query()->firstOrCreate(
            ['church_id' => null, 'abbreviation' => 'KJV'],
            ['name' => 'King James Version', 'language' => 'English', 'description' => 'The public-domain King James Version.', 'copyright' => 'Public domain', 'status' => 'active', 'is_default' => true],
        );

        if ($translation->verses()->exists()) {
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
