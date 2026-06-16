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
        Schema::create('property_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->string('uid')->index(); // unique id from iCal
            $table->date('checkout_date');
            $table->string('guest_name')->nullable();
            $table->string('source')->nullable(); // airbnb, vrbo, etc.
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamps();

            $table->unique(['property_id', 'uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_checkouts');
    }
};
