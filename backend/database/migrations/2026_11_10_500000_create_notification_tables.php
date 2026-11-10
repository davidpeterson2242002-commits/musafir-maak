<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-event channel matrix (spec 5.14 / 9.9). A missing row = the event's defaults.
        Schema::create('notification_channels', function (Blueprint $table) {
            $table->id();
            $table->string('event', 60)->unique();
            $table->boolean('push')->default(true);
            $table->boolean('whatsapp')->default(false);
            $table->boolean('email')->default(false);
            $table->timestamps();
        });

        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('segment', 40);
            $table->json('filters')->nullable();   // city_id, origin_city_id, destination_city_id, days
            $table->json('channels');              // ["push", "whatsapp", "email"]
            $table->string('title', 120);
            $table->text('body');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->string('status', 20)->default('queued'); // queued / sending / sent / failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        // One row per delivery attempt outcome, per channel.
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 20)->nullable();   // WhatsApp recipients without an account (parcel receivers, OTP)
            $table->string('event', 60)->index();
            $table->string('channel', 20);             // push / whatsapp / email
            $table->string('status', 20);              // sent / failed / skipped
            $table->string('title', 200)->nullable();
            $table->text('error')->nullable();
            $table->foreignId('broadcast_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('broadcasts');
        Schema::dropIfExists('notification_channels');
    }
};
