<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AttendanceSession;
use App\Models\BookstoreOrder;
use App\Models\BookstoreProduct;
use App\Models\Campus;
use App\Models\Church;
use App\Models\CommunicationDelivery;
use App\Models\Donation;
use App\Models\Event;
use App\Models\Facility;
use App\Models\Family;
use App\Models\Feedback;
use App\Models\LeadershipReport;
use App\Models\Member;
use App\Models\Ministry;
use App\Models\PrayerRequest;
use App\Models\Program;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\Workflow;
use App\Services\ActivityLogger;
use App\Support\ModuleRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

final class ModuleManagementController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorizeSettings($request);

        $church = $this->settingsChurch();
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'category' => (string) $request->query('category', 'all'),
            'status' => (string) $request->query('status', 'all'),
        ];
        $moduleSettings = $this->moduleSettings($church, $filters);

        return view('admin.modules', [
            'church' => $church,
            'filters' => $filters,
            'moduleSettings' => $moduleSettings,
            'stats' => $this->stats($moduleSettings),
            'categories' => $this->categories($moduleSettings['all_modules']),
            'recentActivity' => ActivityLog::query()
                ->with('user')
                ->where('module', 'Settings')
                ->whereIn('action', ['modules_updated', 'modules_reset'])
                ->latest()
                ->limit(5)
                ->get(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Administration', 'url' => route('users.index')],
                ['label' => 'Module Management', 'url' => null],
            ],
        ]);
    }

    public function update(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeSettings($request);

        $validated = $request->validate([
            'enabled_modules' => ['nullable', 'array'],
            'enabled_modules.*' => ['string', Rule::in(ModuleRegistry::configurableRoutes()->all())],
        ]);

        $church = $this->settingsChurch();
        $settings = $church->settings ?? [];
        $enabled = collect($validated['enabled_modules'] ?? []);
        $disabled = ModuleRegistry::configurableRoutes()
            ->reject(fn (string $route): bool => $enabled->contains($route))
            ->values()
            ->all();

        $church->forceFill([
            'settings' => array_merge($settings, [
                'disabled_modules' => $disabled,
                'last_updated_by' => $request->user()?->name,
                'last_updated_at' => now()->toDateTimeString(),
            ]),
        ])->save();

        $activityLogger->log('Settings', 'modules_updated', 'Administrator updated enabled modules.', $church, [
            'resource' => 'Module Management',
            'risk' => 'medium',
            'status' => 'success',
            'disabled_modules' => $disabled,
        ], $request);

        return back()->with('status', 'Module settings saved.');
    }

    public function reset(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeSettings($request);

        $church = $this->settingsChurch();
        $settings = $church->settings ?? [];
        $church->forceFill([
            'settings' => array_merge($settings, [
                'disabled_modules' => [],
                'last_updated_by' => $request->user()?->name,
                'last_updated_at' => now()->toDateTimeString(),
            ]),
        ])->save();

        $activityLogger->log('Settings', 'modules_reset', 'Administrator restored default enabled modules.', $church, [
            'resource' => 'Module Management',
            'risk' => 'medium',
            'status' => 'success',
        ], $request);

        return redirect()->route('modules.index')->with('status', 'Default modules restored.');
    }

    private function authorizeSettings(Request $request): void
    {
        $user = $request->user();
        abort_unless($user?->isSuperAdministrator() || $user?->hasPermission('manage settings'), 403);
    }

    private function settingsChurch(): Church
    {
        return Church::query()->firstOrCreate(
            ['slug' => 'kingdom-life-global-church'],
            [
                'name' => config('church.name'),
                'timezone' => config('church.timezone'),
                'currency' => config('church.currency'),
                'email' => config('church.contact_email'),
                'phone' => config('church.contact_phone'),
                'address' => config('church.address'),
                'settings' => [],
            ],
        );
    }

    /**
     * @param  array{q: string, category: string, status: string}  $filters
     * @return array{all_modules: Collection<int, array<string, mixed>>, modules: Collection<int, array<string, mixed>>, disabled: Collection<int, string>, enabled_count: int, disabled_count: int, required_count: int, total: int}
     */
    private function moduleSettings(Church $church, array $filters): array
    {
        $disabled = ModuleRegistry::disabledRoutes($church);
        $allModules = ModuleRegistry::modules()
            ->map(fn (array $item): array => $this->normalizeModule($item, $disabled))
            ->values();

        $modules = $allModules
            ->filter(function (array $module) use ($filters): bool {
                if ($filters['q'] !== '' && ! str_contains(strtolower($module['label'].' '.$module['description'].' '.$module['route']), strtolower($filters['q']))) {
                    return false;
                }

                if ($filters['category'] !== 'all' && $module['category'] !== $filters['category']) {
                    return false;
                }

                return match ($filters['status']) {
                    'enabled' => ! $module['disabled'],
                    'disabled' => $module['disabled'],
                    'required' => $module['required'],
                    default => true,
                };
            })
            ->values();

        return [
            'all_modules' => $allModules,
            'modules' => $modules,
            'disabled' => $disabled,
            'enabled_count' => $allModules->where('disabled', false)->count(),
            'disabled_count' => $allModules->where('disabled', true)->count(),
            'required_count' => $allModules->where('required', true)->count(),
            'total' => $allModules->count(),
        ];
    }

    /**
     * @param  Collection<int, string>  $disabled
     * @return array<string, mixed>
     */
    private function normalizeModule(array $item, Collection $disabled): array
    {
        $route = (string) $item['route'];
        $category = $this->categoryForRoute($route);
        $isRequired = ModuleRegistry::isRequiredRoute($route);

        return [
            ...$item,
            'category' => $category,
            'category_label' => str($category)->headline()->toString(),
            'required' => $isRequired,
            'disabled' => ! $isRequired && $disabled->contains($route),
            'status' => empty($item['planned']) ? 'live' : 'planned',
            'usage' => $this->usageForRoute($route),
            'description' => $this->descriptionForModule($item),
        ];
    }

    private function categoryForRoute(string $route): string
    {
        return match (true) {
            str_starts_with($route, 'finance') || str_starts_with($route, 'bookstore') => 'finance',
            str_starts_with($route, 'communications') => 'communication',
            str_starts_with($route, 'users') || str_starts_with($route, 'roles') || str_starts_with($route, 'settings') || str_starts_with($route, 'audit-logs') || str_starts_with($route, 'campuses') || str_starts_with($route, 'assets') || str_starts_with($route, 'facilities') || str_starts_with($route, 'modules') => 'administration',
            str_starts_with($route, 'reports') || str_starts_with($route, 'leadership-reports') || str_starts_with($route, 'feedback') => 'reports',
            str_starts_with($route, 'meeting-integrations') || str_starts_with($route, 'meetings') => 'integration',
            str_starts_with($route, 'ministries') || str_starts_with($route, 'prayer-requests') || str_starts_with($route, 'volunteers') || str_starts_with($route, 'sermons') || str_starts_with($route, 'children-youth') || str_starts_with($route, 'counselling') || str_starts_with($route, 'staff') => 'ministry',
            default => 'core',
        };
    }

    private function usageForRoute(string $route): string
    {
        return match ($route) {
            'members.index' => number_format(Member::query()->count()).' members',
            'families.index' => number_format(Family::query()->count()).' families',
            'programs.index' => number_format(Program::query()->count()).' programs',
            'events.index' => number_format(Event::query()->count()).' events',
            'attendance.index' => number_format(AttendanceSession::query()->count()).' attendance sessions',
            'finance.index' => number_format((float) Donation::query()->sum('amount'), 2).' giving total',
            'communications.index' => number_format(CommunicationDelivery::query()->count()).' messages',
            'prayer-requests.index' => number_format(PrayerRequest::query()->count()).' prayer requests',
            'volunteers.index' => number_format(Volunteer::query()->count()).' volunteers',
            'ministries.index' => number_format(Ministry::query()->count()).' ministries',
            'campuses.index' => number_format(Campus::query()->count()).' campuses',
            'assets.index' => number_format(Asset::query()->count()).' assets',
            'facilities.index' => number_format(Facility::query()->count()).' facilities',
            'bookstore.index' => number_format(BookstoreProduct::query()->count()).' products / '.number_format(BookstoreOrder::query()->count()).' orders',
            'feedback.index' => number_format(Feedback::query()->count()).' feedback records',
            'staff.index' => number_format(Staff::query()->count()).' staff records',
            'workflows.index' => number_format(Workflow::query()->count()).' workflows',
            'leadership-reports.index' => number_format(LeadershipReport::query()->count()).' leadership reports',
            'users.index' => number_format(User::query()->count()).' users',
            'roles.index' => number_format(Role::query()->count()).' roles',
            default => 'Navigation module',
        };
    }

    private function descriptionForModule(array $item): string
    {
        $planned = collect($item['planned'] ?? [])->take(3)->implode(', ');

        return $planned !== '' ? $planned : 'Core system administration and access control.';
    }

    /**
     * @param  array{all_modules: Collection<int, array<string, mixed>>, enabled_count: int, disabled_count: int, required_count: int, total: int}  $moduleSettings
     * @return array<int, array{label: string, value: string, sub: string, icon: string, tone: string}>
     */
    private function stats(array $moduleSettings): array
    {
        $total = max($moduleSettings['total'], 1);

        return [
            ['label' => 'Total Modules', 'value' => (string) $moduleSettings['total'], 'sub' => 'Across all categories', 'icon' => 'layout-grid', 'tone' => 'violet'],
            ['label' => 'Enabled Modules', 'value' => (string) $moduleSettings['enabled_count'], 'sub' => round(($moduleSettings['enabled_count'] / $total) * 100, 1).'% enabled', 'icon' => 'badge-check', 'tone' => 'emerald'],
            ['label' => 'Disabled Modules', 'value' => (string) $moduleSettings['disabled_count'], 'sub' => round(($moduleSettings['disabled_count'] / $total) * 100, 1).'% disabled', 'icon' => 'shield-x', 'tone' => 'rose'],
            ['label' => 'Required Modules', 'value' => (string) $moduleSettings['required_count'], 'sub' => 'Cannot be disabled', 'icon' => 'shield-check', 'tone' => 'blue'],
            ['label' => 'System Health', 'value' => 'Excellent', 'sub' => 'Core services running', 'icon' => 'circle-check', 'tone' => 'emerald'],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $modules
     * @return Collection<int, array{key: string, label: string, count: int, icon: string, tone: string}>
     */
    private function categories(Collection $modules): Collection
    {
        $icons = [
            'core' => ['icon' => 'users', 'tone' => 'violet'],
            'ministry' => ['icon' => 'landmark', 'tone' => 'emerald'],
            'finance' => ['icon' => 'wallet', 'tone' => 'orange'],
            'communication' => ['icon' => 'message-square', 'tone' => 'sky'],
            'administration' => ['icon' => 'shield-check', 'tone' => 'blue'],
            'reports' => ['icon' => 'chart-column', 'tone' => 'amber'],
            'integration' => ['icon' => 'radio-tower', 'tone' => 'cyan'],
        ];

        return $modules
            ->groupBy('category')
            ->map(fn (Collection $items, string $category): array => [
                'key' => $category,
                'label' => str($category)->headline()->toString(),
                'count' => $items->count(),
                ...($icons[$category] ?? ['icon' => 'layout-grid', 'tone' => 'slate']),
            ])
            ->sortBy('label')
            ->values();
    }
}
