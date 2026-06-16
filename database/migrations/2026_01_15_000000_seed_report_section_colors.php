<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $defaults = [
            'report_header_color' => '#842eb8',
            'report_status_color' => '#0e7a4b',
            'report_checklist_color' => '#3b82f6',
            'report_issues_color' => '#ef4444',
            'report_photos_color' => '#0e8a97',
            'report_time_color' => '#7c3aed',
            'report_supplies_color' => '#ec4899',
            'report_audit_color' => '#64748b',
            'report_button_primary_color' => '#842eb8',
            'report_button_secondary_color' => '#ffffff',
        ];

        foreach ($defaults as $key => $value) {
            if (Setting::get($key) === null) {
                Setting::set($key, $value);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $keys = [
            'report_header_color',
            'report_status_color',
            'report_checklist_color',
            'report_issues_color',
            'report_photos_color',
            'report_time_color',
            'report_supplies_color',
            'report_audit_color',
            'report_button_primary_color',
            'report_button_secondary_color',
        ];

        foreach ($keys as $key) {
            DB::table('settings')->where('key', $key)->delete();
        }
    }
};
