#!/usr/bin/env sh
set -eu

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is required. Run docker/setup.sh or docker/setup.ps1 first." >&2
    exit 1
fi

mkdir -p \
    bootstrap/cache \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

bootstrap_managed_layout() {
    if [ "${APP_CONTAINERIZED:-false}" != "true" ]; then
        return
    fi

    managed_root="${UPDATER_MANAGED_ROOT:-/var/www/html/.managed}"
    releases_path="${UPDATER_RELEASES_PATH:-$managed_root/releases}"
    shared_path="${UPDATER_SHARED_PATH:-$managed_root/shared}"
    current_link="${UPDATER_CURRENT_LINK:-$managed_root/current}"
    version_file="/var/www/html/VERSION"
    release_version="$(tr -d '\r\n' < "$version_file" 2>/dev/null || true)"
    release_name="v${release_version:-1.0.0}"
    release_path="$releases_path/$release_name"

    mkdir -p \
        "$managed_root" \
        "$releases_path" \
        "$shared_path" \
        "$shared_path/storage/app/public" \
        "$shared_path/updates" \
        "$shared_path/backups" \
        "$release_path"

    if [ ! -f "$shared_path/.env" ] && [ -f /var/www/html/.env ]; then
        cp /var/www/html/.env "$shared_path/.env"
    fi

    if [ ! -d "$shared_path/storage" ]; then
        mkdir -p "$shared_path/storage/app/public"
    fi

    if [ ! -L "$current_link" ]; then
        rm -f "$current_link"
        ln -s "$release_path" "$current_link"
    fi

    if [ "$(id -u)" = "0" ]; then
        chown -R www-data:www-data \
            "$managed_root" \
            bootstrap/cache \
            storage/app
    fi
}

bootstrap_managed_layout

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data bootstrap/cache

    if [ ! -f storage/app/.docker-permissions-ready ]; then
        chown -R www-data:www-data storage/app
        touch storage/app/.docker-permissions-ready
        chown www-data:www-data storage/app/.docker-permissions-ready
    fi
fi

run_as_app() {
    if [ "$(id -u)" = "0" ]; then
        gosu www-data "$@"
    else
        "$@"
    fi
}

wait_for_database() {
    attempts=0
    max_attempts="${STARTUP_MAX_ATTEMPTS:-60}"

    until php -r '
        $driver = getenv("DB_CONNECTION") ?: "mysql";

        if ($driver === "sqlite") {
            exit(0);
        }

        $host = getenv("DB_HOST") ?: "db";
        $port = getenv("DB_PORT") ?: ($driver === "pgsql" ? "5432" : "3306");
        $database = getenv("DB_DATABASE") ?: "ecclesiaos";
        $username = getenv("DB_USERNAME") ?: "ecclesiaos";
        $password = getenv("DB_PASSWORD") ?: "";
        $dsn = sprintf("%s:host=%s;port=%s;dbname=%s", $driver, $host, $port, $database);

        try {
            new PDO($dsn, $username, $password, [PDO::ATTR_TIMEOUT => 3]);
        } catch (Throwable) {
            exit(1);
        }
    ' >/dev/null 2>&1; do
        attempts=$((attempts + 1))
        if [ "$attempts" -ge "$max_attempts" ]; then
            echo "Database did not become ready after ${max_attempts} attempts." >&2
            exit 1
        fi

        echo "Waiting for the database (${attempts}/${max_attempts})..."
        sleep 2
    done
}

wait_for_redis() {
    attempts=0
    max_attempts="${STARTUP_MAX_ATTEMPTS:-60}"

    until php -r '
        try {
            $redis = new Redis();
            $redis->connect(getenv("REDIS_HOST") ?: "redis", (int) (getenv("REDIS_PORT") ?: 6379), 3);
            $password = getenv("REDIS_PASSWORD");
            if ($password !== false && $password !== "" && $password !== "null") {
                $redis->auth($password);
            }
            $redis->ping();
        } catch (Throwable) {
            exit(1);
        }
    ' >/dev/null 2>&1; do
        attempts=$((attempts + 1))
        if [ "$attempts" -ge "$max_attempts" ]; then
            echo "Redis did not become ready after ${max_attempts} attempts." >&2
            exit 1
        fi

        echo "Waiting for Redis (${attempts}/${max_attempts})..."
        sleep 2
    done
}

if [ "${WAIT_FOR_DATABASE:-true}" = "true" ]; then
    wait_for_database
fi

if [ "${WAIT_FOR_REDIS:-true}" = "true" ]; then
    wait_for_redis
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    run_as_app php artisan migrate --force --isolated
fi

if [ "${RUN_OPTIMIZATIONS:-true}" = "true" ]; then
    run_as_app php artisan optimize
fi

if [ "$(id -u)" = "0" ]; then
    case "$1" in
        php-fpm|php-fpm*)
            exec "$@"
            ;;
    esac

    exec gosu www-data "$@"
fi

exec "$@"
