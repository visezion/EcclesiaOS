<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BibleReadingPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BiblePlanManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeManagement($request);
        $plans = BibleReadingPlan::query()
            ->where('church_id', $request->user()->church_id)
            ->with('days')
            ->withCount('users')
            ->orderByDesc('updated_at')
            ->get();

        return view('bible.admin-plans', [
            'plans' => $plans,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Bible', 'url' => route('bible.index')],
                ['label' => 'Manage Reading Plans', 'url' => null],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request);
        $data = $this->validatedPlan($request);
        $days = $this->parseSchedule($data['schedule']);

        DB::transaction(function () use ($data, $days, $request): void {
            $plan = BibleReadingPlan::create([
                'church_id' => $request->user()->church_id,
                'name' => $data['name'],
                'description' => $data['description'],
                'category' => $data['category'],
                'duration_days' => count($days),
                'is_recommended' => $request->boolean('is_recommended'),
            ]);
            $plan->days()->createMany($days);
        });

        return redirect()->route('bible.admin.plans.index')->with('status', 'Reading plan created with '.count($days).' scheduled days.');
    }

    public function update(Request $request, BibleReadingPlan $plan): RedirectResponse
    {
        $this->authorizeOwnedPlan($request, $plan);
        $data = $this->validatedPlan($request);
        $days = $this->parseSchedule($data['schedule']);

        DB::transaction(function () use ($data, $days, $request, $plan): void {
            $plan->update([
                'name' => $data['name'],
                'description' => $data['description'],
                'category' => $data['category'],
                'duration_days' => count($days),
                'is_recommended' => $request->boolean('is_recommended'),
            ]);
            $plan->days()->delete();
            $plan->days()->createMany($days);
            DB::table('bible_reading_plan_user')
                ->where('bible_reading_plan_id', $plan->id)
                ->where('current_day', '>', count($days))
                ->update(['current_day' => count($days), 'updated_at' => now()]);
        });

        return redirect()->route('bible.admin.plans.index')->with('status', 'Reading plan updated.');
    }

    public function destroy(Request $request, BibleReadingPlan $plan): RedirectResponse
    {
        $this->authorizeOwnedPlan($request, $plan);
        $plan->delete();

        return redirect()->route('bible.admin.plans.index')->with('status', 'Reading plan deleted.');
    }

    private function validatedPlan(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:2000'],
            'category' => ['required', 'string', 'max:80'],
            'schedule' => ['required', 'string', 'max:100000'],
            'is_recommended' => ['nullable', 'boolean'],
        ]);
    }

    private function parseSchedule(string $schedule): array
    {
        $lines = collect(preg_split('/\R/u', $schedule))
            ->map(fn ($line): string => trim((string) $line))
            ->filter()
            ->values();

        if ($lines->isEmpty() || $lines->count() > 730) {
            throw ValidationException::withMessages(['schedule' => 'Add between 1 and 730 daily readings.']);
        }

        return $lines->map(function (string $line, int $index): array {
            $parts = array_map('trim', explode('|', $line, 3));
            $title = count($parts) >= 2 ? $parts[0] : 'Day '.($index + 1);
            $passages = count($parts) >= 2 ? $parts[1] : $parts[0];
            $reflection = $parts[2] ?? null;
            if ($title === '' || $passages === '') {
                throw ValidationException::withMessages(['schedule' => 'Every schedule line must include a title and Bible passage.']);
            }

            return [
                'day_number' => $index + 1,
                'title' => mb_substr($title, 0, 180),
                'passages' => $passages,
                'reflection' => filled($reflection) ? $reflection : null,
            ];
        })->all();
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdministrator() || $request->user()?->hasPermission('manage bible plans'), 403);
        abort_unless($request->user()?->church_id, 422, 'Select a church before managing reading plans.');
    }

    private function authorizeOwnedPlan(Request $request, BibleReadingPlan $plan): void
    {
        $this->authorizeManagement($request);
        abort_unless($plan->church_id === $request->user()->church_id, 404);
    }
}
