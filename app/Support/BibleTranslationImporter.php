<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BibleTranslation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BibleTranslationImporter
{
    private const NEW_TESTAMENT_BOOKS = [
        'matthew', 'mark', 'luke', 'john', 'acts', 'romans', '1 corinthians', '2 corinthians',
        'galatians', 'ephesians', 'philippians', 'colossians', '1 thessalonians', '2 thessalonians',
        '1 timothy', '2 timothy', 'titus', 'philemon', 'hebrews', 'james', '1 peter', '2 peter',
        '1 john', '2 john', '3 john', 'jude', 'revelation',
    ];

    public function import(BibleTranslation $translation, string $path): int
    {
        $rows = $this->rowsFromFile($path);

        DB::transaction(function () use ($translation, $rows): void {
            foreach (array_chunk($rows, 500) as $chunk) {
                $translation->verses()->upsert(
                    $chunk,
                    ['bible_translation_id', 'book_slug', 'chapter', 'verse'],
                    ['book', 'testament', 'text', 'updated_at'],
                );
            }
        });

        return count($rows);
    }

    public function rowsFromFile(string $path): array
    {
        $contents = file_get_contents($path) ?: '';
        $decoded = json_decode($contents, true);
        $source = is_array($decoded) ? $decoded : $this->csvRows($path);

        return collect($source)->map(function (array $row): ?array {
            $book = trim((string) ($row['book'] ?? ''));
            $chapter = (int) ($row['chapter'] ?? 0);
            $verse = (int) ($row['verse'] ?? 0);
            $text = trim((string) ($row['text'] ?? $row['content'] ?? ''));

            if ($book === '' || $chapter < 1 || $verse < 1 || $text === '') {
                return null;
            }

            return [
                'book' => $book,
                'book_slug' => Str::slug($book),
                'testament' => in_array(strtolower($book), self::NEW_TESTAMENT_BOOKS, true) ? 'new' : 'old',
                'chapter' => $chapter,
                'verse' => $verse,
                'text' => $text,
                'updated_at' => now(),
                'created_at' => now(),
            ];
        })->filter()->values()->all();
    }

    private function csvRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return [];
        }

        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($header, $values) ?: [];
        }
        fclose($handle);

        return $rows;
    }
}
