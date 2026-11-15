<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Rating prompts a user closed with ✕: never shown on their home screen again. */
    public function up(): void
    {
        Schema::create('rating_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('subject_id');
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_dismissals');
    }
};
