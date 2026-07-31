<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BibleBookmark;
use App\Models\BibleHighlight;
use App\Models\BibleNote;
use App\Models\BibleReadingPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class BibleStudyController extends Controller
{
    public function plans(Request $request): View
    {
        $this->authorizeBible($request);
        $this->seedPlans($request);
        $plans = BibleReadingPlan::query()->with(['users' => fn ($q) => $q->whereKey($request->user()->id)])->orderByDesc('is_recommended')->orderBy('name')->get();

        return view('bible.plans', compact('plans') + ['breadcrumbs' => $this->crumbs('Reading Plans')]);
    }

    public function bookmarks(Request $request): View
    {
        $this->authorizeBible($request);
        $bookmarks = BibleBookmark::query()->where('user_id', $request->user()->id)->with('translation')->latest()->paginate(7);

        return view('bible.bookmarks', compact('bookmarks') + ['breadcrumbs' => $this->crumbs('Bookmarks')]);
    }

    public function notes(Request $request): View
    {
        $this->authorizeBible($request);
        $notes = BibleNote::query()->where('user_id', $request->user()->id)->with('translation')->latest('updated_at')->paginate(7);

        return view('bible.notes', compact('notes') + ['breadcrumbs' => $this->crumbs('Notes')]);
    }

    public function highlights(Request $request): View
    {
        $this->authorizeBible($request);
        $highlights = BibleHighlight::query()->where('user_id', $request->user()->id)->with('translation')->latest()->paginate(7);

        return view('bible.highlights', compact('highlights') + ['breadcrumbs' => $this->crumbs('Highlights')]);
    }

    public function startPlan(Request $request, BibleReadingPlan $plan): RedirectResponse
    {
        $this->authorizeBible($request);
        $plan->users()->syncWithoutDetaching([$request->user()->id => ['current_day' => 1, 'current_streak' => 0, 'started_at' => now()]]);

        return back()->with('status', 'Reading plan started.');
    }

    public function storeBookmark(Request $request): RedirectResponse
    {
        $this->authorizeBible($request);
        $data = $request->validate(['translation_id' => ['required', 'exists:bible_translations,id'], 'reference' => ['required', 'string', 'max:120'], 'book' => ['required', 'string', 'max:80'], 'chapter' => ['required', 'integer', 'min:1'], 'verse' => ['required', 'integer', 'min:1'], 'preview' => ['nullable', 'string', 'max:1000']]);
        BibleBookmark::create([...$data, 'bible_translation_id' => $data['translation_id'], 'user_id' => $request->user()->id, 'church_id' => $request->user()->church_id]);

        return back()->with('status', 'Verse bookmarked.');
    }

    public function storeNote(Request $request): RedirectResponse
    {
        $this->authorizeBible($request);
        $data = $request->validate(['translation_id' => ['required', 'exists:bible_translations,id'], 'reference' => ['required', 'string', 'max:120'], 'title' => ['required', 'string', 'max:180'], 'body' => ['required', 'string', 'max:20000']]);
        BibleNote::create([...$data, 'bible_translation_id' => $data['translation_id'], 'user_id' => $request->user()->id, 'church_id' => $request->user()->church_id]);

        return back()->with('status', 'Note saved.');
    }

    private function authorizeBible(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('use bible'), 403);
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
