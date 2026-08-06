<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->string('profile_photo_path')->nullable()->after('phone');
        });

        Schema::create('member_import_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('source_type', 30);
            $table->json('mapping');
            $table->json('transformations')->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_shared')->default(false);
            $table->timestamps();
            $table->unique(['church_id', 'name']);
        });

        Schema::create('member_import_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('driver', 30);
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('database_name');
            $table->string('username')->nullable();
            $table->text('password_encrypted')->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 30)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['church_id', 'name']);
        });

        Schema::create('member_imports', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 36)->unique();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('profile_id')->nullable()->constrained('member_import_profiles')->nullOnDelete();
            $table->foreignId('connection_id')->nullable()->constrained('member_import_connections')->nullOnDelete();
            $table->string('name');
            $table->string('source_type', 30)->index();
            $table->string('status', 30)->default('draft')->index();
            $table->string('disk', 30)->nullable();
            $table->string('path', 1000)->nullable();
            $table->string('original_filename')->nullable();
            $table->string('source_table')->nullable();
            $table->json('source_options')->nullable();
            $table->json('mapping')->nullable();
            $table->json('options')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('summary')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->foreignId('rolled_back_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['church_id', 'status', 'created_at']);
        });

        Schema::create('member_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('member_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('source_data');
            $table->json('normalized_data')->nullable();
            $table->string('status', 30)->default('staged')->index();
            $table->string('duplicate_action', 20)->default('skip');
            $table->foreignId('matched_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('imported_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->text('error')->nullable();
            $table->json('rollback_snapshot')->nullable();
            $table->string('post_import_checksum', 64)->nullable();
            $table->timestamps();
            $table->unique(['member_import_id', 'row_number']);
        });

        Schema::create('member_external_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_import_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 60);
            $table->string('external_id', 190);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['church_id', 'source', 'external_id']);
        });

        Schema::create('member_history_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_import_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 80)->index();
            $table->string('status', 60)->nullable();
            $table->dateTime('occurred_at')->nullable()->index();
            $table->string('source_reference')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_history_entries');
        Schema::dropIfExists('member_external_identities');
        Schema::dropIfExists('member_import_rows');
        Schema::dropIfExists('member_imports');
        Schema::dropIfExists('member_import_connections');
        Schema::dropIfExists('member_import_profiles');

        Schema::table('members', function (Blueprint $table): void {
            $table->dropColumn('profile_photo_path');
        });
    }
};
