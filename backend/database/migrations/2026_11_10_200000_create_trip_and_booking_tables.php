<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('route_id')->constrained('routes')->restrictOnDelete();
            $table->timestamp('departs_at')->index();
            $table->timestamp('est_arrival_at')->nullable();
            $table->unsignedInteger('price_per_seat'); // SDG
            $table->unsignedSmallInteger('seats_total');
            $table->unsignedSmallInteger('seats_available');
            $table->unsignedSmallInteger('luggage_large')->default(1); // per passenger
            $table->unsignedSmallInteger('luggage_small')->default(1);
            $table->unsignedInteger('extra_luggage_price')->nullable(); // per extra bag, SDG
            $table->boolean('accepts_parcels')->default(false);
            $table->unsignedInteger('parcel_price_small')->nullable();
            $table->unsignedInteger('parcel_price_medium')->nullable();
            $table->unsignedInteger('parcel_price_large')->nullable();
            $table->unsignedSmallInteger('max_parcels')->default(0);
            $table->boolean('women_only')->default(false);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('scheduled')->index(); // App\Enums\TripStatus
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reminded_evening_at')->nullable(); // driver reminders
            $table->timestamp('reminded_soon_at')->nullable();
            $table->boolean('hidden_by_admin')->default(false);
            $table->timestamps();
            $table->index(['route_id', 'status', 'departs_at']);
        });

        Schema::create('trip_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10); // pickup / dropoff
            $table->string('label', 150);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->restrictOnDelete();
            $table->foreignId('passenger_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('seats');
            $table->foreignId('pickup_point_id')->nullable()->constrained('trip_points')->nullOnDelete();
            $table->unsignedSmallInteger('extra_bags')->default(0);
            $table->boolean('includes_family')->default(false);
            $table->string('message', 500)->nullable();
            $table->string('status', 30)->default('pending')->index(); // App\Enums\BookingStatus
            $table->unsignedInteger('price_total'); // paid to the driver directly, SDG
            $table->unsignedInteger('fee_amount')->nullable(); // set at acceptance (M4)
            $table->boolean('fee_free')->default(false);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->boolean('late_cancellation')->default(false);
            $table->timestamp('no_show_at')->nullable();
            $table->timestamp('no_show_disputed_at')->nullable();
            $table->text('no_show_dispute_details')->nullable();
            $table->timestamp('no_show_confirmed_at')->nullable(); // fee reversed from here (M4)
            $table->string('no_show_resolution', 20)->nullable(); // confirmed / overturned
            $table->foreignId('no_show_resolved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reminded_evening_at')->nullable(); // passenger reminders
            $table->timestamp('reminded_soon_at')->nullable();
            $table->timestamps();
            $table->index(['passenger_id', 'status']);
            $table->index(['trip_id', 'status']);
        });

        Schema::create('route_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('origin_city_id')->constrained('cities')->cascadeOnDelete();
            $table->foreignId('destination_city_id')->constrained('cities')->cascadeOnDelete();
            $table->date('date')->nullable(); // null = any date
            $table->boolean('active')->default(true);
            $table->timestamp('last_notified_at')->nullable();
            $table->unsignedInteger('notified_count')->default(0);
            $table->timestamps();
            $table->index(['origin_city_id', 'destination_city_id', 'active']);
        });

        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origin_city_id')->constrained('cities')->cascadeOnDelete();
            $table->foreignId('destination_city_id')->constrained('cities')->cascadeOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('routes')->nullOnDelete();
            $table->date('date')->nullable();
            $table->string('kind', 10)->default('trip'); // trip / parcel
            $table->unsignedInteger('results_count');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('booking_restricted_until')->nullable()->after('suspended_until');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('booking_restricted_until'));
        Schema::dropIfExists('search_logs');
        Schema::dropIfExists('route_alerts');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('trip_points');
        Schema::dropIfExists('trips');
    }
};
