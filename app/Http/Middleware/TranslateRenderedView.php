<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Translates static text that is still emitted directly by a Blade view.
 *
 * Most of the application uses __() or the $term() view helper already. This
 * middleware is the final safety net for legacy and module views so a page
 * cannot silently fall back to English just because one label was written as
 * a literal in a template.
 */
final class TranslateRenderedView
{
    /** @var array<string, array<string, string>> */
    private static array $jsonCatalogs = [];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (app()->getLocale() === 'en'
            || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            || $response->getContent() === false
        ) {
            return $response;
        }

        $response->setContent($this->translateHtml((string) $response->getContent()));

        return $response;
    }

    private function translateHtml(string $html): string
    {
        $html = preg_replace_callback(
            '~(<script\b[^>]*>.*?</script\s*>|<style\b[^>]*>.*?</style\s*>|>([^<>]+)<)~is',
            function (array $matches): string {
                if (! isset($matches[2])) {
                    return $matches[1];
                }

                return '>'.$this->translateFragment($matches[2]).'<';
            },
            $html
        ) ?? $html;

        return preg_replace_callback(
            '~\b(placeholder|title|aria-label|alt)=("|\')([^"\']+)(\2)~i',
            function (array $matches): string {
                return $matches[1].'='.$matches[2].$this->translateFragment($matches[3]).$matches[4];
            },
            $html
        ) ?? $html;
    }

    private function translateFragment(string $fragment): string
    {
        $leading = substr($fragment, 0, strlen($fragment) - strlen(ltrim($fragment)));
        $trailing = substr($fragment, strlen(rtrim($fragment)));
        $source = trim($fragment);

        if ($source === '' || ! preg_match('/[A-Za-z]{2,}/', $source)) {
            return $fragment;
        }

        $decoded = html_entity_decode($source, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $translated = __($decoded);
        $translated = is_string($translated) ? $translated : $decoded;

        if ($translated === $decoded) {
            $translated = $this->translateKnownPhrases($decoded);
        }

        if ($translated === $decoded) {
            return $fragment;
        }

        return $leading.htmlspecialchars($translated, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8').$trailing;
    }

    private function translateKnownPhrases(string $text): string
    {
        $locale = app()->getLocale();
        $catalog = self::$jsonCatalogs[$locale] ??= $this->loadJsonCatalog($locale);

        if ($catalog === []) {
            return $text;
        }

        // Longest-first prevents a short key such as “Report” from being
        // applied before a more precise phrase such as “Report Summary”.
        uksort($catalog, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));
        foreach ($catalog as $source => $replacement) {
            if ($source === '' || ! str_contains($text, $source)) {
                continue;
            }

            $text = str_replace($source, $replacement, $text);
        }

        return $text;
    }

    /** @return array<string, string> */
    private function loadJsonCatalog(string $locale): array
    {
        $path = lang_path($locale.'.json');
        if (! is_file($path)) {
            return [];
        }

        $catalog = json_decode((string) file_get_contents($path), true);

        return is_array($catalog)
            ? array_filter($catalog, static fn (mixed $value): bool => is_string($value))
            : [];
    }
}
