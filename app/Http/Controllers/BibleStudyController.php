<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BibleBookmark;
use App\Models\BibleHighlight;
use App\Models\BibleNote;
use App\Models\BibleReadingPlan;
use App\Models\BibleTranslation;
use App\Support\BibleReadingPlanDefaults;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BibleStudyController extends Controller
{
    public function plans(Request $request): View
    {
        $this->authorizeBible($request);
        $this->seedPlans($request);
        BibleReadingPlanDefaults::ensure();
        $filters = ['q' => trim((string) $request->query('q', '')), 'category' => (string) $request->query('category', ''), 'status' => (string) $request->query('status', ''), 'duration' => (string) $request->query('duration', '')];
        $query = BibleReadingPlan::query()
            ->where(fn ($scope) => $scope->whereNull('church_id')->orWhere('church_id', $request->user()->church_id))
            ->with(['days', 'users' => fn ($q) => $q->whereKey($request->user()->id)]);
        if ($filters['q'] !== '') {
            $query->where(fn ($builder) => $builder->where('name', 'like', '%'.$filters['q'].'%')->orWhere('description', 'like', '%'.$filters['q'].'%'));
        }
        if ($filters['category'] !== '') {
            $query->where('category', $filters['category']);
        }
        if ($filters['duration'] !== '') {
            $query->where('duration_days', '<=', (int) $filters['duration']);
        }
        if ($filters['status'] === 'active') {
            $query->whereHas('users', fn ($builder) => $builder->whereKey($request->user()->id)->whereNull('completed_at'));
        }
        if ($filters['status'] === 'completed') {
            $query->whereHas('users', fn ($builder) => $builder->whereKey($request->user()->id)->whereNotNull('completed_at'));
        }
        $plans = $query->orderByDesc('is_recommended')->orderBy('name')->get();
        $enrolledPlans = $plans->filter(fn ($plan): bool => $plan->users->isNotEmpty())->values();
        $activePlans = $enrolledPlans->filter(fn ($plan): bool => blank($plan->users->first()?->pivot?->completed_at))->values();
        $recommendedPlans = $plans->where('is_recommended', true)->values();
        $availablePlans = $plans->filter(fn ($plan): bool => $plan->users->isEmpty())->sortByDesc('is_recommended')->values();
        $completedPlans = $enrolledPlans->filter(fn ($plan): bool => filled($plan->users->first()?->pivot?->completed_at))->values();
        $categories = BibleReadingPlan::query()
            ->where(fn ($scope) => $scope->whereNull('church_id')->orWhere('church_id', $request->user()->church_id))
            ->select('category')
            ->selectRaw('count(*) as total')
            ->groupBy('category')
            ->orderBy('category')
            ->get();
        $canManagePlans = $request->user()->isSuperAdministrator() || $request->user()->hasPermission('manage bible plans');
        $todayPlan = $activePlans->first();
        $todayReading = $todayPlan?->days->firstWhere('day_number', (int) ($todayPlan->users->first()?->pivot?->current_day ?? 1));

        return view('bible.plans', compact('plans', 'enrolledPlans', 'activePlans', 'recommendedPlans', 'availablePlans', 'completedPlans', 'categories', 'filters', 'canManagePlans', 'todayPlan', 'todayReading') + ['breadcrumbs' => $this->crumbs('Reading Plans')]);
    }

    public function bookmarks(Request $request): View
    {
        $this->authorizeBible($request);
        $filters = ['q' => trim((string) $request->query('q', '')), 'book' => (string) $request->query('book', ''), 'tag' => (string) $request->query('tag', ''), 'translation_id' => (string) $request->query('translation_id', '')];
        $query = BibleBookmark::query()->where('user_id', $request->user()->id)->with('translation');
        if ($filters['q'] !== '') {
            $query->where(fn ($builder) => $builder->where('reference', 'like', '%'.$filters['q'].'%')->orWhere('preview', 'like', '%'.$filters['q'].'%'));
        }
        if ($filters['book'] !== '') {
            $query->where('book', $filters['book']);
        }
        if ($filters['tag'] !== '') {
            $query->whereJsonContains('tags', $filters['tag']);
        }
        if ($filters['translation_id'] !== '') {
            $query->where('bible_translation_id', $filters['translation_id']);
        }
        $bookmarks = $query->latest()->paginate(5)->withQueryString();
        $translations = BibleTranslation::query()->where('church_id', $request->user()->church_id)->where('status', 'active')->orderBy('name')->get();
        $books = BibleBookmark::query()->where('user_id', $request->user()->id)->select('book')->distinct()->orderBy('book')->pluck('book');
        $tags = BibleBookmark::query()->where('user_id', $request->user()->id)->pluck('tags')->flatten()->filter()->unique()->sort()->values();

        return view('bible.bookmarks', compact('bookmarks', 'translations', 'books', 'tags', 'filters') + ['breadcrumbs' => $this->crumbs('Bookmarks')]);
    }

    public function notes(Request $request): View
    {
        $this->authorizeBible($request);
        $filters = ['q' => trim((string) $request->query('q', '')), 'book' => (string) $request->query('book', ''), 'tag' => (string) $request->query('tag', ''), 'note_type' => (string) $request->query('note_type', '')];
        $query = BibleNote::query()->where('user_id', $request->user()->id)->with('translation');
        if ($filters['q'] !== '') {
            $query->where(fn ($builder) => $builder->where('reference', 'like', '%'.$filters['q'].'%')->orWhere('title', 'like', '%'.$filters['q'].'%')->orWhere('body', 'like', '%'.$filters['q'].'%'));
        }
        if ($filters['book'] !== '') {
            $query->where('reference', 'like', $filters['book'].'%');
        }
        if ($filters['tag'] !== '') {
            $query->whereJsonContains('tags', $filters['tag']);
        }
        if ($filters['note_type'] !== '') {
            $query->where('note_type', $filters['note_type']);
        }
        $notes = $query->latest('updated_at')->paginate(7)->withQueryString();
        $selectedNote = $notes->firstWhere('id', (int) $request->query('selected')) ?: $notes->first();
        $translations = BibleTranslation::query()->where('church_id', $request->user()->church_id)->where('status', 'active')->orderBy('name')->get();
        $books = BibleNote::query()->where('user_id', $request->user()->id)->selectRaw("substr(reference, 1, instr(reference, ' ') - 1) as book")->where('reference', 'like', '% %')->distinct()->pluck('book')->filter()->values();
        $tags = BibleNote::query()->where('user_id', $request->user()->id)->pluck('tags')->flatten()->filter()->unique()->sort()->values();

        return view('bible.notes', compact('notes', 'selectedNote', 'translations', 'books', 'tags', 'filters') + ['breadcrumbs' => $this->crumbs('Notes')]);
    }

    public function highlights(Request $request): View
    {
        $this->authorizeBible($request);
        $filters = ['q' => trim((string) $request->query('q', '')), 'color' => (string) $request->query('color', ''), 'book' => (string) $request->query('book', ''), 'tag' => (string) $request->query('tag', ''), 'date' => (string) $request->query('date', '')];
        $query = BibleHighlight::query()->where('user_id', $request->user()->id)->with('translation');
        if ($filters['q'] !== '') {
            $query->where(fn ($builder) => $builder->where('reference', 'like', '%'.$filters['q'].'%')->orWhere('snippet', 'like', '%'.$filters['q'].'%')->orWhere('meaning', 'like', '%'.$filters['q'].'%'));
        }
        if ($filters['color'] !== '') {
            $query->where('color', $filters['color']);
        }
        if ($filters['book'] !== '') {
            $query->where('reference', 'like', $filters['book'].'%');
        }
        if ($filters['tag'] !== '') {
            $query->where(fn ($builder) => $builder->whereJsonContains('tags', $filters['tag'])->orWhere('meaning', $filters['tag']));
        }
        if ($filters['date'] === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($filters['date'] === '7') {
            $query->where('created_at', '>=', now()->subDays(7));
        } elseif ($filters['date'] === '30') {
            $query->where('created_at', '>=', now()->subDays(30));
        } elseif ($filters['date'] === 'year') {
            $query->whereYear('created_at', now()->year);
        }
        $highlights = $query->latest()->paginate(7)->withQueryString();
        $allHighlights = BibleHighlight::query()->where('user_id', $request->user()->id)->get(['color', 'reference', 'meaning', 'tags']);
        $books = $allHighlights->map(function ($highlight): string {
            preg_match('/^(.+?)\s+\d+:\d+/', $highlight->reference, $matches);

            return trim((string) ($matches[1] ?? ''));
        })->filter()->unique()->sort()->values();
        $tags = $allHighlights->pluck('tags')->flatten()->merge($allHighlights->pluck('meaning'))->filter()->unique()->sort()->values();
        $colorCounts = $allHighlights->countBy('color');
        $totalHighlights = $allHighlights->count();
        $translations = BibleTranslation::query()->where('church_id', $request->user()->church_id)->where('status', 'active')->orderBy('name')->get();

        return view('bible.highlights', compact('highlights', 'filters', 'books', 'tags', 'colorCounts', 'totalHighlights', 'translations') + ['breadcrumbs' => $this->crumbs('Highlights')]);
    }

    public function startPlan(Request $request, BibleReadingPlan $plan): RedirectResponse
    {
        $this->authorizeBible($request);
        abort_unless($plan->church_id === null || $plan->church_id === $request->user()->church_id, 404);
        abort_if($plan->days()->doesntExist(), 422, 'This reading plan does not have a schedule yet.');
        $plan->users()->syncWithoutDetaching([$request->user()->id => ['current_day' => 1, 'current_streak' => 0, 'started_at' => now(), 'completed_at' => null, 'last_read_at' => null]]);

        return back()->with('status', 'Reading plan started.');
    }

    public function completePlanDay(Request $request, BibleReadingPlan $plan): RedirectResponse
    {
        $this->authorizeBible($request);
        abort_unless($plan->church_id === null || $plan->church_id === $request->user()->church_id, 404);
        $data = $request->validate(['day' => ['required', 'integer', 'min:1']]);
        $membership = $plan->users()->whereKey($request->user()->id)->firstOrFail();
        $pivot = $membership->pivot;
        abort_if(filled($pivot->completed_at), 409, 'This reading plan is already complete.');
        abort_if((int) $pivot->current_day !== (int) $data['day'], 409, 'Your reading-plan progress has already changed. Refresh the page.');
        abort_unless($plan->days()->where('day_number', $data['day'])->exists(), 422, 'This reading day is not configured.');

        $lastRead = $pivot->last_read_at ? CarbonImmutable::parse($pivot->last_read_at) : null;
        $streak = $lastRead?->isToday()
            ? (int) $pivot->current_streak
            : ($lastRead?->isYesterday() ? (int) $pivot->current_streak + 1 : 1);
        $lastDay = (int) $plan->days()->max('day_number');
        $completed = (int) $data['day'] >= $lastDay;
        $plan->users()->updateExistingPivot($request->user()->id, [
            'current_day' => $completed ? $lastDay : (int) $data['day'] + 1,
            'current_streak' => $streak,
            'last_read_at' => now(),
            'completed_at' => $completed ? now() : null,
        ]);

        return back()->with('status', $completed ? 'Reading plan completed. Well done!' : "Today's reading marked complete.");
    }

    public function storeBookmark(Request $request): RedirectResponse
    {
        $this->authorizeBible($request);
        $data = $request->validate(['translation_id' => ['required', Rule::exists('bible_translations', 'id')->where('church_id', $request->user()->church_id)], 'reference' => ['required', 'string', 'max:120'], 'book' => ['required', 'string', 'max:80'], 'chapter' => ['required', 'integer', 'min:1'], 'verse' => ['required', 'integer', 'min:1'], 'preview' => ['nullable', 'string', 'max:1000']]);
        BibleBookmark::create([...$data, 'bible_translation_id' => $data['translation_id'], 'user_id' => $request->user()->id, 'church_id' => $request->user()->church_id]);

        return back()->with('status', 'Verse bookmarked.');
    }

    public function storeNote(Request $request): RedirectResponse
    {
        $this->authorizeBible($request);
        $data = $request->validate(['translation_id' => ['required', Rule::exists('bible_translations', 'id')->where('church_id', $request->user()->church_id)], 'reference' => ['required', 'string', 'max:120'], 'title' => ['required', 'string', 'max:180'], 'body' => ['required', 'string', 'max:20000']]);
        BibleNote::create([...$data, 'bible_translation_id' => $data['translation_id'], 'user_id' => $request->user()->id, 'church_id' => $request->user()->church_id]);

        return back()->with('status', 'Note saved.');
    }

    public function storeHighlight(Request $request): RedirectResponse
    {
        $this->authorizeBible($request);
        $data = $request->validate(['translation_id' => ['required', Rule::exists('bible_translations', 'id')->where('church_id', $request->user()->church_id)], 'reference' => ['required', 'string', 'max:120'], 'snippet' => ['required', 'string', 'max:2000'], 'color' => ['required', 'in:yellow,green,purple,pink,blue'], 'meaning' => ['nullable', 'string', 'max:120'], 'tags' => ['nullable', 'string', 'max:500']]);
        $tags = collect(explode(',', (string) ($data['tags'] ?? '')))->map(fn ($tag): string => trim($tag))->filter()->values()->all();
        BibleHighlight::create([...$data, 'bible_translation_id' => $data['translation_id'], 'tags' => $tags, 'user_id' => $request->user()->id, 'church_id' => $request->user()->church_id]);

        return back()->with('status', 'Highlight saved.');
    }

    public function destroyBookmark(Request $request, BibleBookmark $bookmark): RedirectResponse
    {
        $this->authorizeBible($request);
        abort_unless($bookmark->user_id === $request->user()->id, 404);
        $bookmark->delete();

        return back()->with('status', 'Bookmark removed.');
    }

    public function updateNote(Request $request, BibleNote $note): RedirectResponse
    {
        $this->authorizeBible($request);
        abort_unless($note->user_id === $request->user()->id, 404);
        $data = $request->validate(['reference' => ['required', 'string', 'max:120'], 'title' => ['required', 'string', 'max:180'], 'body' => ['required', 'string', 'max:20000'], 'note_type' => ['required', 'string', 'max:40']]);
        $note->update($data);

        return back()->with('status', 'Note saved.');
    }

    public function destroyNote(Request $request, BibleNote $note): RedirectResponse
    {
        $this->authorizeBible($request);
        abort_unless($note->user_id === $request->user()->id, 404);
        $note->delete();

        return redirect()->route('bible.notes')->with('status', 'Note deleted.');
    }

    public function exportNotes(Request $request): StreamedResponse
    {
        $this->authorizeBible($request);
        $notes = BibleNote::query()->where('user_id', $request->user()->id)->with('translation')->latest()->get();

        return response()->streamDownload(function () use ($notes): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['Reference', 'Title', 'Body', 'Translation', 'Updated At']);
            foreach ($notes as $note) {
                fputcsv($handle, [$note->reference, $note->title, strip_tags($note->body), $note->translation?->abbreviation, $note->updated_at?->toIso8601String()]);
            } fclose($handle);
        }, 'bible-notes.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportHighlights(Request $request): StreamedResponse
    {
        $this->authorizeBible($request);
        $highlights = BibleHighlight::query()->where('user_id', $request->user()->id)->with('translation')->latest()->get();

        return response()->streamDownload(function () use ($highlights): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['Reference', 'Snippet', 'Color', 'Meaning', 'Tags', 'Translation', 'Highlighted At']);
            foreach ($highlights as $highlight) {
                fputcsv($handle, [$highlight->reference, $highlight->snippet, $highlight->color, $highlight->meaning, implode(', ', $highlight->tags ?? []), $highlight->translation?->abbreviation, $highlight->created_at?->toIso8601String()]);
            }
            fclose($handle);
        }, 'bible-highlights.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportBookmarks(Request $request): StreamedResponse
    {
        $this->authorizeBible($request);
        $bookmarks = BibleBookmark::query()->where('user_id', $request->user()->id)->with('translation')->latest()->get();

        return response()->streamDownload(function () use ($bookmarks): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['Reference', 'Preview', 'Translation', 'Book', 'Chapter', 'Verse', 'Saved At']);
            foreach ($bookmarks as $bookmark) {
                fputcsv($handle, [$bookmark->reference, $bookmark->preview, $bookmark->translation?->abbreviation, $bookmark->book, $bookmark->chapter, $bookmark->verse, $bookmark->created_at?->toIso8601String()]);
            }
            fclose($handle);
        }, 'bible-bookmarks.csv', ['Content-Type' => 'text/csv']);
    }

    private function authorizeBible(Request $request): void
    {
        abort_unless($request->user(), 403);
    }

    private function crumbs(string $label): array
    {
        return [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Bible', 'url' => route('bible.index')], ['label' => $label, 'url' => null]];
    }

    private function seedPlans(Request $request): void
    {
        if (BibleReadingPlan::query()->exists()) {
            return;
        } BibleReadingPlan::insert([['name' => 'Chronological Plan', 'description' => 'Read the Bible in chronological order of events.', 'category' => 'Chronological', 'duration_days' => 365, 'is_recommended' => true, 'created_at' => now(), 'updated_at' => now()], ['name' => 'New Testament in 90 Days', 'description' => 'Read the entire New Testament in just 90 days.', 'category' => 'New Testament', 'duration_days' => 90, 'is_recommended' => true, 'created_at' => now(), 'updated_at' => now()], ['name' => 'Psalms of Praise', 'description' => 'A journey through the book of Psalms.', 'category' => 'Psalms', 'duration_days' => 50, 'is_recommended' => true, 'created_at' => now(), 'updated_at' => now()]]);
    }
}
