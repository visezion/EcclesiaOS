<?php

declare(strict_types=1);

namespace App\Services;

final class SearchService
{
    public function search(?string $query): array
    {
        $query = trim((string) $query);

        if ($query === '') {
            return ['query' => $query, 'results' => []];
        }

        $navigation = collect(config('navigation'))
            ->map(fn (array $item): array => [
                'category' => 'Module',
                'title' => $item['label'],
                'description' => 'Open '.$item['label'].' module',
                'url' => route($item['route']),
            ]);

        $sample = collect([
            ['category' => 'Member', 'title' => 'Sarah Johnson', 'description' => 'New member registration', 'url' => route('members.index')],
            ['category' => 'Member', 'title' => 'Michael Thompson', 'description' => 'Donor and volunteer record', 'url' => route('members.index')],
            ['category' => 'Event', 'title' => 'Youth Camp 2024', 'description' => 'Upcoming youth event', 'url' => route('events.index')],
            ['category' => 'Event', 'title' => 'Sunday Worship Service', 'description' => 'Main weekly service', 'url' => route('events.index')],
            ['category' => 'Asset', 'title' => 'Wireless Microphones', 'description' => 'Asset inventory item', 'url' => route('assets.index')],
            ['category' => 'Asset', 'title' => 'Main Hall Projector', 'description' => 'Maintenance due soon', 'url' => route('assets.index')],
        ]);

        return [
            'query' => $query,
            'results' => $navigation
                ->merge($sample)
                ->filter(fn (array $result): bool => $this->matches($result, $query))
                ->values()
                ->take(12)
                ->all(),
        ];
    }

    private function matches(array $result, string $query): bool
    {
        return str_contains(strtolower($result['title'].' '.$result['description'].' '.$result['category']), strtolower($query));
    }
}
