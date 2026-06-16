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
        Schema::table('checklist_items', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['room_id']);
            // Make column nullable
            $table->foreignId('room_id')->nullable()->change();
            // Re-add foreign key constraint with nullable support
            $table->foreign('room_id')->references('id')->on('rooms')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checklist_items', function (Blueprint $table) {
            // Drop foreign key constraint
            $table->dropForeign(['room_id']);
            // Make column not nullable
            $table->foreignId('room_id')->nullable(false)->change();
            // Re-add foreign key constraint
            $table->foreign('room_id')->references('id')->on('rooms')->cascadeOnDelete();
        });
    }
};
