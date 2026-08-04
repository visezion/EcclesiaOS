<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Church;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class OrganizationTerminology
{
    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'campus_singular' => 'Campus',
            'campus_plural' => 'Campuses',
            'ministry_singular' => 'Ministry',
            'ministry_plural' => 'Ministries',
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, string>
     */
    public static function fromSettings(array $settings): array
    {
        $defaults = self::defaults();

        return [
            'campus_singular' => self::clean($settings['campus_singular_label'] ?? null, $defaults['campus_singular']),
            'campus_plural' => self::clean($settings['campus_plural_label'] ?? null, $defaults['campus_plural']),
            'ministry_singular' => self::clean($settings['ministry_singular_label'] ?? null, $defaults['ministry_singular']),
            'ministry_plural' => self::clean($settings['ministry_plural_label'] ?? null, $defaults['ministry_plural']),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function forChurch(?Church $church): array
    {
        return self::fromSettings($church?->settings ?? []);
    }

    /**
     * @return array<string, string>
     */
    public static function forRequest(Request $request): array
    {
        $cached = $request->attributes->get('organization_terminology');

        if (is_array($cached)) {
            return $cached;
        }

        $user = $request->user();
        $church = $user?->church_id ? Church::query()->find($user->church_id) : ($user ? Church::query()->first() : null);
        $terminology = self::forChurch($church);

        $request->attributes->set('organization_terminology', $terminology);

        return $terminology;
    }

    /**
     * Resolve terminology placeholders and legacy organization words in display text.
     * Database values, route names, permission names, and request values must not be
     * passed through this method.
     *
     * @param  array<string, string>|null  $terminology
     */
    public static function translate(?string $text, ?array $terminology = null): string
    {
        if ($text === null || $text === '') {
            return (string) $text;
        }

        $terminology ??= request()->hasSession() || request()->user()
            ? self::forRequest(request())
            : self::defaults();

        $replacements = [
            ':campus_singular' => $terminology['campus_singular'],
            ':campus_plural' => $terminology['campus_plural'],
            ':ministry_singular' => $terminology['ministry_singular'],
            ':ministry_plural' => $terminology['ministry_plural'],
        ];
        $translated = strtr($text, $replacements);
        $translated = preg_replace('/\b(?:Branches|Campuses)\s*&\s*(?:Branches|Campuses)\b/i', 'Churches & '.$terminology['campus_plural'], $translated) ?? $translated;
        $translated = preg_replace('/\b(?:Ministry|Department)\s*(?:&|\/)\s*(?:Ministry|Department)\b/i', $terminology['ministry_singular'], $translated) ?? $translated;

        return strtr($translated, [
            'Campuses' => $terminology['campus_plural'],
            'Branches' => $terminology['campus_plural'],
            'Campus' => $terminology['campus_singular'],
            'Branch' => $terminology['campus_singular'],
            'campuses' => Str::lower($terminology['campus_plural']),
            'branches' => Str::lower($terminology['campus_plural']),
            'campus' => Str::lower($terminology['campus_singular']),
            'branch' => Str::lower($terminology['campus_singular']),
            'Ministries' => $terminology['ministry_plural'],
            'Departments' => $terminology['ministry_plural'],
            'Ministry' => $terminology['ministry_singular'],
            'Department' => $terminology['ministry_singular'],
            'ministries' => Str::lower($terminology['ministry_plural']),
            'departments' => Str::lower($terminology['ministry_plural']),
            'ministry' => Str::lower($terminology['ministry_singular']),
            'department' => Str::lower($terminology['ministry_singular']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, string>|null  $terminology
     * @return array<string, mixed>
     */
    public static function translateNavigationItem(array $item, ?array $terminology = null): array
    {
        foreach (['label', 'description', 'section'] as $key) {
            if (isset($item[$key]) && is_string($item[$key])) {
                $item[$key] = self::translate($item[$key], $terminology);
            }
        }

        if (isset($item['planned']) && is_array($item['planned'])) {
            $item['planned'] = array_map(fn ($value): mixed => is_string($value) ? self::translate($value, $terminology) : $value, $item['planned']);
        }

        if (isset($item['children']) && is_array($item['children'])) {
            $item['children'] = array_map(fn (array $child): array => self::translateNavigationItem($child, $terminology), $item['children']);
        }

        return $item;
    }

    private static function clean(mixed $value, string $fallback): string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : $fallback;
    }
}
