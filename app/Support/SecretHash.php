<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class SecretHash
{
    private const PREFIX = 'hmac-sha256:';

    public static function make(string $secret): string
    {
        return self::PREFIX.hash_hmac('sha256', $secret, self::key());
    }

    public static function verify(string $secret, string $storedHash): bool
    {
        if (str_starts_with($storedHash, self::PREFIX)) {
            return hash_equals(self::make($secret), $storedHash);
        }

        return hash_equals($storedHash, hash('sha256', $secret));
    }

    public static function needsRehash(string $storedHash): bool
    {
        return ! str_starts_with($storedHash, self::PREFIX);
    }

    private static function key(): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('APP_KEY must be configured before secrets can be stored.');
        }

        return $key;
    }
}
