<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('draft')->index(); // App\Enums\DriverStatus
            $table->unsignedInteger('free_bookings_remaining')->default(0);
            $table->unsignedBigInteger('balance_owed')->default(0); // SDG, used from M4
            $table->unsignedBigInteger('wallet_balance')->default(0); // SDG, used from M4
            $table->timestamp('settlement_due_at')->nullable();
            $table->timestamp('frozen_at')->nullable();
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // App\Enums\VehicleType
            $table->string('make', 60);
            $table->string('model', 60);
            $table->string('color', 40);
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('plate_number', 30);
            $table->string('plate_normalized', 30)->index(); // for duplicate checks
            $table->unsignedSmallInteger('seats'); // passenger seats, excluding the driver
            $table->string('status', 20)->default('draft')->index(); // App\Enums\VehicleStatus
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Private uploads (ID, licence, vehicle photos...). One current file per owner+kind.
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner'); // user / driver_profile / vehicle
            $table->string('kind', 30); // App\Enums\DocumentKind
            $table->string('disk', 20);
            $table->string('path');
            $table->string('mime', 60)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->timestamps();
            $table->unique(['owner_type', 'owner_id', 'kind']);
        });

        // One row per submission. The latest row for a subject is its current review.
        Schema::create('verification_reviews', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject'); // user (passenger ID) / driver_profile / vehicle
            $table->string('status', 20)->default('pending')->index(); // App\Enums\ReviewStatus
            $table->string('id_document_type', 20)->nullable(); // passport / national_id (identity reviews)
            $table->unsignedSmallInteger('priority')->default(0)->index(); // higher first (e.g. pending first booking)
            $table->json('reasons')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_reviews');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('driver_profiles');
    }
};
