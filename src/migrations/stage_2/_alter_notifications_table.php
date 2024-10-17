<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('notifications', function (Blueprint $table) {

            $table->enum('position', ["marketing", "delivery", "admin", "finance"]);
            $table->string('type')->nullable();

            // ===========
            // $table->id();
            // $table->string('title');
            // $table->string('content');
            // $table->json('payload');
            // $table->datetime('readed_at')->nullable();
            // $table->foreignId('user')->constrained('users','id')->onUpdate('cascade')->onDelete('cascade');
            // $table->timestamps();
            // $table->softDeletes();

        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['position','type']);
        });
    }
};
