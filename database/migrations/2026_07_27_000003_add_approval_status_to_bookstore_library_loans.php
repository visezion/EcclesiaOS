<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookstore_library_loans', function (Blueprint $table): void {
            if (! Schema::hasColumn('bookstore_library_loans', 'approval_status')) {
                $table->string('approval_status')->default('not_required')->index()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookstore_library_loans', function (Blueprint $table): void {
            if (Schema::hasColumn('bookstore_library_loans', 'approval_status')) {
                $table->dropColumn('approval_status');
            }
        });
    }
};
