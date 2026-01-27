<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->enum('position', ["marketing", "delivery", "admin", "finance"])->change();

            //  ================
            // $table->id();
            // $table->string('email')->unique();
            // $table->string('name');
            // $table->enum('position', ["marketing","delivery","admin"]);
            // $table->string('password');
            // $table->integer('otp_code')->nullable();
            // $table->timestamps();
            // $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('position', ["marketing", "delivery", "admin"])->change();
        });
    }
};
