<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BibleReadingPlan;
use App\Models\BibleReadingPlanDay;
use Illuminate\Support\Collection;

final class BibleReadingPlanDefaults
{
    private const BOOKS = [
        'Genesis' => 50, 'Exodus' => 40, 'Leviticus' => 27, 'Numbers' => 36, 'Deuteronomy' => 34,
        'Joshua' => 24, 'Judges' => 21, 'Ruth' => 4, '1 Samuel' => 31, '2 Samuel' => 24,
        '1 Kings' => 22, '2 Kings' => 25, '1 Chronicles' => 29, '2 Chronicles' => 36, 'Ezra' => 10,
        'Nehemiah' => 13, 'Esther' => 10, 'Job' => 42, 'Psalms' => 150, 'Proverbs' => 31,
        'Ecclesiastes' => 12, 'Song of Solomon' => 8, 'Isaiah' => 66, 'Jeremiah' => 52, 'Lamentations' => 5,
        'Ezekiel' => 48, 'Daniel' => 12, 'Hosea' => 14, 'Joel' => 3, 'Amos' => 9,
        'Obadiah' => 1, 'Jonah' => 4, 'Micah' => 7, 'Nahum' => 3, 'Habakkuk' => 3,
        'Zephaniah' => 3, 'Haggai' => 2, 'Zechariah' => 14, 'Malachi' => 4, 'Matthew' => 28,
        'Mark' => 16, 'Luke' => 24, 'John' => 21, 'Acts' => 28, 'Romans' => 16,
        '1 Corinthians' => 16, '2 Corinthians' => 13, 'Galatians' => 6, 'Ephesians' => 6, 'Philippians' => 4,
        'Colossians' => 4, '1 Thessalonians' => 5, '2 Thessalonians' => 3, '1 Timothy' => 6, '2 Timothy' => 4,
        'Titus' => 3, 'Philemon' => 1, 'Hebrews' => 13, 'James' => 5, '1 Peter' => 5,
        '2 Peter' => 3, '1 John' => 5, '2 John' => 1, '3 John' => 1, 'Jude' => 1, 'Revelation' => 22,
    ];

    public static function ensure(): void
    {
        BibleReadingPlan::query()->whereNull('church_id')->withCount('days')->get()->each(function (BibleReadingPlan $plan): void {
            if ($plan->days_count > 0) {
                return;
            }

            $chapters = match ($plan->category) {
                'New Testament' => self::chapters(array_slice(self::BOOKS, 39, null, true)),
                'Psalms' => self::chapters(['Psalms' => 150]),
                default => self::chapters(self::BOOKS),
            };
            $days = self::distribute($chapters, max(1, min($plan->duration_days, $chapters->count())));
            $now = now();
            $rows = collect($days)->map(fn (array $day): array => [
                ...$day,
                'bible_reading_plan_id' => $plan->id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            BibleReadingPlanDay::query()->upsert($rows, ['bible_reading_plan_id', 'day_number'], ['title', 'passages', 'reflection', 'updated_at']);
            $plan->update(['duration_days' => count($days)]);
        });
    }

    private static function chapters(array $books): Collection
    {
        return collect($books)->flatMap(fn (int $count, string $book): array => array_map(
            fn (int $chapter): array => ['book' => $book, 'chapter' => $chapter],
            range(1, $count),
        ))->values();
    }

    private static function distribute(Collection $chapters, int $dayCount): array
    {
        $total = $chapters->count();
        $days = [];
        for ($day = 0; $day < $dayCount; $day++) {
            $start = (int) floor(($day * $total) / $dayCount);
            $end = max($start, (int) floor((($day + 1) * $total) / $dayCount) - 1);
            $portion = $chapters->slice($start, $end - $start + 1)->values();
            $passages = $portion->groupBy('book')->map(function (Collection $items, string $book): string {
                $first = (int) $items->first()['chapter'];
                $last = (int) $items->last()['chapter'];

                return $book.' '.$first.($last > $first ? '-'.$last : '');
            })->implode('; ');
            $days[] = [
                'day_number' => $day + 1,
                'title' => 'Day '.($day + 1).' Reading',
                'passages' => $passages,
                'reflection' => null,
            ];
        }

        return $days;
    }
}
