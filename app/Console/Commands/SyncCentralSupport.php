<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Church;
use App\Services\CentralSupportClient;
use App\Services\CentralSupportOutbox;
use App\Services\CentralSupportSettings;
use Illuminate\Console\Command;

final class SyncCentralSupport extends Command
{
    protected $signature = 'support:sync-central {--limit=100}';

    protected $description = 'Synchronize queued church support events with the central EcclesiaOS support server';

    public function handle(CentralSupportSettings $settings, CentralSupportOutbox $outbox, CentralSupportClient $client): int
    {
        $sent = 0;
        $failed = 0;

        Church::query()->each(function (Church $church) use ($settings, $outbox, $client, &$sent, &$failed): void {
            $connection = $settings->forChurch($church);
            if (! $connection['enabled'] || ! $connection['api_token_configured']) {
                return;
            }
            $result = $outbox->syncPending($client, $church->id, max(1, (int) $this->option('limit')));
            $sent += $result['sent'];
            $failed += $result['failed'];
        });

        $this->info($sent.' central support event(s) synchronized; '.$failed.' failed.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
