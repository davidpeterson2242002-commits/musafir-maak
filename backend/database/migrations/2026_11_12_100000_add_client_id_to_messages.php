<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** App-generated id per message, so a retried send never creates a duplicate. */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('client_id', 64)->nullable()->after('sender_id');
            $table->unique(['conversation_id', 'sender_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique(['conversation_id', 'sender_id', 'client_id']);
            $table->dropColumn('client_id');
        });
    }
};
