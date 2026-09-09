<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BackupDestination;
use App\Models\BackupRecord;
use App\Models\BackupRestoreRequest;
use App\Models\BackupRestoreUpload;
use App\Models\BackupSchedule;
use App\Services\ActivityLogger;
use App\Services\Backups\BackupDestinationManager;
use App\Services\Backups\BackupManager;
use App\Services\Backups\BackupPackageValidator;
use App\Services\Backups\RestoreUploadManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class BackupDisasterRecoveryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission($request, 'backup.view');
        $churchId = (int) $request->user()->church_id;
        $backups = BackupRecord::query()->where('church_id', $churchId)->with('creator')->latest()->paginate(15)->withQueryString();
        $schedule = BackupSchedule::query()->firstOrCreate(['church_id' => $churchId], [
            'timezone' => $request->user()->church?->timezone ?? config('app.timezone'),
        ]);
        $lastSuccessful = BackupRecord::query()->where('church_id', $churchId)->where('status', 'completed')->latest('completed_at')->first();
        $lastVerified = BackupRecord::query()->where('church_id', $churchId)->whereNotNull('verified_at')->latest('verified_at')->first();
        $completedBackups = BackupRecord::query()->where('church_id', $churchId)->where('status', 'completed');
        $activeBackups = BackupRecord::query()->where('church_id', $churchId)->whereIn('status', ['queued', 'running'])->count();
        $destinationQuery = BackupDestination::query()->where('church_id', $churchId);
        $offsiteHealthy = (clone $destinationQuery)->where('is_offsite', true)->where('last_test_status', 'success')->exists();
        $readinessChecks = [
            (bool) $lastSuccessful,
            (bool) $lastVerified,
            (bool) $schedule->enabled,
            $offsiteHealthy,
        ];

        return view('backups.index', [
            'activeTab' => (string) $request->query('tab', 'dashboard'),
            'backups' => $backups,
            'schedule' => $schedule,
            'destinations' => BackupDestination::query()->where('church_id', $churchId)->orderBy('name')->get(),
            'restoreRequests' => BackupRestoreRequest::query()->where('church_id', $churchId)->latest()->limit(10)->get(),
            'restoreUploads' => BackupRestoreUpload::query()->where('church_id', $churchId)->where('user_id', $request->user()->id)->latest()->limit(10)->get(),
            'health' => [
                'last_successful' => $lastSuccessful,
                'last_verified' => $lastVerified,
                'automatic_enabled' => $schedule->enabled,
                'offsite_healthy' => $offsiteHealthy,
                'recovery_healthy' => $lastSuccessful?->completed_at?->greaterThan(now()->subDays(2)) ?? false,
                'readiness_score' => (int) round((collect($readinessChecks)->filter()->count() / count($readinessChecks)) * 100),
                'ready_checks' => collect($readinessChecks)->filter()->count(),
                'completed_count' => (clone $completedBackups)->count(),
                'protected_bytes' => (int) (clone $completedBackups)->sum('size_bytes'),
                'active_jobs' => $activeBackups,
                'destination_count' => (clone $destinationQuery)->where('is_active', true)->count(),
                'next_run_at' => $schedule->next_run_at,
            ],
            'permissions' => collect(['backup.create', 'backup.download', 'backup.delete', 'backup.configure', 'backup.restore', 'backup.restore_production', 'backup.external_storage'])
                ->mapWithKeys(fn (string $permission): array => [$permission => $request->user()->isSuperAdministrator() || $request->user()->hasPermission($permission)]),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Backup & Disaster Recovery', 'url' => null],
            ],
        ]);
    }

    public function store(Request $request, BackupManager $manager, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizePermission($request, 'backup.create');
        $validated = $request->validate([
            'type' => ['required', Rule::in(['database', 'files', 'full'])],
            'destination_ids' => ['nullable', 'array'],
            'destination_ids.*' => ['integer'],
        ]);
        $destinationIds = BackupDestination::query()->where('church_id', $request->user()->church_id)
            ->whereIn('id', $validated['destination_ids'] ?? [])->pluck('id')->all();
        $backup = $manager->create($request->user()->church, $validated['type'], $request->user(), 'manual', $destinationIds);
        $logger->log('Backup & Disaster Recovery', 'backup_queued', Str::headline($validated['type']).' backup queued.', $backup, ['risk' => 'medium'], $request);

        return back()->with('status', 'Backup queued. Progress will update automatically.');
    }

    public function status(Request $request, BackupRecord $backup): JsonResponse
    {
        $this->authorizeBackup($request, $backup, 'backup.view');

        return response()->json([
            'status' => $backup->status,
            'stage' => $backup->stage,
            'progress' => $backup->progress,
            'error' => $backup->error,
            'events' => $backup->events()->latest()->limit(12)->get(['level', 'stage', 'message', 'created_at']),
        ]);
    }

    public function download(Request $request, BackupRecord $backup): StreamedResponse
    {
        $this->authorizeBackup($request, $backup, 'backup.download');
        abort_unless($backup->status === 'completed' && $backup->path && Storage::disk($backup->disk)->exists($backup->path), 404);

        return Storage::disk($backup->disk)->download($backup->path, $backup->filename, ['Content-Type' => 'application/octet-stream']);
    }

    public function verify(Request $request, BackupRecord $backup, BackupManager $manager, BackupPackageValidator $validator): RedirectResponse
    {
        $this->authorizeBackup($request, $backup, 'backup.view');
        try {
            $manager->verify($backup, $validator);

            return back()->with('status', 'Backup integrity, encryption, archive, and compatibility checks passed.');
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Backup verification failed: '.$exception->getMessage());
        }
    }

    public function destroy(Request $request, BackupRecord $backup, BackupManager $manager, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeBackup($request, $backup, 'backup.delete');
        $request->validate(['current_password' => ['required', 'current_password'], 'confirmation' => ['required', Rule::in(['DELETE BACKUP'])]]);
        $logger->log('Backup & Disaster Recovery', 'backup_deleted', 'An encrypted backup package was deleted.', $backup, ['risk' => 'high'], $request);
        $manager->delete($backup, $request->user());

        return back()->with('status', 'Backup package deleted.');
    }

    public function updateSchedule(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'backup.configure');
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'backup_type' => ['required', Rule::in(['database', 'files', 'full'])],
            'frequency' => ['required', Rule::in(['manual', 'daily', 'weekly', 'monthly', 'custom'])],
            'days' => ['nullable', 'array'], 'days.*' => [Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'run_at' => ['required', 'date_format:H:i'],
            'keep_daily' => ['required', 'integer', 'min:0', 'max:365'],
            'keep_weekly' => ['required', 'integer', 'min:0', 'max:104'],
            'keep_monthly' => ['required', 'integer', 'min:0', 'max:120'],
            'keep_yearly' => ['required', 'integer', 'min:0', 'max:20'],
            'destination_ids' => ['nullable', 'array'], 'destination_ids.*' => ['integer'],
        ]);
        $destinationIds = BackupDestination::query()->where('church_id', $request->user()->church_id)
            ->whereIn('id', $validated['destination_ids'] ?? [])->pluck('id')->all();
        BackupSchedule::query()->updateOrCreate(['church_id' => $request->user()->church_id], [
            ...$validated,
            'enabled' => $request->boolean('enabled'),
            'days' => $validated['days'] ?? [],
            'destination_ids' => $destinationIds,
            'timezone' => $request->user()->church?->timezone ?? config('app.timezone'),
            'next_run_at' => null,
        ]);

        return back()->with('status', 'Backup automation and retention policy saved.');
    }

    public function storeDestination(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'backup.external_storage');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'driver' => ['required', Rule::in(['local', 's3', 'sftp'])],
            'is_offsite' => ['nullable', 'boolean'],
            'key' => ['nullable', 'string', 'max:500'], 'secret' => ['nullable', 'string', 'max:1000'],
            'region' => ['nullable', 'string', 'max:120'], 'bucket' => ['nullable', 'string', 'max:255'],
            'endpoint' => ['nullable', 'url', 'max:1000'], 'use_path_style_endpoint' => ['nullable', 'boolean'],
            'host' => ['nullable', 'string', 'max:255'], 'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:1000'], 'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'root' => ['nullable', 'string', 'max:500'],
        ]);
        $configuration = collect($validated)->except(['name', 'driver', 'is_offsite'])->filter(fn ($value): bool => $value !== null && $value !== '')->all();
        BackupDestination::query()->create([
            'church_id' => $request->user()->church_id, 'name' => $validated['name'], 'driver' => $validated['driver'],
            'configuration' => $configuration, 'is_offsite' => $request->boolean('is_offsite'),
        ]);

        return back()->with('status', 'Encrypted backup destination saved. Test it before use.');
    }

    public function testDestination(Request $request, BackupDestination $destination, BackupDestinationManager $manager): RedirectResponse
    {
        $this->authorizeDestination($request, $destination);
        try {
            $manager->test($destination);
            $destination->forceFill(['last_tested_at' => now(), 'last_test_status' => 'success', 'last_error' => null])->save();

            return back()->with('status', 'Destination write, read, and delete test passed.');
        } catch (Throwable $exception) {
            report($exception);
            $destination->forceFill(['last_tested_at' => now(), 'last_test_status' => 'failed', 'last_error' => $exception->getMessage()])->save();

            return back()->with('error', 'Destination test failed: '.$exception->getMessage());
        }
    }

    public function destroyDestination(Request $request, BackupDestination $destination): RedirectResponse
    {
        $this->authorizeDestination($request, $destination);
        $destination->delete();

        return back()->with('status', 'Backup destination removed. Existing backup packages were not deleted.');
    }

    public function startUpload(Request $request, RestoreUploadManager $manager): JsonResponse
    {
        $this->authorizePermission($request, 'backup.restore');
        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255', 'ends_with:.ecbak'],
            'total_size' => ['required', 'integer', 'min:1'],
            'chunk_size' => ['required', 'integer', 'min:1048576'],
            'checksum' => ['nullable', 'string', 'size:64'],
        ]);
        $upload = $manager->start($request->user()->church, $request->user(), $validated['filename'], (int) $validated['total_size'], (int) $validated['chunk_size'], $validated['checksum'] ?? null);

        return response()->json(['id' => $upload->opaqueId(), 'uuid' => $upload->uuid, 'received_chunks' => [], 'progress' => 0], 201);
    }

    public function uploadChunk(Request $request, BackupRestoreUpload $upload, RestoreUploadManager $manager): JsonResponse
    {
        $this->authorizeUpload($request, $upload);
        $validated = $request->validate(['index' => ['required', 'integer', 'min:0'], 'chunk' => ['required', 'file', 'max:102400']]);
        $manager->chunk($upload, (int) $validated['index'], $validated['chunk']);

        return response()->json(['received_chunks' => $upload->refresh()->received_chunks, 'progress' => $upload->progress]);
    }

    public function uploadStatus(Request $request, BackupRestoreUpload $upload): JsonResponse
    {
        $this->authorizeUpload($request, $upload);

        return response()->json(['received_chunks' => $upload->received_chunks ?? [], 'progress' => $upload->progress, 'status' => $upload->status]);
    }

    public function completeUpload(Request $request, BackupRestoreUpload $upload, RestoreUploadManager $manager): JsonResponse
    {
        $this->authorizeUpload($request, $upload);
        $manager->complete($upload);

        return response()->json(['status' => 'uploaded', 'progress' => 100]);
    }

    public function validateUpload(Request $request, BackupRestoreUpload $upload, BackupPackageValidator $validator): RedirectResponse
    {
        $this->authorizeUpload($request, $upload);
        abort_unless($upload->status === 'uploaded', 422, 'Complete the upload before validation.');
        $restore = BackupRestoreRequest::query()->create([
            'uuid' => (string) Str::uuid(), 'church_id' => $upload->church_id, 'restore_upload_id' => $upload->id,
            'requested_by' => $request->user()->id, 'mode' => 'validate', 'status' => 'validating', 'stage' => 'validating', 'progress' => 20,
        ]);
        try {
            $validation = $validator->validate(storage_path('app/'.$upload->path.'/package.ecbak'), $request->user()->church);
            $restore->forceFill(['status' => $validation['safe_to_restore'] ? 'validated' : 'blocked', 'stage' => 'compatibility_check', 'progress' => 100, 'validation' => $validation, 'validated_at' => now()])->save();

            return back()->with($validation['safe_to_restore'] ? 'status' : 'error', $validation['safe_to_restore'] ? 'Backup package passed all pre-restore safety checks.' : 'Restore blocked because the package is incompatible with this church or application.');
        } catch (Throwable $exception) {
            report($exception);
            $restore->forceFill(['status' => 'blocked', 'stage' => 'validation_failed', 'error' => $exception->getMessage(), 'failed_at' => now()])->save();

            return back()->with('error', 'Restore validation failed: '.$exception->getMessage());
        }
    }

    public function requestProductionRestore(Request $request, BackupRestoreRequest $restore): RedirectResponse
    {
        $this->authorizePermission($request, 'backup.restore_production');
        abort_unless((int) $restore->church_id === (int) $request->user()->church_id && $restore->status === 'validated' && data_get($restore->validation, 'safe_to_restore'), 403);
        $churchName = strtoupper((string) $request->user()->church->name);
        $request->validate(['current_password' => ['required', 'current_password'], 'confirmation' => ['required', Rule::in(['RESTORE '.$churchName])]]);
        $restore->forceFill(['mode' => 'full', 'status' => 'awaiting_maintenance_window', 'stage' => 'safety_backup_required', 'progress' => 0])->save();

        return back()->with('status', 'Production restore authorized. It is waiting for a managed maintenance window and pre-restore safety backup. No live data has been changed.');
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission($permission), 403);
    }

    private function authorizeBackup(Request $request, BackupRecord $backup, string $permission): void
    {
        $this->authorizePermission($request, $permission);
        abort_unless((int) $backup->church_id === (int) $request->user()->church_id, 404);
    }

    private function authorizeDestination(Request $request, BackupDestination $destination): void
    {
        $this->authorizePermission($request, 'backup.external_storage');
        abort_unless((int) $destination->church_id === (int) $request->user()->church_id, 404);
    }

    private function authorizeUpload(Request $request, BackupRestoreUpload $upload): void
    {
        $this->authorizePermission($request, 'backup.restore');
        abort_unless((int) $upload->church_id === (int) $request->user()->church_id && (int) $upload->user_id === (int) $request->user()->id, 404);
    }
}
