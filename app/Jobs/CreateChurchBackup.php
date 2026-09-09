<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BackupRecord;
use App\Services\Backups\BackupManager;
use App\Services\Backups\BackupPackageBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class CreateChurchBackup implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public int $tries = 1;

    public function __construct(public readonly int $backupId)
    {
        $this->onQueue('backups');
    }

    public function handle(BackupManager $manager, BackupPackageBuilder $builder): void
    {
        $manager->process(BackupRecord::query()->findOrFail($this->backupId), $builder);
    }
}
