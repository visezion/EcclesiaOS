<?php

declare(strict_types=1);

namespace App\Services\Updates;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;
use ZipArchive;

final class SafeReleaseArchive
{
    public function extract(string $archivePath, string $destination): void
    {
        if (file_exists($destination) || is_link($destination)) {
            throw new RuntimeException('The update extraction directory already exists.');
        }

        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('The update package is not a valid ZIP archive.');
        }

        try {
            $entries = $this->validatedEntries($zip);
            File::ensureDirectoryExists($destination, 0755, true);

            foreach ($entries as $entry) {
                $target = $destination.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $entry['name']);
                if ($entry['directory']) {
                    File::ensureDirectoryExists($target, 0755, true);

                    continue;
                }

                File::ensureDirectoryExists(dirname($target), 0755, true);
                $input = $zip->getStream($entry['source']);
                if ($input === false) {
                    throw new RuntimeException("The update file {$entry['name']} could not be read.");
                }

                $output = fopen($target, 'wb');
                if ($output === false) {
                    fclose($input);
                    throw new RuntimeException("The update file {$entry['name']} could not be written.");
                }

                $written = stream_copy_to_stream($input, $output);
                fclose($input);
                fclose($output);

                if ($written === false || $written !== $entry['size']) {
                    throw new RuntimeException("The update file {$entry['name']} was not extracted completely.");
                }
            }
        } catch (Throwable $exception) {
            if (is_dir($destination)) {
                File::deleteDirectory($destination);
            }

            throw $exception;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<int, array{source: string, name: string, size: int, directory: bool}>
     */
    private function validatedEntries(ZipArchive $zip): array
    {
        $entries = [];
        $expandedBytes = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $source = (string) ($stat['name'] ?? '');
            $name = $this->normalizeName($source);
            if ($name === null) {
                continue;
            }

            $parts = explode('/', rtrim($name, '/'));
            $first = $parts[0] ?? '';
            if ($first === '.env' || $first === 'storage' || ($first === 'public' && ($parts[1] ?? '') === 'storage')) {
                throw new RuntimeException('The update archive contains protected church data paths.');
            }

            $attributes = 0;
            if ($zip->getExternalAttributesIndex($index, $operatingSystem, $attributes)
                && (($attributes >> 16) & 0170000) === 0120000) {
                throw new RuntimeException('Symbolic links are not allowed inside update packages.');
            }

            $size = (int) ($stat['size'] ?? -1);
            if ($size < 0) {
                throw new RuntimeException('The update archive contains an invalid file size.');
            }

            $expandedBytes += $size;
            if ($expandedBytes > (int) config('updater.max_expanded_bytes')) {
                throw new RuntimeException('The expanded update package exceeds the configured limit.');
            }

            $entries[] = [
                'source' => $source,
                'name' => $name,
                'size' => $size,
                'directory' => str_ends_with($name, '/'),
            ];
        }

        return $entries;
    }

    private function normalizeName(string $name): ?string
    {
        $name = str_replace('\\', '/', $name);
        if (
            $name === ''
            || str_starts_with($name, '/')
            || preg_match('/^[A-Za-z]:\//', $name) === 1
            || preg_match('/[\x00-\x1F\x7F]/', $name) === 1
        ) {
            throw new RuntimeException('The update archive contains an unsafe path.');
        }

        while (str_starts_with($name, './')) {
            $name = substr($name, 2);
        }

        if ($name === '' || $name === '.') {
            return null;
        }

        $directory = str_ends_with($name, '/');
        $parts = explode('/', rtrim($name, '/'));
        if (in_array('', $parts, true) || in_array('.', $parts, true) || in_array('..', $parts, true)) {
            throw new RuntimeException('The update archive contains an unsafe path.');
        }

        return implode('/', $parts).($directory ? '/' : '');
    }
}
