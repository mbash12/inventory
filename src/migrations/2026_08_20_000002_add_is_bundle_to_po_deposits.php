<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('po_deposits', 'is_bundle')) {
            Schema::table('po_deposits', function (Blueprint $table) {
                $column = $table->boolean('is_bundle')->default(false);
                if (Schema::hasColumn('po_deposits', 'is_po_deposit')) {
                    $column->after('is_po_deposit');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('po_deposits', 'is_bundle')) {
            Schema::table('po_deposits', function (Blueprint $table) {
                $table->dropColumn('is_bundle');
            });
        }
    }
};
