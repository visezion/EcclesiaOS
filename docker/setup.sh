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

if ! docker compose --env-file "$environment_path" exec -T app \
    php artisan app:bootstrap-admin --check --no-interaction >/dev/null 2>&1; then
    if [ -t 0 ]; then
        printf 'First administrator name [Church Administrator]: '
        IFS= read -r administrator_name
        administrator_name="${administrator_name:-Church Administrator}"

        printf 'First administrator email: '
        IFS= read -r administrator_email

        printf 'First administrator password (12+ characters, mixed case and number): '
        trap 'stty echo' 0 1 2 15
        stty -echo
        IFS= read -r administrator_password
        stty echo
        trap - 0 1 2 15
        printf '\n'

        docker compose --env-file "$environment_path" exec -T \
            -e "BOOTSTRAP_ADMIN_NAME=$administrator_name" \
            -e "BOOTSTRAP_ADMIN_EMAIL=$administrator_email" \
            -e "BOOTSTRAP_ADMIN_PASSWORD=$administrator_password" \
            app php artisan app:bootstrap-admin --no-interaction
        unset administrator_password
    else
        echo "No administrator exists. Run 'php artisan app:bootstrap-admin' in the app container."
    fi
fi

echo "EcclesiaOS is running at the APP_URL configured in .env.docker."
