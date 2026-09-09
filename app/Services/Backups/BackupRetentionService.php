<?php

declare(strict_types=1);

namespace App\Services\Backups;

use App\Models\BackupRecord;
use App\Models\BackupSchedule;
use Illuminate\Support\Collection;

final class BackupRetentionService
{
    public function prune(BackupSchedule $schedule, BackupManager $manager): int
    {
        $records = BackupRecord::query()->where('church_id', $schedule->church_id)->where('status', 'completed')->oldest('completed_at')->get();
        $keep = collect()
            ->merge($this->latestPerPeriod($records, 'day', (int) $schedule->keep_daily))
            ->merge($this->latestPerPeriod($records, 'week', (int) $schedule->keep_weekly))
            ->merge($this->latestPerPeriod($records, 'month', (int) $schedule->keep_monthly))
            ->merge($this->latestPerPeriod($records, 'year', (int) $schedule->keep_yearly))
            ->unique()->all();
        $deleted = 0;
        $records->whereNotIn('id', $keep)->each(function (BackupRecord $record) use ($manager, &$deleted): void {
            $manager->delete($record);
            $deleted++;
        });

        return $deleted;
    }

    /** @return array<int, int> */
    private function latestPerPeriod(Collection $records, string $period, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }
        $format = match ($period) {
            'day' => 'Y-m-d', 'week' => 'o-W', 'month' => 'Y-m', default => 'Y',
        };

        return $records->sortByDesc('completed_at')->groupBy(fn (BackupRecord $record): string => $record->completed_at?->format($format) ?? '')
            ->take($limit)->map(fn (Collection $group): int => (int) $group->first()->id)->values()->all();
    }
}
