<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('po_deposits', function (Blueprint $table) {
            $table->string('sync_status')->nullable()->after('invoice_pic'); // pending, syncing, success, failed
            $table->timestamp('last_synced_at')->nullable()->after('sync_status');
            $table->text('sync_error')->nullable()->after('last_synced_at');
            $table->integer('sync_retry_count')->default(0)->after('sync_error');
        });
    }

    public function down(): void
    {
        Schema::table('po_deposits', function (Blueprint $table) {
            $table->dropColumn(['sync_status', 'last_synced_at', 'sync_error', 'sync_retry_count']);
        });
    }
};
