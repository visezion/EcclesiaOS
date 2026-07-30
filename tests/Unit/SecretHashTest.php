<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SecretHash;
use Tests\TestCase;

final class SecretHashTest extends TestCase
{
    public function test_secrets_use_keyed_hashes_and_verify_without_storing_plaintext(): void
    {
        $hash = SecretHash::make('strong-webhook-secret');

        $this->assertStringStartsWith('hmac-sha256:', $hash);
        $this->assertStringNotContainsString('strong-webhook-secret', $hash);
        $this->assertTrue(SecretHash::verify('strong-webhook-secret', $hash));
        $this->assertFalse(SecretHash::verify('wrong-secret', $hash));
        $this->assertFalse(SecretHash::needsRehash($hash));
    }

    public function test_legacy_sha256_hashes_remain_valid_for_migration(): void
    {
        $legacyHash = hash('sha256', 'legacy-secret');

        $this->assertTrue(SecretHash::verify('legacy-secret', $legacyHash));
        $this->assertTrue(SecretHash::needsRehash($legacyHash));
    }
}
