<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // App users (passengers / senders / drivers). Auth is phone OTP only, no password.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(); // filled in profile setup after first OTP login
            $table->string('phone', 20)->unique(); // E.164, e.g. +249912345678
            $table->string('whatsapp_phone', 20)->nullable(); // defaults to phone
            $table->string('email')->nullable();
            $table->string('gender', 10)->nullable(); // App\Enums\Gender
            $table->unsignedBigInteger('city_id')->nullable()->index(); // FK added in geo migration
            $table->string('photo_path')->nullable();
            $table->string('status', 20)->default('active')->index(); // App\Enums\UserStatus
            $table->timestamp('suspended_until')->nullable();
            $table->string('id_verification_status', 20)->default('none')->index(); // App\Enums\IdVerificationStatus
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
