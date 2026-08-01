<?php

declare(strict_types=1);

namespace App\Support;

final class Utf8Text
{
    private const SUSPICIOUS_MOJIBAKE = '/(?:[\x{00C2}\x{00C3}\x{00E2}\x{00F0}][\x{0080}-\x{00BF}\x{0152}\x{0153}\x{0160}\x{0161}\x{0178}\x{017D}\x{017E}\x{0192}\x{02C6}\x{02DC}\x{2013}-\x{2022}\x{2026}\x{2030}\x{2039}\x{203A}\x{20AC}\x{2122}]|\x{FFFD})/u';

    public static function repair(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $result = $value;

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $currentScore = self::mojibakeScore($result);
            if ($currentScore === 0) {
                break;
            }

            $converted = @mb_convert_encoding($result, 'Windows-1252', 'UTF-8');
            if (! mb_check_encoding($converted, 'UTF-8')) {
                break;
            }

            $convertedScore = self::mojibakeScore($converted);
            if ($convertedScore >= $currentScore) {
                break;
            }

            $result = $converted;
        }

        return str_replace("\u{FFFD}", '', $result);
    }

    private static function mojibakeScore(string $value): int
    {
        return preg_match_all(self::SUSPICIOUS_MOJIBAKE, $value) ?: 0;
    }
}
