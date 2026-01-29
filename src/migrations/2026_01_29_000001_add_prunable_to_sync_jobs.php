<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sync_jobs', function (Blueprint $table) {
            // Add prunable column for Laravel's model pruning
            $table->timestamp('pruned_at')->nullable()->after('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sync_jobs', function (Blueprint $table) {
            $table->dropColumn('pruned_at');
        });
    }
};
