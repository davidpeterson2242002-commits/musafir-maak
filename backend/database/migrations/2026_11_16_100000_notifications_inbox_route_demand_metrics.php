<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // In-app notifications list (the bell): a missed push is still there to tap.
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event', 60);
            $table->string('title', 200);
            $table->text('body');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'id']);
            $table->index(['user_id', 'read_at']);
        });

        // "Tell me when this closed route opens" (passengers searching, drivers posting).
        Schema::table('route_alerts', function (Blueprint $table) {
            $table->string('kind', 10)->default('trips')->after('destination_city_id'); // trips | reopen
            $table->string('role', 10)->nullable()->after('kind');                      // passenger | driver (reopen)
            $table->index(['kind', 'active']);
        });

        // Searches on closed routes: demand the admin can act on.
        Schema::table('search_logs', function (Blueprint $table) {
            $table->boolean('closed')->default(false)->after('results_count');
        });

        // One row per minute of API traffic (written by the scheduler from cache counters).
        Schema::create('metric_minutes', function (Blueprint $table) {
            $table->id();
            $table->timestamp('minute')->unique();
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedInteger('errors')->default(0);      // 5xx
            $table->unsignedInteger('slow')->default(0);        // > 1 s
            $table->unsignedBigInteger('total_ms')->default(0);
            $table->unsignedInteger('max_ms')->default(0);
            $table->unsignedInteger('online')->default(0);      // users seen in the last 2 minutes
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_minutes');
        Schema::table('search_logs', fn (Blueprint $t) => $t->dropColumn('closed'));
        Schema::table('route_alerts', function (Blueprint $table) {
            $table->dropIndex(['kind', 'active']);
            $table->dropColumn(['kind', 'role']);
        });
        Schema::dropIfExists('user_notifications');
    }
};
