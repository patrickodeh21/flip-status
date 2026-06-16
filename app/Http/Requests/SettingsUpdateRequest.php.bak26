<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingsUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:255'],
            'theme_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'application_logo' => ['nullable', 'image', 'max:5120'], // 5MB
            'favicon' => ['nullable', 'file', 'mimes:ico,png,svg,jpg,jpeg', 'max:1024'], // 1MB, favicon formats
            'logo_alignment' => ['nullable', 'string', 'in:left,center,right'],
            'button_primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'button_success_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'button_danger_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'button_warning_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'button_info_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'report_header_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'report_status_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'report_checklist_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'report_issues_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'report_photos_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'report_time_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'report_supplies_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'report_audit_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'report_button_primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'report_button_secondary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'date_format' => ['nullable', 'string', Rule::in(['Y-m-d', 'm/d/Y', 'd/m/Y', 'M d, Y', 'F d, Y', 'd M Y'])],
            'time_format' => ['nullable', 'string', 'in:12,24'],
            'items_per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'timezone' => ['nullable', 'string', 'max:255', Rule::in([
                'UTC',
                'America/New_York',
                'America/Chicago',
                'America/Denver',
                'America/Los_Angeles',
                'Europe/London',
                'Europe/Paris',
                'Asia/Dhaka',
                'Asia/Kolkata',
                'Asia/Dubai',
                'Asia/Singapore',
                'Asia/Tokyo',
                'Australia/Sydney',
            ])],
            'auto_save_enabled' => ['nullable', 'boolean'],
            'auto_save_delay' => ['nullable', 'integer', 'min:100', 'max:5000'],
            'notify_session_started' => ['nullable', 'boolean'],
            'notify_session_completed' => ['nullable', 'boolean'],
            'notify_assignments' => ['nullable', 'boolean'],
        ];
    }
}
