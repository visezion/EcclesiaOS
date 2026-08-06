<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Church;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

final class CentralSupportSettings
{
    private const KEY = 'central_support.connection';

    /**
     * @return array<string, mixed>
     */
    public function forChurch(Church $church): array
    {
        $stored = $this->raw($church);

        return [
            'endpoint' => rtrim((string) config('services.central_support.url'), '/'),
            'installation_id' => (string) ($stored['installation_id'] ?? ''),
            'enabled' => (bool) ($stored['enabled'] ?? false),
            'remote_access_enabled' => (bool) ($stored['remote_access_enabled'] ?? false),
            'api_token' => $this->decrypt($stored['api_token_encrypted'] ?? null),
            'api_token_configured' => filled($stored['api_token_encrypted'] ?? null),
            'api_token_last_four' => $stored['api_token_last_four'] ?? null,
            'last_tested_at' => $stored['last_tested_at'] ?? null,
            'last_test_status' => $stored['last_test_status'] ?? null,
            'last_test_message' => $stored['last_test_message'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function save(Church $church, array $input): void
    {
        $value = $this->raw($church);
        $value['installation_id'] = $value['installation_id'] ?? (string) Str::uuid();
        $value['enabled'] = (bool) ($input['enabled'] ?? false);
        $value['remote_access_enabled'] = (bool) ($input['remote_access_enabled'] ?? false);

        if (filled($input['api_token'] ?? null)) {
            $token = trim((string) $input['api_token']);
            $value['api_token_encrypted'] = Crypt::encryptString($token);
            $value['api_token_last_four'] = Str::substr($token, -4);
        }

        Setting::query()->updateOrCreate(
            ['church_id' => $church->id, 'key' => self::KEY],
            ['value' => $value, 'type' => 'encrypted_integration'],
        );
    }

    public function recordTest(Church $church, bool $success, string $message): void
    {
        $value = $this->raw($church);
        $value['last_tested_at'] = now()->toIso8601String();
        $value['last_test_status'] = $success ? 'success' : 'failed';
        $value['last_test_message'] = $message;

        Setting::query()->updateOrCreate(
            ['church_id' => $church->id, 'key' => self::KEY],
            ['value' => $value, 'type' => 'encrypted_integration'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function raw(Church $church): array
    {
        $value = Setting::query()->where('church_id', $church->id)->where('key', self::KEY)->value('value');

        return is_array($value) ? $value : [];
    }

    private function decrypt(mixed $encrypted): string
    {
        if (! is_string($encrypted) || $encrypted === '') {
            return '';
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (Throwable) {
            return '';
        }
    }
}
