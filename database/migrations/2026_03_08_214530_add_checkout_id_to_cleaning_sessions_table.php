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
        Schema::table('cleaning_sessions', function (Blueprint $table) {
            $table->foreignId('checkout_id')->nullable()->constrained('property_checkouts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cleaning_sessions', function (Blueprint $table) {
            $table->dropForeign(['checkout_id']);
            $table->dropColumn('checkout_id');
        });
    }
};
