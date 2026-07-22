<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('member_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('preferred_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 40)->nullable()->index();
            $table->string('marital_status', 40)->nullable()->index();
            $table->date('anniversary_date')->nullable();
            $table->string('occupation')->nullable();
            $table->string('employer')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->string('address_line')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->string('country')->nullable();
            $table->string('alternate_email')->nullable();
            $table->string('home_phone')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_relationship')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_alt_phone')->nullable();
            $table->string('care_level')->default('standard')->index();
            $table->text('care_notes')->nullable();
            $table->json('communication_preferences')->nullable();
            $table->json('spiritual_journey')->nullable();
            $table->json('skills')->nullable();
            $table->json('documents')->nullable();
            $table->unsignedInteger('volunteer_hours')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_profiles');
    }
};
