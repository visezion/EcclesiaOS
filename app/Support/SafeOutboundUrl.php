<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class SafeOutboundUrl
{
    public static function normalize(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);

        if (
            $url === ''
            || filter_var($url, FILTER_VALIDATE_URL) === false
            || ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || blank($parts['host'] ?? null)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
        ) {
            throw new InvalidArgumentException('The endpoint must be a public HTTPS URL without credentials, query parameters, or fragments.');
        }

        $host = self::canonicalHost((string) $parts['host']);
        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new InvalidArgumentException('Private and local network endpoints are not allowed.');
        }

        self::assertPublicHost($host);

        return rtrim($url, '/');
    }

    /**
     * @return array<string, mixed>
     */
    public static function requestOptions(string $url): array
    {
        $url = self::normalize($url);
        $host = self::canonicalHost((string) parse_url($url, PHP_URL_HOST));
        $options = ['allow_redirects' => false];

        if (app()->environment('testing')) {
            return $options;
        }

        $addresses = self::resolve($host);
        if ($addresses === []) {
            throw new InvalidArgumentException('The endpoint host could not be resolved.');
        }

        if (defined('CURLOPT_RESOLVE')) {
            $address = $addresses[0];
            if (str_contains($address, ':')) {
                $address = '['.$address.']';
            }

            $options['curl'] = [
                constant('CURLOPT_RESOLVE') => [$host.':443:'.$address],
            ];
        }

        return $options;
    }

    private static function assertPublicHost(string $host): void
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (! self::isPublicAddress($host)) {
                throw new InvalidArgumentException('Private and local network endpoints are not allowed.');
            }

            return;
        }

        if (app()->environment('testing')) {
            return;
        }

        $addresses = self::resolve($host);
        if ($addresses === []) {
            throw new InvalidArgumentException('The endpoint host could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (! self::isPublicAddress($address)) {
                throw new InvalidArgumentException('The endpoint resolves to a private or reserved network address.');
            }
        }
    }

    private static function canonicalHost(string $host): string
    {
        return strtolower(rtrim(trim($host, '[]'), '.'));
    }

    /**
     * @return list<string>
     */
    private static function resolve(string $host): array
    {
        $addresses = [];

        $ipv4 = gethostbynamel($host);
        if (is_array($ipv4)) {
            $addresses = array_merge($addresses, $ipv4);
        }

        if (function_exists('dns_get_record')) {
            $ipv6Records = @dns_get_record($host, DNS_AAAA);
            if (is_array($ipv6Records)) {
                foreach ($ipv6Records as $record) {
                    if (is_string($record['ipv6'] ?? null)) {
                        $addresses[] = $record['ipv6'];
                    }
                }
            }
        }

        return array_values(array_unique($addresses));
    }

    private static function isPublicAddress(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
