<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;

final class SocialAuthProviderRegistry
{
    /**
     * @return Collection<string, array<string, mixed>>
     */
    public static function providers(): Collection
    {
        return collect([
            'google' => [
                'label' => 'Google',
                'icon' => 'globe-2',
                'logo' => 'images/auth-providers/google.svg',
                'color' => '#DB4437',
                'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_url' => 'https://oauth2.googleapis.com/token',
                'user_url' => 'https://www.googleapis.com/oauth2/v3/userinfo',
                'scope' => 'openid email profile',
            ],
            'facebook' => [
                'label' => 'Facebook',
                'icon' => 'users-round',
                'logo' => 'images/auth-providers/facebook.svg',
                'color' => '#1877F2',
                'auth_url' => 'https://www.facebook.com/v20.0/dialog/oauth',
                'token_url' => 'https://graph.facebook.com/v20.0/oauth/access_token',
                'user_url' => 'https://graph.facebook.com/v20.0/me?fields=id,name,email,picture',
                'scope' => 'email public_profile',
            ],
            'linkedin' => [
                'label' => 'LinkedIn',
                'icon' => 'briefcase-medical',
                'logo' => 'images/auth-providers/linkedin.svg',
                'color' => '#0A66C2',
                'auth_url' => 'https://www.linkedin.com/oauth/v2/authorization',
                'token_url' => 'https://www.linkedin.com/oauth/v2/accessToken',
                'user_url' => 'https://api.linkedin.com/v2/userinfo',
                'scope' => 'openid profile email',
            ],
            'x' => [
                'label' => 'X',
                'icon' => 'x',
                'logo' => 'images/auth-providers/x.svg',
                'color' => '#0F172A',
                'auth_url' => 'https://twitter.com/i/oauth2/authorize',
                'token_url' => 'https://api.twitter.com/2/oauth2/token',
                'user_url' => 'https://api.twitter.com/2/users/me?user.fields=profile_image_url',
                'scope' => 'users.read tweet.read offline.access',
                'pkce' => true,
            ],
            'github' => [
                'label' => 'GitHub',
                'icon' => 'git-branch',
                'logo' => 'images/auth-providers/github.svg',
                'color' => '#24292F',
                'auth_url' => 'https://github.com/login/oauth/authorize',
                'token_url' => 'https://github.com/login/oauth/access_token',
                'user_url' => 'https://api.github.com/user',
                'emails_url' => 'https://api.github.com/user/emails',
                'scope' => 'read:user user:email',
            ],
            'microsoft' => [
                'label' => 'Microsoft',
                'icon' => 'panel-top',
                'logo' => 'images/auth-providers/microsoft.svg',
                'color' => '#2563EB',
                'auth_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
                'token_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
                'user_url' => 'https://graph.microsoft.com/oidc/userinfo',
                'scope' => 'openid email profile',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function provider(string $provider): array
    {
        $definition = self::providers()->get($provider);
        abort_if($definition === null, 404);

        return $definition;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return Collection<int, array<string, mixed>>
     */
    public static function loginProviders(array $settings): Collection
    {
        $configured = data_get($settings, 'social_auth.providers', []);

        return self::providers()
            ->map(function (array $definition, string $key) use ($configured): array {
                $provider = $configured[$key] ?? [];

                return [
                    ...$definition,
                    'key' => $key,
                    'enabled' => (bool) ($provider['enabled'] ?? false),
                    'configured' => filled($provider['client_id'] ?? null) && filled($provider['client_secret_encrypted'] ?? null),
                ];
            })
            ->filter(fn (array $provider): bool => $provider['enabled'] && $provider['configured'])
            ->values();
    }
}
