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
        Schema::table('properties', function (Blueprint $table) {
            $table->string('airbnb_ical_url', 1000)->nullable()->after('ical_url');
            $table->string('vrbo_ical_url', 1000)->nullable()->after('airbnb_ical_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['airbnb_ical_url', 'vrbo_ical_url']);
        });
    }
};
