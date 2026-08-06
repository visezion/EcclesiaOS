<?php

declare(strict_types=1);

namespace App\Services\MemberImport;

use App\Models\MemberImport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use SplFileObject;

final class MemberImportFileReader
{
    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, mixed>>}
     */
    public function read(MemberImport $import, int $limit = 25000): array
    {
        if (! $import->disk || ! $import->path || ! Storage::disk($import->disk)->exists($import->path)) {
            throw ValidationException::withMessages(['members_file' => 'The staged import file is unavailable.']);
        }

        $path = Storage::disk($import->disk)->path($import->path);

        return match ($import->source_type) {
            'csv' => $this->csv($path, $limit),
            'xlsx', 'xls' => $this->spreadsheet($path, $limit),
            default => throw ValidationException::withMessages(['members_file' => 'This file type is not supported by the selected importer.']),
        };
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, mixed>>}
     */
    private function csv(string $path, int $limit): array
    {
        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $rawHeaders = $file->fgetcsv();
        if (! is_array($rawHeaders)) {
            throw new RuntimeException('The CSV file has no readable header row.');
        }

        $headers = $this->headers($rawHeaders);
        $rows = [];
        while (! $file->eof() && count($rows) < $limit) {
            $values = $file->fgetcsv();
            if (! is_array($values) || $values === [null] || collect($values)->filter(fn ($value) => filled($value))->isEmpty()) {
                continue;
            }
            $rows[] = $this->combine($headers, $values);
        }

        return compact('headers', 'rows');
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, mixed>>}
     */
    private function spreadsheet(string $path, int $limit): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, false);
        $rawHeaders = array_shift($rawRows);
        if (! is_array($rawHeaders)) {
            throw new RuntimeException('The spreadsheet has no readable header row.');
        }

        $headers = $this->headers($rawHeaders);
        $rows = [];
        foreach (array_slice($rawRows, 0, $limit) as $values) {
            if (collect($values)->filter(fn ($value) => filled($value))->isEmpty()) {
                continue;
            }
            $rows[] = $this->combine($headers, $values);
        }

        return compact('headers', 'rows');
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return array<int, string>
     */
    private function headers(array $headers): array
    {
        $seen = [];

        return collect($headers)->map(function ($header, int $index) use (&$seen): string {
            $header = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', trim((string) $header));
            $base = trim((string) preg_replace('/[^a-z0-9]+/i', '_', Str::ascii((string) $header)), '_');
            $base = Str::lower($base) ?: 'column_'.($index + 1);
            $seen[$base] = ($seen[$base] ?? 0) + 1;

            return $seen[$base] > 1 ? $base.'_'.$seen[$base] : $base;
        })->all();
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, mixed>  $values
     * @return array<string, mixed>
     */
    private function combine(array $headers, array $values): array
    {
        $values = array_pad(array_slice($values, 0, count($headers)), count($headers), null);

        return array_combine($headers, $values) ?: [];
    }
}
