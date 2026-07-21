<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use JsonException;

final class OpaqueId
{
    /**
     * @var array<string, string>
     */
    private static array $encoded = [];

    public static function encode(int|string|null $id, string $scope): string
    {
        $numericId = filter_var($id, FILTER_VALIDATE_INT);

        if ($numericId === false || (int) $numericId < 1) {
            return '';
        }

        $cacheKey = $scope.':'.(int) $numericId;
        if (isset(self::$encoded[$cacheKey])) {
            return self::$encoded[$cacheKey];
        }

        $payload = json_encode([
            'id' => (int) $numericId,
            'scope' => $scope,
        ], JSON_THROW_ON_ERROR);

        return self::$encoded[$cacheKey] = self::base64UrlEncode(Crypt::encryptString($payload));
    }

    public static function decode(mixed $value, string $scope): ?int
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString(self::base64UrlDecode($value)), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            return null;
        }

        if (($payload['scope'] ?? null) !== $scope) {
            return null;
        }

        $id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);

        return $id !== false && (int) $id > 0 ? (int) $id : null;
    }

    /**
     * @return array<int, int>
     */
    public static function decodeMany(mixed $values, string $scope): array
    {
        return collect(Arr::wrap($values))
            ->map(fn (mixed $value): ?int => self::decode($value, $scope))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $encoded = strtr($value, '-_', '+/');
        $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);

        $decoded = base64_decode($encoded, true);

        if ($decoded === false) {
            throw new DecryptException('Invalid opaque identifier.');
        }

        return $decoded;
    }
}
