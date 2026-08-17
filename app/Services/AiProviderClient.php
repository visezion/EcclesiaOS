<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Church;
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
        $system = 'You are the EcclesiaOS Church Operations Copilot. Help users understand and operate the application. For questions about church records, answer only from the supplied verified data. For general workflow, product, or operational questions, provide a useful explanation without pretending to access records that were not supplied. Never invent names, numbers, permissions, settings, or completed actions. Do not provide pastoral, medical, legal, or financial advice. The data was filtered by application permissions. Mention when a request needs a supported query or human confirmation. Return concise Markdown with useful headings and bullets.';
        $model = $config['model'];

        if ($config['provider'] === 'anthropic') {
            $model = $this->resolveAnthropicModel($config);
            $payload = ['model' => $model, 'max_tokens' => $config['max_tokens']];
            $payload += ['system' => $system, 'messages' => [['role' => 'user', 'content' => "Question: {$question}\n\nVerified data:\n".json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)]]];
            $response = Http::timeout($config['timeout'])->withHeaders(['x-api-key' => $config['api_key'], 'anthropic-version' => '2023-06-01'])->post(config('ai.anthropic_endpoint'), $payload);
            $response->throw();

            return trim((string) collect($response->json('content', []))->where('type', 'text')->pluck('text')->implode("\n"));
        }

        $payload = ['model' => $model, 'max_tokens' => $config['max_tokens']];
        $payload += ['store' => false, 'instructions' => $system, 'input' => "Question: {$question}\n\nVerified data:\n".json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)];
        $response = Http::timeout($config['timeout'])->withToken($config['api_key'])->post(config('ai.openai_endpoint'), $payload);
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
        $response = Http::timeout($config['timeout'])
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
    }
}
