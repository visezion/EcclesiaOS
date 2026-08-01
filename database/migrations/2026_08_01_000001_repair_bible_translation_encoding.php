<?php

declare(strict_types=1);

use App\Support\Utf8Text;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('bible_translations')
            ->select(['id', 'name', 'language', 'description', 'copyright'])
            ->orderBy('id')
            ->chunkById(500, function ($translations): void {
                foreach ($translations as $translation) {
                    $values = collect(['name', 'language', 'description', 'copyright'])
                        ->mapWithKeys(fn (string $column): array => [$column => Utf8Text::repair($translation->{$column})])
                        ->all();

                    DB::table('bible_translations')
                        ->where('id', $translation->id)
                        ->update($values);
                }
            });
    }

    public function down(): void
    {
        // Encoding repair is intentionally irreversible.
    }
};
