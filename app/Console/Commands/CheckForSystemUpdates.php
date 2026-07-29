<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Updates\UpdateManager;
use Illuminate\Console\Command;
use Throwable;

final class CheckForSystemUpdates extends Command
{
    protected $signature = 'app:update-check {--force : Ignore the cached GitHub response}';

    protected $description = 'Check GitHub Releases for a newer application version';

    public function handle(UpdateManager $manager): int
    {
        try {
            $update = $manager->check((bool) $this->option('force'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $update) {
            $this->info('The application is up to date.');

            return self::SUCCESS;
        }

        $this->info("Version {$update->version} is available.");

        return self::SUCCESS;
    }
}
