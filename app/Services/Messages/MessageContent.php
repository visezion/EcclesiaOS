<?php

declare(strict_types=1);

namespace App\Services\Messages;

use Illuminate\Support\Str;

final class MessageContent
{
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ol', 'ul', 'li', 'a'];

    public function sanitize(?string $html, string $fallback): string
    {
        if (! filled($html)) {
            return nl2br(e($fallback));
        }

        $clean = strip_tags($html, '<p><br><strong><b><em><i><u><ol><ul><li><a>');

        return (string) preg_replace_callback('/<\s*(\/?)\s*([a-z0-9]+)([^>]*)>/i', function (array $match): string {
            $closing = $match[1] === '/';
            $tag = strtolower($match[2]);
            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                return '';
            }

            if ($closing) {
                return in_array($tag, ['br'], true) ? '' : '</'.$tag.'>';
            }

            if ($tag !== 'a') {
                return '<'.$tag.'>';
            }

            preg_match('/href\s*=\s*["\']([^"\']+)["\']/i', $match[3], $href);
            $url = trim($href[1] ?? '');
            if (! Str::startsWith(strtolower($url), ['https://', 'http://', 'mailto:'])) {
                return '<a>';
            }

            return '<a href="'.e($url).'" rel="noopener noreferrer" target="_blank">';
        }, $clean);
    }

    public function plainText(string $html): string
    {
        return trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html))));
    }
}
