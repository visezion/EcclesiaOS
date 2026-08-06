<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessMemberImport;
use App\Models\Campus;
use App\Models\Church;
use App\Models\MemberImport;
use App\Models\MemberImportProfile;
use App\Services\ActivityLogger;
use App\Services\MemberImport\MemberImportFileReader;
use App\Services\MemberImport\MemberImportMapper;
use App\Services\MemberImport\MemberImportProcessor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

final class MemberImportController extends Controller
{
    public function __construct(
        private readonly MemberImportFileReader $reader,
        private readonly MemberImportMapper $mapper,
        private readonly MemberImportProcessor $processor,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeImport($request);
        $churchId = $this->churchId($request);

        return view('member-imports.index', [
            'imports' => MemberImport::query()
                ->where('church_id', $churchId)
                ->with('creator')
                ->latest()
                ->paginate(12),
            'profiles' => MemberImportProfile::query()
                ->where('church_id', $churchId)
                ->where(fn ($query) => $query->where('is_shared', true)->orWhere('created_by', $request->user()->id))
                ->orderBy('name')
                ->get(),
            'campuses' => Campus::query()->where('church_id', $churchId)->where('status', 'active')->orderBy('name')->get(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Members', 'url' => route('members.index')],
                ['label' => 'Import Center', 'url' => null],
            ],
        ]);
    }

    public function storeFile(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeImport($request);
        $churchId = $this->churchId($request);
        $validated = $request->validate([
            'members_file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls,json,xml,zip', 'max:51200'],
            'name' => ['nullable', 'string', 'max:150'],
            'profile_id' => [
                'nullable',
                Rule::exists('member_import_profiles', 'id')->where(fn ($query) => $query->where('church_id', $churchId)),
            ],
            'default_campus_id' => [
                'required',
                Rule::exists('campuses', 'id')->where(fn ($query) => $query->where('church_id', $churchId)->where('status', 'active')),
            ],
        ]);
        $file = $validated['members_file'];
        $extension = Str::lower($file->getClientOriginalExtension());
        $sourceType = $extension === 'txt' ? 'csv' : $extension;
        $path = $file->store('member-imports/'.$churchId.'/sources', 'local');
        $profile = filled($validated['profile_id'] ?? null)
            ? MemberImportProfile::query()->where('church_id', $churchId)->findOrFail($validated['profile_id'])
            : null;
        $import = MemberImport::query()->create([
            'reference' => 'MIM-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'church_id' => $churchId,
            'created_by' => $request->user()->id,
            'profile_id' => $profile?->id,
            'name' => filled($validated['name'] ?? null) ? trim((string) $validated['name']) : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'source_type' => $sourceType,
            'status' => 'analyzing',
            'disk' => 'local',
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'options' => [
                'default_campus_id' => (int) $validated['default_campus_id'],
                'duplicate_strategy' => 'skip',
                'create_families' => true,
            ],
            'source_options' => ['source_name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)],
        ]);

        try {
            $parsed = $this->reader->read($import);
            $mapping = $profile?->mapping ?: $this->mapper->autoMap($parsed['headers']);
            $import->update([
                'mapping' => $mapping,
                'source_options' => [
                    ...($import->source_options ?? []),
                    ...($parsed['metadata'] ?? []),
                    'headers' => $parsed['headers'],
                ],
                'total_rows' => count($parsed['rows']),
                'status' => 'draft',
            ]);
            foreach (array_chunk($parsed['rows'], 500) as $chunkOffset => $rows) {
                $inserts = [];
                foreach ($rows as $index => $row) {
                    $inserts[] = [
                        'member_import_id' => $import->id,
                        'row_number' => ($chunkOffset * 500) + $index + 2,
                        'source_data' => json_encode($row, JSON_THROW_ON_ERROR),
                        'status' => 'staged',
                        'duplicate_action' => 'skip',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                DB::table('member_import_rows')->insert($inserts);
            }
            $this->analyze($import, $mapping, 'skip');
        } catch (Throwable $exception) {
            report($exception);
            Storage::disk('local')->delete($path);
            Storage::disk('local')->deleteDirectory('member-imports/'.$churchId.'/assets/'.$import->id);
            $import->forceDelete();
            throw ValidationException::withMessages(['members_file' => 'The file could not be analyzed: '.$exception->getMessage()]);
        }

        $logger->log('Members', 'member_import_staged', $import->reference.' was uploaded for review.', $import, [
            'resource' => 'Member Import',
            'rows' => $import->total_rows,
            'source_type' => $sourceType,
            'status' => 'success',
            'risk' => 'low',
        ], $request);

        return redirect()->route('member-imports.show', $import)->with('status', 'File analyzed. Review the mapping and duplicate actions before importing.');
    }

    public function show(Request $request, MemberImport $memberImport): View
    {
        $this->authorizeRecord($request, $memberImport);
        $memberImport->load(['creator', 'profile']);
        $statusCounts = $memberImport->rows()->select('status', DB::raw('count(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status');

        return view('member-imports.show', [
            'import' => $memberImport,
            'rows' => $memberImport->rows()->with(['matchedMember', 'importedMember'])->orderBy('row_number')->paginate(50),
            'statusCounts' => $statusCounts,
            'headers' => (array) data_get($memberImport->source_options, 'headers', []),
            'fields' => $this->mapper->fields(),
            'campuses' => Campus::query()->where('church_id', $memberImport->church_id)->where('status', 'active')->orderBy('name')->get(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Members', 'url' => route('members.index')],
                ['label' => 'Import Center', 'url' => route('member-imports.index')],
                ['label' => $memberImport->reference, 'url' => null],
            ],
        ]);
    }

    public function updateMapping(Request $request, MemberImport $memberImport): RedirectResponse
    {
        $this->authorizeRecord($request, $memberImport);
        abort_unless(in_array($memberImport->status, ['draft', 'ready'], true), 409);
        $validated = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', 'string', 'max:190'],
            'duplicate_strategy' => ['required', Rule::in(['skip', 'update', 'merge', 'create'])],
            'default_campus_id' => [
                'required',
                Rule::exists('campuses', 'id')->where(fn ($query) => $query->where('church_id', $memberImport->church_id)->where('status', 'active')),
            ],
            'create_families' => ['nullable', 'boolean'],
        ]);
        $allowedFields = array_keys($this->mapper->fields());
        $headers = (array) data_get($memberImport->source_options, 'headers', []);
        $mapping = collect($validated['mapping'])
            ->filter(fn ($source, $field): bool => in_array($field, $allowedFields, true) && filled($source) && in_array($source, $headers, true))
            ->map(fn ($source): string => (string) $source)
            ->all();
        if (! isset($mapping['full_name']) && (! isset($mapping['first_name']) || ! isset($mapping['last_name']))) {
            throw ValidationException::withMessages(['mapping' => 'Map Full name, or map both First name and Last name.']);
        }
        $memberImport->update([
            'mapping' => $mapping,
            'options' => [
                ...($memberImport->options ?? []),
                'default_campus_id' => (int) $validated['default_campus_id'],
                'duplicate_strategy' => $validated['duplicate_strategy'],
                'create_families' => $request->boolean('create_families'),
            ],
            'status' => 'draft',
        ]);
        $this->analyze($memberImport, $mapping, $validated['duplicate_strategy']);

        return back()->with('status', 'Mapping applied and every row was re-analyzed.');
    }

    public function updateRow(Request $request, MemberImport $memberImport, int $row): RedirectResponse
    {
        $this->authorizeRecord($request, $memberImport);
        abort_unless(in_array($memberImport->status, ['draft', 'ready'], true), 409);
        $validated = $request->validate(['duplicate_action' => ['required', Rule::in(['skip', 'update', 'merge', 'create'])]]);
        $importRow = $memberImport->rows()->findOrFail($row);
        abort_unless($importRow->status === 'duplicate', 422, 'Only duplicate rows need a resolution.');
        $importRow->update(['duplicate_action' => $validated['duplicate_action']]);

        return back()->with('status', 'Duplicate action updated.');
    }

    public function storeProfile(Request $request, MemberImport $memberImport): RedirectResponse
    {
        $this->authorizeRecord($request, $memberImport);
        abort_unless(in_array($memberImport->status, ['draft', 'ready'], true), 409);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('member_import_profiles')->where(fn ($query) => $query->where('church_id', $memberImport->church_id))],
            'is_shared' => ['nullable', 'boolean'],
        ]);
        $profile = MemberImportProfile::query()->create([
            'church_id' => $memberImport->church_id,
            'created_by' => $request->user()->id,
            'name' => trim($validated['name']),
            'source_type' => $memberImport->source_type,
            'mapping' => $memberImport->mapping,
            'options' => $memberImport->options,
            'is_shared' => $request->boolean('is_shared'),
        ]);
        $memberImport->update(['profile_id' => $profile->id]);

        return back()->with('status', 'Mapping profile saved for future imports.');
    }

    public function destroyProfile(Request $request, MemberImportProfile $profile): RedirectResponse
    {
        $this->authorizeImport($request);
        abort_unless($request->user()->isSuperAdministrator() || ($profile->church_id === $request->user()->church_id && ($profile->created_by === $request->user()->id || $profile->is_shared)), 404);
        $profile->delete();

        return back()->with('status', 'Mapping profile removed.');
    }

    public function start(Request $request, MemberImport $memberImport, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeRecord($request, $memberImport);
        abort_unless(in_array($memberImport->status, ['draft', 'ready'], true), 409);
        abort_if($memberImport->rows()->whereIn('status', ['ready', 'duplicate'])->doesntExist(), 422, 'There are no valid rows to import.');
        $memberImport->update(['status' => 'queued', 'error' => null]);
        ProcessMemberImport::dispatch($memberImport->id);
        $logger->log('Members', 'member_import_started', $memberImport->reference.' was queued.', $memberImport, [
            'resource' => 'Member Import', 'rows' => $memberImport->total_rows, 'status' => 'success', 'risk' => 'medium',
        ], $request);

        return back()->with('status', 'Import queued. Progress will update automatically.');
    }

    public function progress(Request $request, MemberImport $memberImport): JsonResponse
    {
        $this->authorizeRecord($request, $memberImport);
        $memberImport->refresh();
        $percent = $memberImport->total_rows > 0 ? min(100, round(($memberImport->processed_rows / $memberImport->total_rows) * 100, 1)) : 0;

        return response()->json([
            'status' => $memberImport->status,
            'percent' => $percent,
            'total' => $memberImport->total_rows,
            'processed' => $memberImport->processed_rows,
            'created' => $memberImport->created_rows,
            'updated' => $memberImport->updated_rows,
            'skipped' => $memberImport->skipped_rows,
            'failed' => $memberImport->failed_rows,
            'completed_at' => $memberImport->completed_at?->toIso8601String(),
            'error' => $memberImport->error,
        ]);
    }

    public function rollback(Request $request, MemberImport $memberImport, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeRecord($request, $memberImport);
        abort_unless(in_array($memberImport->status, ['completed', 'completed_with_errors'], true), 409);
        $result = $this->processor->rollback($memberImport, $request->user()->id);
        $logger->log('Members', 'member_import_rolled_back', $memberImport->reference.' was rolled back.', $memberImport, [
            'resource' => 'Member Import', ...$result, 'status' => $result['conflicts'] > 0 ? 'warning' : 'success', 'risk' => 'high',
        ], $request);

        return back()->with('status', $result['restored'].' member changes rolled back'.($result['conflicts'] ? '; '.$result['conflicts'].' changed records were safely left untouched.' : '.'));
    }

    private function analyze(MemberImport $import, array $mapping, string $duplicateAction): void
    {
        $import->rows()->orderBy('id')->lazyById(200)->each(fn ($row) => $this->mapper->analyze($import, $row, $mapping, $duplicateAction));
        $import->update(['status' => 'ready']);
    }

    private function authorizeImport(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('manage members'), 403);
    }

    private function authorizeRecord(Request $request, MemberImport $import): void
    {
        $this->authorizeImport($request);
        abort_unless($request->user()->isSuperAdministrator() || $import->church_id === $request->user()->church_id, 404);
    }

    private function churchId(Request $request): int
    {
        if ($request->user()->isSuperAdministrator()) {
            return (int) ($request->user()->church_id ?: Church::query()->value('id'));
        }

        return (int) $request->user()->church_id;
    }
}
