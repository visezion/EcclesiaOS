<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSIONS = [
        'request financial assistance' => 'Submit and track financial assistance requests for an assigned campus.',
        'request cross-campus financial assistance' => 'Route financial assistance requests to headquarters or another campus.',
        'approve financial assistance' => 'Review and approve financial assistance requests for an authorized campus.',
        'manage financial assistance' => 'Manage, approve, and record disbursement of financial assistance requests.',
    ];

    public function up(): void
    {
        Schema::create('financial_assistance_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('source_campus_id')->nullable()->constrained('campuses')->nullOnDelete();
            $table->foreignId('target_campus_id')->constrained('campuses')->restrictOnDelete();
            $table->string('category', 50)->index();
            $table->string('beneficiary_type', 40);
            $table->string('beneficiary_name', 180);
            $table->string('title', 180);
            $table->text('purpose');
            $table->text('justification');
            $table->decimal('amount', 14, 2);
            $table->decimal('approved_amount', 14, 2)->nullable();
            $table->string('currency', 3);
            $table->date('needed_by')->nullable();
            $table->string('urgency', 30)->default('normal')->index();
            $table->string('preferred_payment_method', 50)->nullable();
            $table->string('payee_name', 180)->nullable();
            $table->string('status', 40)->default('submitted')->index();
            $table->string('current_stage', 50)->default('campus_review')->index();
            $table->text('decision_notes')->nullable();
            $table->text('disbursement_notes')->nullable();
            $table->string('disbursement_reference', 120)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('disbursed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['church_id', 'target_campus_id', 'status'], 'financial_assistance_scope_status');
        });

        Schema::create('financial_assistance_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_assistance_request_id')->constrained('financial_assistance_requests', 'id', 'fin_assist_attach_request_fk')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 30)->default('evidence');
            $table->string('disk', 40)->default('local');
            $table->string('path', 1000);
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('sha256', 64);
            $table->timestamps();
        });

        Schema::create('financial_assistance_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_assistance_request_id')->constrained('financial_assistance_requests', 'id', 'fin_assist_activity_request_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50);
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['financial_assistance_request_id', 'created_at'], 'financial_assistance_activity_time');
        });

        foreach (self::PERMISSIONS as $permission => $description) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => Str::slug($permission)],
                [
                    'name' => $permission,
                    'description' => $description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $this->attach('request financial assistance', ['Ministry Leader', 'Branch Pastor', 'Senior Pastor', 'Church Administrator', 'Finance Officer']);
        $this->attach('request cross-campus financial assistance', ['Branch Pastor', 'Senior Pastor', 'Church Administrator', 'Finance Officer']);
        $this->attach('approve financial assistance', ['Branch Pastor', 'Senior Pastor', 'Church Administrator', 'Finance Officer']);
        $this->attach('manage financial assistance', ['Church Administrator', 'Finance Officer']);
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_assistance_activities');
        Schema::dropIfExists('financial_assistance_attachments');
        Schema::dropIfExists('financial_assistance_requests');

        $permissionIds = DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }

    /**
     * @param  array<int, string>  $roles
     */
    private function attach(string $permission, array $roles): void
    {
        $permissionId = DB::table('permissions')->where('name', $permission)->value('id');
        if ($permissionId === null) {
            return;
        }

        DB::table('roles')->whereIn('name', $roles)->pluck('id')->each(function (int $roleId) use ($permissionId): void {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
};
