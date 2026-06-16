<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        // Admin, owner, and company can create users
        return $user && ($user->hasRole('admin') || $user->hasRole('owner') || $user->hasRole('company'));
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower($this->input('email')),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $authUser = $this->user();
        $roleRules = ['required', Rule::in(['admin', 'owner', 'company', 'housekeeper'])];

        // Owners can only create housekeepers, Companies can create owners and housekeepers
        if ($authUser->hasRole('owner') && !$authUser->hasRole('admin') && !$authUser->hasRole('company')) {
            $roleRules = ['required', Rule::in(['housekeeper'])];
        } elseif ($authUser->hasRole('company') && !$authUser->hasRole('admin')) {
            $roleRules = ['required', Rule::in(['owner', 'housekeeper'])];
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'profile_photo' => ['nullable', 'image', 'max:5120'], // 5MB
            'role' => $roleRules,
            'owner_id' => ['nullable', 'exists:users,id'],
            'owner_ids' => ['nullable', 'array'],
            'owner_ids.*' => ['exists:users,id'],
            'property_ids' => ['nullable', 'array'],
            'property_ids.*' => ['exists:properties,id'],
        ];
    }
}
