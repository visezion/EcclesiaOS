<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BibleTranslation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

final class BibleFreeTranslationInstaller
{
    public static function sources(): array
    {
        return [
            'KJV' => 'https://ebible.org/Scriptures/eng-kjv_vpl.zip',
            'ASV' => 'https://ebible.org/Scriptures/eng-asv_vpl.zip',
            'WEB' => 'https://ebible.org/Scriptures/eng-web_vpl.zip',
            'YLT' => 'https://ebible.org/Scriptures/engylt_readaloud.zip',
            'DARBY' => 'https://ebible.org/Scriptures/engDBY_vpl.zip',
            'DRB' => 'https://ebible.org/Scriptures/engDRA_vpl.zip',
            'BBE' => 'https://ebible.org/Scriptures/engBBE_vpl.zip',
            'WEBBE' => 'https://ebible.org/Scriptures/eng-webbe_vpl.zip',
            'BSB' => 'https://ebible.org/Scriptures/engbsb_vpl.zip',
            'WEBU' => 'https://ebible.org/Scriptures/engwebu_vpl.zip',
            'WEBP' => 'https://ebible.org/Scriptures/engwebp_vpl.zip',
            'WBS' => 'https://ebible.org/Scriptures/engwebster_vpl.zip',
            'WMB' => 'https://ebible.org/Scriptures/engwmb_vpl.zip',
        ];
    }

    public static function localPath(string $abbreviation): string
    {
        return 'bible/free/'.strtoupper($abbreviation).'.txt';
    }

    public static function download(string $abbreviation, bool $force = false): int
    {
        $abbreviation = strtoupper($abbreviation);
        $path = self::localPath($abbreviation);
        if (! $force && Storage::disk('local')->exists($path)) {
            return 0;
        }

        abort_unless(class_exists(ZipArchive::class), 503, 'The PHP ZIP extension is required to download Bible translations.');

        try {
            $response = Http::connectTimeout(15)->timeout(180)->get(self::sources()[$abbreviation] ?? abort(422, 'No download source configured.'));
        } catch (ConnectionException) {
            abort(502, 'The free translation provider could not be reached.');
        }

        abort_unless($response->successful(), 502, 'The free translation archive could not be downloaded.');
        $temporary = @tempnam(sys_get_temp_dir(), 'bible-');
        abort_unless(is_string($temporary), 503, 'A temporary file could not be created for the translation archive.');
        abort_unless(@file_put_contents($temporary, $response->body()) !== false, 503, 'The translation archive could not be written to temporary storage.');
        $zip = new ZipArchive;
        if ($zip->open($temporary) !== true) {
            @unlink($temporary);
            abort(422, 'The translation archive is invalid.');
        }
        $text = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (Str::endsWith(strtolower($name), '.txt') && ! Str::contains($name, 'about')) {
                $text .= $zip->getFromIndex($i)."\n";
            }
        }
        $zip->close();
        @unlink($temporary);
        abort_if(trim($text) === '', 422, 'The archive did not contain readable verse text.');
        abort_unless(Storage::disk('local')->put($path, $text), 503, 'The translation file could not be saved to application storage.');

        return substr_count($text, "\n");
    }

    public static function install(BibleTranslation $translation): int
    {
        $path = self::localPath($translation->abbreviation);
        abort_unless(Storage::disk('local')->exists($path), 422, 'This translation is not downloaded yet. Run php artisan bible:download-free.');
        $books = self::books();
        $newTestament = ['MAT', 'MAR', 'MRK', 'LUK', 'JOH', 'JHN', 'ACT', 'ROM', '1CO', '2CO', 'GAL', 'EPH', 'PHI', 'PHP', 'COL', '1TH', '2TH', '1TI', '2TI', 'TIT', 'PHM', 'HEB', 'JAM', 'JAS', '1PE', '2PE', '1JO', '2JO', '3JO', '1JN', '2JN', '3JN', 'JUD', 'REV'];
        $rows = [];
        foreach (preg_split('/\R/', Storage::disk('local')->get($path)) as $line) {
            if (preg_match('/^([A-Z0-9]{3})\s+(\d+):(\d+)\s+(.+)$/', trim($line), $match) !== 1 || ! isset($books[$match[1]])) {
                continue;
            }
            $book = $books[$match[1]];
            $rows[] = ['book' => $book, 'book_slug' => Str::slug($book), 'testament' => in_array($match[1], $newTestament, true) ? 'new' : 'old', 'chapter' => (int) $match[2], 'verse' => (int) $match[3], 'text' => trim($match[4]), 'created_at' => now(), 'updated_at' => now()];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            $translation->verses()->upsert($chunk, ['bible_translation_id', 'book_slug', 'chapter', 'verse'], ['book', 'testament', 'text', 'updated_at']);
        }

        return count($rows);
    }

    private static function books(): array
    {
        return ['GEN' => 'Genesis', 'EXO' => 'Exodus', 'LEV' => 'Leviticus', 'NUM' => 'Numbers', 'DEU' => 'Deuteronomy', 'JOS' => 'Joshua', 'JDG' => 'Judges', 'RUT' => 'Ruth', '1SA' => '1 Samuel', '2SA' => '2 Samuel', '1KI' => '1 Kings', '2KI' => '2 Kings', '1CH' => '1 Chronicles', '2CH' => '2 Chronicles', 'EZR' => 'Ezra', 'NEH' => 'Nehemiah', 'EST' => 'Esther', 'JOB' => 'Job', 'PSA' => 'Psalms', 'PRO' => 'Proverbs', 'ECC' => 'Ecclesiastes', 'SNG' => 'Song of Solomon', 'SOL' => 'Song of Solomon', 'ISA' => 'Isaiah', 'JER' => 'Jeremiah', 'LAM' => 'Lamentations', 'EZK' => 'Ezekiel', 'EZE' => 'Ezekiel', 'DAN' => 'Daniel', 'HOS' => 'Hosea', 'JOL' => 'Joel', 'JOE' => 'Joel', 'AMO' => 'Amos', 'OBA' => 'Obadiah', 'JON' => 'Jonah', 'MIC' => 'Micah', 'NAM' => 'Nahum', 'NAH' => 'Nahum', 'HAB' => 'Habakkuk', 'ZEP' => 'Zephaniah', 'HAG' => 'Haggai', 'ZEC' => 'Zechariah', 'MAL' => 'Malachi', 'MAT' => 'Matthew', 'MAR' => 'Mark', 'MRK' => 'Mark', 'LUK' => 'Luke', 'JOH' => 'John', 'JHN' => 'John', 'ACT' => 'Acts', 'ROM' => 'Romans', '1CO' => '1 Corinthians', '2CO' => '2 Corinthians', 'GAL' => 'Galatians', 'EPH' => 'Ephesians', 'PHI' => 'Philippians', 'PHP' => 'Philippians', 'COL' => 'Colossians', '1TH' => '1 Thessalonians', '2TH' => '2 Thessalonians', '1TI' => '1 Timothy', '2TI' => '2 Timothy', 'TIT' => 'Titus', 'PHM' => 'Philemon', 'HEB' => 'Hebrews', 'JAM' => 'James', 'JAS' => 'James', '1PE' => '1 Peter', '2PE' => '2 Peter', '1JO' => '1 John', '2JO' => '2 John', '3JO' => '3 John', '1JN' => '1 John', '2JN' => '2 John', '3JN' => '3 John', 'JUD' => 'Jude', 'REV' => 'Revelation'];
    }
}
