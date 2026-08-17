<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Church;
use Illuminate\Support\Facades\Crypt;

final class AiProviderSettings
{
    public function get(Church $church): array
    {
        $settings = is_array(data_get($church->settings, 'ai_copilot')) ? data_get($church->settings, 'ai_copilot') : [];
        $key = null;
        if (filled($settings['api_key'] ?? null)) {
            try {
                $key = Crypt::decryptString((string) $settings['api_key']);
            } catch (\Throwable) {
                $key = null;
            }
        }

        $provider = in_array($settings['provider'] ?? null, array_keys(config('ai.providers')), true) ? $settings['provider'] : config('ai.default_provider');
        $models = config("ai.providers.{$provider}.models", []);
        $model = (string) ($settings['model'] ?? config('ai.default_model'));
        if (! in_array($model, $models, true)) {
            $model = (string) ($models[0] ?? config('ai.default_model'));
        }

        return [
            'provider' => $provider,
            'model' => $model,
            'api_key' => $key,
            'configured' => filled($key),
            'timeout' => (int) ($settings['timeout'] ?? config('ai.timeout')),
            'max_tokens' => (int) ($settings['max_tokens'] ?? config('ai.max_tokens')),
        ];
    }

    public function save(Church $church, array $data): void
    {
        $current = is_array(data_get($church->settings, 'ai_copilot')) ? data_get($church->settings, 'ai_copilot') : [];
        $settings = $church->settings ?? [];
        $settings['ai_copilot'] = array_merge($current, [
            'provider' => $data['provider'],
            'model' => $data['model'],
            'timeout' => (int) $data['timeout'],
            'max_tokens' => (int) $data['max_tokens'],
        ]);
        if (filled($data['api_key'] ?? null)) {
            $settings['ai_copilot']['api_key'] = Crypt::encryptString((string) $data['api_key']);
        }
        $church->forceFill(['settings' => $settings])->save();
    }
}
