<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\Event;
use App\Models\Member;
use App\Models\User;
use App\Support\ModuleRegistry;

final class SearchService
{
    public function search(?string $query, ?User $actor = null): array
    {
        $query = trim((string) $query);

        if ($query === '') {
            return ['query' => $query, 'results' => []];
        }

        $navigation = collect(ModuleRegistry::visibleNavigation($actor?->church))
            ->flatMap(fn (array $item): array => [$item, ...($item['children'] ?? [])])
            ->filter(fn (array $item): bool => isset($item['route']))
            ->filter(fn (array $item): bool => $this->canAccessNavigationItem($item, $actor))
            ->map(fn (array $item): array => [
                'category' => 'Module',
                'title' => $item['label'],
                'description' => 'Open '.$item['label'].' module',
                'url' => route($item['route']),
            ]);

        $records = collect()
            ->when(! ModuleRegistry::isDisabledRoute('members.index'), fn ($results) => $results->merge($this->memberResults($query)))
            ->when(! ModuleRegistry::isDisabledRoute('events.index'), fn ($results) => $results->merge($this->eventResults($query)))
            ->when(! ModuleRegistry::isDisabledRoute('assets.index'), fn ($results) => $results->merge($this->assetResults($query)));

        return [
            'query' => $query,
            'results' => $navigation
                ->merge($records)
                ->filter(fn (array $result): bool => $this->matches($result, $query))
                ->values()
                ->take(12)
                ->all(),
        ];
    }

    private function memberResults(string $query): array
    {
        $term = '%'.$query.'%';

        return Member::query()
            ->where(fn ($member) => $member
                ->where('first_name', 'like', $term)
                ->orWhere('last_name', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('phone', 'like', $term))
            ->limit(4)
            ->get()
            ->map(fn (Member $member): array => [
                'category' => 'Member',
                'title' => $member->first_name.' '.$member->last_name,
                'description' => trim(($member->email ?: 'No email').' '.$member->status),
                'url' => route('members.show', $member),
            ])
            ->all();
    }

    private function eventResults(string $query): array
    {
        $term = '%'.$query.'%';

        return Event::query()
            ->where(fn ($event) => $event
                ->where('title', 'like', $term)
                ->orWhere('venue', 'like', $term)
                ->orWhere('category', 'like', $term))
            ->latest('starts_at')
            ->limit(4)
            ->get()
            ->map(fn (Event $event): array => [
                'category' => 'Event',
                'title' => $event->title,
                'description' => trim(($event->venue ?: 'No venue').' '.$event->status),
                'url' => route('events.index', ['q' => $event->title]),
            ])
            ->all();
    }

    private function assetResults(string $query): array
    {
        $term = '%'.$query.'%';

        return Asset::query()
            ->with('category')
            ->where(fn ($asset) => $asset
                ->where('name', 'like', $term)
                ->orWhere('serial_number', 'like', $term)
                ->orWhereHas('category', fn ($category) => $category->where('name', 'like', $term)))
            ->limit(4)
            ->get()
            ->map(fn (Asset $asset): array => [
                'category' => 'Asset',
                'title' => $asset->name,
                'description' => trim(($asset->category?->name ?: 'Uncategorized').' '.$asset->status),
                'url' => route('assets.index', ['q' => $asset->name]),
            ])
            ->all();
    }

    private function matches(array $result, string $query): bool
    {
        return str_contains(strtolower($result['title'].' '.$result['description'].' '.$result['category']), strtolower($query));
    }

    private function canAccessNavigationItem(array $item, ?User $actor): bool
    {
        return $actor?->isSuperAdministrator()
            || (! empty($item['permissions_any']) && $actor?->hasAnyPermission($item['permissions_any']))
            || (empty($item['permissions_any']) && (empty($item['permission']) || $actor?->hasPermission($item['permission'])));
    }
}
