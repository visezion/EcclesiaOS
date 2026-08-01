<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BibleVerseDiffer;
use PHPUnit\Framework\TestCase;

final class BibleVerseDifferTest extends TestCase
{
    public function test_it_marks_words_that_differ_from_the_baseline(): void
    {
        $difference = (new BibleVerseDiffer)->compare(
            'For God so loved the world',
            'For God loved the whole world',
        );

        $changedWords = collect($difference['tokens'])
            ->where('different', true)
            ->pluck('text')
            ->all();

        $this->assertSame(['whole'], $changedWords);
        $this->assertLessThan(100, $difference['similarity']);
    }

    public function test_identical_text_has_no_differences(): void
    {
        $difference = (new BibleVerseDiffer)->compare('Jesus wept.', 'Jesus wept.');

        $this->assertSame(100, $difference['similarity']);
        $this->assertSame(0, $difference['different_count']);
        $this->assertNotContains(true, collect($difference['tokens'])->pluck('different')->all());
    }
}
