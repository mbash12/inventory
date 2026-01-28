<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Check if columns already exist before adding them
        $columnsToAdd = [];

        if (!Schema::hasColumn('products', 'uom_code')) {
            $columnsToAdd[] = 'uom_code';
        }

        if (!Schema::hasColumn('products', 'tax_code')) {
            $columnsToAdd[] = 'tax_code';
        }

        if (!empty($columnsToAdd)) {
            Schema::table('products', function (Blueprint $table) use ($columnsToAdd) {
                if (in_array('uom_code', $columnsToAdd)) {
                    $table->string('uom_code')->nullable()->after('design_approved');
                }
                if (in_array('tax_code', $columnsToAdd)) {
                    $table->string('tax_code')->nullable()->after('uom_code');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['uom_code', 'tax_code']);
        });
    }
};