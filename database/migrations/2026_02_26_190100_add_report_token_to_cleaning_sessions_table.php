<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cleaning_sessions', function (Blueprint $table) {
            $table->string('report_token', 32)->nullable()->after('start_longitude');
        });

        DB::table('cleaning_sessions')
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($sessions): void {
                foreach ($sessions as $session) {
                    DB::table('cleaning_sessions')
                        ->where('id', $session->id)
                        ->update(['report_token' => Str::lower((string) Str::ulid())]);
                }
            });

        Schema::table('cleaning_sessions', function (Blueprint $table) {
            $table->unique('report_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cleaning_sessions', function (Blueprint $table) {
            $table->dropUnique(['report_token']);
            $table->dropColumn('report_token');
        });
    }
};
