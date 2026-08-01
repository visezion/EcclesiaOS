<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\BibleTranslation;
use App\Support\Utf8Text;
use Tests\TestCase;

final class Utf8TextTest extends TestCase
{
    public function test_it_repairs_repeated_utf8_mojibake(): void
    {
        $expected = 'Young'."\u{2019}".'s Literal Translation'."\u{00A0}".'Study Edition';
        $corrupted = $expected;

        for ($pass = 0; $pass < 4; $pass++) {
            $corrupted = mb_convert_encoding($corrupted, 'UTF-8', 'Windows-1252');
        }

        $this->assertNotSame($expected, $corrupted);
        $this->assertSame($expected, Utf8Text::repair($corrupted));
    }

    public function test_bible_translation_names_are_repaired_when_assigned(): void
    {
        $expected = 'Version'."\u{2014}".'Name';
        $corrupted = mb_convert_encoding($expected, 'UTF-8', 'Windows-1252');
        $translation = new BibleTranslation;
        $translation->name = $corrupted;

        $this->assertSame($expected, $translation->name);
        $this->assertSame($expected, $translation->getAttributes()['name']);
    }
}
