<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\User;

class PropertyStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'owner', 'company']) ?? false;
    }

    public function rules(): array
    {
        $user = $this->user();
        $isAdmin = $user?->hasRole('admin');
        $isCompany = $user?->hasRole('company');
        $userId = $user?->id;

        $ownerIdRule = ['required', 'integer', Rule::in([$userId])]; // Default for owner

        if ($isAdmin) {
            $ownerIdRule = ['required', 'integer', Rule::exists('users', 'id')];
        } elseif ($isCompany) {
            // Company can assign to themselves OR their managed owners
            $managedOwners = User::where('owner_id', $userId)->pluck('id')->toArray();
            $allowedIds = array_merge([$userId], $managedOwners);
            $ownerIdRule = ['required', 'integer', Rule::in($allowedIds)];
        }

        return [
            'name'         => ['required', 'string', 'max:255'],
            'address'      => ['nullable', 'string', 'max:255'],
            'latitude'     => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'    => ['nullable', 'numeric', 'between:-180,180'],
            'geo_radius_m' => ['nullable', 'integer', 'min:50'],
            'photo'        => ['nullable', 'image', 'max:5120'], // 5MB
            'ical_url'     => ['nullable', 'url', 'max:1000'],
            'airbnb_ical_url' => ['nullable', 'url', 'max:1000'],
            'vrbo_ical_url'   => ['nullable', 'url', 'max:1000'],

            'timezone'     => ['required', 'string', 'max:10'],

            'owner_id'     => $ownerIdRule,

            'attach'       => ['nullable', Rule::in(['none', 'rooms'])],
        ];
    }

    /**
     * If the requester is an owner (and not admin/company), force owner_id to themselves.
     */
    protected function prepareForValidation(): void
    {
        $user = $this->user();

        if ($user?->hasRole('admin') && !$this->filled('owner_id')) {
            $this->merge([
                'owner_id' => $user->id,
            ]);
        }

        if ($user?->hasRole('owner') && !$user->hasRole('admin') && !$user->hasRole('company')) {
            $this->merge([
                'owner_id' => $user->id,
            ]);
        }
    }
}
