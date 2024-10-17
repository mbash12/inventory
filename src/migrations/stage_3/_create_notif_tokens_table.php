<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('notif_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token');
            $table->foreignId('user_id')->nullable()->constrained('users', 'id')->onUpdate('cascade')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notif_tokens');
    }
};
