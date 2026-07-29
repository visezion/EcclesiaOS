<?php

namespace App\Support;

final class Csv
{
    /**
     * Write a row while preventing spreadsheet applications from evaluating cells as formulas.
     *
     * @param  resource  $stream
     * @param  array<int, mixed>  $fields
     */
    public static function write($stream, array $fields): int|false
    {
        return fputcsv($stream, array_map(self::sanitize(...), $fields));
    }

    /**
     * Encode a sanitized row for responses assembled in memory.
     *
     * @param  array<int, mixed>  $fields
     */
    public static function row(array $fields): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return '';
        }

        self::write($stream, $fields);
        rewind($stream);
        $row = stream_get_contents($stream);
        fclose($stream);

        return $row === false ? '' : $row;
    }

    public static function sanitize(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
