<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('threads', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['po','invoice','logistic'])->default('po');
            $table->foreignId('user_id')->nullable()->constrained('users', 'id')->onUpdate('cascade')->onDelete('set null');
            $table->foreignId('project_id')->nullable()->constrained('projects', 'id')->onUpdate('cascade')->onDelete('set null');
            $table->string('notes')->nullable();
            $table->date('reminder')->nullable();
            $table->date('deadline')->nullable();
            $table->json('meta_data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('threads');
    }
};
