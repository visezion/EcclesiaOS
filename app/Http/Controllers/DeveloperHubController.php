<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class DeveloperHubController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->isSuperAdministrator() || $user?->hasPermission('manage settings'), 403);

        return view('admin.developer-hub', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Administration', 'url' => route('users.index')],
                ['label' => 'Developer Hub', 'url' => null],
            ],
            'quickLinks' => [
                ['label' => 'README', 'path' => 'README.md', 'icon' => 'book-open'],
                ['label' => 'Developer Docs', 'path' => 'docs/DEVELOPER_HUB.md', 'icon' => 'file-text'],
                ['label' => 'Navigation Config', 'path' => 'config/navigation.php', 'icon' => 'layout-grid'],
                ['label' => 'Routes', 'path' => 'routes/web.php', 'icon' => 'route'],
                ['label' => 'Main Migration', 'path' => 'database/migrations/2026_07_21_000000_create_church_management_tables.php', 'icon' => 'database'],
            ],
            'architectureLayers' => [
                ['name' => 'HTTP Entry', 'items' => ['public/index.php', 'routes/web.php', 'middleware aliases in bootstrap/app.php'], 'icon' => 'route'],
                ['name' => 'Controllers', 'items' => ['Thin request handlers', 'Authorization checks', 'Validation and redirects'], 'icon' => 'braces'],
                ['name' => 'Domain Models', 'items' => ['Eloquent models in app/Models', 'Opaque route keys for user-facing IDs', 'Relationships and casts close to the data'], 'icon' => 'database'],
                ['name' => 'Services', 'items' => ['Reusable business logic', 'Dashboard/search/activity helpers', 'No large logic blocks in Blade'], 'icon' => 'settings'],
                ['name' => 'Views', 'items' => ['Blade pages', 'Reusable x-components', 'Tailwind utility styling', 'Alpine for local interaction'], 'icon' => 'layout-dashboard'],
                ['name' => 'Security', 'items' => ['Auth middleware', 'Permission middleware', 'Policies', 'Module availability guard', 'Activity logs'], 'icon' => 'shield-check'],
            ],
            'moduleSteps' => [
                'Define the module purpose, owner, permissions, routes, records, reports, and audit events.',
                'Add the navigation item in config/navigation.php with label, route, icon, permission, and planned capabilities.',
                'Register explicit routes in routes/web.php before the generic coming-soon route loop.',
                'Create a controller in app/Http/Controllers and keep it thin.',
                'Create or extend Eloquent models, migrations, relationships, factories, and seed data.',
                'Add authorization through permissions, policies, or middleware before exposing data.',
                'Create Blade views using x-app-layout, dashboard-card, stat-card, tables, and existing brand colors.',
                'Connect all buttons to real routes, forms, exports, modals, or remove unavailable actions.',
                'Log important create/update/delete/security actions through ActivityLogger.',
                'Add feature tests for render, create, update, delete, export, permission, and disabled-module behavior.',
            ],
            'qualityGates' => [
                ['command' => 'php artisan test', 'purpose' => 'Runs feature and unit coverage.'],
                ['command' => 'vendor\\bin\\pint --test', 'purpose' => 'Checks PHP formatting.'],
                ['command' => 'vendor\\bin\\phpstan analyse', 'purpose' => 'Runs static analysis when configured.'],
                ['command' => 'npm run build', 'purpose' => 'Compiles Tailwind, Alpine, charts, and icons.'],
                ['command' => 'php artisan route:list', 'purpose' => 'Confirms named routes and endpoints.'],
            ],
            'layoutRules' => [
                'Use x-app-layout for authenticated pages and pass breadcrumbs.',
                'Keep primary workflows visible on the first screen; do not create marketing-style landing pages for tools.',
                'Use dashboard-card for panels, stat-card for metric cards, and Lucide data-lucide names already imported in resources/js/app.js.',
                'Tables must have real data, working filters, connected actions, and empty states.',
                'Buttons must submit a form, open a modal, navigate to a route, export data, or be removed.',
                'Respect the brand palette: violet primary, restrained slate surfaces, semantic green/orange/rose states.',
            ],
        ]);
    }
}
