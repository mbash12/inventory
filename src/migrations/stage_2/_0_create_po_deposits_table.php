<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('po_deposits', function (Blueprint $table) {
            $table->id();
            $table->string('job_number')->unique();
            $table->date('client_po_date')->nullable();
            $table->string('client_po_number')->nullable();
            $table->string('client_company');
            $table->string('client_pic_name')->nullable();
            $table->string('pic_name')->nullable();
            $table->enum('status', ["open", "close"]);
            $table->date('closed_at')->nullable();
            $table->json('purchase_ordres')->nullable();
            $table->boolean('is_po_deposit')->default(false);
            $table->json('invoices')->nullable();
            $table->enum('invoice_status', ["progress", "sent"])->nullable();
            $table->bigInteger('budget')->nullable();
            $table->bigInteger('expense')->nullable();
            $table->bigInteger('balance')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_deposits');
    }
};
