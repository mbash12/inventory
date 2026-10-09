<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What Accounting last accepted for each Sales Order, so the next sync can
     * send only the difference.
     */
    public function up(): void
    {
        Schema::create('accounting_sync_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('po_deposit_id');
            $table->string('so_key', 32)->comment('"po" for a grouped non-deposit order, "p<project id>" for deposit/aktual orders');
            $table->unsignedBigInteger('company_id');
            $table->unsignedInteger('sync_version')->default(0);
            $table->unsignedBigInteger('accounting_so_id')->nullable();
            $table->string('accounting_order_number')->nullable();
            $table->json('state');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['po_deposit_id', 'so_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_sync_snapshots');
    }
};
