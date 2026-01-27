<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery')->constrained('deliveries','id')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('origin')->constrained('warehouses','id')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('destination')->constrained('warehouses','id')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('product')->constrained('products','id')->onUpdate('cascade')->onDelete('cascade');
            $table->bigInteger('quantity');
            $table->bigInteger('actual_quantity');
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_items');
    }
};
