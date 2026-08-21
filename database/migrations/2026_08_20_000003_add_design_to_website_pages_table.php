<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('website_pages', 'design')) {
            Schema::table('website_pages', function (Blueprint $table): void {
                $table->json('design')->nullable()->after('sections');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('website_pages', 'design')) {
            Schema::table('website_pages', function (Blueprint $table): void {
                $table->dropColumn('design');
            });
        }
    }
};
