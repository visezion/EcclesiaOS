<?php

declare(strict_types=1);

namespace App\Services\Updates;

use App\Models\SystemUpdate;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class GitHubReleaseService
{
    /**
     * @return array<string, mixed>
     */
    public function latest(): array
    {
        if (! config('updater.enabled')) {
            throw new RuntimeException('Application updates are disabled.');
        }

        $repository = (string) config('updater.repository');
        if (preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $repository) !== 1) {
            throw new RuntimeException('The configured GitHub repository is invalid.');
        }

        $channel = (string) config('updater.channel', 'stable');
        $endpoint = $channel === 'stable'
            ? "/repos/{$repository}/releases/latest"
            : "/repos/{$repository}/releases?per_page=10";

        $payload = $this->client()->get($endpoint)->throw()->json();
        $release = $channel === 'stable'
            ? $payload
            : collect(is_array($payload) ? $payload : [])->first(
                fn (mixed $item): bool => is_array($item) && ! ($item['draft'] ?? true),
            );

        if (! is_array($release)) {
            throw new RuntimeException('GitHub did not return a valid release.');
        }

        return $this->normalizeRelease($release);
    }

    public function download(SystemUpdate $update, string $destination): void
    {
        $assetUrl = (string) $update->asset_api_url;
        $apiBase = rtrim((string) config('updater.github_api_url'), '/').'/';

        if ($assetUrl === '' || ! str_starts_with($assetUrl, $apiBase)) {
            throw new RuntimeException('The update asset URL is not trusted.');
        }

        try {
            $response = $this->client()
                ->withHeaders(['Accept' => 'application/octet-stream'])
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 3,
                        'strict' => true,
                        'referer' => false,
                        'protocols' => ['https'],
                    ],
                ])
                ->sink($destination)
                ->get($assetUrl);

            $response->throw();
        } catch (Throwable $exception) {
            @unlink($destination);

            throw $exception;
        }

        $size = is_file($destination) ? filesize($destination) : false;
        if (
            $size === false
            || $size <= 0
            || $size > (int) config('updater.max_download_bytes')
            || ($update->asset_size !== null && $size !== $update->asset_size)
        ) {
            @unlink($destination);

            throw new RuntimeException('The downloaded update package has an invalid size.');
        }
    }

    /**
     * @param  array<string, mixed>  $release
     * @return array<string, mixed>
     */
    private function normalizeRelease(array $release): array
    {
        if (($release['draft'] ?? false) === true) {
            throw new RuntimeException('Draft releases cannot be installed.');
        }

        if ((string) config('updater.channel', 'stable') === 'stable' && ($release['prerelease'] ?? false) === true) {
            throw new RuntimeException('Pre-release updates are disabled on the stable channel.');
        }

        if (config('updater.require_immutable', true) && ($release['immutable'] ?? false) !== true) {
            throw new RuntimeException('The GitHub release is not immutable. Enable release immutability before publishing production updates.');
        }

        $tag = trim((string) ($release['tag_name'] ?? ''));
        $version = ltrim($tag, 'vV');
        if (strlen($version) > 40 || strlen($tag) > 80 || preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version) !== 1) {
            throw new RuntimeException('The GitHub release tag is not a supported semantic version.');
        }

        $assets = collect(is_array($release['assets'] ?? null) ? $release['assets'] : []);
        $manifestName = (string) config('updater.manifest_asset');
        $manifestAsset = $assets->firstWhere('name', $manifestName);
        if (! is_array($manifestAsset)) {
            throw new RuntimeException("The release is missing {$manifestName}.");
        }

        $manifest = $this->downloadManifest($manifestAsset);
        if ((string) ($manifest['version'] ?? '') !== $version) {
            throw new RuntimeException('The release manifest version does not match the Git tag.');
        }

        foreach (['version', 'artifact', 'sha256', 'minimum_version', 'minimum_php'] as $field) {
            if (! isset($manifest[$field]) || ! is_string($manifest[$field]) || trim($manifest[$field]) === '') {
                throw new RuntimeException("The release manifest is missing {$field}.");
            }
        }

        $artifactName = basename((string) $manifest['artifact']);
        if ($artifactName === '' || strlen($artifactName) > 255 || $artifactName !== (string) $manifest['artifact']) {
            throw new RuntimeException('The release manifest contains an invalid artifact name.');
        }

        $artifact = $assets->firstWhere('name', $artifactName);
        if (! is_array($artifact)) {
            throw new RuntimeException("The release is missing {$artifactName}.");
        }

        $manifestDigest = strtolower((string) ($manifest['sha256'] ?? ''));
        $apiDigest = strtolower((string) ($artifact['digest'] ?? ''));
        if (str_starts_with($apiDigest, 'sha256:')) {
            $apiDigest = substr($apiDigest, 7);
        }

        if (preg_match('/^[a-f0-9]{64}$/', $manifestDigest) !== 1) {
            throw new RuntimeException('The release manifest is missing a valid SHA-256 digest.');
        }

        if ($apiDigest !== '' && ! hash_equals($manifestDigest, $apiDigest)) {
            throw new RuntimeException('The release manifest digest does not match GitHub.');
        }

        $size = (int) ($artifact['size'] ?? 0);
        if ($size <= 0 || $size > (int) config('updater.max_download_bytes')) {
            throw new RuntimeException('The release artifact exceeds the configured size limit.');
        }

        return [
            'version' => $version,
            'tag' => $tag,
            'name' => mb_substr((string) ($release['name'] ?? $tag), 0, 255),
            'changelog' => mb_substr((string) ($release['body'] ?? ''), 0, 100_000),
            'release_url' => (string) ($release['html_url'] ?? ''),
            'published_at' => $release['published_at'] ?? null,
            'immutable' => (bool) ($release['immutable'] ?? false),
            'asset_name' => $artifactName,
            'asset_api_url' => (string) ($artifact['url'] ?? ''),
            'asset_download_url' => (string) ($artifact['browser_download_url'] ?? ''),
            'asset_digest' => 'sha256:'.$manifestDigest,
            'asset_size' => $size,
            'manifest' => $manifest,
        ];
    }

    /**
     * @param  array<string, mixed>  $asset
     * @return array<string, mixed>
     */
    private function downloadManifest(array $asset): array
    {
        $assetUrl = (string) ($asset['url'] ?? '');
        $apiBase = rtrim((string) config('updater.github_api_url'), '/').'/';
        if ($assetUrl === '' || ! str_starts_with($assetUrl, $apiBase)) {
            throw new RuntimeException('The release manifest URL is not trusted.');
        }

        $response = $this->client()
            ->withHeaders(['Accept' => 'application/octet-stream'])
            ->get($assetUrl)
            ->throw();

        if (strlen($response->body()) > 65536) {
            throw new RuntimeException('The release manifest is unexpectedly large.');
        }

        $manifest = $response->json();
        if (! is_array($manifest)) {
            throw new RuntimeException('The release manifest is not valid JSON.');
        }

        return $manifest;
    }

    private function client(): PendingRequest
    {
        $client = Http::baseUrl(rtrim((string) config('updater.github_api_url'), '/'))
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => config('app.name').'-Updater/'.config('updater.current_version'),
            ])
            ->connectTimeout(5)
            ->timeout((int) config('updater.request_timeout_seconds'));

        $token = trim((string) config('updater.github_token'));

        return $token === '' ? $client : $client->withToken($token);
    }
}
