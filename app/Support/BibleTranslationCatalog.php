<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BibleTranslation;

final class BibleTranslationCatalog
{
    public static function ensureFreeDefaults(): void
    {
        foreach (self::definitions() as $definition) {
            BibleTranslation::query()->updateOrCreate(
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
            ['name' => "Young's Literal Translation", 'abbreviation' => 'YLT', 'language' => 'English', 'description' => 'Literal English translation by Robert Young.', 'copyright' => 'Public domain', 'source_url' => 'https://ebible.org/eng-ylt/', 'is_default' => false],
            ['name' => 'Darby Bible', 'abbreviation' => 'DARBY', 'language' => 'English', 'description' => 'English translation by John Nelson Darby.', 'copyright' => 'Public domain', 'source_url' => 'https://ebible.org/eng-dby/', 'is_default' => false],
            ['name' => 'Douay-Rheims Bible', 'abbreviation' => 'DRB', 'language' => 'English', 'description' => 'Historic English Catholic translation.', 'copyright' => 'Public domain', 'source_url' => 'https://ebible.org/eng-drcov/', 'is_default' => false],
            ['name' => 'Bible in Basic English', 'abbreviation' => 'BBE', 'language' => 'English', 'description' => 'Readable English translation using basic vocabulary.', 'copyright' => 'Public domain', 'source_url' => 'https://ebible.org/eng-bbe/', 'is_default' => false],
            ['name' => 'World English Bible British Edition', 'abbreviation' => 'WEBBE', 'language' => 'English', 'description' => 'British spelling edition of the World English Bible.', 'copyright' => 'Public domain', 'source_url' => 'https://ebible.org/eng-webbe/', 'is_default' => false],
            ['name' => 'Berean Standard Bible', 'abbreviation' => 'BSB', 'language' => 'English', 'description' => 'Modern English translation designed for reading, study, and public use.', 'copyright' => 'Public domain', 'source_url' => 'https://ebible.org/engbsb/', 'is_default' => false],
            ['name' => 'World English Bible Updated', 'abbreviation' => 'WEBU', 'language' => 'English', 'description' => 'Updated World English Bible using LORD and GOD for the Divine Name.', 'copyright' => 'Public domain', 'source_url' => 'https://ebible.org/engwebu/', 'is_default' => false],
            ['name' => 'World English Bible Protestant Edition', 'abbreviation' => 'WEBP', 'language' => 'English', 'description' => 'Modern public-domain World English Bible edition containing the 66-book Protestant canon.', 'copyright' => 'Public domain', 'source_url' => 'https://ebible.org/engwebp/', 'is_default' => false],
            ['name' => 'Noah Webster Bible', 'abbreviation' => 'WBS', 'language' => 'English', 'description' => 'Noah Webster revision of the King James Bible in American English.', 'copyright' => 'Public domain', 'source_url' => 'https://ebible.org/engwebster/', 'is_default' => false],
            ['name' => 'World Messianic Bible', 'abbreviation' => 'WMB', 'language' => 'English', 'description' => 'Messianic Jewish edition of the public-domain World English Bible.', 'copyright' => 'Public domain', 'source_url' => 'https://ebible.org/engwmb/', 'is_default' => false],
        ];
    }
}
