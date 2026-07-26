<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookstore_products', function (Blueprint $table): void {
            $table->string('author')->nullable()->after('category');
            $table->string('isbn')->nullable()->index()->after('author');
            $table->string('format')->default('hardcopy')->index()->after('isbn');
            $table->string('publisher')->nullable()->after('format');
            $table->string('digital_url')->nullable()->after('publisher');
            $table->boolean('is_library_item')->default(false)->index()->after('digital_url');
            $table->boolean('borrowable')->default(false)->index()->after('is_library_item');
            $table->boolean('rentable')->default(false)->index()->after('borrowable');
            $table->decimal('rental_price', 12, 2)->nullable()->after('rentable');
        });

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
