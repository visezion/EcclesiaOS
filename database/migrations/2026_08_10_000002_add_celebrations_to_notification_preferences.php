<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('user_notification_preferences')->orderBy('id')->eachById(function (object $preference): void {
            $categories = $this->decode($preference->categories);
            $categoryChannels = $this->decodeMap($preference->category_channels);
            if (! in_array('celebrations', $categories, true)) {
                $categories[] = 'celebrations';
            }
            if (! array_key_exists('celebrations', $categoryChannels)) {
                $categoryChannels['celebrations'] = $this->decode($preference->channels);
            }
            DB::table('user_notification_preferences')->where('id', $preference->id)->update([
                'categories' => json_encode(array_values(array_unique($categories)), JSON_THROW_ON_ERROR),
                'category_channels' => json_encode($categoryChannels, JSON_THROW_ON_ERROR),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('user_notification_preferences')->orderBy('id')->eachById(function (object $preference): void {
            $categories = array_values(array_filter($this->decode($preference->categories), fn (string $category): bool => $category !== 'celebrations'));
            $categoryChannels = $this->decodeMap($preference->category_channels);
            unset($categoryChannels['celebrations']);
            DB::table('user_notification_preferences')->where('id', $preference->id)->update([
                'categories' => json_encode($categories, JSON_THROW_ON_ERROR),
                'category_channels' => json_encode($categoryChannels, JSON_THROW_ON_ERROR),
            ]);
        });
    }

    /** @return array<int, string> */
    private function decode(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    /** @return array<string, array<int, string>> */
    private function decodeMap(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)->mapWithKeys(fn (mixed $channels, mixed $category): array => [
            (string) $category => $this->decode($channels),
        ])->all();
    }
};
