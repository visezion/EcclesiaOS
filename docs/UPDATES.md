# EcclesiaOS Updates

## Overview

EcclesiaOS updates are distributed as custom GitHub Release assets. The live application never runs `git pull` and never replaces the church database, `.env`, or uploaded files.

The application checks GitHub every six hours. A Super Administrator can review the changelog in **Administration > System Updates**, confirm their password, and queue the update. The server scheduler performs the approved update outside the browser request.

## Required Production Layout

```text
/var/www/ecclesiaos/
|-- current -> releases/v1.0.0
|-- releases/
|   `-- v1.0.0/
`-- shared/
    |-- .env
    |-- storage/
    |-- updates/
    `-- backups/
```

The web server document root must be `/var/www/ecclesiaos/current/public`.

Configure the production `.env`:

```dotenv
UPDATER_ENABLED=true
UPDATER_INSTALL_ENABLED=true
UPDATE_REPOSITORY=visezion/EcclesiaOS
UPDATE_CHANNEL=stable
UPDATE_REQUIRE_IMMUTABLE=true
UPDATER_RELEASES_PATH=/var/www/ecclesiaos/releases
UPDATER_SHARED_PATH=/var/www/ecclesiaos/shared
UPDATER_CURRENT_LINK=/var/www/ecclesiaos/current
UPDATER_HEALTH_URL=https://church.example/up
UPDATER_BACKUP_STORAGE=true
UPDATER_BACKUP_RETENTION=5
UPDATER_PHP_BINARY=/usr/bin/php
UPDATER_RELOAD_COMMAND_JSON='["/usr/bin/sudo","-n","/usr/bin/systemctl","reload","php8.4-fpm"]'
UPDATER_MYSQLDUMP_BINARY=/usr/bin/mysqldump
UPDATER_PG_DUMP_BINARY=/usr/bin/pg_dump
```

For a private repository, add a fine-grained, read-only GitHub token:

```dotenv
GITHUB_UPDATE_TOKEN=github_pat_read_only_token
```

The web-server and scheduler user must be able to write to `releases/` and `shared/`. The shared `.env` and backup directories must not be publicly accessible.

If production uses SQLite, set `DB_DATABASE` to an absolute path inside `/var/www/ecclesiaos/shared`. The updater refuses to install when an SQLite database is inside a versioned release directory.

The reload command is passed directly to the operating system without a shell. Configure a narrow `sudoers` rule that permits only the exact PHP-FPM reload command for the scheduler user. For Apache deployments, use the equivalent Apache reload command.

## First Deployment

1. Deploy the first package into `/var/www/ecclesiaos/releases/v1.0.0`.
2. Move `.env` to `/var/www/ecclesiaos/shared/.env`.
3. Move `storage` to `/var/www/ecclesiaos/shared/storage`.
4. Link the release `.env` and `storage` paths to the shared paths.
5. Link `/var/www/ecclesiaos/current` to the first release.
6. Point the web server at `/var/www/ecclesiaos/current/public`.
7. Set permissions so the PHP and scheduler user can write to the managed directories.
8. Enable the updater only after **System Updates** reports every readiness check as passed.

## Scheduler

Run Laravel's scheduler every minute:

```cron
* * * * * cd /var/www/ecclesiaos/current && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler checks GitHub every six hours and processes approved updates every minute. Installation remains disabled unless `UPDATER_INSTALL_ENABLED=true` and every safety diagnostic passes.

## Publishing a Release

1. Add only forward, backward-compatible database migrations.
2. Update `VERSION`, for example from `1.0.0` to `2.1.0`.
3. Set `release/minimum-version.txt` to the oldest version that may upgrade directly.
4. Update `CHANGELOG.md`.
5. Merge the release commit into `main`.
6. Create and push the matching signed tag:

```bash
git tag -s v2.1.0 -m "EcclesiaOS v2.1.0"
git push origin v2.1.0
```

The GitHub workflow runs dependency audits, formatting checks, tests, and the frontend build. It then creates `ecclesiaos-v2.1.0.zip`, generates `update-manifest.json` with its SHA-256 digest, creates build-provenance attestations, and publishes both as GitHub Release assets.

Enable **Release immutability** in the GitHub repository settings before publishing production releases. The application rejects mutable releases by default.

## Data Safety Rules

- Never package `.env`, `storage/`, `public/storage`, SQLite databases, or production caches.
- Never use `migrate:fresh`, `migrate:refresh`, `db:wipe`, or production seeders.
- Use additive migrations first. Remove old columns only after every supported release no longer uses them.
- Test an upgrade using a copy of the previous production database.
- Keep the previous release directory until the new release has been verified.
- Test database and uploaded-file restoration regularly.
- A code rollback does not reverse database migrations, so migrations must remain compatible with the previous release.

## Commands

```bash
php artisan app:update-check --force
php artisan app:update --pending
php artisan app:update 2.1.0
php artisan app:update-rollback 2.1.0
```

## XAMPP and Development Installations

Development installations may check GitHub and display changelogs, but one-click installation is intentionally blocked on Windows and unmanaged working trees. This prevents the updater from overwriting source code or uncommitted work.
