<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->date('delivery_date');
            $table->string('do_number');
            $table->foreignId('default_origin')->constrained('warehouses','id')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('destination')->constrained('warehouses','id')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('project')->constrained('projects','id')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('shipping_vendor')->nullable()->constrained('shipping_vendors','id');
            $table->enum('status', ["ready","partial","delivered","cancel"]);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();
    }
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
