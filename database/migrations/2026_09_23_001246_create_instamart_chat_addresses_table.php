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
        Schema::create('instamart_chat_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('chat_jid')->unique();
            $table->string('address_id');
            $table->string('address_line');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instamart_chat_addresses');
    }
};
