# KingdomHub Developer Hub

This document is the contributor guide for KingdomHub. It explains the application architecture, layout system, module process, security rules, quality checks, and release expectations.

## 1. Project Purpose

KingdomHub is an enterprise-style church management system built with Laravel. It is organized as a modular monolith: one Laravel application, shared authentication and permissions, reusable layout components, and separate feature modules for members, attendance, programs, communications, workflows, administration, and future church operations.

The current goal is to keep every implemented module database-backed, permission-aware, test-covered, and consistent with the established UI.

## 2. Stack

- PHP 8.2+
- Laravel 12
- Blade
- Eloquent ORM
- SQLite for local development, MySQL/MariaDB ready through Laravel config
- Tailwind CSS 4
- Alpine.js
- Vite
- Chart.js
- Lucide icons
- PHPUnit
- Laravel Pint
- Larastan/PHPStan

## 3. Local Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

For SQLite:

```bash
touch database/database.sqlite
php artisan migrate --seed
```

On Windows PowerShell:

```powershell
New-Item -ItemType File -Path database/database.sqlite
php artisan migrate --seed
```

Run the application:

```bash
php artisan serve
npm run build
```

Default development login:

- Email: `admin@kingdomhub.test`
- Password: `password`

## 4. Architecture Design

KingdomHub follows this request flow:

```text
Browser
  -> public/index.php
  -> routes/web.php
  -> auth + module.enabled + role/permission middleware
  -> controller
  -> request validation + policy/permission checks
  -> service/domain logic when needed
  -> Eloquent models + database
  -> Blade view/components
  -> Vite assets: Tailwind, Alpine, Chart.js, Lucide
```

Core layers:

- HTTP entry: `public/index.php`, `routes/web.php`, `bootstrap/app.php`
- Controllers: `app/Http/Controllers`
- Middleware: `app/Http/Middleware`
- Policies: `app/Policies`
- Models: `app/Models`
- Services: `app/Services`
- Shared support classes: `app/Support`
- Views: `resources/views`
- Reusable UI components: `resources/views/components`
- Frontend entry: `resources/js/app.js`, `resources/css/app.css`
- Database: `database/migrations`, `database/seeders`, `database/factories`
- Navigation: `config/navigation.php`
- Roles and permissions: `config/access.php`

## 5. Module Architecture

A module is a feature area with:

- Navigation entry in `config/navigation.php`
- Named route in `routes/web.php`
- Controller action
- Permission or role gate
- Optional policy
- Blade view
- Optional model and migration
- Optional service class
- Feature tests
- Activity logging for important changes
- Module Management compatibility through `App\Support\ModuleRegistry`

Disabled modules are hidden from sidebar navigation and global search. Direct URLs are blocked by `App\Http\Middleware\EnsureModuleEnabled`.

Required administration routes cannot be disabled because the system needs them to remain recoverable.

## 6. How To Add A New Module

1. Define the module scope.
   - Name
   - Business purpose
   - Owner role
   - Required records
   - Reports
   - User actions
   - Audit events

2. Add permissions.
   - Update `config/access.php`.
   - Assign permissions to the correct default roles.
   - Add policy methods when record-level rules are needed.

3. Add navigation.
   - Update `config/navigation.php`.
   - Include `label`, `route`, `icon`, `permission`, and `planned` capability notes.
   - Use Lucide icon names already imported in `resources/js/app.js`; if adding a new icon, import it and add it to the `icons` registry.

4. Register routes.
   - Add explicit routes in `routes/web.php`.
   - Place them before the generic coming-soon route loop.
   - Use stable route names such as `module.index`, `module.store`, `module.update`, `module.destroy`, and `module.export`.
   - Add the route name to the explicit skip list if it is also in navigation.

5. Add data structures.
   - Create migrations for persistent records.
   - Add indexes for frequent filters and foreign keys.
   - Add soft deletes when records should be recoverable.
   - Add models, relationships, casts, factories, and seed data.

6. Add the controller.
   - Keep controllers thin.
   - Validate all inputs.
   - Authorize before exposing sensitive data.
   - Use services for repeatable business logic.
   - Redirect after writes.

7. Add views.
   - Use `x-app-layout`.
   - Pass breadcrumbs.
   - Use `dashboard-card`, `stat-card`, tables, filters, and semantic status badges.
   - Make every button connect to a real route, form, modal, export, or remove it.

8. Add search support.
   - Extend `App\Services\SearchService` when the module should appear in global search.
   - Respect disabled modules through `ModuleRegistry::visibleNavigation()`.

9. Add audit logging.
   - Use `App\Services\ActivityLogger`.
   - Log creates, updates, deletes, imports, exports, approvals, settings changes, failed security events, and high-risk actions.

10. Add tests.
   - Render test
   - Create/update/delete tests
   - Export/import tests if applicable
   - Permission test
   - Disabled module route test if the module is configurable
   - Search test when the module is searchable

## 7. Layout And UI Rules

All authenticated pages should use:

```blade
<x-app-layout title="Page Title" :breadcrumbs="$breadcrumbs">
    ...
</x-app-layout>
```

Layout standards:

- Keep the actual working page as the first screen.
- Use `dashboard-card` for panels and repeated cards.
- Use tables for operational lists.
- Use filters that submit real GET requests or Alpine-backed local filtering.
- Use modals only for short create/edit workflows.
- Use semantic colors: violet for primary actions, emerald for success, orange for warning, rose for danger, slate for neutral surfaces.
- Keep cards at 8px radius or use the app component defaults.
- Do not nest cards inside cards.
- Avoid fake actions. If a button has no backend behavior, remove it until the action exists.
- Use Lucide icons through `data-lucide`.
- Add new Lucide icons to `resources/js/app.js` before using them.

## 8. Data Design Rules

- Store real data in database tables, not Blade arrays, once a module is implemented.
- Keep casts in models.
- Keep relationship names clear and singular/plural according to Laravel conventions.
- Use opaque IDs for user-facing URLs when models already support them.
- Use eager loading for list pages.
- Use pagination for large lists.
- Use transactions for multi-record writes.
- Use soft deletes for operational records that should be restorable or auditable.
- Store module-specific settings in the owning model when possible. Use `church.settings` for church-wide settings.

## 9. Security And Permissions

Every feature must answer:

- Who can open the page?
- Who can create records?
- Who can edit records?
- Who can delete records?
- Which church and campus data can the user see?
- What audit log should be written?
- What happens when the module is disabled?

Security tools:

- `auth` middleware
- `module.enabled` middleware
- `role` middleware
- `permission` middleware
- policies under `app/Policies`
- `ActivityLogger`
- CSRF protection
- Laravel validation
- password hashing through Laravel auth

Never trust hidden form inputs for authorization. Validate and authorize on the server.

## 10. Workflow And Approval Extensions

Workflow-backed features should:

- Use `Workflow` for reusable approval definitions.
- Use `Approval` records for actual approval instances.
- Store workflow progress and history in structured payload fields.
- Notify assigned users through communication delivery records when required.
- Include audit logging for submitted, approved, rejected, escalated, and completed states.

## 11. Testing Procedure

Run focused tests while developing:

```bash
php artisan test --filter=ModuleRoutesTest
php artisan test --filter=AdminPagesTest
```

Before handoff:

```bash
php artisan test
vendor\bin\pint --test
vendor\bin\phpstan analyse
npm run build
php artisan view:clear
```

On non-Windows systems, use:

```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
```

## 12. Contribution Checklist

Before submitting work:

- Routes are named and reachable.
- Sidebar entry exists only when the page exists.
- Buttons and links perform real actions.
- Forms validate input.
- Database writes are persisted.
- Policies or permissions protect sensitive actions.
- Activity logs are created for important changes.
- Search behavior is correct.
- Disabled module behavior is correct.
- Tests cover the new behavior.
- `npm run build` passes.
- No secrets are committed.
- README or this Developer Hub is updated when architecture, setup, or module procedure changes.

## 13. Production Readiness Checklist

- `APP_ENV=production`
- `APP_DEBUG=false`
- Strong `APP_KEY`
- Real database credentials
- Mail configured
- Queue configured
- Cache configured
- Session driver configured
- HTTPS enforced
- Secure cookies
- Backups configured
- Log rotation configured
- Development credentials removed
- `composer install --no-dev --optimize-autoloader`
- `npm ci && npm run build`
- `php artisan config:cache`
- `php artisan route:cache`
- `php artisan view:cache`

## 14. Ownership Expectations

When adding or changing a module, the developer owns:

- Route and controller behavior
- Data model and migration quality
- UI consistency
- Permission rules
- Audit events
- Tests
- Documentation
- Module Management compatibility

The standard is simple: if a user can see it, it should work; if it changes data, it should be validated, authorized, logged, and tested.
