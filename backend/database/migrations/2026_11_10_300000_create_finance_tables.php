<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only. balance = what the driver owes us; wallet = prepaid credit.
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained()->restrictOnDelete();
            $table->string('type', 20)->index(); // App\Enums\LedgerType
            $table->string('bucket', 10); // balance / wallet
            $table->bigInteger('amount'); // signed SDG: + increases the bucket, − decreases it
            $table->bigInteger('balance_after');
            $table->bigInteger('wallet_after');
            $table->nullableMorphs('reference'); // booking / settlement_request / parcel (M5)
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->text('instructions')->nullable();
            $table->json('agent_locations')->nullable(); // for cash at agent/office
            $table->boolean('requires_reference')->default(true);
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('settlement_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->string('purpose', 20); // settlement / wallet_topup
            $table->unsignedBigInteger('amount');
            $table->string('reference_number', 100)->nullable();
            $table->string('receipt_disk', 20)->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending / confirmed / rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reject_reason', 500)->nullable();
            $table->timestamps();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('fee_model', 20)->nullable()->after('fee_free'); // snapshot at acceptance
            $table->timestamp('fee_reversed_at')->nullable()->after('fee_model');
        });

        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->timestamp('settlement_reminded_at')->nullable()->after('settlement_due_at');
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', fn (Blueprint $table) => $table->dropColumn('settlement_reminded_at'));
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn(['fee_model', 'fee_reversed_at']));
        Schema::dropIfExists('settlement_requests');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('ledger_entries');
    }
};
