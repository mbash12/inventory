<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('invoice_progresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('po_deposit')->nullable()->constrained('po_deposits', 'id')->onUpdate('cascade')->onDelete('cascade');
            $table->enum('pic', ["marketing", "delivery", "admin", "finance"])->nullable();
            $table->enum('status', ["progress", "sent"])->default('progress')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_progresses');
    }
};
