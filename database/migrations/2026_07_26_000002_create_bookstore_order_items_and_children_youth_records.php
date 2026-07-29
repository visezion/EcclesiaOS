<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookstore_order_items')) {
            Schema::create('bookstore_order_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('bookstore_order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('bookstore_product_id')->nullable()->constrained()->nullOnDelete();
                $table->string('product_name');
                $table->unsignedInteger('quantity');
                $table->decimal('unit_price', 12, 2);
                $table->decimal('line_total', 12, 2);
                $table->timestamps();

                $table->index(['bookstore_order_id', 'bookstore_product_id']);
            });
        }

        if (! Schema::hasTable('children_youth_records')) {
            Schema::create('children_youth_records', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('church_id')->constrained()->cascadeOnDelete();
                $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('guardian_member_id')->nullable()->constrained('members')->nullOnDelete();
                $table->string('first_name');
                $table->string('last_name');
                $table->date('date_of_birth')->nullable();
                $table->string('age_group')->index();
                $table->string('guardian_name')->nullable();
                $table->string('guardian_phone')->nullable();
                $table->string('consent_status')->default('pending')->index();
                $table->string('check_in_status')->default('not_checked_in')->index();
                $table->string('pickup_code')->nullable()->index();
                $table->text('medical_notes')->nullable();
                $table->string('status')->default('active')->index();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['church_id', 'campus_id', 'age_group']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('children_youth_records');
        Schema::dropIfExists('bookstore_order_items');
    }
};
