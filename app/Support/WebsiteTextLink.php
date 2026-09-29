<?php

namespace App\Support;

final class WebsiteTextLink
{
    public static function url(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $url = trim($value);
        if ($url === '' || strlen($url) > 500 || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return null;
        }

        if (preg_match('~^https?://[^/\s?#]+~i', $url)
            || preg_match('/^mailto:[^@\s]+@[^@\s]+\.[^@\s]+$/i', $url)
            || preg_match('~^/(?!/)~', $url)
            || str_starts_with($url, '#')) {
            return $url;
        }

        return null;
    }
}
