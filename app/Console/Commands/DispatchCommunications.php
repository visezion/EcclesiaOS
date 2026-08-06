<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationDelivery;
use App\Services\Communications\CommunicationCampaignDispatcher;
use App\Services\Communications\CommunicationDeliveryDispatcher;
use App\Services\Communications\NotificationAutomationRunner;
use Illuminate\Console\Command;

final class DispatchCommunications extends Command
{
    protected $signature = 'communications:dispatch {--limit=100}';

    protected $description = 'Dispatch due communication campaigns and queued delivery retries';

    public function handle(CommunicationCampaignDispatcher $campaigns, CommunicationDeliveryDispatcher $deliveries, NotificationAutomationRunner $automation): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $automation->runDueReminders($limit);

        CommunicationCampaign::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get()
            ->each(fn (CommunicationCampaign $campaign) => $campaigns->dispatch($campaign));

        CommunicationDelivery::query()
            ->where('status', 'queued')
            ->where(fn ($query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(fn (CommunicationDelivery $delivery) => $deliveries->dispatch($delivery));

        return self::SUCCESS;
    }
}
