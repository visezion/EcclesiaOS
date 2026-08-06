<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MemberImport;
use App\Services\MemberImport\MemberImportProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProcessMemberImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(public readonly int $importId)
    {
        $this->onQueue('imports');
    }

    public function handle(MemberImportProcessor $processor): void
    {
        $import = MemberImport::query()->findOrFail($this->importId);
        $processor->process($import);
    }
}
