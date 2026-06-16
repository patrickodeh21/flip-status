<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class SetupRolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Define permissions (granular so you can gate views/actions)
        $perms = [
            'properties.view',
            'properties.manage',
            'rooms.manage',
            'tasks.manage',
            'sessions.view',
            'sessions.manage',
            'sessions.view_all',
            'users.view',
            'users.manage',
            'roles.assign',
        ];

        foreach ($perms as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Roles
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $owner = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $hk    = Role::firstOrCreate(['name' => 'housekeeper', 'guard_name' => 'web']);
        $company = Role::firstOrCreate(['name' => 'company', 'guard_name' => 'web']);

        // Attach permissions
        $admin->syncPermissions(Permission::all());

        $ownerPerms = [
            'properties.view',
            'properties.manage',
            'rooms.manage',
            'tasks.manage',
            'sessions.view',
            'sessions.manage',
            'sessions.view_all',
            'users.view',
            'roles.assign', // allow assigning HK role to new users
        ];
        $owner->syncPermissions($ownerPerms);
        
        // Company role has same permissions as owner
        $company->syncPermissions($ownerPerms);

        $hkPerms = [
            'sessions.view',
            'sessions.manage',
        ];
        $hk->syncPermissions($hkPerms);
    }
}
