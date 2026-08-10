<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Communications\CelebrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class DispatchCelebrations extends Command
{
    protected $signature = 'celebrations:dispatch {--church= : Dispatch one church only} {--date= : Date in Y-m-d format, primarily for testing}';

    protected $description = 'Send configured birthday and wedding anniversary celebrations';

    public function handle(CelebrationService $celebrations): int
    {
        $date = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->setTimeFrom(now())
            : now();
        $summary = $celebrations->dispatchDue(
            $this->option('church') ? (int) $this->option('church') : null,
            $date,
        );
        $this->info('Celebrations processed: '.json_encode($summary));

        return self::SUCCESS;
    }
}
