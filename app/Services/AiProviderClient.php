<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Church;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class AiProviderClient
{
    public function __construct(private readonly AiProviderSettings $settings) {}

    public function answer(Church $church, string $question, array $context): string
    {
        $config = $this->settings->get($church);
        if (! $config['configured']) {
            throw new RuntimeException('Configure an AI provider API key in Settings before using the Copilot.');
        }
        $system = 'You are the EcclesiaOS Reports and Analytics Copilot. Your only job is to analyze the supplied verified church metrics and reports. Explain trends, comparisons, changes, patterns, risks, priorities, and practical reporting insights. Use only the supplied data; never invent numbers, names, dates, permissions, or conclusions unsupported by the data. Do not create, edit, send, or manage records, and do not answer unrelated operational or general questions. If the request is outside reports and analytics, clearly say that this Copilot is limited to reports, metrics, trends, comparisons, and analysis. Do not provide pastoral, medical, legal, or financial advice. The data was filtered by application permissions and campus scope. Return concise Markdown with useful headings, tables, and bullets.';
        $model = $config['model'];

        if ($config['provider'] === 'anthropic') {
            $model = $this->resolveAnthropicModel($config);
            $payload = ['model' => $model, 'max_tokens' => $config['max_tokens']];
            $payload += ['system' => $system, 'messages' => [['role' => 'user', 'content' => "Question: {$question}\n\nVerified data:\n".json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)]]];
            $response = Http::connectTimeout(5)->timeout($config['timeout'])->withHeaders(['x-api-key' => $config['api_key'], 'anthropic-version' => '2023-06-01'])->post(config('ai.anthropic_endpoint'), $payload);
            $response->throw();

            return trim((string) collect($response->json('content', []))->where('type', 'text')->pluck('text')->implode("\n"));
        }

        $payload = ['model' => $model, 'max_output_tokens' => $config['max_tokens']];
        $payload += ['store' => false, 'instructions' => $system, 'input' => "Question: {$question}\n\nVerified data:\n".json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)];
        $response = Http::connectTimeout(5)->timeout($config['timeout'])->withToken($config['api_key'])->post(config('ai.openai_endpoint'), $payload);
        $response->throw();
        $text = $response->json('output_text');
        if (filled($text)) {
            return trim((string) $text);
        }

        return trim((string) collect($response->json('output', []))->flatMap(fn ($item) => $item['content'] ?? [])->pluck('text')->filter()->implode("\n"));
    }

    public function test(Church $church): void
    {
        $this->answer($church, 'Confirm the connection in one short sentence.', ['status' => 'connection_test']);
    }

    /**
     * Anthropic model access is account-specific. Resolve the configured model
     * against the account's live model catalog before sending a message so a
     * retired or unavailable model cannot produce a misleading 404.
     *
     * @param  array{api_key: string, model: string, timeout: int}  $config
     */
    private function resolveAnthropicModel(array $config): string
    {
        return Cache::remember('ai-copilot.anthropic-model.'.sha1($config['api_key']), now()->addMinutes(10), function () use ($config): string {
            $response = Http::connectTimeout(5)
                ->timeout($config['timeout'])
                ->withHeaders(['x-api-key' => $config['api_key'], 'anthropic-version' => '2023-06-01'])
                ->get(config('ai.anthropic_models_endpoint'), ['limit' => 100]);

            if ($response->failed()) {
                return $config['model'];
            }

            $models = collect($response->json('data', []))
                ->pluck('id')
                ->filter(fn ($id): bool => is_string($id) && $id !== '')
                ->values();

            if ($models->contains($config['model'])) {
                return $config['model'];
            }

            $fallback = $models->first(fn (string $model): bool => str_contains($model, 'sonnet'))
                ?? $models->first(fn (string $model): bool => str_contains($model, 'haiku'))
                ?? $models->first();

            if (! $fallback) {
                throw new RuntimeException('Claude returned no usable models for this API key. Check the API key and Anthropic account access.');
            }

            return $fallback;
        });
    }
}
