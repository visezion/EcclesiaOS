<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Branding;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    /** @var list<string> */
    private const SUPPORTED_LOCALES = ['en', 'fr', 'es'];

    public function handle(Request $request, Closure $next): Response
    {
        $accountLocale = data_get($request->user()?->account_settings, 'preferences.language');
        $churchLocale = data_get($request->user()?->church?->settings, 'language')
            ?? data_get(Branding::current()->settings, 'language');
        $defaultLocale = self::normalizeLocale($churchLocale) ?? config('app.locale', 'en');
        $locale = is_string($accountLocale) && in_array($accountLocale, self::SUPPORTED_LOCALES, true)
            ? $accountLocale
            : $request->session()->get('locale', $request->cookie('locale', $defaultLocale));

        if (! is_string($locale) || ! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = 'en';
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);
        $request->session()->put('locale', $locale);

        return $next($request);
    }

    private static function normalizeLocale(mixed $language): ?string
    {
        return match (strtolower(trim((string) $language))) {
            'en', 'english', 'english (us)', 'english (uk)' => 'en',
            'fr', 'french' => 'fr',
            'es', 'spanish' => 'es',
            default => null,
        };
    }
}
