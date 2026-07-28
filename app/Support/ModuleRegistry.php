<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Church;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ModuleRegistry
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function modules(): Collection
    {
        return collect(config('navigation'))
            ->flatMap(fn (array $item): array => $item['children'] ?? [$item])
            ->filter(fn (array $item): bool => isset($item['route']))
            ->concat(self::featureModules())
            ->unique('route')
            ->values();
    }

    /**
     * Feature modules that are controlled by Module Management but are not
     * standalone sidebar destinations.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private static function featureModules(): Collection
    {
        return collect([
            [
                'label' => 'Studio',
                'route' => 'meetings.rooms.studio',
                'open_route' => 'meetings.index',
                'icon' => 'monitor-play',
                'permission' => 'manage studio',
                'active_routes' => [
                    'meetings.rooms.short.state',
                    'meetings.rooms.short.qna.store',
                    'meetings.rooms.short.polls.vote',
                ],
                'description' => 'Live production studio controls for meeting rooms, scenes, overlays, polls, Q&A, and stream state.',
            ],
        ]);
    }

    /**
     * @return Collection<int, string>
     */
    public static function configurableRoutes(): Collection
    {
        return self::modules()
            ->reject(fn (array $item): bool => self::isRequiredRoute((string) $item['route']))
            ->pluck('route')
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public static function disabledRoutes(?Church $church = null): Collection
    {
        $church ??= Church::query()->first();

        return collect(data_get($church?->settings, 'disabled_modules', []))
            ->filter(fn ($route): bool => is_string($route) && self::configurableRoutes()->contains($route))
            ->values();
    }

    public static function isDisabledRoute(?string $routeName, ?Church $church = null): bool
    {
        if ($routeName === null || self::isRequiredRoute($routeName)) {
            return false;
        }

        $module = self::moduleForRoute($routeName);

        return $module !== null && self::disabledRoutes($church)->contains($module['route']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function moduleForRoute(string $routeName): ?array
    {
        $modules = self::modules();

        $exact = $modules
            ->first(fn (array $item): bool => $routeName === (string) $item['route']);

        if ($exact !== null) {
            return $exact;
        }

        $active = $modules
            ->sortByDesc(fn (array $item): int => collect($item['active_routes'] ?? [])->map(fn (string $route): int => strlen($route))->max() ?? 0)
            ->first(fn (array $item): bool => collect($item['active_routes'] ?? [])->contains(fn (string $activeRoute): bool => $routeName === $activeRoute || Str::startsWith($routeName, $activeRoute.'.')));

        if ($active !== null) {
            return $active;
        }

        $specific = $modules
            ->reject(fn (array $item): bool => Str::endsWith((string) $item['route'], '.index'))
            ->sortByDesc(fn (array $item): int => strlen((string) $item['route']))
            ->first(fn (array $item): bool => Str::startsWith($routeName, (string) $item['route'].'.'));

        if ($specific !== null) {
            return $specific;
        }

        return $modules
            ->filter(fn (array $item): bool => Str::endsWith((string) $item['route'], '.index'))
            ->sortByDesc(fn (array $item): int => strlen(Str::beforeLast((string) $item['route'], '.index')))
            ->first(function (array $item) use ($routeName): bool {
                $route = (string) $item['route'];
                $base = Str::beforeLast($route, '.index');

                return $base !== $route && Str::startsWith($routeName, $base.'.');
            });
    }

    public static function isRequiredRoute(string $route): bool
    {
        return $route === 'dashboard'
            || Str::startsWith($route, [
                'settings.',
                'users.',
                'roles.',
                'modules.',
                'auth-settings.',
                'developer-hub.',
                'audit-logs.',
                'profile.',
                'account.',
                'search',
            ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function visibleNavigation(?Church $church = null): array
    {
        $disabled = self::disabledRoutes($church);

        return collect(config('navigation'))
            ->map(function (array $item) use ($disabled): ?array {
                $children = collect($item['children'] ?? [])
                    ->reject(fn (array $child): bool => $disabled->contains($child['route'] ?? null))
                    ->values()
                    ->all();

                if ($children !== []) {
                    $item['children'] = $children;

                    return $item;
                }

                if (isset($item['children'])) {
                    return null;
                }

                return $disabled->contains($item['route'] ?? null) ? null : $item;
            })
            ->filter()
            ->values()
            ->all();
    }
}
