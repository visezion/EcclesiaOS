<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CATEGORIES = ['reports', 'financial_assistance', 'approvals'];

    public function up(): void
    {
        DB::table('user_notification_preferences')
            ->orderBy('id')
            ->chunkById(200, function ($preferences): void {
                foreach ($preferences as $preference) {
                    $channels = $this->decode($preference->channels);
                    $categories = array_values(array_unique([
                        ...$this->decode($preference->categories),
                        ...self::CATEGORIES,
                    ]));
                    $categoryChannels = $this->decodeMap($preference->category_channels);
                    foreach (self::CATEGORIES as $category) {
                        $categoryChannels[$category] ??= $channels;
                    }

                    DB::table('user_notification_preferences')->where('id', $preference->id)->update([
                        'categories' => json_encode($categories, JSON_THROW_ON_ERROR),
                        'category_channels' => json_encode($categoryChannels, JSON_THROW_ON_ERROR),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('user_notification_preferences')
            ->orderBy('id')
            ->chunkById(200, function ($preferences): void {
                foreach ($preferences as $preference) {
                    $categories = array_values(array_diff($this->decode($preference->categories), self::CATEGORIES));
                    $categoryChannels = $this->decodeMap($preference->category_channels);
                    foreach (self::CATEGORIES as $category) {
                        unset($categoryChannels[$category]);
                    }

                    DB::table('user_notification_preferences')->where('id', $preference->id)->update([
                        'categories' => json_encode($categories, JSON_THROW_ON_ERROR),
                        'category_channels' => json_encode($categoryChannels, JSON_THROW_ON_ERROR),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    /**
     * @return array<int, string>
     */
    private function decode(?string $value): array
    {
        $decoded = json_decode($value ?: '[]', true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function decodeMap(?string $value): array
    {
        $decoded = json_decode($value ?: '{}', true);

        return is_array($decoded) ? $decoded : [];
    }
};
