<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SystemUpdate;
use App\Services\ActivityLogger;
use App\Services\Updates\UpdateEnvironment;
use App\Services\Updates\UpdateManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

final class SystemUpdateController extends Controller
{
    public function index(Request $request, UpdateManager $manager, UpdateEnvironment $environment): View
    {
        $this->authorizeUpdater($request);

        return view('updates.index', [
            'currentVersion' => $manager->currentVersion(),
            'availableUpdate' => $manager->available(),
            'diagnostics' => $environment->diagnostics(),
            'updates' => SystemUpdate::query()->with('approver')->latest()->paginate(15),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'System Updates', 'url' => null],
            ],
        ]);
    }

    public function check(Request $request, UpdateManager $manager, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeUpdater($request);

        try {
            $update = $manager->check(force: true);
            $activityLogger->log(
                'System Updates',
                'update_check_completed',
                $update ? "Version {$update->version} is available." : 'The system is up to date.',
                $update,
                ['resource' => 'System Update', 'risk' => 'low', 'status' => 'success'],
                $request,
            );

            return back()->with('status', $update
                ? "Version {$update->version} is available."
                : 'You are running the latest available version.');
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'The update server could not be checked. '.$exception->getMessage());
        }
    }

    public function approve(
        Request $request,
        SystemUpdate $systemUpdate,
        UpdateManager $manager,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        $this->authorizeUpdater($request);

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'confirmation' => ['required', Rule::in(['UPDATE '.$systemUpdate->version])],
        ]);

        try {
            $manager->approve($systemUpdate, $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $activityLogger->log(
            'System Updates',
            'update_approved',
            "Version {$systemUpdate->version} was approved for installation.",
            $systemUpdate,
            ['resource' => 'System Update', 'risk' => 'high', 'status' => 'pending'],
            $request,
        );

        return back()->with('status', "Version {$systemUpdate->version} is queued for installation.");
    }

    public function skip(
        Request $request,
        SystemUpdate $systemUpdate,
        UpdateManager $manager,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        $this->authorizeUpdater($request);

        try {
            $manager->skip($systemUpdate);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $activityLogger->log(
            'System Updates',
            'update_skipped',
            "Version {$systemUpdate->version} was dismissed.",
            $systemUpdate,
            ['resource' => 'System Update', 'risk' => 'low', 'status' => 'skipped'],
            $request,
        );

        return back()->with('status', "Version {$systemUpdate->version} was dismissed.");
    }

    private function authorizeUpdater(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator(), 403);
    }
}
