<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BibleTranslation;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class BibleTranslationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeManagement($request);
        $translations = BibleTranslation::query()
            ->where(fn ($query) => $query->whereNull('church_id')->orWhere('church_id', $request->user()->church_id))
            ->withCount('verses')
            ->latest()
            ->get();

        return view('bible.translations', [
            'translations' => $translations,
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Bible', 'url' => route('bible.index')], ['label' => 'Translations', 'url' => null]],
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeManagement($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'abbreviation' => ['required', 'string', 'max:20', 'alpha_num'],
            'language' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'copyright' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:500'],
        ]);
        $data['abbreviation'] = strtoupper($data['abbreviation']);
        $data['church_id'] = $request->user()->church_id;
        $data['created_by'] = $request->user()->id;
        $data['status'] = 'active';
        $data['is_default'] = false;
        $request->validate(['abbreviation' => [Rule::unique('bible_translations', 'abbreviation')->where(fn ($query) => $query->where('church_id', $request->user()->church_id))]]);
        $translation = BibleTranslation::create($data);
        $activityLogger->log('Bible', 'translation_created', 'Bible translation added.', $translation, ['risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', $translation->name.' was added. Import its verse file to make it available for reading.');
    }

    public function destroy(Request $request, BibleTranslation $translation, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeManagement($request);
        abort_unless($translation->church_id === $request->user()->church_id, 404);
        abort_if($translation->is_default, 422, 'The default translation cannot be removed.');
        $name = $translation->name;
        $translation->delete();
        $activityLogger->log('Bible', 'translation_deleted', 'Bible translation removed.', $translation, ['risk' => 'medium', 'status' => 'success'], $request);

        return back()->with('status', $name.' was removed.');
    }

    public function import(Request $request, BibleTranslation $translation, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeManagement($request);
        abort_unless($translation->church_id === $request->user()->church_id, 404);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,json', 'max:51200']]);
        $rows = $this->rowsFromFile($request->file('file')->getRealPath());
        abort_if($rows === [], 422, 'The verse file did not contain valid rows.');
        DB::transaction(function () use ($translation, $rows): void {
            foreach (array_chunk($rows, 500) as $chunk) {
                $translation->verses()->upsert($chunk, ['bible_translation_id', 'book_slug', 'chapter', 'verse'], ['book', 'testament', 'text', 'updated_at']);
            }
        });
        $activityLogger->log('Bible', 'translation_imported', 'Bible translation verses imported.', $translation, ['risk' => 'medium', 'status' => 'success', 'rows' => count($rows)], $request);

        return back()->with('status', count($rows).' verse rows imported into '.$translation->abbreviation.'.');
    }

    private function rowsFromFile(string $path): array
    {
        $contents = file_get_contents($path) ?: '';
        $decoded = json_decode($contents, true);
        $source = is_array($decoded) ? $decoded : $this->csvRows($path);

        return collect($source)->map(function (array $row): ?array {
            $book = trim((string) ($row['book'] ?? ''));
            $chapter = (int) ($row['chapter'] ?? 0);
            $verse = (int) ($row['verse'] ?? 0);
            $text = trim((string) ($row['text'] ?? ''));
            if ($book === '' || $chapter < 1 || $verse < 1 || $text === '') {
                return null;
            }

return ['book' => $book, 'book_slug' => Str::slug($book), 'testament' => in_array(strtolower($book), ['matthew', 'mark', 'luke', 'john', 'acts', 'romans'], true) ? 'new' : 'old', 'chapter' => $chapter, 'verse' => $verse, 'text' => $text, 'updated_at' => now(), 'created_at' => now()];
        })->filter()->values()->all();
    }

    private function csvRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        } $header = fgetcsv($handle);
        if ($header === false) {
            return [];
        } $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($header, $values) ?: [];
        } fclose($handle);

        return $rows;
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('manage bible translations'), 403);
    }
}
