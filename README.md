# EcclesiaOS

EcclesiaOS is a lightweight Laravel church management system foundation with an enterprise-style dashboard, reusable Blade layout, permission-ready navigation, module placeholders, baseline database schema, seed data, and tests.

## Features

- Dashboard modeled after the supplied reference: summary metrics, charts, bookstore snapshot, assets, leadership reports, feedback, events, ministries, campuses, insights, activity feed, and quick actions.
- Fixed desktop sidebar, mobile drawer, sticky topbar, global search, user dropdown, branded login, and branded error pages.
- Configuration-driven sidebar in `config/navigation.php`.
- Church branding defaults in `config/church.php` and `.env.example`.
- Coming Soon module pattern for every required sidebar route.
- Baseline auth, roles, permissions, role middleware, models, migrations, factories, seeders, and tests.
- Enterprise access-control baseline: profile updates, password changes, password reset links, user management, church/campus assignment, role-permission matrix, permission-filtered sidebar, policies, and activity logging.

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

The recommended deployment path is Docker. For the full production-oriented
setup, including Nginx, PHP-FPM, MySQL, Redis, queue workers, scheduled tasks,
health checks, backups, and update scripts, see
[`docs/DOCKER.md`](docs/DOCKER.md).

### Docker Deployment

#### Quick deployment on a new Ubuntu server

Install Git, Docker Engine, and the Docker Compose plugin:

```bash
sudo apt update
sudo apt install -y git curl ca-certificates
curl -fsSL https://get.docker.com | sudo sh
sudo systemctl enable --now docker
sudo usermod -aG docker ubuntu
sudo apt install -y docker-compose-plugin
```

If the server login is not named `ubuntu`, replace `ubuntu` with the actual
username. Sign out and reconnect after adding the user to the Docker group.
Then verify the tools and deploy EcclesiaOS:

```bash
docker --version
docker compose version
git --version
git clone --branch v1.0.31 --depth 1 https://github.com/visezion/EcclesiaOS.git
cd EcclesiaOS
sh docker/setup.sh
```

The reconnect is required before running the setup script; otherwise Docker
may report a permission error when accessing its socket.

Windows PowerShell:

```powershell
.\docker\setup.ps1
```

Linux or WSL:

```bash
sh docker/setup.sh
```

The first-time deployment commands above intentionally use the immutable
stable release tag. For development, keep that same stable baseline and make
your changes in a separate branch:

```bash
git switch -c feature/your-change
```

Do not develop directly from `main` for a production deployment. Move to a
new stable tag only after the release workflow has passed.

The setup script will:

1. Create `.env.docker` from `.env.docker.example`.
2. Generate a real `APP_KEY`.
3. Generate database credentials.
4. Build or pull the application images.
5. Start the full stack.
6. Bootstrap the first administrator if needed.

For unattended first deploys, set these values in `.env.docker` before running
the setup script:

- `BOOTSTRAP_ADMIN_NAME`
- `BOOTSTRAP_ADMIN_EMAIL`
- `BOOTSTRAP_ADMIN_PASSWORD`

If all three are present, the script creates the initial Super Administrator
without prompting. Otherwise, it falls back to the interactive prompts when run
from a terminal.

Fresh Docker installs also expose a branded first-run installer at `/install`
so the church profile and first administrator can be entered in the browser.

After setup, open the site at the `APP_URL` configured in `.env.docker`.

### Updating an Existing Docker Deployment

Windows PowerShell:

```powershell
.\docker\update.ps1 -Version 1.0.9
```

Linux or WSL:

```bash
sh docker/update.sh 1.0.9
```

Replace `1.0.9` with the GitHub release version you want to deploy.

### Publishing Application Releases

Application releases are published automatically by
`.github/workflows/release.yml` when a semantic-version tag is pushed:

```bash
git switch main
git pull --ff-only
git tag v1.0.9
git push origin v1.0.9
```

Use the next unused semantic version. Do not manually create the GitHub
release before pushing the tag.

The release workflow:

1. Runs the PHP and JavaScript checks.
2. Builds the production application and frontend assets.
3. Creates an installable `ecclesiaos-vX.Y.Z.zip` package.
4. Generates `update-manifest.json` with the package checksum.
5. Creates a draft GitHub release.
6. Uploads and verifies both required assets.
7. Publishes the release only after verification succeeds.

Every installable release must contain:

- `ecclesiaos-vX.Y.Z.zip`
- `update-manifest.json`

Published releases are immutable. Their assets cannot be replaced, and a tag
that has belonged to an immutable release cannot be reused, even after the
release is deleted. If a release fails before publication, fix the workflow and
rerun it for the same draft. If an immutable release was published incorrectly,
publish the fix under the next unused version.

This repository has immutable releases beginning with `v1.0.0`. Always use the
next unused version; an immutable release tag cannot be reused. Starting again
with the exact tag `v1.0.0` requires a different GitHub repository.

The application checks GitHub automatically and notifies administrators when a
newer installable release is available. The Update Now button is shown only
when the ZIP package, manifest, checksum, and managed production layout all
pass validation. Docker deployments create this managed layout automatically:
the application, queue, scheduler, and Nginx all use the same atomic `current`
release link, while the database and uploads remain in persistent volumes.
After a Super Administrator approves an update, the scheduler begins
installation within one minute. See [`docs/UPDATES.md`](docs/UPDATES.md) for
the updater architecture and server requirements.

### Local Development

If you want to run the project without Docker, use the normal Laravel flow:

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

These credentials exist only after running the demo seeder in a non-production
environment. Production seeding is blocked, and Docker deployments have no
default administrator credentials.

Initial roles:

- Super Administrator
- Church Administrator
- Senior Pastor
- Branch Pastor
- Finance Officer
- Membership Officer
- Asset Manager
- Book Store Manager
- Ministry Leader
- Staff
- Viewer

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
- `app/Http/Controllers/ModuleManagementController.php` manages enabled and disabled modules through Administration.
- `app/Http/Controllers/DeveloperHubController.php` renders the in-app developer documentation hub.
- `app/Http/Controllers/AccessControlController.php` renders the Settings access-control console.
- `app/Http/Controllers/ProfileController.php` handles user profile and password updates.
- `app/Http/Controllers/UserManagementController.php` manages users, status, roles, church assignment, and campus assignment.
- `app/Services/DashboardService.php` owns sample dashboard data and keeps Blade templates free of large hardcoded arrays.
- `app/Services/SearchService.php` provides the global search extension point.
- `app/Services/ActivityLogger.php` records authentication, profile, and access-control audit events.
- `config/access.php` defines the initial roles and permissions.
- `config/navigation.php` defines labels, routes, icons, badges, permissions, and planned capabilities.
- `resources/views/components` contains reusable layout and dashboard UI building blocks.
- `database/migrations/2026_07_21_000000_create_church_management_tables.php` contains the initial broad schema.

## Developer Hub

Full contributor documentation is available in two places:

- In the application: `Administration > Developer Hub`
- In the repository: `docs/DEVELOPER_HUB.md`

Use it for architecture, layout rules, module creation procedure, permissions, data design, testing, and release readiness.

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
- Prefer the Docker setup scripts over manual container commands for repeatable deployments.
- Use `docker/update.sh` or `docker/update.ps1` to move an existing deployment to a tagged release.

## Security Notes

EcclesiaOS uses Laravel CSRF protection, password hashing, validation, Eloquent/query builder protections, middleware-ready authorization, and branded error pages. Do not render raw user input, commit secrets, or enable debug mode in production.

## License

MIT. See `LICENSE`.
