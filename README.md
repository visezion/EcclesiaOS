# KingdomHub

KingdomHub is a lightweight Laravel church management system foundation with an enterprise-style dashboard, reusable Blade layout, permission-ready navigation, module placeholders, baseline database schema, seed data, and tests.

## Features

- Dashboard modeled after the supplied reference: summary metrics, charts, bookstore snapshot, assets, leadership reports, feedback, events, ministries, campuses, insights, activity feed, and quick actions.
- Fixed desktop sidebar, mobile drawer, sticky topbar, global search, user dropdown, branded login, and branded error pages.
- Configuration-driven sidebar in `config/navigation.php`.
- Church branding defaults in `config/church.php` and `.env.example`.
- Coming Soon module pattern for every required sidebar route.
- Baseline auth, roles, permissions, role middleware, models, migrations, factories, seeders, and tests.

## Stack

- PHP 8.2+
- Laravel 12
- Blade
- SQLite for local development, MySQL/MariaDB ready through Laravel config
- Tailwind CSS 4
- Alpine.js
- Vite
- Chart.js
- Lucide icons
- PHPUnit, Laravel Pint, Larastan/PHPStan

## Requirements

- PHP 8.2 or newer
- Composer
- Node.js 20 or newer
- npm
- SQLite locally, or MySQL/MariaDB for production-style environments

## Installation

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

For local SQLite:

```bash
touch database/database.sqlite
php artisan migrate --seed
```

On Windows PowerShell:

```powershell
New-Item -ItemType File -Path database/database.sqlite
php artisan migrate --seed
```

Build assets:

```bash
npm run build
```

Run locally:

```bash
php artisan serve
```

Default development login:

- Email: `admin@kingdomhub.test`
- Password: `password`

## Quality Commands

```bash
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
```

## Architecture

- `app/Http/Controllers/DashboardController.php` renders the dashboard through `DashboardService`.
- `app/Http/Controllers/ModuleController.php` renders reusable Coming Soon pages for undeveloped modules.
- `app/Services/DashboardService.php` owns sample dashboard data and keeps Blade templates free of large hardcoded arrays.
- `app/Services/SearchService.php` provides the global search extension point.
- `config/navigation.php` defines labels, routes, icons, badges, permissions, and planned capabilities.
- `resources/views/components` contains reusable layout and dashboard UI building blocks.
- `database/migrations/2026_07_21_000000_create_church_management_tables.php` contains the initial broad schema.

## Adding A Module

1. Add a navigation item in `config/navigation.php`.
2. Add or update the named route in `routes/web.php`.
3. Create a controller under `app/Http/Controllers`.
4. Add a model and migration if persistent data is required.
5. Put aggregation or workflow logic in a service class.
6. Add a policy or middleware rule for authorization.
7. Create Blade views under `resources/views/modules`.
8. Add feature and unit tests.

The existing Coming Soon path means a module can be registered before its full implementation is ready.

## Replacing Sample Dashboard Data

Replace arrays in `DashboardService` with database-backed queries or read models. Keep controllers thin, eager load relationships, paginate lists, and avoid querying inside Blade loops.

## Production Checklist

- Set `APP_ENV=production`, `APP_DEBUG=false`, and a real `APP_KEY`.
- Configure MySQL/MariaDB, mail, queue, cache, and session drivers.
- Run `composer install --no-dev --optimize-autoloader`.
- Run `npm ci && npm run build`.
- Run `php artisan config:cache`, `route:cache`, and `view:cache`.
- Use HTTPS, secure cookies, backups, log rotation, and least-privilege database credentials.
- Change or remove development credentials.

## Security Notes

KingdomHub uses Laravel CSRF protection, password hashing, validation, Eloquent/query builder protections, middleware-ready authorization, and branded error pages. Do not render raw user input, commit secrets, or enable debug mode in production.

## License

MIT. See `LICENSE`.
