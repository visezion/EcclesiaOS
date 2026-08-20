<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CentralSupportSyncEvent;
use App\Models\Church;
use App\Support\SafeOutboundUrl;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class CentralSupportClient
{
    public function __construct(private readonly CentralSupportSettings $settings) {}

    public function test(Church $church): string
    {
        $response = $this->request($church)->get($this->endpoint('/api/v1/installations/ping'));
        $this->assertSuccessful($response);

        return (string) ($response->json('message') ?: 'Central support connection is healthy.');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function communityQuestions(Church $church, array $filters = []): array
    {
        $response = $this->request($church)->get($this->endpoint('/api/v1/community/questions'), array_filter($filters));
        $this->assertSuccessful($response);

        return (array) $response->json();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCommunityQuestion(Church $church, array $payload): array
    {
        $response = $this->request($church)->post($this->endpoint('/api/v1/community/questions'), $payload);
        $this->assertSuccessful($response);

        return (array) $response->json();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function knowledgeArticles(Church $church, array $filters = []): array
    {
        $response = $this->request($church)->get($this->endpoint('/api/v1/knowledge/articles'), array_filter($filters));
        $this->assertSuccessful($response);

        return (array) $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function knowledgeArticle(Church $church, string $articleId): array
    {
        $response = $this->request($church)->get($this->endpoint('/api/v1/knowledge/articles/'.rawurlencode($articleId)));
        $this->assertSuccessful($response);

        return (array) $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function rateKnowledgeArticle(Church $church, string $articleId, bool $helpful): array
    {
        $response = $this->request($church)->post(
            $this->endpoint('/api/v1/knowledge/articles/'.rawurlencode($articleId).'/helpful'),
            [
                'helpful' => $helpful,
                'voter' => [
                    'local_id' => request()->user()?->opaqueId(),
                    'church_id' => $church->opaqueId(),
                ],
            ],
        );
        $this->assertSuccessful($response);

        return (array) $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function liveSupport(Church $church): array
    {
        $response = $this->request($church)->get($this->endpoint('/api/v1/live-support'));
        $this->assertSuccessful($response);

        return (array) $response->json();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sendLiveMessage(Church $church, array $payload): array
    {
        $response = $this->request($church)->post($this->endpoint('/api/v1/live-support/messages'), $payload);
        $this->assertSuccessful($response);

        return (array) $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function send(CentralSupportSyncEvent $event): array
    {
        $response = $this->request($event->church)->post($this->endpoint('/api/v1/church/events'), [
            'event_id' => $event->event_id,
            'event_type' => $event->event_type,
            'occurred_at' => $event->created_at?->toIso8601String(),
            'payload' => $event->payload,
        ]);
        $this->assertSuccessful($response);

        return (array) $response->json();
    }

    private function request(Church $church): PendingRequest
    {
        $settings = $this->settings->forChurch($church);
        if (! $settings['enabled'] || ! $settings['api_token_configured'] || blank($settings['installation_id'])) {
            throw new RuntimeException('Central support is not fully configured for this church.');
        }

        return Http::acceptJson()
            ->asJson()
            ->withToken((string) $settings['api_token'])
            ->withHeaders([
                'X-EcclesiaOS-Installation' => (string) $settings['installation_id'],
                'X-EcclesiaOS-Version' => (string) config('app.version', 'development'),
            ])
            ->connectTimeout(5)
            ->timeout(20)
            ->withOptions(SafeOutboundUrl::requestOptions((string) $settings['endpoint']));
    }

    private function endpoint(string $path): string
    {
        return SafeOutboundUrl::normalize((string) config('services.central_support.url')).$path;
    }

    private function assertSuccessful(Response $response): void
    {
        if (! $response->successful()) {
            throw new RuntimeException('Central support returned HTTP '.$response->status().'.');
        }
    }
}
