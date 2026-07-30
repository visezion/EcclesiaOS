<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SafeOutboundUrl;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SafeOutboundUrlTest extends TestCase
{
    #[DataProvider('unsafeUrls')]
    public function test_unsafe_outbound_urls_are_rejected(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);

        SafeOutboundUrl::normalize($url);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeUrls(): array
    {
        return [
            'unencrypted HTTP' => ['http://zender.example.test'],
            'localhost' => ['https://localhost'],
            'loopback IPv4' => ['https://127.0.0.1'],
            'private IPv4' => ['https://192.168.1.10'],
            'link-local metadata' => ['https://169.254.169.254/latest/meta-data'],
            'loopback IPv6' => ['https://[::1]'],
            'embedded credentials' => ['https://user:password@zender.example.test'],
            'non-HTTPS port' => ['https://zender.example.test:8443'],
            'query parameters' => ['https://zender.example.test?target=internal'],
            'fragment' => ['https://zender.example.test/#fragment'],
        ];
    }

    public function test_public_https_url_is_normalized_and_redirects_are_disabled(): void
    {
        $url = SafeOutboundUrl::normalize(' https://zender.example.test/ ');

        $this->assertSame('https://zender.example.test', $url);
        $this->assertSame(['allow_redirects' => false], SafeOutboundUrl::requestOptions($url));
    }
}
