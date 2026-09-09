<?php

return [
    'format_version' => 1,
    'disk' => env('BACKUP_DISK', 'local'),
    'root' => trim(env('BACKUP_ROOT', 'backups'), '/'),
    'chunk_size' => (int) env('BACKUP_UPLOAD_CHUNK_SIZE', 10 * 1024 * 1024),
    'max_chunk_size' => (int) env('BACKUP_UPLOAD_MAX_CHUNK_SIZE', 100 * 1024 * 1024),
    'upload_expiry_hours' => (int) env('BACKUP_UPLOAD_EXPIRY_HOURS', 48),
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY', env('APP_KEY')),
    'excluded_tables' => ['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions'],
    'excluded_storage_prefixes' => ['backups/', 'backup-work/', 'restore-uploads/', 'framework/', 'logs/'],
];
