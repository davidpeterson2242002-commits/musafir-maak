<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->restrictOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->string('description', 500);
            $table->string('size', 10); // small / medium / large
            $table->string('photo_disk', 20);
            $table->string('photo_path');
            $table->string('receiver_name', 100);
            $table->string('receiver_phone', 20);
            $table->string('payer', 10); // sender / receiver
            $table->string('message', 500)->nullable();
            $table->unsignedInteger('price'); // paid to the driver directly
            $table->string('status', 20)->default('pending')->index(); // App\Enums\ParcelStatus
            $table->unsignedInteger('fee_amount')->nullable();
            $table->boolean('fee_free')->default(false);
            $table->string('fee_model', 20)->nullable();
            $table->timestamp('fee_reversed_at')->nullable();
            $table->text('delivery_code')->nullable(); // encrypted; the sender can view it
            $table->unsignedSmallInteger('code_attempts')->default(0);
            $table->timestamp('prohibited_ack_at');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->string('pickup_photo_disk', 20)->nullable();
            $table->string('pickup_photo_path')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->string('refusal_reason', 500)->nullable(); // driver refused at pickup
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamp('receiver_notified_at')->nullable();
            $table->timestamp('disputed_at')->nullable();
            $table->text('dispute_details')->nullable();
            $table->string('dispute_resolution', 20)->nullable(); // delivered / cancelled
            $table->foreignId('dispute_resolved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['trip_id', 'status']);
            $table->index(['sender_id', 'status']);
        });

        // One conversation per booking or parcel, between its two parties.
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject'); // booking / parcel
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete(); // passenger or sender
            $table->foreignId('driver_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('customer_last_read_at')->nullable();
            $table->timestamp('driver_last_read_at')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->boolean('has_flagged')->default(false)->index();
            $table->timestamps();
            $table->unique(['subject_type', 'subject_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body'); // as shown to the other party (masked if needed)
            $table->text('original_body')->nullable(); // encrypted; only when masked, for safety review
            $table->boolean('was_masked')->default(false);
            $table->boolean('flagged')->default(false)->index();
            $table->string('flag_reason', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['conversation_id', 'id']);
        });

        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rater_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ratee_id')->constrained('users')->cascadeOnDelete();
            $table->morphs('subject'); // booking / parcel
            $table->string('role', 20); // ratee's role: as_driver / as_passenger
            $table->unsignedTinyInteger('stars');
            $table->string('comment', 1000)->nullable();
            $table->json('tags')->nullable();
            $table->boolean('hidden')->default(false); // admin can hide abusive comments
            $table->timestamps();
            $table->unique(['rater_id', 'subject_type', 'subject_id']);
            $table->index(['ratee_id', 'role']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reported_user_id')->constrained('users')->cascadeOnDelete();
            $table->nullableMorphs('subject'); // booking / trip / parcel / conversation
            $table->string('reason', 30); // App\Enums\ReportReason
            $table->text('details')->nullable();
            $table->unsignedTinyInteger('priority')->default(0)->index(); // 1 = safety-critical
            $table->string('status', 20)->default('open')->index(); // open / in_review / resolved
            $table->text('resolution_note')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('enforcement_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 20); // warn / suspend / ban / reactivate
            $table->timestamp('until')->nullable();
            $table->text('reason');
            $table->foreignId('report_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['blocker_id', 'blocked_id']);
        });

        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->timestamp('low_rating_flagged_at')->nullable()->after('rating_count');
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', fn (Blueprint $table) => $table->dropColumn('low_rating_flagged_at'));
        Schema::dropIfExists('user_blocks');
        Schema::dropIfExists('enforcement_actions');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('ratings');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('parcels');
    }
};
