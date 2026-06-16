<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropertyDuplicateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'owner']) ?? false;
    }

    public function rules(): array
    {
        /** @var \App\Models\Property|null $property */
        $property = $this->route('property');
        $propertyId = $property?->id;

        return [
            'name' => ['required', 'string', 'max:255'],

            'room_ids' => ['sometimes', 'array'],
            'room_ids.*' => [
                'integer',
                Rule::exists('property_room', 'room_id')->where(fn ($q) => $q->where('property_id', $propertyId)),
            ],

            'task_ids' => ['sometimes', 'array'],
            'task_ids.*' => [
                'integer',
                Rule::exists('property_tasks', 'task_id')->where(fn ($q) => $q->where('property_id', $propertyId)),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Ensure arrays when empty/missing to simplify controller logic
        $this->merge([
            'room_ids' => $this->input('room_ids', []),
            'task_ids' => $this->input('task_ids', []),
        ]);
    }
}

