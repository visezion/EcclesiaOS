<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BibleTranslation;

final class BibleTranslationCatalog
{
    public static function ensureFreeDefaults(): void
    {
        foreach (self::definitions() as $definition) {
            BibleTranslation::query()->firstOrCreate(
                ['church_id' => null, 'abbreviation' => $definition['abbreviation']],
                $definition + ['created_by' => null, 'status' => 'active'],
            );
        }
    }

    public static function definitions(): array
    {
        return [
            ['name' => 'King James Version', 'abbreviation' => 'KJV', 'language' => 'English', 'description' => 'Classic English translation in the public domain.', 'copyright' => 'Public domain', 'source_url' => 'https://www.gutenberg.org/ebooks/10', 'is_default' => true],
            ['name' => 'American Standard Version', 'abbreviation' => 'ASV', 'language' => 'English', 'description' => 'Faithful English translation published in 1901.', 'copyright' => 'Public domain in the United States', 'source_url' => 'https://ebible.org/eng-asv/', 'is_default' => false],
            ['name' => 'World English Bible', 'abbreviation' => 'WEB', 'language' => 'English', 'description' => 'Modern English translation released for free use.', 'copyright' => 'Public domain', 'source_url' => 'https://worldenglish.bible/', 'is_default' => false],
            ['name' => 'Young’s Literal Translation', 'abbreviation' => 'YLT', 'language' => 'English', 'description' => 'Literal English translation by Robert Young.', 'copyright' => 'Public domain', 'source_url' => 'https://ebible.org/eng-ylt/', 'is_default' => false],
            ['name' => 'Darby Bible', 'abbreviation' => 'DARBY', 'language' => 'English', 'description' => 'English translation by John Nelson Darby.', 'copyright' => 'Public domain', 'source_url' => 'https://ebible.org/eng-dby/', 'is_default' => false],
            ['name' => 'Douay-Rheims Bible', 'abbreviation' => 'DRB', 'language' => 'English', 'description' => 'Historic English Catholic translation.', 'copyright' => 'Public domain', 'source_url' => 'https://ebible.org/eng-drcov/', 'is_default' => false],
            ['name' => 'Bible in Basic English', 'abbreviation' => 'BBE', 'language' => 'English', 'description' => 'Readable English translation using basic vocabulary.', 'copyright' => 'Public domain', 'source_url' => 'https://ebible.org/eng-bbe/', 'is_default' => false],
            ['name' => 'World English Bible British Edition', 'abbreviation' => 'WEBBE', 'language' => 'English', 'description' => 'British spelling edition of the World English Bible.', 'copyright' => 'Public domain', 'source_url' => 'https://ebible.org/eng-webbe/', 'is_default' => false],
        ];
    }
}
