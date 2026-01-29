<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('sync_jobs', 'debug_logs')) {
                $table->json('debug_logs')->nullable()->after('error_message');
            }
            if (!Schema::hasColumn('sync_jobs', 'http_requests')) {
                $table->json('http_requests')->nullable()->after('debug_logs');
            }
            if (!Schema::hasColumn('sync_jobs', 'http_responses')) {
                $table->json('http_responses')->nullable()->after('http_requests');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sync_jobs', function (Blueprint $table) {
            $table->dropColumn(['debug_logs', 'http_requests', 'http_responses']);
        });
    }
};
