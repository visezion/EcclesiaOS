<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSIONS = [
        'backup.view' => 'View backup history, health, and restore validation results.',
        'backup.create' => 'Create manual database, files, and full system backups.',
        'backup.download' => 'Download encrypted backup packages.',
        'backup.delete' => 'Delete backup packages and their storage copies.',
        'backup.configure' => 'Configure backup automation and retention policies.',
        'backup.restore' => 'Upload and validate packages for restore or cloning.',
        'backup.restore_production' => 'Approve destructive production restore operations.',
        'backup.external_storage' => 'Configure and use external backup destinations.',
    ];

    public function up(): void
    {
        Schema::create('backup_destinations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('driver', 40)->default('local');
            $table->text('configuration')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_offsite')->default(false);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 30)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['church_id', 'name']);
        });

        Schema::create('backup_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('backup_type', 30)->default('full');
            $table->string('frequency', 30)->default('manual');
            $table->json('days')->nullable();
            $table->time('run_at')->default('02:00');
            $table->string('timezone')->default('UTC');
            $table->json('destination_ids')->nullable();
            $table->unsignedSmallInteger('keep_daily')->default(7);
            $table->unsignedSmallInteger('keep_weekly')->default(4);
            $table->unsignedSmallInteger('keep_monthly')->default(12);
            $table->unsignedSmallInteger('keep_yearly')->default(3);
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamps();
            $table->unique('church_id');
        });

        Schema::create('backup_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->string('source', 30)->default('manual');
            $table->string('status', 30)->default('queued')->index();
            $table->string('stage', 60)->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('disk', 60)->default('local');
            $table->string('path', 1000)->nullable();
            $table->string('filename')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->boolean('encrypted')->default(true);
            $table->json('manifest')->nullable();
            $table->json('destination_ids')->nullable();
            $table->json('copies')->nullable();
            $table->unsignedInteger('file_count')->default(0);
            $table->unsignedInteger('record_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['church_id', 'created_at']);
        });

        Schema::create('backup_restore_uploads', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->unsignedBigInteger('total_size');
            $table->unsignedInteger('chunk_size');
            $table->unsignedInteger('total_chunks');
            $table->json('received_chunks')->nullable();
            $table->string('expected_checksum', 64)->nullable();
            $table->string('path', 1000);
            $table->string('status', 30)->default('uploading')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('backup_restore_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('backup_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('restore_upload_id')->nullable()->constrained('backup_restore_uploads')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode', 40)->default('validate');
            $table->string('status', 30)->default('queued')->index();
            $table->string('stage', 60)->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->json('validation')->nullable();
            $table->foreignId('safety_backup_id')->nullable()->constrained('backup_records')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['church_id', 'created_at']);
        });

        Schema::create('backup_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('backup_record_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('restore_request_id')->nullable()->constrained('backup_restore_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('level', 20)->default('info');
            $table->string('stage', 60);
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamps();
            $table->index(['church_id', 'created_at']);
        });

        foreach (self::PERMISSIONS as $permission => $description) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => Str::slug($permission)],
                ['name' => $permission, 'description' => $description, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $this->attach(array_keys(self::PERMISSIONS), ['Super Administrator']);
        $this->attach(['backup.view', 'backup.create', 'backup.download', 'backup.configure', 'backup.restore', 'backup.external_storage'], ['Church Administrator']);
        $this->attach(['backup.view', 'backup.create', 'backup.download'], ['Senior Pastor']);
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_events');
        Schema::dropIfExists('backup_restore_requests');
        Schema::dropIfExists('backup_restore_uploads');
        Schema::dropIfExists('backup_records');
        Schema::dropIfExists('backup_schedules');
        Schema::dropIfExists('backup_destinations');

        $permissionIds = DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }

    /**
     * @param  array<int, string>  $permissions
     * @param  array<int, string>  $roles
     */
    private function attach(array $permissions, array $roles): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', $permissions)->pluck('id');
        $roleIds = DB::table('roles')->whereIn('name', $roles)->pluck('id');

        $roleIds->each(function (int $roleId) use ($permissionIds): void {
            $permissionIds->each(function (int $permissionId) use ($roleId): void {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        });
    }
};
