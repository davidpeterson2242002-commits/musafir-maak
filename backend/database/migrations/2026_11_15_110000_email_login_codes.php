<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where the code went when it was sent by email (setting otp.channel = email).
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->string('email')->nullable()->after('phone');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('otp_codes', fn (Blueprint $table) => $table->dropColumn('email'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('email_verified_at'));
    }
};
