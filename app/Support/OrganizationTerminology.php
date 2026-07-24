<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Church;
use Illuminate\Http\Request;

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
        $user = $request->user();
        $church = $user?->church_id
            ? Church::query()->find($user->church_id)
            : Church::query()->first();

        return self::forChurch($church);
    }

    private static function clean(mixed $value, string $fallback): string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : $fallback;
    }
}
