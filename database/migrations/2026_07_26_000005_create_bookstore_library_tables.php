<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookstore_products', 'author')) {
            Schema::table('bookstore_products', function (Blueprint $table): void {
                $table->string('author')->nullable()->after('category');
            });
        }

        if (! Schema::hasColumn('bookstore_products', 'isbn')) {
            Schema::table('bookstore_products', function (Blueprint $table): void {
                $table->string('isbn')->nullable()->after('author');
            });
            Schema::table('bookstore_products', function (Blueprint $table): void {
                $table->index('isbn');
            });
        }

        if (! Schema::hasColumn('bookstore_products', 'format')) {
            Schema::table('bookstore_products', function (Blueprint $table): void {
                $table->string('format')->default('hardcopy')->after('isbn');
            });
            Schema::table('bookstore_products', function (Blueprint $table): void {
                $table->index('format');
            });
        }

        if (! Schema::hasColumn('bookstore_products', 'publisher')) {
            Schema::table('bookstore_products', function (Blueprint $table): void {
                $table->string('publisher')->nullable()->after('format');
            });
        }

        if (! Schema::hasColumn('bookstore_products', 'digital_url')) {
            Schema::table('bookstore_products', function (Blueprint $table): void {
                $table->string('digital_url')->nullable()->after('publisher');
            });
        }

        if (! Schema::hasColumn('bookstore_products', 'is_library_item')) {
            Schema::table('bookstore_products', function (Blueprint $table): void {
                $table->boolean('is_library_item')->default(false)->after('digital_url');
            });
            Schema::table('bookstore_products', function (Blueprint $table): void {
                $table->index('is_library_item');
            });
        }

        if (! Schema::hasColumn('bookstore_products', 'borrowable')) {
            Schema::table('bookstore_products', function (Blueprint $table): void {
                $table->boolean('borrowable')->default(false)->after('is_library_item');
            });
            Schema::table('bookstore_products', function (Blueprint $table): void {
                $table->index('borrowable');
            });
        }

        if (! Schema::hasColumn('bookstore_products', 'rentable')) {
            Schema::table('bookstore_products', function (Blueprint $table): void {
                $table->boolean('rentable')->default(false)->after('borrowable');
            });
            Schema::table('bookstore_products', function (Blueprint $table): void {
                $table->index('rentable');
            });
        }

        if (! Schema::hasColumn('bookstore_products', 'rental_price')) {
            Schema::table('bookstore_products', function (Blueprint $table): void {
                $table->decimal('rental_price', 12, 2)->nullable()->after('rentable');
            });
        }

        if (! Schema::hasTable('bookstore_library_loans')) {
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

                $table->index(['church_id', 'campus_id', 'status'], 'bookstore_library_loans_church_campus_status_idx');
                $table->index(['bookstore_product_id', 'member_id', 'loan_type'], 'bookstore_library_loans_product_member_type_idx');
            });
        }
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
