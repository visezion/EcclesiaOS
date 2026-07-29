#!/usr/bin/env sh
set -eu

if [ "$#" -ne 1 ]; then
    echo "Usage: sh docker/update.sh <version>" >&2
    exit 1
fi

version="$1"
if ! printf '%s' "$version" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z.-]+)?$'; then
    echo "Version must use semantic versioning, for example 2.1.0." >&2
    exit 1
fi

project_root="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
environment_path="$project_root/.env.docker"

cd "$project_root"

if [ ! -f "$environment_path" ]; then
    echo "Run docker/setup.sh before updating." >&2
    exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
    echo "Docker Engine with Compose v2 is required." >&2
    exit 1
fi

current_version="$(sed -n 's/^ECCLESIAOS_VERSION=//p' "$environment_path" | head -n 1)"
echo "Creating a database backup before updating ${current_version:-unknown} to $version..."
docker compose --env-file "$environment_path" --profile tools run --rm backup

ECCLESIAOS_VERSION="$version" docker compose --env-file "$environment_path" pull app web queue scheduler

temporary_environment="${environment_path}.tmp"
sed "s/^ECCLESIAOS_VERSION=.*/ECCLESIAOS_VERSION=${version}/" "$environment_path" > "$temporary_environment"
mv "$temporary_environment" "$environment_path"
chmod 600 "$environment_path"

docker compose --env-file "$environment_path" up -d --no-build --remove-orphans
docker compose --env-file "$environment_path" exec -T app php artisan about

echo "EcclesiaOS was updated to $version. The pre-update SQL backup is in backups/."
