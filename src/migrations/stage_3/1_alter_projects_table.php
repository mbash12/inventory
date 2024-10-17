<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::table('projects', function (Blueprint $table) {
            $table->date('production_deadline')->nullable();
            $table->date('delivery_deadline')->nullable();
            $table->date('po_deadline')->nullable();
            $table->bigInteger('invoiced_amount')->nullable();
            $table->bigInteger('remaining_amount')->nullable();
            $table->bigInteger('used_amount')->nullable();
            $table->foreignId('deposit_id')->nullable()->constrained('projects', 'id')->onUpdate('cascade')->onDelete('set null');
            $table->json('deadline_meta')->nullable();
            $table->string('invoice_status')->nullable();
            $table->string('invoice_pic')->nullable();
            $table->json('invoices')->nullable();
            $table->enum('status', ["production", "ready", "partial", "delivered", "cancel","draft","new"])->change();
        });
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('production_deadline');
            $table->dropColumn('delivery_deadline');
            $table->dropColumn('po_deadline');
            $table->dropColumn('invoiced_amount');
            $table->dropColumn('remaining_amount');
            $table->dropColumn('used_amount');
            $table->dropColumn('deposit_id');
            $table->dropColumn('deadline_meta');
            $table->dropColumn('invoices');
            $table->dropColumn('invoice_status');
            $table->dropColumn('invoice_pic');
            $table->enum('status', ["production", "ready", "partial", "delivered", "cancel"])->change();
        });
    }
};
