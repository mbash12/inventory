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
        Schema::create('sync_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('sync_type')->default('project_to_sales_order');
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->foreignId('po_deposit_id')->nullable()->constrained('po_deposits')->onDelete('set null');
            $table->foreignId('project_id')->nullable()->constrained('projects')->onDelete('set null');
            $table->integer('company_id')->nullable();
            $table->json('payload')->nullable(); // input data
            $table->json('result')->nullable(); // output/result data
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(3);
            $table->timestamps();
            
            // Indexes for performance
            $table->index('status');
            $table->index('sync_type');
            $table->index(['status', 'sync_type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_jobs');
    }
};
