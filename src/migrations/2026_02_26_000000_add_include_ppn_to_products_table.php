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
        if (!Schema::hasColumn('products', 'include_ppn')) {
            Schema::table('products', function (Blueprint $table) {
                $column = $table->boolean('include_ppn')->default(false);
                // Only use after() if tax_code column exists
                if (Schema::hasColumn('products', 'tax_code')) {
                    $column->after('tax_code');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('products', 'include_ppn')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('include_ppn');
            });
        }
    }
};
