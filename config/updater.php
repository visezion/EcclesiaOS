<?php

$versionFile = base_path('VERSION');
$packagedVersion = is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '1.0.0';
$reloadCommand = json_decode((string) env('UPDATER_RELOAD_COMMAND_JSON', '[]'), true);
$reloadCommand = is_array($reloadCommand)
    ? array_values(array_filter($reloadCommand, fn (mixed $argument): bool => is_string($argument) && $argument !== ''))
    : [];

return [
    'enabled' => env('UPDATER_ENABLED', true),
    // Containers are immutable; Docker deployments update by replacing images.
    'install_enabled' => env('UPDATER_INSTALL_ENABLED', false) && ! env('APP_CONTAINERIZED', false),
    // This file changes with every package while the production .env remains shared.
    'current_version' => $packagedVersion,
    'repository' => env('UPDATE_REPOSITORY', 'visezion/EcclesiaOS'),
    'channel' => env('UPDATE_CHANNEL', 'stable'),
    'check_interval' => (int) env('UPDATE_CHECK_INTERVAL', 21600),
    'check_ttl_seconds' => (int) env('UPDATE_CHECK_INTERVAL', 21600),
    'github_api_url' => 'https://api.github.com',
    'github_token' => env('GITHUB_UPDATE_TOKEN'),
    'require_immutable' => env('UPDATE_REQUIRE_IMMUTABLE', true),
    'manifest_asset' => env('UPDATE_MANIFEST_ASSET', 'update-manifest.json'),
    'max_download_bytes' => (int) env('UPDATE_MAX_DOWNLOAD_BYTES', 524288000),
    'max_expanded_bytes' => (int) env('UPDATE_MAX_EXPANDED_BYTES', 1073741824),
    'request_timeout' => (int) env('UPDATE_REQUEST_TIMEOUT', 30),
    'request_timeout_seconds' => (int) env('UPDATE_REQUEST_TIMEOUT', 30),
    'current_link' => env('UPDATER_CURRENT_LINK'),
    'releases_path' => env('UPDATER_RELEASES_PATH'),
    'shared_path' => env('UPDATER_SHARED_PATH'),
    'backup_storage' => env('UPDATER_BACKUP_STORAGE', true),
    'backup_retention' => (int) env('UPDATER_BACKUP_RETENTION', 5),
    'health_url' => env('UPDATER_HEALTH_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/up'),
    'php_binary' => env('UPDATER_PHP_BINARY', PHP_BINARY),
    'reload_command' => $reloadCommand,
    'mysqldump_path' => env('UPDATER_MYSQLDUMP_BINARY', 'mysqldump'),
    'pg_dump_path' => env('UPDATER_PG_DUMP_BINARY', 'pg_dump'),
];
