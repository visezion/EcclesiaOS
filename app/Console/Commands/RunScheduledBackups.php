<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BackupSchedule;
use App\Services\Backups\BackupManager;
use App\Services\Backups\BackupRetentionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class RunScheduledBackups extends Command
{
    protected $signature = 'backups:run-scheduled';

    protected $description = 'Queue due church backups and apply their retention policies';

    public function handle(BackupManager $manager, BackupRetentionService $retention): int
    {
        BackupSchedule::query()->where('enabled', true)->with('church')->each(function (BackupSchedule $schedule) use ($manager, $retention): void {
            $now = now($schedule->timezone);
            if (! $this->isDue($schedule, $now)) {
                return;
            }
            $manager->create($schedule->church, $schedule->backup_type, null, 'automatic', $schedule->destination_ids ?? []);
            $schedule->forceFill(['last_started_at' => now(), 'next_run_at' => $this->nextRun($schedule, $now)->utc()])->save();
            $retention->prune($schedule, $manager);
            $this->info('Queued '.$schedule->backup_type.' backup for '.$schedule->church->name.'.');
        });

        return self::SUCCESS;
    }

    private function isDue(BackupSchedule $schedule, Carbon $now): bool
    {
        if ($schedule->frequency === 'manual') {
            return false;
        }
        if ($schedule->next_run_at !== null) {
            return $schedule->next_run_at->isPast();
        }
        [$hour, $minute] = array_map('intval', explode(':', (string) $schedule->run_at));
        if ($now->format('H:i') !== sprintf('%02d:%02d', $hour, $minute)) {
            return false;
        }

        return $schedule->frequency !== 'custom'
            || in_array(strtolower($now->format('l')), $schedule->days ?? [], true);
    }

    private function nextRun(BackupSchedule $schedule, Carbon $now): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', (string) $schedule->run_at));
        $candidate = $now->copy()->setTime($hour, $minute)->addDay();

        return match ($schedule->frequency) {
            'weekly' => $now->copy()->setTime($hour, $minute)->addWeek(),
            'monthly' => $now->copy()->setTime($hour, $minute)->addMonthNoOverflow(),
            'custom' => $this->nextCustomDay($candidate, $schedule->days ?? []),
            default => $candidate,
        };
    }

    /** @param array<int, string> $days */
    private function nextCustomDay(Carbon $candidate, array $days): Carbon
    {
        for ($attempt = 0; $attempt < 7; $attempt++) {
            if (in_array(strtolower($candidate->format('l')), $days, true)) {
                return $candidate;
            }
            $candidate->addDay();
        }

        return $candidate;
    }
}
