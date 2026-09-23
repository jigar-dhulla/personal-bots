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
        Schema::table('instamart_connections', function (Blueprint $table) {
            // Logins saved before this column existed were all made with the localhost callback.
            $table->string('redirect_uri')->default('http://localhost:8765/callback')->after('client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instamart_connections', function (Blueprint $table) {
            $table->dropColumn('redirect_uri');
        });
    }
};
