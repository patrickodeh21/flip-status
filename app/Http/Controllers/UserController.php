<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Property;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        $this->assertOwnerOrAdmin();

        $auth = $request->user();
        $role = (string) $request->query('role', '');
        $q    = (string) $request->query('q', '');

        $users = User::query()
            ->with('roles')
            ->when(($auth->hasRole('owner') || $auth->hasRole('company')) && ! $auth->hasRole('admin'), function ($qry) use ($auth) {
                $qry->where(function ($q) use ($auth) {
                    $q->where('users.id', $auth->id)
                        ->orWhere('users.owner_id', $auth->id)
                        ->orWhere(function ($qq) use ($auth) {
                            $qq->whereHas('roles', fn($r) => $r->where('name', 'housekeeper'))
                                ->whereExists(function ($sub) use ($auth) {
                                    $sub->selectRaw(1)
                                        ->from('cleaning_sessions as cs')
                                        ->whereColumn('cs.housekeeper_id', 'users.id')
                                        ->where('cs.owner_id', $auth->id);
                                });
                        });
                    
                    // If company, also show housekeepers of their owners
                    if ($auth->hasRole('company')) {
                        $q->orWhereIn('users.owner_id', function($sub) use ($auth) {
                            $sub->select('id')->from('users')->where('owner_id', $auth->id);
                        });
                    }
                });
            })
            ->when($q !== '', fn($qry) => $qry->where(function ($q2) use ($q) {
                $q2->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            }))
            ->when($role !== '', fn($qry) => $qry->whereHas('roles', fn($r) => $r->where('name', $role)))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', compact('users'));
    }

    public function assignRole(Request $request, User $user)
    {
        $this->assertOwnerOrAdmin();

        $auth = $request->user();

        $data = $request->validate([
            'role' => ['required', Rule::in(['admin', 'owner', 'company', 'housekeeper'])],
        ]);

        // Admin can assign anything.
        if ($auth->hasRole('owner') && ! $auth->hasRole('admin')) {
            // Owners may ONLY assign housekeeper role.
            if ($data['role'] !== 'housekeeper') {
                abort(403, 'Owners can only assign the housekeeper role.');
            }
            // Owners cannot modify privileged users.
            if ($user->hasAnyRole(['admin', 'owner', 'company'])) {
                abort(403, 'Cannot modify admin/owner/company users.');
            }
            // If you want to restrict to their own HKs only, enforce exists() below:
            $isAssignedToOwner = DB::table('cleaning_sessions')
                ->where('owner_id', $auth->id)
                ->where('housekeeper_id', $user->id)
                ->exists();
            abort_unless($isAssignedToOwner, 403, 'Not your housekeeper.');
        }

        // If you want single-role model: use syncRoles([$data['role']]);
        $user->assignRole($data['role']);

        return back()->with('ok', 'Role assigned.');
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        // Admin and owner can create users
        $user = auth()->user();
        abort_unless($user && ($user->hasRole('admin') || $user->hasRole('owner') || $user->hasRole('company')), 403, 'You do not have permission to create users.');

        $owners = [];
        $properties = collect();
        if ($user->hasRole('admin')) {
            // Get both owners and companies for assignment dropdown
            $owners = \App\Models\User::whereHas('roles', function($q) {
                $q->whereIn('name', ['owner', 'company']);
            })->orderBy('name')->get();
            $properties = Property::orderBy('name')->get();
        } elseif ($user->hasRole('owner') || $user->hasRole('company')) {
            $properties = Property::where('owner_id', $user->id)->orderBy('name')->get();
        }

        return view('users.create', compact('owners', 'properties'));
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(UserStoreRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();
            $authUser = $request->user();

            // Handle role restrictions and automatic owner_id assignment
            if ($authUser->hasRole('owner') && !$authUser->hasRole('admin') && !$authUser->hasRole('company')) {
                if ($data['role'] !== 'housekeeper') {
                    abort(403, 'Owners can only create housekeeper users.');
                }
                $data['owner_id'] = $authUser->id;
            } elseif ($authUser->hasRole('company') && !$authUser->hasRole('admin')) {
                if (!in_array($data['role'], ['owner', 'housekeeper'])) {
                    abort(403, 'Companies can only create owner and housekeeper users.');
                }
                $data['owner_id'] = $authUser->id;
            }

            // Ensure owner_id is properly null if not set or empty
            if (isset($data['owner_id']) && ($data['owner_id'] === '' || $data['owner_id'] === 0)) {
                $data['owner_id'] = null;
            }

            // Handle profile photo upload
            if ($request->hasFile('profile_photo')) {
                $data['profile_photo_path'] = $request->file('profile_photo')->store('profile-photos', 'public');
            }

            // Create user
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'], // Automatically hashed by User model cast, but Hash::make is safer if cast is iffy
                'phone_number' => $data['phone_number'] ?? null,
                'profile_photo_path' => $data['profile_photo_path'] ?? null,
                'owner_id' => $data['owner_id'] ?? null,
            ]);

            // Assign role
            $user->assignRole($data['role']);

            // Attach to multiple owners if provided (for housekeepers and company users)
            if (!empty($data['owner_ids'])) {
                $user->managedOwners()->sync($data['owner_ids']);
            }

            // Sync property assignments
            if (isset($data['property_ids'])) {
                $user->properties()->sync($data['property_ids']);
            }

            return redirect()->route('users.index')
                ->with('success', 'User created successfully.');
        });
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user)
    {
        $authUser = auth()->user();

        // Users can edit their own profile
        if ($authUser->id === $user->id) {
            $properties = Property::where('owner_id', $authUser->id)->orderBy('name')->get();
            return view('users.edit', compact('user', 'properties'));
        }

        $owners = [];
        $properties = collect();

        if ($authUser->hasRole('admin')) {
            $owners = \App\Models\User::whereHas('roles', fn($q) => $q->whereIn('name', ['owner', 'company']))->orderBy('name')->get();
            $properties = Property::orderBy('name')->get();
            return view('users.edit', compact('user', 'owners', 'properties'));
        }

        // Owner/Company can only edit their assigned housekeepers/owners
        if (($authUser->hasRole('owner') || $authUser->hasRole('company')) && !$authUser->hasRole('admin')) {
            $isDirectlyOwned = $user->owner_id === $authUser->id;
            $isIndirectlyOwned = false;
            
            if ($authUser->hasRole('company')) {
                $isIndirectlyOwned = User::where('id', $user->owner_id)
                    ->where('owner_id', $authUser->id)
                    ->exists();

                // For company, get owners they manage
                $owners = User::where('owner_id', $authUser->id)
                    ->whereHas('roles', fn($q) => $q->where('name', 'owner'))
                    ->orderBy('name')->get();
            }

            abort_unless($isDirectlyOwned || $isIndirectlyOwned, 403, 'This user is not assigned to you or your team.');

            // Load properties owned by the auth user (or their managed owners)
            $properties = Property::where('owner_id', $authUser->id)->orderBy('name')->get();
        } else {
            abort(403, 'You do not have permission to edit this user.');
        }

        return view('users.edit', compact('user', 'owners', 'properties'));
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UserUpdateRequest $request, User $user)
    {
        return DB::transaction(function () use ($request, $user) {
            $data = $request->validated();
            $authUser = $request->user();

            // Handle profile photo removal
            if ($request->boolean('remove_profile_photo') && $user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
                $data['profile_photo_path'] = null;
            }

            // Handle profile photo upload
            if ($request->hasFile('profile_photo')) {
                // Delete old photo if exists
                if ($user->profile_photo_path) {
                    Storage::disk('public')->delete($user->profile_photo_path);
                }
                $data['profile_photo_path'] = $request->file('profile_photo')->store('profile-photos', 'public');
            }

            // Update password if provided
            if (isset($data['password'])) {
                $data['password'] = $data['password'];
            } else {
                unset($data['password']);
            }

            // Ensure owner_id is properly null if not set or empty
            if (isset($data['owner_id']) && ($data['owner_id'] === '' || $data['owner_id'] === 0)) {
                $data['owner_id'] = null;
            }

            // Owners/Companies enforce owner_id on their housekeepers/owners
            if ($authUser->hasRole('owner') && !$authUser->hasRole('admin') && !$authUser->hasRole('company')) {
                 $data['owner_id'] = $authUser->id;
            }

            // Update user
            $user->update($data);

            // Update role if admin/owner/company and role is provided
            if ($authUser->id !== $user->id && isset($data['role'])) {
                // Admin can assign any role
                if ($authUser->hasRole('admin')) {
                    $user->syncRoles([$data['role']]);
                }
                // Company can assign owner or housekeeper roles
                elseif ($authUser->hasRole('company') && !$authUser->hasRole('admin')) {
                    if (!in_array($data['role'], ['owner', 'housekeeper'])) {
                        abort(403, 'Companies can only assign owner or housekeeper roles.');
                    }
                    $user->syncRoles([$data['role']]);
                }
                // Owner can only assign housekeeper role
                elseif ($authUser->hasRole('owner') && !$authUser->hasRole('admin') && !$authUser->hasRole('company')) {
                    if ($data['role'] !== 'housekeeper') {
                        abort(403, 'Owners can only assign the housekeeper role.');
                    }
                    if ($user->owner_id !== $authUser->id) {
                        abort(403, 'This user is not assigned to you.');
                    }
                    $user->syncRoles([$data['role']]);
                }
            }

            // Update many-to-many owners for housekeepers and company users
            if (($user->hasRole('housekeeper') || $user->hasRole('company')) && isset($data['owner_ids'])) {
                $user->managedOwners()->sync($data['owner_ids']);
            }

            // Sync property assignments — only when the key is explicitly present in the request
            if (array_key_exists('property_ids', $data)) {
                $user->properties()->sync($data['property_ids'] ?? []);
            }

            $redirectRoute = $authUser->id === $user->id
                ? route('profile.edit')
                : route('users.index');

            return redirect($redirectRoute)
                ->with('success', 'User updated successfully.');
        });
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user)
    {
        $authUser = auth()->user();

        // Cannot delete yourself
        abort_if($authUser->id === $user->id, 403, 'You cannot delete your own account.');

        // Only admin can delete users
        abort_unless($authUser->hasRole('admin'), 403, 'Only administrators can delete users.');

        // Delete profile photo if exists
        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully.');
    }

    private function assertOwnerOrAdmin(): void
    {
        $u = auth()->user();
        abort_unless($u && $u->hasAnyRole(['admin', 'owner', 'company']), 403);
    }
}
