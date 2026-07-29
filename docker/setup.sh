#!/usr/bin/env sh
set -eu

project_root="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
environment_path="$project_root/.env.docker"
example_path="$project_root/.env.docker.example"

cd "$project_root"

if ! command -v docker >/dev/null 2>&1; then
    echo "Docker Engine with Compose v2 is required." >&2
    exit 1
fi

if ! docker compose version >/dev/null 2>&1; then
    echo "Docker Compose v2 is required." >&2
    exit 1
fi

if [ ! -f "$environment_path" ]; then
    cp "$example_path" "$environment_path"

    app_key="$(openssl rand -base64 32 | tr -d '\n')"
    database_password="$(openssl rand -hex 24)"
    root_password="$(openssl rand -hex 32)"

    sed -i.bak \
        -e "s|^APP_KEY=.*|APP_KEY=base64:${app_key}|" \
        -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${database_password}|" \
        -e "s|^MYSQL_PASSWORD=.*|MYSQL_PASSWORD=${database_password}|" \
        -e "s|^MYSQL_ROOT_PASSWORD=.*|MYSQL_ROOT_PASSWORD=${root_password}|" \
        "$environment_path"
    rm -f "${environment_path}.bak"

    chmod 600 "$environment_path"
    echo "Created .env.docker with generated application and database secrets."
fi

docker compose --env-file "$environment_path" config --quiet

if [ "${1:-}" = "--registry" ]; then
    docker compose --env-file "$environment_path" pull
    docker compose --env-file "$environment_path" up -d --no-build --remove-orphans
else
    docker compose --env-file "$environment_path" up -d --build --remove-orphans
fi

docker compose --env-file "$environment_path" exec -T app php artisan about
echo "EcclesiaOS is running at the APP_URL configured in .env.docker."

