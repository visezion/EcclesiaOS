<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone')->nullable()->after('email');
            $table->string('employee_id')->nullable()->after('title');
            $table->date('date_joined')->nullable()->after('employee_id');
            $table->date('date_of_birth')->nullable()->after('date_joined');
            $table->string('gender')->nullable()->after('date_of_birth');
            $table->text('address')->nullable()->after('gender');
            $table->string('timezone')->nullable()->after('address');
            $table->string('emergency_contact_name')->nullable()->after('timezone');
            $table->string('emergency_contact_relationship')->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_relationship');
            $table->string('recovery_email')->nullable()->after('emergency_contact_phone');
            $table->boolean('mfa_enabled')->default(false)->after('recovery_email');
            $table->string('avatar_url')->nullable()->after('mfa_enabled');
        });

        Schema::table('campuses', function (Blueprint $table): void {
            $table->string('type')->default('Main Campus')->after('slug');
            $table->unsignedInteger('capacity')->nullable()->after('address');
            $table->decimal('map_x', 5, 2)->nullable()->after('capacity');
            $table->decimal('map_y', 5, 2)->nullable()->after('map_x');
            $table->json('metadata')->nullable()->after('map_y');
        });
    }

    public function down(): void
    {
        Schema::table('campuses', function (Blueprint $table): void {
            $table->dropColumn(['type', 'capacity', 'map_x', 'map_y', 'metadata']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'phone',
                'employee_id',
                'date_joined',
                'date_of_birth',
                'gender',
                'address',
                'timezone',
                'emergency_contact_name',
                'emergency_contact_relationship',
                'emergency_contact_phone',
                'recovery_email',
                'mfa_enabled',
                'avatar_url',
            ]);
        });
    }
};
