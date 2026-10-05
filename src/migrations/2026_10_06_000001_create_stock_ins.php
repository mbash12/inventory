<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('stock_in_required')->default(false);
        });

        Schema::create('manual_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->string('unit', 30)->default('pcs');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('stock_ins', function (Blueprint $table) {
            $table->id();
            $table->enum('direction', ['in', 'out'])->default('in');
            $table->foreignId('project')->nullable()->constrained('projects', 'id');
            $table->string('do_number');
            $table->date('document_date');
            $table->foreignId('origin')->nullable()->constrained('warehouses', 'id');
            $table->foreignId('warehouse')->constrained('warehouses', 'id');
            $table->text('files')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['ready', 'partial', 'received'])->default('ready');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['project', 'status']);
        });

        Schema::create('stock_in_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_in')->constrained('stock_ins', 'id');
            $table->foreignId('product')->nullable()->constrained('products', 'id');
            $table->foreignId('manual_item')->nullable()->constrained('manual_items', 'id');
            $table->bigInteger('quantity');
            $table->bigInteger('actual_quantity');
            $table->dateTime('received_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('manual_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_item')->constrained('manual_items', 'id');
            $table->foreignId('warehouse')->constrained('warehouses', 'id');
            $table->bigInteger('quantity')->default(0);
            $table->timestamps();
            $table->unique(['manual_item', 'warehouse']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_inventories');
        Schema::dropIfExists('stock_in_items');
        Schema::dropIfExists('stock_ins');
        Schema::dropIfExists('manual_items');
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('stock_in_required'));
    }
};
