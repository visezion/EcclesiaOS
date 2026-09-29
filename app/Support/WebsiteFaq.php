<?php

namespace App\Support;

use Illuminate\Support\Str;

final class WebsiteFaq
{
    public static function normalize(array $component): array
    {
        return [
            'faq_title' => Str::limit((string) ($component['faq_title'] ?? 'FAQs'), 120, ''),
            'faq_open' => ($component['faq_open'] ?? '') === 'first' ? 'first' : 'closed',
            'faq_mode' => ($component['faq_mode'] ?? '') === 'multiple' ? 'multiple' : 'single',
            'faq_items' => collect(is_array($component['faq_items'] ?? null) ? $component['faq_items'] : [])
                ->filter(fn ($item) => is_array($item))
                ->take(30)->map(fn (array $item) => [
                    'question' => Str::limit(trim((string) ($item['question'] ?? '')), 240, ''),
                    'answer' => Str::limit(trim((string) ($item['answer'] ?? '')), 5000, ''),
                ])->filter(fn ($item) => $item['question'] !== '' && $item['answer'] !== '')->values()->all(),
        ];
    }
}
