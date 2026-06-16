<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Handle validation for starting a cleaning session.  Latitude and longitude
 * values are optional to accommodate devices or browsers that do not provide
 * location data.  If they are provided they must be valid numeric values.
 */
class StartSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Authorization logic should be handled via policies in the controller
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * Customize messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitude.numeric'  => 'Latitude must be a valid number.',
            'longitude.numeric' => 'Longitude must be a valid number.',
        ];
    }
}