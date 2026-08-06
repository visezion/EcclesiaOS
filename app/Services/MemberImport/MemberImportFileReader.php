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
use XMLReader;
use ZipArchive;

final class MemberImportFileReader
{
    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, mixed>>, metadata?: array<string, mixed>}
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
            'json' => $this->json($path, $limit),
            'xml' => $this->xml($path, $limit),
            'zip' => $this->archive($import, $path, $limit),
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
     * @return array{headers: array<int, string>, rows: array<int, array<string, mixed>>}
     */
    private function json(string $path, int $limit): array
    {
        $decoded = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('The JSON document does not contain member records.');
        }
        foreach (['members', 'records', 'data', 'contacts', 'people'] as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                $decoded = $decoded[$key];
                break;
            }
        }
        if (! array_is_list($decoded)) {
            $decoded = [$decoded];
        }

        return $this->records(array_slice($decoded, 0, $limit));
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, mixed>>}
     */
    private function xml(string $path, int $limit): array
    {
        $contents = (string) file_get_contents($path);
        if (stripos($contents, '<!DOCTYPE') !== false || stripos($contents, '<!ENTITY') !== false) {
            throw new RuntimeException('XML documents containing DTD or entity declarations are not accepted.');
        }
        $reader = new XMLReader;
        if (! $reader->XML($contents, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new RuntimeException('The XML document could not be opened.');
        }
        $recordNames = ['member', 'record', 'person', 'contact'];
        $records = [];
        while ($reader->read() && count($records) < $limit) {
            if ($reader->nodeType !== XMLReader::ELEMENT || ! in_array(Str::lower($reader->localName), $recordNames, true)) {
                continue;
            }
            $node = simplexml_load_string($reader->readOuterXml(), \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
            if ($node !== false) {
                $records[] = json_decode(json_encode($node, JSON_THROW_ON_ERROR), true, 64, JSON_THROW_ON_ERROR);
            }
        }
        $reader->close();
        if ($records === []) {
            throw new RuntimeException('No member, person, contact, or record elements were found in the XML document.');
        }

        return $this->records($records);
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, mixed>>, metadata: array<string, mixed>}
     */
    private function archive(MemberImport $import, string $path, int $limit): array
    {
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw new RuntimeException('The ZIP archive could not be opened.');
        }
        if ($archive->numFiles > 5000) {
            $archive->close();
            throw new RuntimeException('The ZIP archive contains too many files.');
        }

        $dataEntry = null;
        $assets = [];
        $totalSize = 0;
        $supportedData = ['csv', 'xlsx', 'xls', 'json', 'xml'];
        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $stat = $archive->statIndex($index);
            $entry = (string) ($stat['name'] ?? '');
            if ($entry === '' || str_ends_with($entry, '/')) {
                continue;
            }
            $totalSize += (int) ($stat['size'] ?? 0);
            if ($totalSize > 209715200) {
                $archive->close();
                throw new RuntimeException('The uncompressed ZIP archive exceeds 200 MB.');
            }
            $extension = Str::lower(pathinfo($entry, PATHINFO_EXTENSION));
            if ($dataEntry === null && in_array($extension, $supportedData, true)) {
                $dataEntry = ['index' => $index, 'name' => $entry, 'extension' => $extension];
            }
            if (in_array($extension, $imageExtensions, true) && (int) ($stat['size'] ?? 0) <= 10485760) {
                $stream = $archive->getStream($entry);
                if ($stream === false) {
                    continue;
                }
                $assetPath = 'member-imports/'.$import->church_id.'/assets/'.$import->id.'/'.Str::uuid().'.'.$extension;
                Storage::disk('local')->put($assetPath, $stream);
                fclose($stream);
                $assets[Str::lower(basename($entry))] = $assetPath;
            }
        }
        if ($dataEntry === null) {
            $archive->close();
            throw new RuntimeException('The ZIP archive must contain a CSV, Excel, JSON, or XML member data file.');
        }
        $stream = $archive->getStream($dataEntry['name']);
        if ($stream === false) {
            $archive->close();
            throw new RuntimeException('The member data file inside the ZIP archive could not be read.');
        }
        $temporaryBase = tempnam(sys_get_temp_dir(), 'ecclesia-import-');
        if ($temporaryBase === false) {
            fclose($stream);
            $archive->close();
            throw new RuntimeException('A temporary import file could not be created.');
        }
        $temporaryPath = $temporaryBase.'.'.$dataEntry['extension'];
        rename($temporaryBase, $temporaryPath);
        $destination = fopen($temporaryPath, 'wb');
        if ($destination === false) {
            fclose($stream);
            $archive->close();
            throw new RuntimeException('A temporary import file could not be created.');
        }
        stream_copy_to_stream($stream, $destination);
        fclose($destination);
        fclose($stream);
        $archive->close();

        try {
            $parsed = match ($dataEntry['extension']) {
                'csv' => $this->csv($temporaryPath, $limit),
                'xlsx', 'xls' => $this->spreadsheet($temporaryPath, $limit),
                'json' => $this->json($temporaryPath, $limit),
                'xml' => $this->xml($temporaryPath, $limit),
            };
        } finally {
            @unlink($temporaryPath);
        }
        $parsed['metadata'] = [
            'embedded_data_file' => basename($dataEntry['name']),
            'embedded_data_type' => $dataEntry['extension'],
            'assets' => $assets,
        ];

        return $parsed;
    }

    /**
     * @param  array<int, mixed>  $records
     * @return array{headers: array<int, string>, rows: array<int, array<string, mixed>>}
     */
    private function records(array $records): array
    {
        $rows = [];
        $headers = [];
        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }
            $row = [];
            $this->flattenRecord($record, $row);
            $headers = array_values(array_unique([...$headers, ...array_keys($row)]));
            $rows[] = $row;
        }
        if ($rows === []) {
            throw new RuntimeException('The file contains no readable member records.');
        }
        foreach ($rows as &$row) {
            $row = array_replace(array_fill_keys($headers, null), $row);
        }

        return compact('headers', 'rows');
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $output
     */
    private function flattenRecord(array $record, array &$output, string $prefix = ''): void
    {
        foreach ($record as $key => $value) {
            $key = $this->headers([(string) $key])[0];
            $qualified = $prefix === '' ? $key : $prefix.'_'.$key;
            if (is_array($value) && ! array_is_list($value)) {
                $transparent = in_array($key, ['member', 'person', 'profile', 'details', 'contact'], true);
                $this->flattenRecord($value, $output, $transparent ? $prefix : $qualified);

                continue;
            }
            if (is_array($value)) {
                $value = implode(', ', array_filter(array_map(fn ($item) => is_scalar($item) ? (string) $item : null, $value)));
            }
            $output[$qualified] = $value;
            if ($prefix !== '' && ! array_key_exists($key, $output)) {
                $output[$key] = $value;
            }
        }
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
