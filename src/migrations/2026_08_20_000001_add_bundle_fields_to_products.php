<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'qty_per_set')) {
            Schema::table('products', function (Blueprint $table) {
                $column = $table->decimal('qty_per_set', 15, 2)->nullable();
                if (Schema::hasColumn('products', 'quantity')) {
                    $column->after('quantity');
                }
            });
        }
        if (!Schema::hasColumn('products', 'is_group_main')) {
            Schema::table('products', function (Blueprint $table) {
                $column = $table->boolean('is_group_main')->default(false);
                if (Schema::hasColumn('products', 'qty_per_set')) {
                    $column->after('qty_per_set');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'is_group_main')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('is_group_main');
            });
        }
        if (Schema::hasColumn('products', 'qty_per_set')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('qty_per_set');
            });
        }
    }
};
