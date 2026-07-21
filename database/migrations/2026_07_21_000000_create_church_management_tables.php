<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('churches', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('timezone')->default('UTC');
            $table->string('currency', 3)->default('USD');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('campuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->text('address')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['church_id', 'slug']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('church_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('campus_id')->nullable()->after('church_id')->constrained()->nullOnDelete();
            $table->string('title')->nullable()->after('name');
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('families', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('primary_contact_id')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['church_id', 'campus_id']);
        });

        Schema::create('members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('family_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('status')->default('active')->index();
            $table->date('joined_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['church_id', 'campus_id', 'status']);
        });

        Schema::create('funds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['church_id', 'name']);
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();
            $table->string('venue')->nullable();
            $table->string('category')->nullable()->index();
            $table->string('status')->default('scheduled')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->date('service_date')->index();
            $table->string('status')->default('present');
            $table->dateTime('checked_in_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['church_id', 'campus_id', 'service_date']);
        });

        Schema::create('donations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fund_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('method')->nullable();
            $table->dateTime('received_at')->index();
            $table->string('reference')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ministries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->foreignId('leader_id')->nullable()->constrained('members')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('volunteers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ministry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role')->nullable();
            $table->string('status')->default('active')->index();
            $table->json('availability')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['church_id', 'name']);
        });

        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('serial_number')->nullable()->index();
            $table->string('status')->default('available')->index();
            $table->string('condition')->default('good')->index();
            $table->date('purchased_at')->nullable();
            $table->decimal('purchase_amount', 12, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('facilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status')->default('available')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bookstore_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('sku')->nullable()->index();
            $table->string('category')->nullable()->index();
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('reorder_level')->default(5);
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bookstore_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number')->unique();
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('paid')->index();
            $table->dateTime('ordered_at')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('prayer_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('request');
            $table->string('status')->default('open')->index();
            $table->boolean('is_confidential')->default(false);
            $table->dateTime('followed_up_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();
            $table->string('subject');
            $table->text('message');
            $table->string('sentiment')->nullable()->index();
            $table->string('status')->default('open')->index();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('staff', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('department')->nullable()->index();
            $table->string('job_title')->nullable();
            $table->string('employment_status')->default('active')->index();
            $table->date('started_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('module')->index();
            $table->string('action')->index();
            $table->text('description');
            $table->json('properties')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('workflows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('module')->index();
            $table->string('status')->default('draft')->index();
            $table->json('steps')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('approvable');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->text('notes')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key')->index();
            $table->json('value')->nullable();
            $table->string('type')->default('string');
            $table->timestamps();
            $table->unique(['church_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('workflows');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('feedback');
        Schema::dropIfExists('prayer_requests');
        Schema::dropIfExists('bookstore_orders');
        Schema::dropIfExists('bookstore_products');
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_categories');
        Schema::dropIfExists('volunteers');
        Schema::dropIfExists('ministries');
        Schema::dropIfExists('donations');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('events');
        Schema::dropIfExists('funds');
        Schema::dropIfExists('members');
        Schema::dropIfExists('families');
        Schema::dropIfExists('role_user');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('campus_id');
            $table->dropConstrainedForeignId('church_id');
            $table->dropColumn('title');
        });
        Schema::dropIfExists('campuses');
        Schema::dropIfExists('churches');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
