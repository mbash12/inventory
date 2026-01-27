<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('marketing_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('po_deposit')->nullable()->constrained('po_deposits', 'id')->onUpdate('cascade')->onDelete('cascade');
            $table->date('schedule_date')->nullable();
            $table->date('followup_date')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_followups');
    }
};
