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
        Schema::create('instamart_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique();
            $table->string('chat_jid');
            $table->string('sender_jid');
            $table->string('payment_method');
            $table->string('paas_id')->nullable();
            $table->string('status');
            $table->string('cart_total')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instamart_orders');
    }
};
