# Docker Deployment

EcclesiaOS ships with a production-oriented Docker Compose stack:

- Nginx is the only public service.
- PHP 8.4 FPM runs the Laravel application.
- MySQL 8.4 stores church data in a persistent volume.
- Redis stores sessions, cache, queue data, and scheduler locks.
- Dedicated queue and scheduler containers run background work.
- Uploaded files and branding assets use a shared persistent volume.
- Health checks control startup order.
- Migrations and Laravel optimization run automatically.

## Requirements

- Docker Desktop, or Docker Engine with Docker Compose v2.20 or newer
- At least 2 GB RAM available to Docker
- A domain and HTTPS reverse proxy for internet-facing production deployments

Docker is the only runtime dependency. PHP, Composer, Node.js, MySQL, Redis, and
Nginx do not need to be installed on the host.

## First Deployment

### Windows

Run:

```powershell
.\docker\setup.ps1
```

### Linux

Run:

```bash
sh docker/setup.sh
```

The setup script:

1. Copies `.env.docker.example` to `.env.docker`.
2. Generates a unique Laravel key and strong database passwords.
3. validates the Compose configuration.
4. Builds all application images.
5. Starts MySQL and Redis.
6. Runs database migrations.
7. Starts PHP-FPM, Nginx, the queue worker, and the scheduler.
8. Bootstraps the first Super Administrator from `.env.docker` if
   `BOOTSTRAP_ADMIN_NAME`, `BOOTSTRAP_ADMIN_EMAIL`, and
   `BOOTSTRAP_ADMIN_PASSWORD` are present.
9. Otherwise prompts for the first Super Administrator in an interactive shell.
10. Prints Laravel runtime information as a final smoke test.

The default site URL is `http://localhost:8080`.

Fresh deployments also expose a branded installer at `/install`. If no Super
Administrator exists yet, the login screen redirects there automatically.

For unattended provisioning, set the three `BOOTSTRAP_ADMIN_*` values in
`.env.docker` before running the setup script. If you prefer to skip admin
creation entirely, use `-SkipAdmin` on Windows and create the administrator
later with:

```bash
docker compose --env-file .env.docker exec app php artisan app:bootstrap-admin
```

## Production Configuration

Edit `.env.docker` before exposing the site:

```dotenv
APP_URL=https://church.example.org
APP_BIND_ADDRESS=127.0.0.1
APP_PORT=8080
SESSION_SECURE_COOKIE=true
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.org
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=church@example.org
```

Use `APP_BIND_ADDRESS=127.0.0.1` when Caddy, Traefik, HAProxy, a cloud load
balancer, or a host Nginx instance terminates HTTPS. The reverse proxy should
forward traffic to `127.0.0.1:8080`.

Do not commit `.env.docker`. It contains production secrets and is ignored by
Git.

## Release Images

Git tags matching `v*.*.*` publish two images through GitHub Actions:

```text
ghcr.io/visezion/ecclesiaos-app:<version>
ghcr.io/visezion/ecclesiaos-web:<version>
```

To deploy published images instead of building on the server:

```powershell
.\docker\setup.ps1 -UseRegistry
```

```bash
sh docker/setup.sh --registry
```

Set `ECCLESIAOS_VERSION` in `.env.docker` to an existing release such as
`2.1.0`. If the GitHub packages are private, authenticate first:

```bash
echo "$GITHUB_TOKEN" | docker login ghcr.io -u USERNAME --password-stdin
```

The token needs package read permission.

## Safe Updates

The Update Center can show available versions and changelogs inside Docker, but
one-click in-place installation is deliberately disabled. Containers are
immutable and must be replaced with a new versioned image.

Every update script creates a MySQL backup before pulling or starting new
images. Migrations run automatically when the new application container starts.
Named volumes preserve church data and uploaded files.

### Windows

```powershell
.\docker\update.ps1 -Version 2.1.0
```

### Linux

```bash
sh docker/update.sh 2.1.0
```

Backups are written to `backups/`.

## Manual Backup

Run:

```bash
docker compose --env-file .env.docker --profile tools run --rm backup
```

Also back up uploaded files:

```bash
docker run --rm \
  -v ecclesiaos_app-storage:/source:ro \
  -v "$PWD/backups:/backups" \
  alpine \
  tar -czf /backups/ecclesiaos-storage.tar.gz -C /source .
```

Store backups outside the Docker host as part of the production backup policy.

## Restore

Stop the application services before a database restore:

```bash
docker compose --env-file .env.docker stop web app queue scheduler
docker compose --env-file .env.docker exec -T db sh -c \
  'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
  < backups/ecclesiaos-TIMESTAMP.sql
docker compose --env-file .env.docker up -d
```

Test restoration regularly. A backup that has never been restored is not a
verified backup.

## Operations

Check service health:

```bash
docker compose --env-file .env.docker ps
curl --fail http://localhost:8080/up
```

Read logs:

```bash
docker compose --env-file .env.docker logs -f web app queue scheduler
```

Restart the queue worker:

```bash
docker compose --env-file .env.docker restart queue
```

Run an Artisan command:

```bash
docker compose --env-file .env.docker exec app php artisan about
```

Scale queue workers:

```bash
docker compose --env-file .env.docker up -d --scale queue=3
```

Stop containers without deleting data:

```bash
docker compose --env-file .env.docker down
```

Never add `--volumes` unless the database, Redis, and uploaded-file volumes are
intentionally being deleted.

## Persistent Data

The stack uses these named volumes:

- `ecclesiaos_db-data`: MySQL church data
- `ecclesiaos_app-storage`: uploaded files, logos, and generated storage data
- `ecclesiaos_redis-data`: Redis persistence

Updating or recreating containers does not delete these volumes.

## Troubleshooting

Validate configuration:

```bash
docker compose --env-file .env.docker config --quiet
```

If `app` is unhealthy, inspect migrations and startup:

```bash
docker compose --env-file .env.docker logs app
```

If static assets return `404`, rebuild both images at the same version:

```bash
docker compose --env-file .env.docker build --pull app web
docker compose --env-file .env.docker up -d
```

If permissions changed after restoring storage:

```bash
docker compose --env-file .env.docker exec -u root app \
  chown -R www-data:www-data storage bootstrap/cache
```
