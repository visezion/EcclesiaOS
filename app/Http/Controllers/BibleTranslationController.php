<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BibleTranslation;
use App\Services\ActivityLogger;
use App\Support\BibleFreeTranslationInstaller;
use App\Support\BibleTranslationCatalog;
use App\Support\BibleTranslationImporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BibleTranslationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeManagement($request);
        BibleTranslationCatalog::ensureFreeDefaults();
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

        return $this->removeInstalledTranslation($request, $translation, $activityLogger);
    }

    public function uninstall(Request $request, BibleTranslation $translation, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeManagement($request);
        abort_unless($translation->church_id === null, 404);

        $installed = BibleTranslation::query()
            ->where('church_id', $request->user()->church_id)
            ->where('abbreviation', $translation->abbreviation)
            ->firstOrFail();

        return $this->removeInstalledTranslation($request, $installed, $activityLogger);
    }

    private function removeInstalledTranslation(
        Request $request,
        BibleTranslation $translation,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        abort_if($translation->is_default, 422, 'Choose another default translation before uninstalling this one.');
        $name = $translation->name;
        $abbreviation = $translation->abbreviation;
        $verseCount = $translation->verses()->count();
        $translation->delete();
        $activityLogger->log('Bible', 'translation_uninstalled', 'Bible translation uninstalled.', $translation, [
            'risk' => 'medium',
            'status' => 'success',
            'abbreviation' => $abbreviation,
            'verses_removed' => $verseCount,
        ], $request);

        return back()->with('status', $name.' was uninstalled from this church.');
    }

    public function install(Request $request, BibleTranslation $translation, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeManagement($request);
        abort_unless($translation->church_id === null, 404);
        $installed = BibleTranslation::query()->firstOrCreate(
            ['church_id' => $request->user()->church_id, 'abbreviation' => $translation->abbreviation],
            $translation->only(['name', 'abbreviation', 'language', 'description', 'copyright', 'source_url', 'status']) + ['created_by' => $request->user()->id, 'is_default' => false],
        );
        $verseCount = BibleFreeTranslationInstaller::install($installed);
        $activityLogger->log('Bible', 'translation_installed', 'Free Bible translation installed for the church.', $installed, ['risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', $installed->name.' is installed with '.number_format($verseCount).' verses.');
    }

    public function import(Request $request, BibleTranslation $translation, ActivityLogger $activityLogger, BibleTranslationImporter $importer): RedirectResponse
    {
        $this->authorizeManagement($request);
        abort_unless($translation->church_id === $request->user()->church_id, 404);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,json', 'max:51200']]);
        $count = $importer->import($translation, $request->file('file')->getRealPath());
        abort_if($count === 0, 422, 'The verse file did not contain valid rows.');
        $activityLogger->log('Bible', 'translation_imported', 'Bible translation verses imported.', $translation, ['risk' => 'medium', 'status' => 'success', 'rows' => $count], $request);

        return back()->with('status', $count.' verse rows imported into '.$translation->abbreviation.'.');
    }

    public function sample(Request $request): StreamedResponse
    {
        $this->authorizeManagement($request);

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['book', 'chapter', 'verse', 'text']);
            fputcsv($handle, ['John', 3, 16, 'For God so loved the world, that he gave his only begotten Son.']);
            fclose($handle);
        }, 'bible-translation-sample.csv', ['Content-Type' => 'text/csv']);
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('manage bible translations'), 403);
        abort_unless($request->user()?->church_id, 422, 'Select a church before managing Bible translations.');
    }
}
