<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('churches')->orderBy('id')->eachById(function (object $church): void {
            $settings = is_string($church->settings)
                ? json_decode($church->settings, true)
                : $church->settings;

            if (! is_array($settings)) {
                return;
            }

            $cleaned = $settings;
            foreach (['smtp_server', 'sms_provider', 'whatsapp_integration', 'notification_preferences'] as $key) {
                unset($cleaned[$key]);
            }

            if ($cleaned !== $settings) {
                DB::table('churches')->where('id', $church->id)->update([
                    'settings' => json_encode($cleaned, JSON_THROW_ON_ERROR),
                ]);
            }
        });
    }

    public function down(): void
    {
        // These display-only legacy values cannot be restored safely.
    }
};
