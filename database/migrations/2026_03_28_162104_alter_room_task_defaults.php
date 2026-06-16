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
        Schema::table('room_task', function (Blueprint $table) {
            $table->boolean('visible_to_owner')->default(true)->change();
            $table->boolean('visible_to_housekeeper')->default(true)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_task', function (Blueprint $table) {
            $table->boolean('visible_to_owner')->default(false)->change();
            $table->boolean('visible_to_housekeeper')->default(false)->change();
        });
    }
};
