<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project')->constrained('projects', 'id')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('product')->constrained('products', 'id')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('warehouse')->constrained('warehouses', 'id')->onUpdate('cascade')->onDelete('cascade');
            $table->string('warehouse_name');
            $table->string('product_name');
            $table->integer('quantity');
            $table->boolean('storage');
            $table->unique(['warehouse', 'product']);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
