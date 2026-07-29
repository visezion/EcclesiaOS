<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SystemUpdate;
use App\Services\Updates\UpdateInstaller;
use Illuminate\Console\Command;
use Throwable;

final class InstallSystemUpdate extends Command
{
    protected $signature = 'app:update {version? : Approved version to install} {--pending : Install the oldest approved update}';

    protected $description = 'Install an approved application update';

    public function handle(UpdateInstaller $installer): int
    {
        try {
            if ($this->argument('version')) {
                $version = ltrim((string) $this->argument('version'), 'vV');
                $update = SystemUpdate::query()->where('version', $version)->latest()->firstOrFail();
                $installer->install($update);
            } else {
                $update = $installer->installPending();
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $update) {
            $this->info('No approved update is waiting.');

            return self::SUCCESS;
        }

        $this->info("Version {$update->version} was installed.");

        return self::SUCCESS;
    }
}
