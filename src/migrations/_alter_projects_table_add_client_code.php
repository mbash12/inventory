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
        // Check if column already exists before adding it
        if (!Schema::hasColumn('projects', 'client_code')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->string('client_code')->nullable()->after('client_company');
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
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('client_code');
        });
    }
};
