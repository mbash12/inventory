<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('job_number')->unique();
            $table->date('client_po_date')->nullable();
            $table->string('client_po_number')->nullable();
            $table->string('client_pic_name')->nullable();
            $table->enum('status', ["ready", "partial", "delivered", "cancel"]);
            $table->foreignId('shipping_vendor')->nullable()->constrained('shipping_vendors', 'id');
            $table->foreignId('manufacture')->nullable()->constrained('warehouses', 'id');
            $table->string('pic_name');
            $table->string('client_company');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
