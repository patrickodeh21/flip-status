<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Insert default settings
        DB::table('settings')->insert([
            ['key' => 'application_logo_path', 'value' => null, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'favicon_path', 'value' => null, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'site_name', 'value' => config('app.name', 'HK Checklist'), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'theme_color', 'value' => '#842eb8', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'button_primary_color', 'value' => '#842eb8', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'button_success_color', 'value' => '#10b981', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'button_danger_color', 'value' => '#ef4444', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'button_warning_color', 'value' => '#f59e0b', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'button_info_color', 'value' => '#06b6d4', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
