<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('products', function (Blueprint $table) {
            $table->bigInteger('price')->nullable();
            $table->bigInteger('total_price')->nullable();
            $table->boolean('is_production')->default(false);
            $table->date('date')->nullable();

            // =========
            
            // $table->id();
            // $table->string('name');
            // $table->text('description')->nullable();
            // $table->integer('quantity');
            // $table->foreignId('project')->constrained('projects', 'id')->onUpdate('cascade')->onDelete('cascade');
            // $table->timestamps();
            // $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['price', 'total_price', 'is_production', 'date']);
        });
    }
};
