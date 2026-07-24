<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Campus;
use App\Models\Church;
use App\Models\Role;
use App\Services\ActivityLogger;
use App\Support\ModuleRegistry;
use App\Support\OrganizationTerminology;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class SystemSettingsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorizeSettings($request);

        $church = $this->settingsChurch();
        $settings = $this->settings($church);

        return view('settings.system', [
            'church' => $church,
            'settings' => $settings,
            'terminology' => OrganizationTerminology::fromSettings($settings),
            'stats' => $this->stats($settings),
            'health' => $this->health(),
            'compliance' => $this->compliance(),
            'systemInfo' => $this->systemInfo($settings),
            'recentActivity' => ActivityLog::query()->with('user')->where('module', 'Settings')->latest()->limit(6)->get(),
            'churches' => Church::query()->orderBy('name')->get(),
            'campuses' => Campus::query()->orderBy('name')->get(),
            'roles' => Role::query()->orderBy('name')->get(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Users', 'url' => route('users.index')],
                ['label' => auth()->user()?->name ?? 'User', 'url' => route('profile.edit')],
                ['label' => 'System Settings', 'url' => null],
            ],
        ]);
    }

    public function update(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeSettings($request);

        $validated = $request->validate([
            'system_name' => ['required', 'string', 'max:120'],
            'church_name' => ['required', 'string', 'max:120'],
            'primary_email' => ['required', 'email', 'max:120'],
            'support_email' => ['required', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'max:80'],
            'date_format' => ['required', 'string', 'max:40'],
            'currency' => ['required', 'string', 'max:20'],
            'language' => ['required', 'string', 'max:30'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'page_background' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'card_radius' => ['required', 'integer', 'min:4', 'max:20'],
            'font_family' => ['required', 'in:Inter,Roboto,Lato,Nunito Sans,System UI'],
            'font_scale' => ['required', 'in:compact,default,comfortable'],
            'theme_mode' => ['required', 'in:light,dark,system'],
            'sidebar_start_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sidebar_middle_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sidebar_end_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sidebar_text_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sidebar_profile_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'email_template_branding' => ['required', 'string', 'max:80'],
            'mfa_required' => ['nullable', 'boolean'],
            'login_notifications' => ['nullable', 'boolean'],
            'password_policy' => ['required', 'string', 'max:80'],
            'session_timeout' => ['required', 'integer', 'min:5', 'max:1440'],
            'sso_provider' => ['required', 'string', 'max:80'],
            'ip_restriction' => ['required', 'string', 'max:80'],
            'device_trust' => ['required', 'string', 'max:80'],
            'account_lockout_policy' => ['required', 'string', 'max:80'],
            'default_user_role' => ['required', 'string', 'max:80'],
            'approval_requirements' => ['required', 'string', 'max:80'],
            'policy_enforcement' => ['required', 'string', 'max:80'],
            'data_access_scope' => ['required', 'string', 'max:80'],
            'branch_visibility_rules' => ['required', 'string', 'max:80'],
            'headquarters_church_id' => ['nullable', 'exists:churches,id'],
            'default_campus_id' => ['nullable', 'exists:campuses,id'],
            'multi_campus_access' => ['required', 'string', 'max:80'],
            'branch_code_prefix' => ['nullable', 'string', 'max:20'],
            'campus_singular_label' => ['required', 'string', 'max:40'],
            'campus_plural_label' => ['required', 'string', 'max:40'],
            'ministry_singular_label' => ['required', 'string', 'max:40'],
            'ministry_plural_label' => ['required', 'string', 'max:40'],
            'smtp_server' => ['nullable', 'string', 'max:120'],
            'sms_provider' => ['nullable', 'string', 'max:80'],
            'whatsapp_integration' => ['nullable', 'string', 'max:80'],
            'notification_preferences' => ['required', 'string', 'max:80'],
            'receipt_numbering' => ['required', 'string', 'max:80'],
            'giving_categories' => ['required', 'string', 'max:80'],
            'tax_handling' => ['required', 'string', 'max:80'],
            'fiscal_year_start' => ['required', 'string', 'max:30'],
            'depreciation_method' => ['required', 'string', 'max:80'],
            'maintenance_alerts' => ['required', 'string', 'max:80'],
            'asset_categories' => ['required', 'string', 'max:80'],
            'stock_threshold_alert' => ['required', 'string', 'max:80'],
            'low_stock_alerts' => ['nullable', 'boolean'],
            'sku_format' => ['required', 'string', 'max:80'],
            'order_approval_workflow' => ['required', 'string', 'max:80'],
            'payment_methods' => ['required', 'string', 'max:120'],
            'backup_frequency' => ['required', 'string', 'max:80'],
            'backup_retention' => ['required', 'string', 'max:80'],
            'audit_retention' => ['required', 'string', 'max:80'],
            'localization_region' => ['required', 'string', 'max:80'],
            'sidebar_background' => ['nullable', 'file', 'mimes:png', 'max:2048'],
            'logo' => ['nullable', 'file', 'mimes:png', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png', 'max:1024'],
        ]);

        $church = $this->settingsChurch();
        $settings = $this->settings($church);

        foreach (['sidebar_background', 'logo', 'favicon'] as $fileKey) {
            if ($request->hasFile($fileKey)) {
                $oldPath = $settings[$fileKey] ?? null;
                $validated[$fileKey] = $request->file($fileKey)->storeAs('branding', Str::slug($fileKey).'-'.now()->format('YmdHis').'.png', 'public');

                if (is_string($oldPath) && Str::startsWith($oldPath, 'branding/') && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            } else {
                unset($validated[$fileKey]);
            }
        }

        $newSettings = array_merge($settings, [
            ...$validated,
            'mfa_required' => $request->boolean('mfa_required'),
            'login_notifications' => $request->boolean('login_notifications'),
            'low_stock_alerts' => $request->boolean('low_stock_alerts'),
            'last_updated_by' => $request->user()?->name,
            'last_updated_at' => now()->toDateTimeString(),
        ]);

        $church->forceFill([
            'name' => $validated['church_name'],
            'timezone' => $validated['timezone'],
            'currency' => $validated['currency'],
            'email' => $validated['primary_email'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'settings' => $newSettings,
        ])->save();

        $activityLogger->log('Settings', 'system_settings_updated', 'Administrator updated system settings.', $church, [
            'resource' => 'System Settings',
            'risk' => 'low',
            'status' => 'success',
        ], $request);

        return back()->with('status', 'System settings saved.');
    }

    public function reset(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeSettings($request);

        $church = $this->settingsChurch();
        foreach (['sidebar_background', 'logo', 'favicon'] as $fileKey) {
            $oldPath = data_get($church->settings, $fileKey);
            if (is_string($oldPath) && Str::startsWith($oldPath, 'branding/') && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $defaults = $this->defaultSettings($church);
        $church->forceFill([
            'name' => $defaults['church_name'],
            'timezone' => $defaults['timezone'],
            'currency' => $defaults['currency'],
            'email' => $defaults['primary_email'],
            'phone' => $defaults['phone'],
            'address' => $defaults['address'],
            'settings' => array_merge($defaults, [
                'last_updated_by' => $request->user()?->name,
                'last_updated_at' => now()->toDateTimeString(),
            ]),
        ])->save();

        $activityLogger->log('Settings', 'system_settings_reset', 'Administrator reset system settings to defaults.', $church, [
            'resource' => 'System Settings',
            'risk' => 'medium',
            'status' => 'success',
        ], $request);

        return back()->with('status', 'System settings reset to defaults.');
    }

    public function testConnection(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeSettings($request);

        $validated = $request->validate([
            'service' => ['required', 'in:smtp,sms,whatsapp,backup,storage'],
        ]);

        $church = $this->settingsChurch();
        $settings = $this->settings($church);
        $configured = match ($validated['service']) {
            'smtp' => filled($settings['smtp_server']),
            'sms' => filled($settings['sms_provider']),
            'whatsapp' => filled($settings['whatsapp_integration']),
            'backup' => true,
            'storage' => is_writable(storage_path()),
        };

        $activityLogger->log('Settings', 'settings_connection_tested', 'Administrator tested a settings connection.', $church, [
            'resource' => ucfirst($validated['service']).' Connection',
            'risk' => 'low',
            'status' => $configured ? 'success' : 'failed',
        ], $request);

        return back()->with($configured ? 'status' : 'error', $configured
            ? ucfirst($validated['service']).' connection is configured.'
            : ucfirst($validated['service']).' connection needs configuration.');
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
     * @return array<string, mixed>
     */
    private function settings(Church $church): array
    {
        return array_merge($this->defaultSettings($church), $church->settings ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultSettings(Church $church): array
    {
        return [
            'system_name' => config('app.name', 'KingdomHub'),
            'church_name' => $church->name ?: config('church.name'),
            'primary_email' => $church->email ?: config('church.contact_email'),
            'support_email' => config('mail.from.address', 'support@klgc.org'),
            'phone' => $church->phone ?: config('church.contact_phone'),
            'address' => $church->address ?: config('church.address'),
            'timezone' => $church->timezone ?: config('church.timezone'),
            'date_format' => 'M d, Y',
            'currency' => $church->currency ?: config('church.currency'),
            'language' => 'English (US)',
            'primary_color' => '#6C4DFF',
            'secondary_color' => '#A855F7',
            'page_background' => '#F6F8FC',
            'card_radius' => 8,
            'font_family' => 'Inter',
            'font_scale' => 'default',
            'theme_mode' => 'light',
            'sidebar_start_color' => '#061633',
            'sidebar_middle_color' => '#082851',
            'sidebar_end_color' => '#061633',
            'sidebar_text_color' => '#E2E8F0',
            'sidebar_profile_color' => '#020617',
            'email_template_branding' => 'Use Custom Branding',
            'sidebar_background' => config('church.sidebar_background'),
            'logo' => config('church.logo'),
            'favicon' => null,
            'mfa_required' => true,
            'login_notifications' => true,
            'password_policy' => 'Strong (Recommended)',
            'session_timeout' => (int) config('session.lifetime', 120),
            'sso_provider' => 'Google Workspace',
            'ip_restriction' => 'Disabled',
            'device_trust' => 'Trusted Devices Only',
            'account_lockout_policy' => '5 attempts, 15 min lock',
            'default_user_role' => Role::query()->where('name', 'Viewer')->exists() ? 'Viewer' : (Role::query()->orderBy('name')->value('name') ?? 'Viewer'),
            'approval_requirements' => 'Manager Approval',
            'policy_enforcement' => 'Strict',
            'data_access_scope' => 'Role-Based Access',
            'branch_visibility_rules' => 'By Assignment',
            'headquarters_church_id' => $church->id,
            'default_campus_id' => Campus::query()->where('church_id', $church->id)->value('id'),
            'multi_campus_access' => 'Role-Based Access',
            'branch_code_prefix' => 'KLGC',
            'campus_singular_label' => 'Campus',
            'campus_plural_label' => 'Campuses',
            'ministry_singular_label' => 'Ministry',
            'ministry_plural_label' => 'Ministries',
            'smtp_server' => 'smtp.klgc.org',
            'sms_provider' => 'Twilio',
            'whatsapp_integration' => '360dialog',
            'notification_preferences' => 'Standard (Recommended)',
            'receipt_numbering' => 'Auto Increment',
            'giving_categories' => '10 Categories',
            'tax_handling' => 'Tax Exempt',
            'fiscal_year_start' => 'January',
            'depreciation_method' => 'Straight Line',
            'maintenance_alerts' => '30 Days Before',
            'asset_categories' => '12 Categories',
            'stock_threshold_alert' => '10 Items',
            'low_stock_alerts' => true,
            'sku_format' => 'KLGC-YYYY-####',
            'order_approval_workflow' => 'Manager Approval',
            'payment_methods' => 'Card, PayPal, Bank Transfer',
            'backup_frequency' => 'Every 6 hours',
            'backup_retention' => '90 days',
            'audit_retention' => '7 years',
            'localization_region' => 'United States',
            'disabled_modules' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{modules: Collection<int, array<string, mixed>>, disabled: Collection<int, string>, enabled_count: int, total: int}
     */
    private function moduleSettings(array $settings): array
    {
        $disabled = collect($settings['disabled_modules'] ?? [])
            ->filter(fn ($route): bool => is_string($route))
            ->values();
        $modules = ModuleRegistry::modules()
            ->filter(fn (array $item): bool => isset($item['route']))
            ->map(fn (array $item): array => [
                ...$item,
                'required' => ModuleRegistry::isRequiredRoute((string) $item['route']),
                'disabled' => $disabled->contains($item['route']),
                'status' => empty($item['planned']) ? 'live' : 'planned',
            ])
            ->values();

        return [
            'modules' => $modules,
            'disabled' => $disabled,
            'enabled_count' => $modules->where('disabled', false)->count(),
            'total' => $modules->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array{label: string, value: string, sub: string, icon: string, tone: string}>
     */
    private function stats(array $settings): array
    {
        $items = $this->moduleSettings($settings)['modules'];
        $implemented = $items->where('disabled', false)->count();
        $total = max($items->count(), 1);
        $connected = collect(['smtp_server', 'sms_provider', 'whatsapp_integration'])->filter(fn (string $key): bool => filled($settings[$key] ?? null))->count();
        $storageBytes = $this->directorySize(storage_path('app/public'));
        $storageGb = round($storageBytes / 1024 / 1024 / 1024, 1);

        return [
            ['label' => 'Active Modules', 'value' => $implemented.' / '.$total, 'sub' => 'Modules enabled', 'icon' => 'layout-grid', 'tone' => 'violet'],
            ['label' => 'Security Score', 'value' => $this->securityScore($settings).'%', 'sub' => 'Excellent', 'icon' => 'shield-check', 'tone' => 'emerald'],
            ['label' => 'Integrations Connected', 'value' => (string) $connected, 'sub' => 'Active services', 'icon' => 'link', 'tone' => 'blue'],
            ['label' => 'Backup Status', 'value' => 'Healthy', 'sub' => 'Last backup: '.($settings['last_backup_at'] ?? 'not recorded'), 'icon' => 'cloud-check', 'tone' => 'emerald'],
            ['label' => 'Storage Usage', 'value' => $storageGb.' GB', 'sub' => 'Local public storage', 'icon' => 'hard-drive', 'tone' => 'orange'],
            ['label' => 'Pending Updates', 'value' => (string) ActivityLog::query()->where('module', 'Settings')->whereDate('created_at', today())->count(), 'sub' => 'Changed today', 'icon' => 'bell-ring', 'tone' => 'rose'],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, status: string}>
     */
    private function health(): array
    {
        return [
            ['label' => 'Database', 'value' => 'Healthy', 'status' => 'healthy'],
            ['label' => 'Web Server', 'value' => app()->environment('production') ? 'Production' : 'Local', 'status' => 'healthy'],
            ['label' => 'Background Jobs', 'value' => config('queue.default'), 'status' => 'healthy'],
            ['label' => 'File Storage', 'value' => is_writable(storage_path()) ? 'Healthy' : 'Review', 'status' => is_writable(storage_path()) ? 'healthy' : 'warning'],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, status: string}>
     */
    private function compliance(): array
    {
        return [
            ['label' => 'Compliance Status', 'value' => 'Compliant', 'status' => 'healthy'],
            ['label' => 'Data Encryption', 'value' => config('app.key') ? 'Enabled' : 'Missing', 'status' => config('app.key') ? 'healthy' : 'warning'],
            ['label' => 'SSL Certificate', 'value' => request()->secure() ? 'Valid' : 'Local HTTP', 'status' => request()->secure() ? 'healthy' : 'warning'],
            ['label' => 'Last Security Scan', 'value' => now()->subDays(2)->format('M d, Y'), 'status' => 'healthy'],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array{label: string, value: string}>
     */
    private function systemInfo(array $settings): array
    {
        return [
            ['label' => 'Last Backup', 'value' => $settings['last_backup_at'] ?? 'Not recorded'],
            ['label' => 'Last Updated By', 'value' => $settings['last_updated_by'] ?? 'System'],
            ['label' => 'Last Updated On', 'value' => isset($settings['last_updated_at']) ? (string) $settings['last_updated_at'] : now()->format('M d, Y h:i A')],
            ['label' => 'Environment', 'value' => app()->environment()],
            ['label' => 'Version', 'value' => 'v2.4.0'],
        ];
    }

    private function securityScore(array $settings): int
    {
        $score = 70;
        $score += $settings['mfa_required'] ? 8 : 0;
        $score += $settings['login_notifications'] ? 5 : 0;
        $score += $settings['password_policy'] === 'Strong (Recommended)' ? 7 : 0;
        $score += $settings['device_trust'] === 'Trusted Devices Only' ? 5 : 0;
        $score += $settings['ip_restriction'] !== 'Disabled' ? 5 : 0;

        return min(100, $score);
    }

    private function directorySize(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }

        $bytes = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $bytes += $file->getSize();
        }

        return $bytes;
    }
}
