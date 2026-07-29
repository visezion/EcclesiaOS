<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE bookstore_products ADD COLUMN IF NOT EXISTS author varchar(255) NULL AFTER category");
        DB::statement("ALTER TABLE bookstore_products ADD COLUMN IF NOT EXISTS isbn varchar(255) NULL AFTER author");
        DB::statement("ALTER TABLE bookstore_products ADD COLUMN IF NOT EXISTS format varchar(255) NOT NULL DEFAULT 'hardcopy' AFTER isbn");
        DB::statement("ALTER TABLE bookstore_products ADD COLUMN IF NOT EXISTS publisher varchar(255) NULL AFTER format");
        DB::statement("ALTER TABLE bookstore_products ADD COLUMN IF NOT EXISTS digital_url varchar(255) NULL AFTER publisher");
        DB::statement("ALTER TABLE bookstore_products ADD COLUMN IF NOT EXISTS is_library_item tinyint(1) NOT NULL DEFAULT 0 AFTER digital_url");
        DB::statement("ALTER TABLE bookstore_products ADD COLUMN IF NOT EXISTS borrowable tinyint(1) NOT NULL DEFAULT 0 AFTER is_library_item");
        DB::statement("ALTER TABLE bookstore_products ADD COLUMN IF NOT EXISTS rentable tinyint(1) NOT NULL DEFAULT 0 AFTER borrowable");
        DB::statement("ALTER TABLE bookstore_products ADD COLUMN IF NOT EXISTS rental_price decimal(12,2) NULL AFTER rentable");

        Schema::create('bookstore_library_loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bookstore_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('handled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('loan_number')->unique();
            $table->string('loan_type')->index();
            $table->string('status')->default('active')->index();
            $table->dateTime('checked_out_at')->index();
            $table->dateTime('due_at')->nullable()->index();
            $table->dateTime('returned_at')->nullable()->index();
            $table->decimal('rental_amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'campus_id', 'status']);
            $table->index(['bookstore_product_id', 'member_id', 'loan_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookstore_library_loans');

        Schema::table('bookstore_products', function (Blueprint $table): void {
            $table->dropColumn([
                'author',
                'isbn',
                'format',
                'publisher',
                'digital_url',
                'is_library_item',
                'borrowable',
                'rentable',
                'rental_price',
            ]);
        });
    }
};
