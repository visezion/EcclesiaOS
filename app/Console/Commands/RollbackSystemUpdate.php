<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SystemUpdate;
use App\Services\Updates\UpdateInstaller;
use Illuminate\Console\Command;
use Throwable;

final class RollbackSystemUpdate extends Command
{
    protected $signature = 'app:update-rollback {version? : Installed version to roll back}';

    protected $description = 'Switch code back to the release used before an installed update';

    public function handle(UpdateInstaller $installer): int
    {
        $version = $this->argument('version');
        $query = SystemUpdate::query()->where('status', 'completed');
        if ($version) {
            $query->where('version', ltrim((string) $version, 'vV'));
        }

        $update = $query->latest('installed_at')->first();
        if (! $update) {
            $this->error('No completed update is available for rollback.');

            return self::FAILURE;
        }

        try {
            $installer->rollback($update);
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->warn("Code was rolled back from version {$update->version}. Database migrations were not reversed.");

        return self::SUCCESS;
    }
}
