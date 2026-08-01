<?php

declare(strict_types=1);

namespace App\Support;

final class BibleVerseDiffer
{
    public function compare(string $baseline, string $candidate): array
    {
        $baselineTokens = $this->tokens($baseline);
        $candidateTokens = $this->tokens($candidate);
        $baselineWords = array_map($this->normalize(...), $baselineTokens);
        $candidateWords = array_map($this->normalize(...), $candidateTokens);
        $matrix = array_fill(0, count($baselineWords) + 1, array_fill(0, count($candidateWords) + 1, 0));

        for ($left = 1; $left <= count($baselineWords); $left++) {
            for ($right = 1; $right <= count($candidateWords); $right++) {
                $matrix[$left][$right] = $baselineWords[$left - 1] === $candidateWords[$right - 1]
                    ? $matrix[$left - 1][$right - 1] + 1
                    : max($matrix[$left - 1][$right], $matrix[$left][$right - 1]);
            }
        }

        $matchingCandidateTokens = [];
        $left = count($baselineWords);
        $right = count($candidateWords);
        while ($left > 0 && $right > 0) {
            if ($baselineWords[$left - 1] === $candidateWords[$right - 1]) {
                $matchingCandidateTokens[$right - 1] = true;
                $left--;
                $right--;
            } elseif ($matrix[$left - 1][$right] >= $matrix[$left][$right - 1]) {
                $left--;
            } else {
                $right--;
            }
        }

        $tokens = collect($candidateTokens)
            ->map(fn (string $token, int $index): array => [
                'text' => $token,
                'different' => ! isset($matchingCandidateTokens[$index]),
            ])
            ->all();
        $matches = count($matchingCandidateTokens);
        $largestTokenCount = max(count($baselineTokens), count($candidateTokens), 1);

        return [
            'tokens' => $tokens,
            'different_count' => count($candidateTokens) - $matches,
            'similarity' => (int) round(($matches / $largestTokenCount) * 100),
        ];
    }

    private function tokens(string $text): array
    {
        return preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private function normalize(string $token): string
    {
        $word = preg_replace('/[^\p{L}\p{N}]+/u', '', $token) ?: $token;

        return mb_strtolower($word);
    }
}
