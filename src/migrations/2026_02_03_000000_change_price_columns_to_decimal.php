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
        // Change projects table columns to decimal
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('total_price', 15, 2)->nullable()->change();
            $table->decimal('invoiced_amount', 15, 2)->nullable()->change();
            $table->decimal('remaining_amount', 15, 2)->nullable()->change();
            $table->decimal('used_amount', 15, 2)->nullable()->change();
        });

        // Change po_deposits table columns to decimal
        Schema::table('po_deposits', function (Blueprint $table) {
            $table->decimal('budget', 15, 2)->nullable()->change();
            $table->decimal('expense', 15, 2)->nullable()->change();
            $table->decimal('balance', 15, 2)->nullable()->change();
            $table->decimal('total_price', 15, 2)->nullable()->change();
        });

        // Change products table columns to decimal
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 15, 2)->nullable()->change();
            $table->decimal('total_price', 15, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert projects table columns to bigInteger
        Schema::table('projects', function (Blueprint $table) {
            $table->bigInteger('total_price')->nullable()->change();
            $table->bigInteger('invoiced_amount')->nullable()->change();
            $table->bigInteger('remaining_amount')->nullable()->change();
            $table->bigInteger('used_amount')->nullable()->change();
        });

        // Revert po_deposits table columns to bigInteger
        Schema::table('po_deposits', function (Blueprint $table) {
            $table->bigInteger('budget')->nullable()->change();
            $table->bigInteger('expense')->nullable()->change();
            $table->bigInteger('balance')->nullable()->change();
            $table->bigInteger('total_price')->nullable()->change();
        });

        // Revert products table columns to bigInteger
        Schema::table('products', function (Blueprint $table) {
            $table->bigInteger('price')->nullable()->change();
            $table->bigInteger('total_price')->nullable()->change();
        });
    }
};
