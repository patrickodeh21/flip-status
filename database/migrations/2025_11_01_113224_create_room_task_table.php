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
        Schema::create('room_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0); // order tasks within the room
            $table->text('instructions')->nullable();               // room-specific instructions
            $table->boolean('visible_to_owner')->default(true);
            $table->boolean('visible_to_housekeeper')->default(true);
            $table->timestamps();

            $table->unique(['room_id', 'task_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_task');
    }
};
