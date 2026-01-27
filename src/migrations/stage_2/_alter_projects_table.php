<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::table('projects', function (Blueprint $table) {
            $table->enum('status', ["production", "ready", "partial", "delivered", "cancel"])->change();
            $table->string('title')->nullable();
            $table->foreignId('po_deposit')->nullable()->constrained('po_deposits', 'id')->onUpdate('cascade')->onDelete('cascade');
            $table->boolean('is_po_deposit')->default(false);
            $table->boolean('is_real')->default(true);
            $table->date('sent_to_del_at')->nullable();
            $table->bigInteger('total_price')->nullable();
            $table->string('pic_name')->nullable()->change();
            //  ==============

            // $table->id();
            // $table->string('job_number')->unique();
            // $table->date('client_po_date')->nullable();
            // $table->string('client_po_number')->nullable();
            // $table->string('client_pic_name')->nullable();
            // $table->enum('status', ["ready", "partial", "delivered", "cancel"]);
            // $table->foreignId('shipping_vendor')->nullable()->constrained('shipping_vendors', 'id');
            // $table->foreignId('manufacture')->nullable()->constrained('warehouses', 'id');
            // $table->string('pic_name');
            // $table->string('client_company');
            // $table->timestamps();
            // $table->softDeletes();
        });
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {

        Schema::table('projects', function (Blueprint $table) {
            $table->string('pic_name')->change();
            $table->enum('status', ["ready", "partial", "delivered", "cancel"])->change();
            $table->dropColumn(['title', 'po_deposit', 'is_po_deposit', 'is_real', 'sent_to_del_at', 'total_price']);
        });
    }
};
